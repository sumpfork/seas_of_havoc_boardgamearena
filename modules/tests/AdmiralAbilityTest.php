<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class AdmiralActionUT extends SeasOfHavocUT {
    public array $mockCaptains = [];
    public array $drawCalls = [];
    public array $infamyAwards = [];
    public array $logNotifications = [];
    public array $mockPlayerResources = [];
    public array $resourceGainCalls = [];
    public string $mockActivePlayerId = "1";
    public MockCardDeck $cards;

    public function __construct() {
        parent::__construct();
        $this->cards = new MockCardDeck();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($this, $this->cards);
        $game = $this;
        $this->bga->notify = new CapturingMockNotify($this, function(string $type, string $message, array $args) use ($game) {
            if ($type === "log") {
                $game->logNotifications[] = ["message" => $message, "args" => $args];
            }
        });
    }

    public function getActivePlayerId(): string {
        return $this->mockActivePlayerId;
    }

    public function getPlayerCaptain($player_id) {
        return $this->mockCaptains[$player_id] ?? null;
    }

    public function drawCards(string $player_id, int $num_cards = 1) {
        $this->drawCalls[] = [
            "player_id" => $player_id,
            "num_cards" => $num_cards,
        ];
    }

    public function scoreInfamy(string $player_id, int $amount, string $message = "") {
        $this->infamyAwards[] = [
            "player_id" => $player_id,
            "amount" => $amount,
            "message" => $message,
        ];
    }

    public function getPlayerNameById(int $player_id): string {
        return $this->players[$player_id]["player_name"] ?? "Player $player_id";
    }

    public function getGameResourcesHierarchical(?int $player_id = null): array {
        $pid = $player_id ?? (int) $this->mockActivePlayerId;
        return [$pid => $this->mockPlayerResources];
    }

    public function playerGainResources($player_id, $resources) {
        $this->resourceGainCalls[] = $resources;
        foreach ($resources as $resource => $amount) {
            $this->mockPlayerResources[$resource] = ($this->mockPlayerResources[$resource] ?? 0) + $amount;
        }
    }
}

final class AdmiralAbilityTest extends TestCase {
    private AdmiralActionUT $game;

    protected function setUp(): void {
        $this->game = new AdmiralActionUT();
        $this->game->mockCaptains = [
            1 => "admiral",
            2 => "merchant",
        ];
    }

    // --- applyAdmiralTokenTakenAbilities ---

    public function testAdmiralDrawsCardWhenTakingFirstPlayerTokenFromAnotherPlayer(): void {
        $this->game->applyAdmiralTokenTakenAbilities("1", "first_player_token", true);

        $this->assertSame([["player_id" => "1", "num_cards" => 1]], $this->game->drawCalls);
        $this->assertSame([], $this->game->infamyAwards);
        $this->assertCount(1, $this->game->logNotifications);
    }

    public function testAdmiralGainsInfamyWhenTakingFlagFromAnotherPlayer(): void {
        $this->game->applyAdmiralTokenTakenAbilities("1", "green_flag", true);

        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame(
            [["player_id" => "1", "amount" => 1]],
            array_map(
                fn($award) => ["player_id" => $award["player_id"], "amount" => $award["amount"]],
                $this->game->infamyAwards,
            ),
        );
    }

    public function testAdmiralDoesNotGainRewardsWhenClaimingUnownedToken(): void {
        $this->game->applyAdmiralTokenTakenAbilities("1", "green_flag", false);
        $this->game->applyAdmiralTokenTakenAbilities("1", "first_player_token", false);

        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame([], $this->game->infamyAwards);
        $this->assertSame([], $this->game->logNotifications);
    }

    public function testNonAdmiralDoesNotGainRewardsWhenTakingTokenFromAnotherPlayer(): void {
        $this->game->mockCaptains = [
            1 => "merchant",
            2 => "admiral",
        ];

        $this->game->applyAdmiralTokenTakenAbilities("1", "first_player_token", true);
        $this->game->applyAdmiralTokenTakenAbilities("1", "tan_flag", true);

        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame([], $this->game->infamyAwards);
        $this->assertSame([], $this->game->logNotifications);
    }

    // --- Government Funding ---

    public function testGovernmentFundingGainsOneSailWhenMissingSail(): void {
        $this->game->mockPlayerResources = ["sail" => 0, "cannonball" => 2, "doubloon" => 1, "skiff" => 0];

        $this->game->processGovernmentFunding("1");

        $this->assertSame([["sail" => 1]], $this->game->resourceGainCalls);
        $this->assertSame([], $this->game->drawCalls);
    }

    public function testGovernmentFundingGainsOneOfEachMissingResourceType(): void {
        $this->game->mockPlayerResources = ["sail" => 0, "cannonball" => 0, "doubloon" => 1, "skiff" => 0];

        $this->game->processGovernmentFunding("1");

        $this->assertSame([["sail" => 1, "cannonball" => 1]], $this->game->resourceGainCalls);
        $this->assertSame([], $this->game->drawCalls);
    }

    public function testGovernmentFundingGainsAllThreeWhenPlayerHasNone(): void {
        $this->game->mockPlayerResources = ["sail" => 0, "cannonball" => 0, "doubloon" => 0, "skiff" => 0];

        $this->game->processGovernmentFunding("1");

        $this->assertSame([["sail" => 1, "cannonball" => 1, "doubloon" => 1]], $this->game->resourceGainCalls);
        $this->assertSame([], $this->game->drawCalls);
    }

    public function testGovernmentFundingDrawsCardWhenPlayerOwnsAllResourceTypes(): void {
        $this->game->mockPlayerResources = ["sail" => 1, "cannonball" => 3, "doubloon" => 2, "skiff" => 0];

        $this->game->processGovernmentFunding("1");

        $this->assertSame([], $this->game->resourceGainCalls);
        $this->assertSame([["player_id" => "1", "num_cards" => 1]], $this->game->drawCalls);
    }

    public function testGovernmentFundingDoesNotCountSkiffAsResourceType(): void {
        $this->game->mockPlayerResources = ["sail" => 1, "cannonball" => 1, "doubloon" => 0, "skiff" => 3];

        $this->game->processGovernmentFunding("1");

        $this->assertSame([["doubloon" => 1]], $this->game->resourceGainCalls);
        $this->assertSame([], $this->game->drawCalls);
    }

    // --- Inspire ---

    private function damageCardType(): int {
        $damage = array_filter($this->game->playable_cards, fn($c) => ($c["category"] ?? "") === "damage");
        return (int) array_key_first($damage);
    }

    public function testInspireGainsInfamyAndDrawsCardWhenDiscardHasNoDamageCards(): void {
        $this->game->cards->locations["player_discard_1"] = [
            ["id" => 10, "type" => 5, "location" => "player_discard", "location_arg" => "1"],
        ];

        $this->game->processInspire("1");

        $this->assertSame([["player_id" => "1", "amount" => 1]], array_map(
            fn($a) => ["player_id" => $a["player_id"], "amount" => $a["amount"]],
            $this->game->infamyAwards,
        ));
        $this->assertSame([["player_id" => "1", "num_cards" => 1]], $this->game->drawCalls);
        $this->assertSame([], $this->game->cards->moveCardCalls);
    }

    public function testInspireGainsInfamyAndDrawsCardWhenDiscardIsEmpty(): void {
        $this->game->cards->locations["player_discard_1"] = [];

        $this->game->processInspire("1");

        $this->assertCount(1, $this->game->infamyAwards);
        $this->assertCount(1, $this->game->drawCalls);
        $this->assertSame([], $this->game->cards->moveCardCalls);
    }

    public function testInspireScrapsDamageCardWhenDiscardHasDamageCard(): void {
        $damage_type = $this->damageCardType();
        $this->game->cards->locations["player_discard_1"] = [
            ["id" => 99, "type" => $damage_type, "location" => "player_discard", "location_arg" => "1"],
        ];

        $this->game->cards->cards = array_column($this->game->cards->locations["player_discard_1"], null, "id");
        $this->game->processInspire("1");

        $this->assertSame([], $this->game->infamyAwards);
        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame([["card_id" => 99, "location" => "scrap"]], $this->game->cards->moveCardCalls);
    }

    public function testInspireScrapsOnlyOneDamageCardWhenMultipleExist(): void {
        $damage_type = $this->damageCardType();
        $this->game->cards->locations["player_discard_1"] = [
            ["id" => 99, "type" => $damage_type, "location" => "player_discard", "location_arg" => "1"],
            ["id" => 100, "type" => $damage_type, "location" => "player_discard", "location_arg" => "1"],
        ];

        $this->game->cards->cards = array_column($this->game->cards->locations["player_discard_1"], null, "id");
        $this->game->processInspire("1");

        $this->assertCount(1, $this->game->cards->moveCardCalls);
        $this->assertSame("scrap", $this->game->cards->moveCardCalls[0]["location"]);
    }
}
