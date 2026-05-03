<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../../seasofhavoc.game.php";

class TestGamestateMachine extends \Bga\GameFramework\GamestateMachine {}

class SeasOfHavocUT extends SeasOfHavoc
{
    public array $resource_types;
    public array $token_names;
    public array $non_playable_cards;
    public array $playable_cards;
    public array $booty_tokens = [];

    function __construct()
    {
        // Don't call parent constructor to avoid DB initialization
        include __DIR__ . "/../material.inc.php";
        $this->players = [
            1 => ["player_name" => "TestPlayer", "player_color" => "ff0000", "player_no" => 1],
            2 => ["player_name" => "OtherPlayer", "player_color" => "00ff00", "player_no" => 2],
        ];
        $this->gamestate = new TestGamestateMachine();
        $this->gamestate->_setStates([
            2 => [
                "id" => 2,
                "name" => "test",
                "type" => "activeplayer",
                "active_player" => "1",
                "transitions" => ["islandTurnDone" => 3],
            ],
            3 => [
                "id" => 3,
                "name" => "done",
                "type" => "game",
                "active_player" => "1",
                "transitions" => [],
            ],
        ]);
    }

    public function testGetResourceTypes(): array
    {
        return $this->resource_types;
    }
}

if (!class_exists("MockBGA")) {
    class MockBGA
    {
        public function dump($label, $data)
        {
            // Mock implementation - do nothing
        }

        public function trace($message)
        {
            // Mock implementation - do nothing
        }
    }
}

final class SeasOfHavocTest extends TestCase
{
    private $game;

    protected function setUp(): void
    {
        $this->game = new SeasOfHavocUT();
    }

    // Game-level tests

    public function testGameProgression(): void
    {
        // Test that getGameProgression method exists and returns an integer
        // We can't test the actual progression without database setup
        $this->assertTrue(method_exists($this->game, "getGameProgression"));

        // Test that the method is callable
        $this->assertTrue(is_callable([$this->game, "getGameProgression"]));
    }

    // Resource calculation tests

    public function testSumArrayByKey(): void
    {
        // Test summing two arrays by key
        $array1 = ["sail" => 2, "cannonball" => 3, "doubloon" => 1];
        $array2 = ["sail" => 1, "cannonball" => 2, "skiff" => 1];

        $result = $this->game->sum_array_by_key($array1, $array2);

        $this->assertEquals(3, $result["sail"]); // 2 + 1
        $this->assertEquals(5, $result["cannonball"]); // 3 + 2
        $this->assertEquals(1, $result["doubloon"]); // 1 + 0
        $this->assertEquals(1, $result["skiff"]); // 0 + 1
    }

    public function testSumArrayByKeyMultipleArrays(): void
    {
        // Test summing three arrays
        $array1 = ["sail" => 1, "cannonball" => 2];
        $array2 = ["sail" => 2, "doubloon" => 3];
        $array3 = ["sail" => 1, "cannonball" => 1, "skiff" => 1];

        $result = $this->game->sum_array_by_key($array1, $array2, $array3);

        $this->assertEquals(4, $result["sail"]); // 1 + 2 + 1
        $this->assertEquals(3, $result["cannonball"]); // 2 + 0 + 1
        $this->assertEquals(3, $result["doubloon"]); // 0 + 3 + 0
        $this->assertEquals(1, $result["skiff"]); // 0 + 0 + 1
    }

    public function testSumArrayByKeyEmptyArrays(): void
    {
        // Test with empty arrays
        $result = $this->game->sum_array_by_key([]);
        $this->assertEmpty($result);

        // Test one empty, one with values
        $array1 = ["sail" => 2];
        $result = $this->game->sum_array_by_key([], $array1);
        $this->assertEquals(2, $result["sail"]);
    }

    public function testMakeCostNegative(): void
    {
        // Test making all cost values negative
        $cost = ["sail" => 2, "cannonball" => 3, "doubloon" => 1];
        $result = $this->game->makeCostNegative($cost);

        $this->assertEquals(-2, $result["sail"]);
        $this->assertEquals(-3, $result["cannonball"]);
        $this->assertEquals(-1, $result["doubloon"]);
    }

    public function testMakeCostNegativeWithZero(): void
    {
        // Test with zero values
        $cost = ["sail" => 0, "cannonball" => 5];
        $result = $this->game->makeCostNegative($cost);

        $this->assertEquals(0, $result["sail"]);
        $this->assertEquals(-5, $result["cannonball"]);
    }

    public function testCanPayForSufficientResources(): void
    {
        // Test when player has enough resources
        $cost = ["sail" => 2, "cannonball" => 1];
        $resources = ["sail" => 3, "cannonball" => 2, "doubloon" => 1];

        $this->assertTrue($this->game->canPayFor($cost, $resources));
    }

    public function testCanPayForExactResources(): void
    {
        // Test when player has exactly the right amount
        $cost = ["sail" => 2, "cannonball" => 1];
        $resources = ["sail" => 2, "cannonball" => 1];

        $this->assertTrue($this->game->canPayFor($cost, $resources));
    }

    public function testCanPayForInsufficientResources(): void
    {
        // Test when player doesn't have enough resources
        $cost = ["sail" => 3, "cannonball" => 2];
        $resources = ["sail" => 2, "cannonball" => 1, "doubloon" => 5];

        $this->assertFalse($this->game->canPayFor($cost, $resources));
    }

    public function testCanPayForMissingResourceType(): void
    {
        // Test when player doesn't have a required resource type at all
        $cost = ["sail" => 2, "cannonball" => 1];
        $resources = ["doubloon" => 10]; // No sail or cannonball

        $this->assertFalse($this->game->canPayFor($cost, $resources));
    }

    public function testCanPayForEmptyCost(): void
    {
        // Test with no cost (should always be affordable)
        $cost = [];
        $resources = ["sail" => 2, "cannonball" => 1];

        $this->assertTrue($this->game->canPayFor($cost, $resources));
    }

    public function testCanPayForZeroCost(): void
    {
        // Test with zero-cost items
        $cost = ["sail" => 0, "cannonball" => 0];
        $resources = ["sail" => 0, "cannonball" => 0];

        $this->assertTrue($this->game->canPayFor($cost, $resources));
    }
}
