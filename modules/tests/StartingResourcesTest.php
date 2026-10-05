<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../../seasofhavoc.game.php";

/** Starting resources go by play order from the random first player, not by seat number. */
final class StartingResourcesTest extends TestCase
{
    public function testBonusesFollowPlayOrderFromTheFirstPlayer(): void
    {
        // 3 players, seat 2 holds the first player token: seat 3 plays second, seat 1 third.
        $this->assertSame(
            ["sail" => 1, "cannonball" => 1, "doubloon" => 1, "skiff" => 3],
            SeasOfHavoc::startingResources(2, 2, 3),
        );
        $this->assertSame(
            ["sail" => 2, "cannonball" => 1, "doubloon" => 1, "skiff" => 3],
            SeasOfHavoc::startingResources(3, 2, 3),
        );
        $this->assertSame(
            ["sail" => 1, "cannonball" => 2, "doubloon" => 1, "skiff" => 3],
            SeasOfHavoc::startingResources(1, 2, 3),
        );
    }

    public function testFifthPlayerBonus(): void
    {
        // 5 players, seat 3 first: seat 2 is fifth in play order.
        $this->assertSame(
            ["sail" => 1, "cannonball" => 2, "doubloon" => 2, "skiff" => 3],
            SeasOfHavoc::startingResources(2, 3, 5),
        );
    }
}
