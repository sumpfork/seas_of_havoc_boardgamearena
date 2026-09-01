<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class MerchantCardUT extends SeasOfHavocUT {
    public array $mockCaptains = [];
    public array $infamyAwards = [];
    public array $resourceGainCalls = [];
    public array $mockPlayerResources = [];
    public int $mockPlayerInfamy = 0;

    public function __construct() {
        parent::__construct();
        $mockDeck = new MockCardDeck();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($this, $mockDeck);
    }

    public function getMockCards(): MockCardDeck {
        return (new ReflectionProperty(SeasOfHavoc::class, "cards"))->getValue($this);
    }

    public function getPlayerCaptain($player_id): ?string {
        return $this->mockCaptains[$player_id] ?? null;
    }

    public function getPlayerNameById(int $player_id): string {
        return "Player $player_id";
    }

    protected function getPlayerInfamy(string $player_id): int {
        return $this->mockPlayerInfamy;
    }

    public function getGameResourcesHierarchical(?int $player_id = null): array {
        $pid = $player_id ?? (int) $this->gamestate->getActivePlayerId();
        return [$pid => $this->mockPlayerResources];
    }

    public function playerGainResources($player_id, $resources): void {
        $this->resourceGainCalls[] = $resources;
        foreach ($resources as $res => $amount) {
            $this->mockPlayerResources[$res] = ($this->mockPlayerResources[$res] ?? 0) + $amount;
        }
    }

    public function scoreInfamy(string $player_id, int $amount, string $message = ""): void {
        $this->infamyAwards[] = ["player_id" => $player_id, "amount" => $amount];
        $this->mockPlayerInfamy += $amount;
    }

    public function loadPlayersBasicInfos(): array {
        return $this->players;
    }
}

final class MerchantCardAbilityTest extends TestCase {
    private MerchantCardUT $game;
    private int $firstMarketCardType;
    private array $firstMarketCardCost;

    protected function setUp(): void {
        $this->game = new MerchantCardUT();
        $this->game->mockCaptains = [1 => "merchant", 2 => "pirate_queen"];

        foreach ($this->game->playable_cards as $t => $card) {
            if (($card["category"] ?? "") === "market_card" && !empty($card["cost"])) {
                $this->firstMarketCardType = $t;
                $this->firstMarketCardCost = $card["cost"];
                break;
            }
        }
    }

    // --- Barter ---

    public function testProcessBarterReturnsState(): void {
        $result = $this->game->processBarter("1");
        $this->assertSame(STATE_BARTER, $result);
    }

    public function testActBarterSailToInfamy(): void {
        $this->game->mockPlayerResources = ["sail" => 2, "cannonball" => 0, "doubloon" => 0];

        $result = $this->game->actBarterExchange("sail", "resource_to_infamy");

        $this->assertSame([["sail" => -1]], $this->game->resourceGainCalls);
        $this->assertSame([["player_id" => "1", "amount" => 1]], $this->game->infamyAwards);
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }

    public function testActBarterCannonballToInfamy(): void {
        $this->game->mockPlayerResources = ["sail" => 0, "cannonball" => 1, "doubloon" => 0];

        $this->game->actBarterExchange("cannonball", "resource_to_infamy");

        $this->assertSame([["player_id" => "1", "amount" => 2]], $this->game->infamyAwards);
    }

    public function testActBarterDoubloonToInfamy(): void {
        $this->game->mockPlayerResources = ["sail" => 0, "cannonball" => 0, "doubloon" => 1];

        $this->game->actBarterExchange("doubloon", "resource_to_infamy");

        $this->assertSame([["player_id" => "1", "amount" => 3]], $this->game->infamyAwards);
    }

    public function testActBarterInfamyToSail(): void {
        $this->game->mockPlayerInfamy = 1;
        $this->game->mockPlayerResources = [];

        $this->game->actBarterExchange("sail", "infamy_to_resource");

        $this->assertSame([["player_id" => "1", "amount" => -1]], $this->game->infamyAwards);
        $this->assertSame([["sail" => 1]], $this->game->resourceGainCalls);
    }

    public function testActBarterInfamyToCannonball(): void {
        $this->game->mockPlayerInfamy = 5;
        $this->game->mockPlayerResources = [];

        $this->game->actBarterExchange("cannonball", "infamy_to_resource");

        $this->assertSame([["player_id" => "1", "amount" => -2]], $this->game->infamyAwards);
        $this->assertSame([["cannonball" => 1]], $this->game->resourceGainCalls);
    }

    public function testActBarterInfamyToResourceInsufficientInfamy(): void {
        $this->game->mockPlayerInfamy = 1;

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actBarterExchange("cannonball", "infamy_to_resource");
    }

    public function testActBarterInvalidResource(): void {
        $this->game->mockPlayerResources = [];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actBarterExchange("skiff", "resource_to_infamy");
    }

    public function testActBarterNonMerchantThrows(): void {
        $this->game->mockCaptains[1] = "pirate_queen";
        $this->game->mockPlayerResources = ["sail" => 1];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actBarterExchange("sail", "resource_to_infamy");
    }

    public function testActSkipBarter(): void {
        $result = $this->game->actSkipBarter();
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }

    // --- Timely Trading ---

    public function testProcessTimelyTradingReturnsState(): void {
        $this->game->getMockCards()->locations["market"] = [];

        $result = $this->game->processTimelyTrading("1");

        $this->assertSame(STATE_TIMELY_TRADING, $result);
    }

    public function testActTimelyTradingGainDoubloons(): void {
        $result = $this->game->actTimelyTradingGainDoubloons();

        $this->assertSame([["doubloon" => 2]], $this->game->resourceGainCalls);
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }

    public function testActTimelyTradingGainDoubloonsMerchantOnly(): void {
        $this->game->mockCaptains[1] = "pirate_queen";

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actTimelyTradingGainDoubloons();
    }

    public function testActTimelyTradingPurchaseCard(): void {
        $cost = $this->firstMarketCardCost;
        $this->game->getMockCards()->cards[42] = [
            "id" => 42, "type" => $this->firstMarketCardType, "location" => "market", "location_arg" => "0",
        ];
        $this->game->mockPlayerResources = $cost;

        $result = $this->game->actTimelyTradingPurchaseCard(42);

        $this->assertSame([["card_id" => 42, "location" => "hand"]], $this->game->getMockCards()->moveCardCalls);
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }

    public function testActTimelyTradingPurchaseCardRejectsNonMarket(): void {
        $this->game->getMockCards()->cards[99] = [
            "id" => 99, "type" => $this->firstMarketCardType, "location" => "hand", "location_arg" => "1",
        ];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actTimelyTradingPurchaseCard(99);
    }

    public function testActTimelyTradingPurchaseCardCantAfford(): void {
        $this->game->getMockCards()->cards[42] = [
            "id" => 42, "type" => $this->firstMarketCardType, "location" => "market", "location_arg" => "0",
        ];
        $this->game->mockPlayerResources = ["sail" => 0, "cannonball" => 0, "doubloon" => 0];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actTimelyTradingPurchaseCard(42);
    }

    public function testActTimelyTradingPurchaseCardWithSubstitution(): void {
        // Find a market card with sail cost for substitution test
        $sailCardType = null;
        $sailCardCost = [];
        foreach ($this->game->playable_cards as $t => $card) {
            if (($card["category"] ?? "") === "market_card" && ($card["cost"]["sail"] ?? 0) >= 1) {
                $sailCardType = $t;
                $sailCardCost = $card["cost"];
                break;
            }
        }
        if ($sailCardType === null) {
            $this->markTestSkipped("No market card with sail cost found");
        }

        $this->game->getMockCards()->cards[42] = [
            "id" => 42, "type" => $sailCardType, "location" => "market", "location_arg" => "0",
        ];
        // Pay 1 sail as doubloon (1 doubloon substitutes for sail), rest in original resources
        $cost = $sailCardCost;
        unset($cost["sail"]);
        $cost["doubloon"] = ($cost["doubloon"] ?? 0) + 1;
        $this->game->mockPlayerResources = $cost;

        $result = $this->game->actTimelyTradingPurchaseCard(42, 0, 1);

        $this->assertSame([["card_id" => 42, "location" => "hand"]], $this->game->getMockCards()->moveCardCalls);
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }

    public function testActSkipTimelyTrading(): void {
        $result = $this->game->actSkipTimelyTrading();
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }
}
