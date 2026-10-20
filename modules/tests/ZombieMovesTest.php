<?php declare(strict_types=1);

use Bga\Games\SeasOfHavoc\PrimitiveCardPlayAction;
use Bga\Games\SeasOfHavoc\ShipUpgrades;
use Bga\Games\SeasOfHavoc\CardActionOutcome;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SeasOfHavocTest.php';

/** Card resolution with the board stubbed out: only the decision parsing and costs are real. */
class ZombieMovesUT extends SeasOfHavocUT
{
    public function getActivePlayerId(): string { return '1'; }
    function processSimpleAction(PrimitiveCardPlayAction $action_type): CardActionOutcome { return new CardActionOutcome(); }
    function resolveFireAction(array $variant, string $side): array { return []; }
    function processCaptainAbility(string $ability): CardActionOutcome|int { return new CardActionOutcome(); }
    function useNimbleHull($player_id): void {}
    public string $captain = "admiral";
    function getPlayerCaptain($player_id) { return $this->captain; }
    function canRestockMarket(): bool { return false; }
    function canUseExtraRations($player_id): bool { return false; }
    function getGameResourcesHierarchical(?int $player_id = null) { return [1 => []]; }

    public function __construct()
    {
        parent::__construct();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($this, new MockCardDeck());
    }

    public array $placed = [];
    public function getIslandSlots()
    {
        $slot = fn($occupant, $disabled = false) =>
            ["occupying_player_id" => $occupant, "corsair_occupying_player_id" => null, "disabled" => $disabled];
        return [
            "shipyard" => ["n1" => $slot(null)],
            "bank" => ["n1" => $slot("2")],
            "blacksmith" => ["n2" => $slot(null, true)],
            "trading_post" => ["n1" => $slot(null)],
            "workshop" => ["n1" => $slot(null)],
        ];
    }
    public function getPlayerShipUpgrades($player_id): array { return []; }
    function actPlaceSkiff(string $slotname, string $number): mixed
    {
        $this->placed[] = "$slotname $number";
        return "islandTurnDone";
    }
}

/** A market of three cards of one type, two claimed by player 1, who can afford one of them. */
class ZombiePurchasesUT extends ZombieMovesUT
{
    public ?array $bought = null;
    public int|string $type;
    public array $resources;
    public function getIslandSlots()
    {
        $slot = fn($occupant) => ["occupying_player_id" => $occupant, "corsair_occupying_player_id" => null, "disabled" => false];
        return ["market" => ["n1" => $slot("1"), "n2" => $slot("2"), "n3" => $slot("1")]];
    }
    function getMarketSlots(): array
    {
        return [["id" => "11", "type" => $this->type], ["id" => "12", "type" => $this->type], ["id" => "13", "type" => $this->type], null, null];
    }
    function getGameResourcesHierarchical(?int $player_id = null) { return [1 => $this->resources]; }
    function actCompletePurchases(array $cards_purchased) { $this->bought = $cards_purchased; }
}

final class ZombieMovesTest extends TestCase
{
    /** Every card, bare and with every upgrade: the zombie's random decisions must play and be paid for. */
    public function testRandomDecisionsAreAcceptedAndAffordable(): void
    {
        $game = new ZombieMovesUT();
        $all_upgrades = array_fill_keys(array_keys($game->non_playable_cards), true);
        foreach ($game->playable_cards as $type => $card) {
            foreach ([[], $all_upgrades] as $active) {
                $actions = ShipUpgrades::rewriteActions($card["actions"], $active);
                foreach ([["sail" => 9, "cannonball" => 9, "doubloon" => 9], []] as $resources) {
                    for ($i = 0; $i < 20; $i++) {
                        $budget = $resources;
                        $decisions = $game->randomCardDecisions($actions, $budget);
                        $outcome = $game->processCardActions($actions, $decisions);
                        $this->assertTrue(
                            $game->canPayFor($outcome->cost, $resources),
                            "card $type: " . json_encode($decisions) . " costs " . json_encode($outcome->cost),
                        );
                    }
                }
            }
        }
    }

    /** Only a free, enabled slot the zombie can finish: not taken, disabled, the Trading Post with nothing to trade or an unaffordable Workshop. */
    public function testIslandTurnPlacesOnAFreeSlot(): void
    {
        $game = new ZombieMovesUT();
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame("islandTurnDone", $game->zombieIslandTurn(1));
        }
        $this->assertSame(["shipyard n1"], array_unique($game->placed));
    }

    /** Claimed cards are bought in market order until the next one is unaffordable; others' claims are left alone. */
    public function testPurchasesBuyTheClaimedCardsItCanAfford(): void
    {
        $game = new ZombiePurchasesUT();
        $game->type = array_key_first(array_filter($game->playable_cards, fn($card) => !empty($card["cost"])));
        $game->resources = $game->playable_cards[$game->type]["cost"];
        $game->zombiePurchases(1);
        $this->assertSame([["card_id" => 11]], $game->bought);
    }

    /** The Merchant pays a sail or cannonball shortfall in doubloons. */
    public function testMerchantPaysAShortfallInDoubloons(): void
    {
        $game = new ZombiePurchasesUT();
        $game->captain = "merchant";
        $game->type = array_key_first(array_filter($game->playable_cards, fn($card) => !empty($card["cost"]["sail"])));
        $cost = $game->playable_cards[$game->type]["cost"];
        $game->resources = ["sail" => 0, "doubloon" => ($cost["doubloon"] ?? 0) + $cost["sail"]] + $cost;
        $game->resources["sail"] = 0;
        $game->zombiePurchases(1);
        $this->assertSame([["card_id" => 11, "doubloons_as_sails" => $cost["sail"]]], $game->bought);
    }
}
