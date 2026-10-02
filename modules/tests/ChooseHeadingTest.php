<?php declare(strict_types=1);

use Bga\Games\SeasOfHavoc\Heading;
use Bga\Games\SeasOfHavoc\SeaBoard;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class ChooseHeadingUT extends SeasOfHavocUT
{
    public string $active = "1";
    public function getActivePlayerId(): string { return $this->active; }
    public function activeNextPlayer(): int|string { return $this->active = $this->active === "1" ? "2" : "1"; }
    public function giveExtraTime(int $playerId, ?int $specificTime = null): void {}
    public function getPlayerNameById(int $player_id): string { return "Player $player_id"; }
}

final class ChooseHeadingTest extends TestCase
{
    /** Player 1 points both ships, then player 2 their one, then the Island Phase starts. */
    public function testPlayersPointEachOfTheirShipsInTurn(): void
    {
        $game = new ChooseHeadingUT();
        $ships = [
            "1" => ["x" => 0, "y" => 0, "object" => ["type" => "player_ship", "arg" => "1", "heading" => Heading::NO_HEADING]],
            "1_2" => ["x" => 1, "y" => 0, "object" => ["type" => "player_ship", "arg" => "1_2", "heading" => Heading::NO_HEADING]],
            "2" => ["x" => 2, "y" => 0, "object" => ["type" => "player_ship", "arg" => "2", "heading" => Heading::NO_HEADING]],
        ];
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()
            ->onlyMethods(["findObject", "removeObject", "placeObject"])->getMock();
        $board->method("findObject")->willReturnCallback(function ($type, $arg) use (&$ships) { return $ships[$arg] ?? null; });
        $board->method("placeObject")->willReturnCallback(function ($x, $y, $o) use (&$ships) {
            $ships[$o["arg"]]["object"] = $o;
        });
        (new ReflectionProperty(SeasOfHavoc::class, "seaboard"))->setValue($game, $board);

        $this->assertSame("1", $game->argChooseHeading()["ship"]["arg"]);
        $this->assertSame(STATE_CHOOSE_HEADING, $game->actChooseHeading(Heading::EAST->value));
        $this->assertSame("1_2", $game->argChooseHeading()["ship"]["arg"]);
        $this->assertSame(STATE_CHOOSE_HEADING, $game->actChooseHeading(Heading::SOUTH->value));
        $this->assertSame("2", $game->argChooseHeading()["ship"]["arg"]);
        $this->assertSame(STATE_ISLAND_PHASE_SETUP, $game->actChooseHeading(Heading::WEST->value));
        $this->assertSame(
            [Heading::EAST, Heading::SOUTH, Heading::WEST],
            array_map(fn($s) => $s["object"]["heading"], array_values($ships)),
        );
    }
}
