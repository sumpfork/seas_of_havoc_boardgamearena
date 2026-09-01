<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../../seasofhavoc.game.php";
require_once __DIR__ . "/../../states.inc.php";

if (!class_exists("MockCardDeck")) {
    class MockCardDeck {
        public array $cards = [];
        public array $locations = [];
        public array $moveCardCalls = [];

        public function getCard(int $card_id): ?array {
            return $this->cards[$card_id] ?? null;
        }

        public function getCardsInLocation(string $location, $location_arg = null): array {
            $key = $location . ($location_arg !== null ? "_$location_arg" : "");
            return $this->locations[$key] ?? [];
        }

        public function getPlayerHand(string $player_id): array {
            return $this->getCardsInLocation("hand", $player_id);
        }

        public function moveCard(int $card_id, string $location): void {
            $this->moveCardCalls[] = ["card_id" => $card_id, "location" => $location];
        }
    }
}

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
        // material.inc.php sets $this->resource_types etc. on the child class's public
        // property slots. The private SeasOfHavoc slots remain uninitialized, so we copy
        // them via reflection so game methods (which use private scope) can access them.
        foreach (["resource_types", "playable_cards", "non_playable_cards"] as $prop) {
            $r = new ReflectionProperty(SeasOfHavoc::class, $prop);
            $r->setValue($this, $this->$prop);
        }
        $this->players = [
            1 => ["player_name" => "TestPlayer", "player_color" => "ff0000", "player_no" => 1],
            2 => ["player_name" => "OtherPlayer", "player_color" => "00ff00", "player_no" => 2],
        ];
        $this->bga = new MockBGA();
        $this->bga->notify = new CapturingMockNotify($this);
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

    // In production, action methods are called through the state class wrapper which
    // reads the return value and calls nextState(). In tests we call them directly,
    // so we need to do that ourselves.
    private function runAction(callable $action): mixed
    {
        $transition = $action();
        if (is_string($transition) && $transition !== '') {
            $this->gamestate->nextState($transition);
        }
        return $transition;
    }

    public function actPlaceSkiff(string $slotname, string $number): mixed
    {
        return $this->runAction(fn() => parent::actPlaceSkiff($slotname, $number));
    }

    public function actResourcePickedInDialog(string $resource, string $context, string $number): mixed
    {
        return $this->runAction(fn() => parent::actResourcePickedInDialog($resource, $context, $number));
    }

    public function actTradingPostExchange(
        array $resources_spent,
        array $resources_gained,
        string $slot_number,
        ?int $use_booty_card_id = null,
    ): mixed {
        return $this->runAction(fn() => parent::actTradingPostExchange($resources_spent, $resources_gained, $slot_number, $use_booty_card_id));
    }

    public function actRebelDiscardCard(int $card_id): mixed
    {
        return $this->runAction(fn() => parent::actRebelDiscardCard($card_id));
    }

    public function actRallyTheFlagsChooseFlag(string $flag_key): mixed
    {
        return $this->runAction(fn() => parent::actRallyTheFlagsChooseFlag($flag_key));
    }

    public function actExtortionScrapCard(int $card_id): mixed
    {
        return $this->runAction(fn() => parent::actExtortionScrapCard($card_id));
    }
}

if (!class_exists("CapturingMockNotify")) {
    class CapturingMockNotify extends \Bga\GameFramework\Notify
    {
        private object $game;
        /** @var callable|null */
        private $onAll;

        public function __construct(object $game, ?callable $onAll = null)
        {
            $this->game = $game;
            $this->onAll = $onAll;
        }

        public function all(string $notifName, string|\Bga\GameFramework\NotificationMessage $message = '', array $args = []): void
        {
            $this->game->debugLastNotif = ["type" => $notifName, "message" => $message, "args" => $args];
            if ($this->onAll !== null) {
                ($this->onAll)($notifName, $message, $args);
            }
        }

        public function player(int $playerId, string $notifName, string|\Bga\GameFramework\NotificationMessage $message = '', array $args = []): void
        {
            $this->game->debugLastNotif = array_merge(["type" => $notifName, "message" => $message, "player_id" => $playerId], $args);
        }
    }
}

if (!class_exists("MockBGA")) {
    class MockBGA extends \Bga\GameFramework\Bga
    {
        public function dump($label, $data) {}
        public function trace($message) {}
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
