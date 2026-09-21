<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

/**
 * Runs processCardActions against a scripted sea board so the scoring, damage and explosion
 * consequences of each upgraded shot type can be checked end to end.
 */
class ShipUpgradeFiringUT extends SeasOfHavocUT
{
    public Deck $deck;
    /** Queued resolveCannonFire results, consumed in order. */
    public array $fireResults = [];
    public array $fireCalls = [];
    public array $surrounding = [];
    public array $shipsAt = [];
    public array $infamy = [];
    public array $damaged = [];
    public array $gains = [];
    public array $activeUpgrades = [];
    public array $resources = ["1" => [], "2" => ["sail" => 1, "cannonball" => 3, "doubloon" => 2]];

    public function __construct()
    {
        parent::__construct();
        $this->deck = new Deck();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($this, $this->deck);
        $board = new ShipUpgradeFiringBoard();
        $board->test = $this;
        (new ReflectionProperty(SeasOfHavoc::class, "seaboard"))->setValue($this, $board);
        $this->deck->createCards([["type" => 0, "type_arg" => 0, "nbr" => 20]], "damage_deck");
    }

    public function getActivePlayerId(): string { return "1"; }
    public function getPlayerNameById(int $player_id): string { return "Player$player_id"; }
    public function getPlayerCaptain($player_id) { return "admiral"; }
    public function getPlayerShipUpgrades($player_id) {
        return array_map(
            fn($key) => ["upgrade_key" => $key, "is_activated" => 1],
            $this->activeUpgrades[(string) $player_id] ?? [],
        );
    }
    public function scoreInfamy(string $player_id, int $amount, string $message = "") {
        $this->infamy[] = ["player_id" => $player_id, "amount" => $amount];
    }
    public function getGameResourcesHierarchical(?int $player_id = null) { return $this->resources; }
    public function playerGainResources($player_id, $resources) {
        $this->gains[] = [(string) $player_id, $resources];
        foreach ($resources as $key => $amount) {
            $this->resources[(string) $player_id][$key] += $amount;
        }
    }
    /** Create a loose card and return its id. */
    public function makeCard(int $type): int {
        $this->deck->createCards([["type" => $type, "type_arg" => 0, "nbr" => 1]], "limbo", 0);
        return (int) array_key_last($this->deck->getCardsInLocation("limbo"));
    }
    public function dealDamageCard(string $hit_player_id): void {
        $this->damaged[] = $hit_player_id;
        parent::dealDamageCard($hit_player_id);
    }
}

class ShipUpgradeFiringBoard extends SeaBoard
{
    public ShipUpgradeFiringUT $test;

    public function __construct() {}

    public function resolveCannonFire(
        string $player_id,
        Turn $direction,
        int $distance,
        array $collision_types,
        int $from_distance = 0,
    ) {
        $this->test->fireCalls[] = [
            "direction" => $direction,
            "distance" => $distance,
            "from" => $from_distance,
        ];
        return array_shift($this->test->fireResults) ?? ["type" => "fire_miss"];
    }

    public function getSurroundingPositions(int $x, int $y): array { return $this->test->surrounding; }

    public function getObjectsOfTypes(int $x, int $y, array $types) {
        return $this->test->shipsAt["$x,$y"] ?? [];
    }
}

final class ShipUpgradeFiringTest extends TestCase
{
    private ShipUpgradeFiringUT $game;

    protected function setUp(): void
    {
        $this->game = new ShipUpgradeFiringUT();
    }

    private static function hit(int $x, int $y, int $distance, string $victim, Heading $victimHeading): array
    {
        return [
            "type" => "fire_hit",
            "hit_x" => $x,
            "hit_y" => $y,
            "hit_distance" => $distance,
            "fire_heading" => Heading::NORTH,
            "hit_objects" => [["type" => "player_ship", "arg" => $victim, "heading" => $victimHeading]],
        ];
    }

    private function fire(string $decision, array $upgrades, array $action): array
    {
        $this->game->activeUpgrades["1"] = $upgrades;
        $rewritten = ShipUpgrades::rewriteActions([$action], array_fill_keys($upgrades, true))[0];
        return $this->game->processCardActions([$rewritten], [$decision]);
    }

    public function testBroadsideHitScoresTwoAndDealsDamage(): void
    {
        $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::EAST)];

        $result = $this->fire("fire left", [], ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]);

        $this->assertSame([["player_id" => "1", "amount" => 2]], $this->game->infamy);
        $this->assertSame(["2"], $this->game->damaged);
        $this->assertSame(["cannonball" => 1], $result["cost"]);
    }

    public function testRakingAShipFacingTheShotScoresThree(): void
    {
        $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::SOUTH)]; // bow-on to a northward shot

        $this->fire("fire left", [], ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]);

        $this->assertSame(3, $this->game->infamy[0]["amount"]);
    }

    public function testCarronadeFiresAtRangeOneForFree(): void
    {
        $this->game->fireResults = [["type" => "fire_miss"]];

        $result = $this->fire("carronade left", ["brig_carronade"], [
            "action" => "fire",
            "range" => 3,
            "cost" => ["cannonball" => 1],
        ]);

        $this->assertSame(1, $this->game->fireCalls[0]["distance"]);
        $this->assertSame([], $result["cost"]);
    }

    public function testHeavyGunsPassThroughShipsButStopAtRocksAndScoreABonus(): void
    {
        $this->game->fireResults = [
            self::hit(2, 2, 1, "2", Heading::EAST),
            self::hit(2, 1, 2, "3", Heading::EAST),
            [
                "type" => "fire_hit",
                "hit_x" => 2,
                "hit_y" => 0,
                "hit_distance" => 3,
                "fire_heading" => Heading::NORTH,
                "hit_objects" => [["type" => "rock", "arg" => "r1", "heading" => Heading::NO_HEADING]],
            ],
            ["type" => "fire_miss"], // must never be reached: the rock stops the shot
        ];

        $result = $this->fire("heavy guns left", ["ship_of_the_line_heavy_guns"], [
            "action" => "fire",
            "range" => 3,
            "cost" => ["cannonball" => 1],
        ]);

        $this->assertCount(3, $this->game->fireCalls);
        $this->assertSame([0, 1, 2], array_column($this->game->fireCalls, "from"));
        $this->assertSame([5, 5, 5], array_column($this->game->fireCalls, "distance"));
        // 2 for a broadside hit, +1 from heavy guns, for each of the two ships passed through.
        $this->assertSame([3, 3], array_column($this->game->infamy, "amount"));
        $this->assertSame(["2", "3"], $this->game->damaged);
        $this->assertSame(["cannonball" => 2], $result["cost"]);
    }

    public function testRocketExplodesIntoSurroundingShips(): void
    {
        $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::EAST)];
        $this->game->surrounding = [["x" => 2, "y" => 1], ["x" => 3, "y" => 2], ["x" => 1, "y" => 2]];
        $this->game->shipsAt = [
            "2,1" => [["type" => "player_ship", "arg" => "3", "heading" => Heading::EAST]],
            "1,2" => [["type" => "player_ship", "arg" => "1", "heading" => Heading::EAST]], // the firing ship
        ];

        $result = $this->fire("rocket left", ["war_junk_rockets"], [
            "action" => "fire",
            "range" => 3,
            "cost" => ["cannonball" => 1],
        ]);

        // 2 for the direct hit, then 1 for the neighbour; no infamy for catching your own ship.
        $this->assertSame([2, 1], array_column($this->game->infamy, "amount"));
        $this->assertSame(["2", "3", "1"], $this->game->damaged);
        $explosions = array_values(array_filter($result["action_chain"], fn($e) => $e["type"] === "explosion"));
        $this->assertCount(2, $explosions);
    }

    public function testRocketExplodesAfterHittingARock(): void
    {
        $this->game->fireResults = [[
            "type" => "fire_hit",
            "hit_x" => 2,
            "hit_y" => 2,
            "hit_distance" => 1,
            "fire_heading" => Heading::NORTH,
            "hit_objects" => [["type" => "rock", "arg" => "r1", "heading" => Heading::NO_HEADING]],
        ]];
        $this->game->surrounding = [["x" => 2, "y" => 1]];
        $this->game->shipsAt = ["2,1" => [["type" => "player_ship", "arg" => "2", "heading" => Heading::EAST]]];

        $this->fire("rocket left", ["war_junk_rockets"], [
            "action" => "fire",
            "range" => 3,
            "cost" => ["cannonball" => 1],
        ]);

        $this->assertSame([1], array_column($this->game->infamy, "amount"));
        $this->assertSame(["2"], $this->game->damaged);
    }

    public function testChainShotTakesTheVictimsLargestResource(): void
    {
        $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::EAST)];

        $this->fire("chain shot left", ["sloop_of_war_chain_shot"], [
            "action" => "fire",
            "range" => 3,
            "cost" => ["cannonball" => 1],
        ]);

        $this->assertSame(2, $this->game->fireCalls[0]["distance"]);
        $this->assertSame([["2", ["cannonball" => -1]]], $this->game->gains);
    }

    public function testChainShotOnAPlayerWithNothingToLoseIsHarmless(): void
    {
        $this->game->resources["2"] = ["sail" => 0, "cannonball" => 0, "doubloon" => 0, "skiff" => 3];
        $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::EAST)];

        $this->fire("chain shot left", ["sloop_of_war_chain_shot"], [
            "action" => "fire",
            "range" => 3,
            "cost" => ["cannonball" => 1],
        ]);

        $this->assertSame([], $this->game->gains);
    }

    public function testChasersFireForeAndAft(): void
    {
        $this->game->fireResults = [["type" => "fire_miss"]];

        $this->fire("chaser aft", ["galleon_bow_and_stern_chasers"], [
            "action" => "fire",
            "range" => 3,
            "cost" => ["cannonball" => 1],
        ]);

        $this->assertSame(Turn::AROUND, $this->game->fireCalls[0]["direction"]);
    }

    public function testDoubleGunCrewsFiresOutOfBothSidesAtReducedRange(): void
    {
        $this->game->fireResults = [["type" => "fire_miss"], ["type" => "fire_miss"]];

        $this->fire("both sides left", ["ship_of_the_line_double_gun_crews"], [
            "action" => "2 x fire",
            "range" => 2,
            "cost" => ["cannonball" => 2],
        ]);

        $this->assertSame([Turn::LEFT, Turn::RIGHT], array_column($this->game->fireCalls, "direction"));
        $this->assertSame([1, 1], array_column($this->game->fireCalls, "distance"));
    }

    public function testPlainMultiCannonShotFiresOncePerCannon(): void
    {
        $this->game->fireResults = [
            self::hit(2, 2, 1, "2", Heading::EAST),
            self::hit(2, 2, 1, "2", Heading::EAST),
        ];

        $this->fire("2 x fire left", [], ["action" => "2 x fire", "range" => 2, "cost" => ["cannonball" => 2]]);

        $this->assertSame([Turn::LEFT, Turn::LEFT], array_column($this->game->fireCalls, "direction"));
        $this->assertSame(["2", "2"], $this->game->damaged);
        $this->assertSame([2, 2], array_column($this->game->infamy, "amount"));
    }

    public function testWatertightBulkheadsScrapsDamageOnlyWhenDamageIsAlreadyOnTop(): void
    {
        $this->game->activeUpgrades["2"] = ["war_junk_bulwark"];

        // First hit: nothing on top of the discard yet, so the damage card stays.
        $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::EAST)];
        $this->fire("fire left", [], ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]);
        $kept = array_values($this->game->deck->getCardsInLocation("player_discard_2"));
        $this->assertCount(1, $kept);
        $this->assertSame([], $this->game->deck->getCardsInLocation("scrap"));

        // Second hit: a damage card is now on top, so this one is scrapped immediately.
        $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::EAST)];
        $this->fire("fire left", [], ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]);
        $this->assertCount(1, $this->game->deck->getCardsInLocation("player_discard_2"));
        $this->assertCount(1, $this->game->deck->getCardsInLocation("scrap"));
    }

    public function testBulkheadsDoesNotFireWhenANonDamageCardCoversTheDamage(): void
    {
        $this->game->activeUpgrades["2"] = ["war_junk_bulwark"];
        // A damage card, then an ordinary card played on top of it.
        $this->game->discardCardToPlayer($this->game->makeCard(0), "2");
        $this->game->discardCardToPlayer($this->game->makeCard(5), "2");

        $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::EAST)];
        $this->fire("fire left", [], ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]);

        $this->assertSame([], $this->game->deck->getCardsInLocation("scrap"));
        $this->assertCount(3, $this->game->deck->getCardsInLocation("player_discard_2"));
    }

    public function testWithoutBulkheadsEveryHitLeavesADamageCard(): void
    {
        foreach ([0, 1] as $_) {
            $this->game->fireResults = [self::hit(2, 2, 1, "2", Heading::EAST)];
            $this->fire("fire left", [], ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]);
        }

        $this->assertCount(2, $this->game->deck->getCardsInLocation("player_discard_2"));
        $this->assertSame([], $this->game->deck->getCardsInLocation("scrap"));
    }
}
