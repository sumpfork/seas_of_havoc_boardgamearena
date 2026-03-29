<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

/**
 * Mock Deck object for card operations in payWithOptionalBooty.
 */
class MockDeck {
    public array $cards = [];
    public array $moved = [];

    public function getCard(int $card_id): ?array {
        return $this->cards[$card_id] ?? null;
    }

    public function moveCard(int $card_id, string $location, int $location_arg = 0): void {
        $this->moved[] = ['card_id' => $card_id, 'location' => $location, 'location_arg' => $location_arg];
        if (isset($this->cards[$card_id])) {
            $this->cards[$card_id]['location'] = $location;
            $this->cards[$card_id]['location_arg'] = $location_arg;
        }
    }

    public function getCardsInLocation(string $location, $location_arg = null): array {
        return array_filter($this->cards, function ($card) use ($location, $location_arg) {
            return $card['location'] === $location
                && ($location_arg === null || (int)$card['location_arg'] === (int)$location_arg);
        });
    }
}

/**
 * Extended test subclass with mock infrastructure for booty payment testing.
 *
 * SeasOfHavoc declares private $booty_tokens, so we use Reflection to
 * populate it from the material.inc.php data loaded by the parent ctor.
 */
class BootyPaymentUT extends SeasOfHavocUT {
    public array $booty_tokens = [];
    public array $mockPlayerResources = [];
    public array $paidCosts = [];

    function __construct() {
        parent::__construct();
        // material.inc.php sets properties on *this* class (public/dynamic).
        // Private parent properties are separate — copy them via Reflection.
        self::copyToParent($this, 'booty_tokens', $this->booty_tokens);
        self::copyToParent($this, 'resource_types', $this->resource_types);
    }

    private static function copyToParent(object $obj, string $prop, mixed $value): void {
        $r = new ReflectionProperty(SeasOfHavoc::class, $prop);
        $r->setValue($obj, $value);
    }

    public function setMockCards(object $deck): void {
        $r = new ReflectionProperty(SeasOfHavoc::class, 'cards');
        $r->setValue($this, $deck);
    }

    public function callResolveBootyResources(array $resources, array $cost, ?string $player_choice = null): array {
        $m = new ReflectionMethod(SeasOfHavoc::class, 'resolveBootyResourcesForPayment');
        return $m->invoke($this, $resources, $cost, $player_choice);
    }

    public function callGetBootyTokenConfig(int $type_arg): ?array {
        $m = new ReflectionMethod(SeasOfHavoc::class, 'getBootyTokenConfigByTypeArg');
        return $m->invoke($this, $type_arg);
    }

    // --- DB-touching overrides ------------------------------------------------

    public function getGameResourcesHierarchical(?int $player_id = null) {
        return [1 => $this->mockPlayerResources];
    }

    public function pay($player_id, $cost) {
        if (!$this->canPayFor($cost, $this->mockPlayerResources)) {
            throw new BgaUserException("You cannot afford this action");
        }
        $this->paidCosts[] = $cost;
        foreach ($cost as $res => $amount) {
            $this->mockPlayerResources[$res] = ($this->mockPlayerResources[$res] ?? 0) - $amount;
        }
    }
}


final class BootyPaymentTest extends TestCase {

    private BootyPaymentUT $game;

    protected function setUp(): void {
        $this->game = new BootyPaymentUT();
    }

    // =========================================================================
    //  getBootyTokenConfigByTypeArg
    // =========================================================================

    public function testGetBootyTokenConfigForEachToken(): void {
        for ($id = 0; $id <= 8; $id++) {
            $cfg = $this->game->callGetBootyTokenConfig($id);
            $this->assertNotNull($cfg, "Token image_id=$id should exist");
            $this->assertEquals($id, $cfg['image_id']);
            $this->assertNotEmpty($cfg['resources']);
        }
    }

    public function testGetBootyTokenConfigInvalidId(): void {
        $this->assertNull($this->game->callGetBootyTokenConfig(99));
        $this->assertNull($this->game->callGetBootyTokenConfig(-1));
    }

    public function testGetBootyTokenConfigKnownResources(): void {
        // sail × 3
        $this->assertEquals(['sail' => 3],
            $this->game->callGetBootyTokenConfig(0)['resources']);
        // doubloon:1 + choice:1
        $this->assertEquals(['doubloon' => 1, 'choice' => 1],
            $this->game->callGetBootyTokenConfig(3)['resources']);
        // cannonball × 2
        $this->assertEquals(['cannonball' => 2],
            $this->game->callGetBootyTokenConfig(8)['resources']);
    }

    // =========================================================================
    //  resolveBootyResourcesForPayment
    // =========================================================================

    public function testResolveFixedOnly(): void {
        $result = $this->game->callResolveBootyResources(
            ['sail' => 1, 'cannonball' => 1],
            ['sail' => 2, 'cannonball' => 1]
        );
        $this->assertEquals(1, $result['sail']);
        $this->assertEquals(1, $result['cannonball']);
    }

    public function testResolveAllFixedExcess(): void {
        // Token gives sail:3, cost only needs sail:1.
        // Resolved = 3 (excess handled by cost-reduction step, not here).
        $result = $this->game->callResolveBootyResources(
            ['sail' => 3], ['sail' => 1]
        );
        $this->assertEquals(3, $result['sail']);
    }

    public function testResolveFixedNoOverlapWithCost(): void {
        $result = $this->game->callResolveBootyResources(
            ['sail' => 2], ['cannonball' => 3]
        );
        $this->assertEquals(2, $result['sail']);
        $this->assertArrayNotHasKey('cannonball', $result);
    }

    public function testResolveChoiceAutoAssignsToHighestNeed(): void {
        // choice:1, cost: sail:3 vs cannonball:1 → choice → sail (need 3)
        $result = $this->game->callResolveBootyResources(
            ['choice' => 1], ['sail' => 3, 'cannonball' => 1]
        );
        $this->assertEquals(1, $result['sail']);
        $this->assertArrayNotHasKey('cannonball', $result);
    }

    public function testResolveChoiceAfterFixedCoverage(): void {
        // cannonball:1 + choice:1, cost: cannonball:2, sail:1
        // Fixed covers cannonball:1 → remaining cannonball need = 1, sail need = 1.
        // First iteration: cannonball remaining 1 > bestNeed 0 → cannonball is best.
        //                  sail remaining 1 > bestNeed 1 → FALSE, cannonball stays.
        // Choice → cannonball.
        $result = $this->game->callResolveBootyResources(
            ['cannonball' => 1, 'choice' => 1],
            ['cannonball' => 2, 'sail' => 1]
        );
        $this->assertEquals(2, $result['cannonball']);
        $this->assertArrayNotHasKey('sail', $result);
    }

    public function testResolveMultipleChoiceUnitsSpreadAcross(): void {
        // choice:2, cost: sail:1, cannonball:1
        // 1st choice: sail need 1 > 0 → sail is best; cannonball need 1 > 1? NO → sail stays
        //   → out['sail'] = 1
        // 2nd choice: sail remaining 1−1 = 0; cannonball remaining 1−0 = 1 → cannonball
        //   → out['cannonball'] = 1
        $result = $this->game->callResolveBootyResources(
            ['choice' => 2], ['sail' => 1, 'cannonball' => 1]
        );
        $this->assertEquals(1, $result['sail'] ?? 0);
        $this->assertEquals(1, $result['cannonball'] ?? 0);
    }

    public function testResolveChoiceWithExplicitPlayerChoice(): void {
        // sail:1 + choice:1, player picks cannonball
        $result = $this->game->callResolveBootyResources(
            ['sail' => 1, 'choice' => 1],
            ['sail' => 1, 'cannonball' => 1],
            'cannonball'
        );
        $this->assertEquals(1, $result['sail']);
        $this->assertEquals(1, $result['cannonball']);
    }

    public function testResolveChoicePlayerChoiceOverridesAutoAssign(): void {
        // choice:1, cost: sail:3, cannonball:1. Player explicitly picks cannonball.
        // Auto would pick sail (higher need), but player overrides.
        $result = $this->game->callResolveBootyResources(
            ['choice' => 1], ['sail' => 3, 'cannonball' => 1], 'cannonball'
        );
        $this->assertEquals(1, $result['cannonball']);
        $this->assertArrayNotHasKey('sail', $result);
    }

    public function testResolveChoiceWithEmptyCostIsWasted(): void {
        $result = $this->game->callResolveBootyResources(
            ['choice' => 1], []
        );
        $this->assertEmpty($result);
    }

    public function testResolveChoiceWhenAllNeedsCoveredIsWasted(): void {
        // doubloon:2 + choice:1, cost: doubloon:1
        // Fixed covers doubloon:1 already. Remaining need = 0 everywhere.
        $result = $this->game->callResolveBootyResources(
            ['doubloon' => 2, 'choice' => 1], ['doubloon' => 1]
        );
        // choice has nowhere useful to go
        $this->assertEquals(2, $result['doubloon']);
        // only doubloon key should exist
        $this->assertCount(1, $result);
    }

    // --- Exercising real token configs ----------------------------------------

    public function testRealToken0SailOnly(): void {
        $cfg = $this->game->callGetBootyTokenConfig(0); // sail:3
        $res = $this->game->callResolveBootyResources($cfg['resources'], ['sail' => 2]);
        $this->assertEquals(3, $res['sail']);
    }

    public function testRealToken3WildForMultiCost(): void {
        $cfg = $this->game->callGetBootyTokenConfig(3); // doubloon:1, choice:1
        $res = $this->game->callResolveBootyResources($cfg['resources'], ['sail' => 2, 'doubloon' => 1]);
        $this->assertEquals(1, $res['doubloon']);
        $this->assertEquals(1, $res['sail']); // choice auto-assigned to sail
    }

    public function testRealToken4WildForMultiCost(): void {
        $cfg = $this->game->callGetBootyTokenConfig(4); // cannonball:1, choice:1
        $res = $this->game->callResolveBootyResources($cfg['resources'], ['sail' => 2, 'cannonball' => 1]);
        $this->assertEquals(1, $res['cannonball']);
        $this->assertEquals(1, $res['sail']); // choice → sail (need 2 > 0)
    }

    public function testRealToken8PureCannonball(): void {
        $cfg = $this->game->callGetBootyTokenConfig(8); // cannonball:2
        $res = $this->game->callResolveBootyResources($cfg['resources'], ['cannonball' => 1]);
        $this->assertEquals(2, $res['cannonball']); // excess stays
    }

    // =========================================================================
    //  Cost-reduction math (the loop inside payWithOptionalBooty)
    // =========================================================================

    private function computeRemainingCost(array $cost, array $bootyResolved): array {
        $remaining = [];
        foreach ($cost as $res => $need) {
            $remaining[$res] = max(0, $need - ($bootyResolved[$res] ?? 0));
        }
        return $remaining;
    }

    public function testCostReductionFullyCovered(): void {
        $rem = $this->computeRemainingCost(
            ['sail' => 1, 'cannonball' => 1],
            ['sail' => 1, 'cannonball' => 1]
        );
        $this->assertEquals(0, $rem['sail']);
        $this->assertEquals(0, $rem['cannonball']);
    }

    public function testCostReductionPartiallyCovered(): void {
        $rem = $this->computeRemainingCost(
            ['sail' => 3, 'cannonball' => 2],
            ['sail' => 1]
        );
        $this->assertEquals(2, $rem['sail']);
        $this->assertEquals(2, $rem['cannonball']);
    }

    public function testCostReductionExcessCappedAtZero(): void {
        $rem = $this->computeRemainingCost(
            ['sail' => 1],
            ['sail' => 3]
        );
        $this->assertEquals(0, $rem['sail']);
    }

    public function testCostReductionWrongResourceType(): void {
        $rem = $this->computeRemainingCost(
            ['cannonball' => 2],
            ['sail' => 2]
        );
        $this->assertEquals(2, $rem['cannonball']);
    }

    // =========================================================================
    //  payWithOptionalBooty  (integration, mocked DB)
    // =========================================================================

    private function setupMockCards(): MockDeck {
        $deck = new MockDeck();
        $this->game->setMockCards($deck);
        return $deck;
    }

    private function addBootyCard(MockDeck $deck, int $card_id, int $type_arg, int $owner = 1): void {
        $deck->cards[$card_id] = [
            'id' => $card_id, 'type' => 'booty', 'type_arg' => $type_arg,
            'location' => 'booty_player', 'location_arg' => $owner,
        ];
    }

    // --- without booty -------------------------------------------------------

    public function testPayWithoutBootyDeductsNormally(): void {
        $this->setupMockCards();
        $this->game->mockPlayerResources = ['sail' => 5, 'cannonball' => 3, 'doubloon' => 2];
        $this->game->paidCosts = [];

        $this->game->payWithOptionalBooty(1, ['sail' => 2, 'cannonball' => 1]);

        $this->assertCount(1, $this->game->paidCosts);
        $this->assertEquals(['sail' => 2, 'cannonball' => 1], $this->game->paidCosts[0]);
    }

    public function testPayWithoutBootyCannotAfford(): void {
        $this->setupMockCards();
        $this->game->mockPlayerResources = ['sail' => 0];
        $this->expectException(BgaUserException::class);
        $this->game->payWithOptionalBooty(1, ['sail' => 1]);
    }

    public function testPayEmptyCostIsNoop(): void {
        $this->setupMockCards();
        $this->game->mockPlayerResources = ['sail' => 5];
        $this->game->paidCosts = [];

        $this->game->payWithOptionalBooty(1, []);

        $this->assertEmpty($this->game->paidCosts);
    }

    // --- with booty fully covering cost --------------------------------------

    public function testPayWithBootyFullyCoveringCost(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 100, 2); // sail:1, cannonball:1
        $this->game->mockPlayerResources = ['sail' => 0, 'cannonball' => 0, 'doubloon' => 0];
        $this->game->paidCosts = [];
        $this->game->trackedNotifications = [];

        $this->game->payWithOptionalBooty(1, ['sail' => 1, 'cannonball' => 1], 100);

        // Remaining cost should be all zeros
        $this->assertCount(1, $this->game->paidCosts);
        $this->assertEquals(0, $this->game->paidCosts[0]['sail'] ?? 0);
        $this->assertEquals(0, $this->game->paidCosts[0]['cannonball'] ?? 0);
        // Card discarded
        $this->assertCount(1, $deck->moved);
        $this->assertEquals('booty_discard', $deck->moved[0]['location']);
        // Notification sent
        $this->assertCount(1, $this->game->trackedNotifications);
        $this->assertEquals('bootyTokenUsed', $this->game->trackedNotifications[0]['type']);
    }

    public function testPayWithBootyZeroResourcePlayerCanAffordZero(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 100, 0); // sail:3
        $this->game->mockPlayerResources = ['sail' => 0, 'cannonball' => 0];
        $this->game->paidCosts = [];

        // Cost is sail:2 — fully covered by booty sail:3
        $this->game->payWithOptionalBooty(1, ['sail' => 2], 100);

        $this->assertEquals(0, $this->game->paidCosts[0]['sail'] ?? 0);
    }

    // --- with booty partially covering cost ----------------------------------

    public function testPayWithBootyPartialCoverage(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 101, 1); // sail:1, doubloon:1
        $this->game->mockPlayerResources = ['sail' => 5, 'cannonball' => 3, 'doubloon' => 2];
        $this->game->paidCosts = [];

        $this->game->payWithOptionalBooty(1, ['sail' => 2, 'doubloon' => 1], 101);

        // Booty covers sail:1 + doubloon:1 → remaining sail:1, doubloon:0
        $this->assertEquals(1, $this->game->paidCosts[0]['sail']);
        $this->assertEquals(0, $this->game->paidCosts[0]['doubloon']);
    }

    public function testPayWithBootyExcessResourcesNoRefund(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 102, 0); // sail:3
        $this->game->mockPlayerResources = ['sail' => 5];
        $this->game->paidCosts = [];

        // Cost: sail:1 — booty gives sail:3, but excess is wasted
        $this->game->payWithOptionalBooty(1, ['sail' => 1], 102);

        $this->assertEquals(0, $this->game->paidCosts[0]['sail'] ?? 0);
        // Player resources only changed by the remaining cost (0), not by the full booty
        $this->assertEquals(5, $this->game->mockPlayerResources['sail']);
    }

    public function testPayWithBootyMixedWithPlayerResources(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 103, 6); // doubloon:1, cannonball:1
        $this->game->mockPlayerResources = ['sail' => 2, 'cannonball' => 0, 'doubloon' => 0];
        $this->game->paidCosts = [];

        // Cost: sail:1, doubloon:1, cannonball:1
        // Booty covers doubloon:1 + cannonball:1 → remaining: sail:1
        $this->game->payWithOptionalBooty(1, ['sail' => 1, 'doubloon' => 1, 'cannonball' => 1], 103);

        $this->assertEquals(1, $this->game->paidCosts[0]['sail']);
        $this->assertEquals(0, $this->game->paidCosts[0]['doubloon'] ?? 0);
        $this->assertEquals(0, $this->game->paidCosts[0]['cannonball'] ?? 0);
        $this->assertEquals(1, $this->game->mockPlayerResources['sail']); // 2 - 1
    }

    // --- with booty wild (choice) resources ----------------------------------

    public function testPayWithBootyChoiceAutoResolved(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 104, 3); // doubloon:1, choice:1
        $this->game->mockPlayerResources = ['sail' => 0, 'cannonball' => 0, 'doubloon' => 0];
        $this->game->paidCosts = [];

        // Cost: sail:1, doubloon:1
        // doubloon:1 covers doubloon. choice auto → sail (only remaining need).
        $this->game->payWithOptionalBooty(1, ['sail' => 1, 'doubloon' => 1], 104);

        $this->assertEquals(0, $this->game->paidCosts[0]['sail'] ?? 0);
        $this->assertEquals(0, $this->game->paidCosts[0]['doubloon'] ?? 0);
    }

    public function testPayWithBootyChoiceGoesToHigherNeed(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 105, 4); // cannonball:1, choice:1
        $this->game->mockPlayerResources = ['sail' => 1, 'cannonball' => 0, 'doubloon' => 0];
        $this->game->paidCosts = [];

        // Cost: sail:2, cannonball:1
        // cannonball:1 covers cannonball. choice → sail (need 2 vs 0).
        // Remaining: sail:1. Player has sail:1, can afford.
        $this->game->payWithOptionalBooty(1, ['sail' => 2, 'cannonball' => 1], 105);

        $this->assertEquals(1, $this->game->paidCosts[0]['sail']);
        $this->assertEquals(0, $this->game->paidCosts[0]['cannonball'] ?? 0);
    }

    // --- error cases ---------------------------------------------------------

    public function testPayWithBootyCannotAffordRemainder(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 200, 2); // sail:1, cannonball:1
        $this->game->mockPlayerResources = ['sail' => 0, 'cannonball' => 0, 'doubloon' => 0];

        // Cost: sail:3, cannonball:2. Booty covers sail:1, cannonball:1.
        // Remaining: sail:2, cannonball:1 — player has 0 of each.
        $this->expectException(BgaUserException::class);
        $this->game->payWithOptionalBooty(1, ['sail' => 3, 'cannonball' => 2], 200);
    }

    public function testPayWithBootyInvalidCardId(): void {
        $this->setupMockCards(); // empty deck
        $this->game->mockPlayerResources = ['sail' => 5];

        $this->expectException(BgaUserException::class);
        $this->game->payWithOptionalBooty(1, ['sail' => 1], 999);
    }

    public function testPayWithBootyNotOwnedByPlayer(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 201, 2, 2); // belongs to player 2
        $this->game->mockPlayerResources = ['sail' => 5];

        $this->expectException(BgaUserException::class);
        $this->game->payWithOptionalBooty(1, ['sail' => 1], 201);
    }

    public function testPayWithBootyCardNotInPlayerLocation(): void {
        $deck = $this->setupMockCards();
        $deck->cards[202] = [
            'id' => 202, 'type' => 'booty', 'type_arg' => 2,
            'location' => 'booty_discard', 'location_arg' => 1,
        ];
        $this->game->mockPlayerResources = ['sail' => 5];

        $this->expectException(BgaUserException::class);
        $this->game->payWithOptionalBooty(1, ['sail' => 1], 202);
    }

    // --- booty notification content ------------------------------------------

    public function testBootyNotificationDescribesUsedResources(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 300, 2); // sail:1, cannonball:1
        $this->game->mockPlayerResources = ['sail' => 0, 'cannonball' => 0];
        $this->game->trackedNotifications = [];

        $this->game->payWithOptionalBooty(1, ['sail' => 1, 'cannonball' => 1], 300);

        $notif = $this->game->trackedNotifications[0];
        $this->assertEquals('bootyTokenUsed', $notif['type']);
        $usage = $notif['args']['booty_usage'];
        $this->assertStringContainsString('[sail]', $usage);
        $this->assertStringContainsString('[cannonball]', $usage);
    }

    public function testBootyNotificationCapsAtCost(): void {
        $deck = $this->setupMockCards();
        $this->addBootyCard($deck, 301, 0); // sail:3
        $this->game->mockPlayerResources = ['sail' => 0];
        $this->game->trackedNotifications = [];

        // Cost: sail:1. Booty resolved = sail:3, but usage caps at cost.
        $this->game->payWithOptionalBooty(1, ['sail' => 1], 301);

        $usage = $this->game->trackedNotifications[0]['args']['booty_usage'];
        // Should contain exactly one [sail] (min(3,1) = 1)
        $this->assertEquals('[sail]', $usage);
    }
}
