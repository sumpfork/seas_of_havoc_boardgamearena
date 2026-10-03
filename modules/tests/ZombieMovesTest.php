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

    /** Only a free, enabled slot the zombie can finish: not taken, disabled, the Trading Post or an unaffordable Workshop. */
    public function testIslandTurnPlacesOnAFreeSlot(): void
    {
        $game = new ZombieMovesUT();
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame("islandTurnDone", $game->zombieIslandTurn(1));
        }
        $this->assertSame(["shipyard n1"], array_unique($game->placed));
    }
}
