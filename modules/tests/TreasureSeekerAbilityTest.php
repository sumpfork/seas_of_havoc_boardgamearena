<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

final class TreasureSeekerAbilityTest extends TestCase {
    private SeasOfHavocUT $game;
    private array $mockCellObjects = [];

    protected function setUp(): void {
        $this->game = new SeasOfHavocUT();
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
}
