<?php declare(strict_types=1);

use Bga\Games\SeasOfHavoc\CardActionOutcome;
use Bga\Games\SeasOfHavoc\Heading;
use Bga\Games\SeasOfHavoc\PrimitiveCardPlayAction;
use Bga\Games\SeasOfHavoc\SeaBoard;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

/** Salvaging on an in-memory board: the DB query function does nothing. */
final class ShipwreckSalvageTest extends TestCase
{
    /** Salvaged wrecks stay off the board until the turn has played out, so nothing in it can salvage them again. */
    public function testSalvagedShipwrecksArePlacedAgainOnlyWhenAskedTo(): void
    {
        $game = new SeasOfHavocUT();
        $board = new SeaBoard(fn($sql) => [], $game->bga);
        (new ReflectionProperty(SeasOfHavoc::class, "seaboard"))->setValue($game, $board);
        $deck = new MockCardDeck();
        $deck->cards[90] = ["id" => 90, "type" => "booty", "type_arg" => 1, "location" => "booty_deck", "location_arg" => 0];
        $deck->locations["booty_deck"] = [$deck->cards[90]];
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($game, $deck);

        $board->placeObject(1, 1, ["type" => "player_ship", "arg" => "1", "heading" => Heading::EAST]);
        $board->placeObject(2, 1, ["type" => "shipwreck", "arg" => "4", "heading" => Heading::NO_HEADING]);
        $board->placeObject(3, 1, ["type" => "shipwreck", "arg" => "5", "heading" => Heading::NO_HEADING]);

        $outcome = new CardActionOutcome();
        $outcome->absorb($game->processSimpleAction(PrimitiveCardPlayAction::FORWARD));
        $outcome->absorb($game->processSimpleAction(PrimitiveCardPlayAction::FORWARD));
        $wrecks = fn() => array_values(array_filter($board->getAllObjectsFlat(), fn($o) => $o["type"] === "shipwreck"));
        $this->assertSame([], $wrecks(), "both wrecks are off the board while the card resolves");
        $this->assertSame([["shipwreck_arg" => "4", "old_x" => 2, "old_y" => 1], ["shipwreck_arg" => "5", "old_x" => 3, "old_y" => 1]], $outcome->shipwreckEvents);

        $events = $game->placeSalvagedShipwrecks();
        $this->assertSame(["4", "5"], array_column($events, "shipwreck_arg"));
        $this->assertSame([], $game->placeSalvagedShipwrecks(), "each is placed once");
        $placed = $wrecks();
        $this->assertCount(2, $placed);
        foreach ($events as $event) {
            $this->assertEmpty($board->getObjectsOfTypes($event["new_x"], $event["new_y"], ["player_ship", "rock"]));
            $this->assertNotEmpty(array_filter($placed, fn($o) =>
                $o["arg"] === $event["shipwreck_arg"] && $o["x"] === $event["new_x"] && $o["y"] === $event["new_y"]));
        }
    }
}
