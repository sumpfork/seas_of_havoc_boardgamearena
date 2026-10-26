<?php declare(strict_types=1);

use Bga\Games\SeasOfHavoc\SeaBoard;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

/** Player 2 is the Treasure Seeker. */
class TreasureSeekerUT extends SeasOfHavocUT {
    public function getPlayerCaptain($player_id) { return $player_id == 2 ? "treasure_seeker" : null; }
}

final class TreasureSeekerAbilityTest extends TestCase {
    private SeasOfHavocUT $game;
    private array $mockCellObjects = [];

    protected function setUp(): void {
        $this->game = new TreasureSeekerUT();
        $cellObjects = &$this->mockCellObjects;

        $mockSeaboard = $this->getMockBuilder(SeaBoard::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["getObjectsOfTypes", "getSurroundingPositions"])
            ->getMock();

        $mockSeaboard->method("getObjectsOfTypes")->willReturnCallback(
            function (int $x, int $y, array $types) use (&$cellObjects): array {
                return array_values(
                    array_filter(
                        $cellObjects["$x,$y"] ?? [],
                        fn($object) => in_array($object["type"], $types, true),
                    ),
                );
            },
        );

        $mockSeaboard->method("getSurroundingPositions")->willReturnCallback(
            fn(int $x, int $y): array => [
                ["x" => $x, "y" => $y + 1],
                ["x" => $x + 1, "y" => $y],
            ],
        );

        $ref = new ReflectionProperty(SeasOfHavoc::class, "seaboard");
        $ref->setValue($this->game, $mockSeaboard);
    }

    private function setCellObjects(int $x, int $y, array $objects): void {
        $this->mockCellObjects["$x,$y"] = $objects;
    }

    public function testShipwreckCanBePlacedOnGustOrWhirlpool(): void {
        $this->setCellObjects(1, 2, [["type" => "gust", "arg" => "0"]]);
        $this->setCellObjects(2, 2, [["type" => "whirlpool", "arg" => "0"]]);

        $this->assertTrue($this->game->isValidShipwreckBoardPosition(1, 2));
        $this->assertTrue($this->game->isValidShipwreckBoardPosition(2, 2));
    }

    public function testShipwreckCannotBePlacedOnRockShipOrOtherShipwreck(): void {
        $this->setCellObjects(1, 2, [["type" => "rock", "arg" => "0"]]);
        $this->setCellObjects(2, 2, [["type" => "player_ship", "arg" => "1"]]);
        $this->setCellObjects(3, 2, [["type" => "shipwreck", "arg" => "0"]]);

        $this->assertFalse($this->game->isValidShipwreckBoardPosition(1, 2));
        $this->assertFalse($this->game->isValidShipwreckBoardPosition(2, 2));
        $this->assertFalse($this->game->isValidShipwreckBoardPosition(3, 2));
    }

    public function testValidTreasureSeekerPositionsIncludeGustAndWhirlpoolNeighbors(): void {
        $this->setCellObjects(2, 3, [["type" => "gust", "arg" => "0"]]);
        $this->setCellObjects(3, 2, [["type" => "whirlpool", "arg" => "0"]]);

        $valid = $this->game->getValidTreasureSeekerShipwreckPositions(2, 2);

        $this->assertContains(["x" => 2, "y" => 3], $valid);
        $this->assertContains(["x" => 3, "y" => 2], $valid);
    }

    /** One card salvaged wrecks 1, 2 and 3; wreck 3 was placed with no surrounding space free. */
    public function testTreasureSeekerAdjustsEachPlacementInTurn(): void {
        $this->setCellObjects(5, 6, [["type" => "rock", "arg" => "0"]]);
        $this->setCellObjects(6, 5, [["type" => "rock", "arg" => "1"]]);
        $events = [
            ["shipwreck_arg" => "1", "new_x" => 7, "new_y" => 7],
            ["shipwreck_arg" => "3", "new_x" => 5, "new_y" => 5],
            ["shipwreck_arg" => "2", "new_x" => 3, "new_y" => 3],
        ];
        $pending = fn() => array_map(
            fn($label) => $this->game->getGameStateValue($label),
            ["pending_shipwreck_arg", "pending_shipwreck_x", "pending_shipwreck_y"],
        );

        $this->assertTrue($this->game->tryBeginTreasureSeekerShipwreckAdjust($events));
        $this->game->gamestate->changeActivePlayer(2);
        $this->assertSame([1, 7, 7], $pending());
        $this->assertSame(STATE_TREASURE_SEEKER_ADJUST, $this->game->actSkipTreasureSeekerAdjust());
        $this->assertSame([2, 3, 3], $pending());
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->actSkipTreasureSeekerAdjust());
        $this->assertEquals(1, $this->game->getActivePlayerId(), "play returns to the player who salvaged");
    }

    public function testNothingToAdjustWhenNoPlacementCanMove(): void {
        $this->setCellObjects(5, 6, [["type" => "rock", "arg" => "0"]]);
        $this->setCellObjects(6, 5, [["type" => "rock", "arg" => "1"]]);
        $this->assertFalse($this->game->tryBeginTreasureSeekerShipwreckAdjust(
            [["shipwreck_arg" => "1", "new_x" => 5, "new_y" => 5]],
        ));
    }
}
