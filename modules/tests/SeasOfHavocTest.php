<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../../seasofhavoc.game.php";

if (!class_exists("MockCardDeck")) {
    class MockCardDeck {
        public array $cards = [];
        public array $locations = [];
        public array $moveCardCalls = [];

        public function getCard(int $card_id): ?array {
            return $this->cards[$card_id] ?? null;
        }

        public function getCardsInLocation(string $location, $location_arg = null, ?string $order_by = null): array {
            $key = $location . ($location_arg !== null ? "_$location_arg" : "");
            $cards = $this->locations[$key] ?? [];
            if ($order_by !== null) {
                usort($cards, fn($a, $b) => $a[$order_by] <=> $b[$order_by]);
            }
            return $cards;
        }

        public function getCardOnTop(string $location): ?array {
            $cards = $this->getCardsInLocation($location, null, "location_arg");
            return empty($cards) ? null : end($cards);
        }

        public function insertCardOnExtremePosition(int $card_id, string $location, bool $bOnTop): void {
            $cards = $this->getCardsInLocation($location);
            $args = array_map(fn($c) => (int) $c["location_arg"], $cards);
            $extreme = empty($args) ? 0 : ($bOnTop ? max($args) : min($args));
            $this->moveCard($card_id, $location, $bOnTop ? $extreme + 1 : $extreme - 1);
        }

        public function pickCardForLocation(string $from, string $to, $location_arg = 0): ?array {
            $cards = $this->getCardsInLocation($from);
            if (empty($cards)) {
                return null;
            }
            $card = reset($cards);
            $this->moveCard((int) $card["id"], $to, $location_arg);
            return $this->cards[(int) $card["id"]] ?? $card;
        }

        public function getPlayerHand(string $player_id): array {
            return $this->getCardsInLocation("hand", $player_id);
        }

        public function moveCard(int $card_id, string $location, $location_arg = null): void {
            $this->moveCardCalls[] = ["card_id" => $card_id, "location" => $location];
            if (isset($this->cards[$card_id])) {
                $this->cards[$card_id]["location"] = $location;
                if ($location_arg !== null) {
                    $this->cards[$card_id]["location_arg"] = $location_arg;
                }
            }
        }

        public function countCardInLocation(string $location, $location_arg = null): int {
            $key = $location . ($location_arg !== null ? "_$location_arg" : "");
            return count($this->locations[$key] ?? []);
        }

        public function pickCardsForLocation(int $count, string $from, string $to): void {}
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

    public function actResourcePickedInDialog(string $resource): mixed
    {
        return $this->runAction(fn() => parent::actResourcePickedInDialog($resource));
    }

    public function actTradingPostExchange(
        array $resources_spent,
        array $resources_gained,
        string $slot_number,
        ?int $use_booty_card_id = null,
    ): mixed {
        return $this->runAction(fn() => parent::actTradingPostExchange($resources_spent, $resources_gained, $slot_number, $use_booty_card_id));
    }

    public function actActivateShipUpgrade(string $upgrade_key, ?int $use_booty_card_id = null): mixed
    {
        return $this->runAction(fn() => parent::actActivateShipUpgrade($upgrade_key, $use_booty_card_id));
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
        /** Every notification sent, in order, shaped like debugLastNotif. */
        public array $sent = [];

        public function __construct(object $game, ?callable $onAll = null)
        {
            $this->game = $game;
            $this->onAll = $onAll;
        }

        public function all(string $notifName, string|\Bga\GameFramework\NotificationMessage $message = '', array $args = []): void
        {
            $this->game->debugLastNotif = ["type" => $notifName, "message" => $message, "args" => $args];
            $this->sent[] = $this->game->debugLastNotif;
            if ($this->onAll !== null) {
                ($this->onAll)($notifName, $message, $args);
            }
        }

        public function player(int $playerId, string $notifName, string|\Bga\GameFramework\NotificationMessage $message = '', array $args = []): void
        {
            $this->game->debugLastNotif = array_merge(["type" => $notifName, "message" => $message, "player_id" => $playerId], $args);
            $this->sent[] = $this->game->debugLastNotif;
        }
    }
}

if (!class_exists("MockBGA")) {
    class MockBGA extends \Bga\GameFramework\Bga
    {
        public function __construct()
        {
            // The Bga stub declares these typed properties but never initialises them, so any code
            // reaching the framework score counters would fatal before the test could assert.
            $this->playerScore = new \Bga\GameFramework\Components\Counters\StubPlayerCounter();
            $this->playerScoreAux = new \Bga\GameFramework\Components\Counters\StubPlayerCounter();
            $this->playerStats = new \Bga\GameFramework\PlayerStats();
            $this->tableStats = new \Bga\GameFramework\TableStats();
        }
        public function dump($label, $data) {}
        public function trace($message) {}
        public function mytrace($message) {}
        public function mydump($label, $data) {}
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

    public function testGameProgressionIsAnIntegerFromDamageDeck(): void
    {
        $game = new class extends SeasOfHavocUT {
            function getPlayersNumber(): int
            {
                return 3;
            }
        };
        $deck = new MockCardDeck();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($game, $deck);

        // 3 players: 25 damage cards in total.
        $deck->locations["damage_deck"] = array_fill(0, 25, []);
        $this->assertSame(0, $game->getGameProgression());
        $deck->locations["damage_deck"] = array_fill(0, 17, []);
        $this->assertSame(32, $game->getGameProgression());
        $deck->locations["damage_deck"] = [];
        $this->assertSame(100, $game->getGameProgression());
    }

    public function testCardActionOutcomeAbsorbAddsCostsAndKeepsFirstPickup(): void
    {
        $outcome = new \Bga\Games\SeasOfHavoc\CardActionOutcome([["type" => "move"]], ["sail" => 1]);
        $outcome->absorb(
            new \Bga\Games\SeasOfHavoc\CardActionOutcome([["type" => "turn"]], ["sail" => 1], false, ["shipwreck_arg" => "a"]),
            ["cannonball" => 1],
        );
        $outcome->absorb(
            new \Bga\Games\SeasOfHavoc\CardActionOutcome([["type" => "collision"]], [], true, ["shipwreck_arg" => "b"], ["id" => 7]),
        );

        $this->assertSame([["type" => "move"], ["type" => "turn"], ["type" => "collision"]], $outcome->actionChain);
        $this->assertSame(["sail" => 2, "cannonball" => 1], $outcome->cost);
        $this->assertTrue($outcome->collisionOccurred);
        $this->assertSame(["shipwreck_arg" => "a"], $outcome->shipwreckEvent);
        $this->assertSame(["id" => 7], $outcome->bootyCard);
    }

    // Resource calculation tests

    public function testSkiffLogsDescribePlacementAndRetrieval(): void
    {
        $method = new ReflectionMethod(SeasOfHavoc::class, "formatResourceChangeMessage");
        // The message is a nested log (each phrase translated separately); render it as the client does.
        $render = function ($log) use (&$render) {
            return is_array($log)
                ? preg_replace_callback('/\$\{(\w+)\}/', fn($m) => $render($log["args"][$m[1]]), $log["log"])
                : $log;
        };
        $format = fn($resources) => $render($method->invoke($this->game, $resources));
        $this->assertSame("places 1 [skiff]", $format(["skiff" => -1]));
        $this->assertSame("retrieves 3 [skiff]", $format(["skiff" => 3]));
        $this->assertSame("places 1 [skiff], pays 2 [sail], gains 1 [doubloon]",
            $format(["skiff" => -1, "sail" => -2, "doubloon" => 1]));
        $this->assertSame("", $method->invoke($this->game, ["skiff" => 0]));
    }

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
