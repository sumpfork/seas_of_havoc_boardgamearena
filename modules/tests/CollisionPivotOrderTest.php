<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SeasOfHavocTest.php';

/**
 * A ship sitting on a whirlpool that pivots out of a collision: the pivot happens first and the
 * whirlpool spins it afterwards. Each move in the chain has to start where the previous one ended,
 * because that is the order the client animates them in.
 */
class PivotBoard extends SeaBoard
{
    public const NORTH = 1, EAST = 2, SOUTH = 3, WEST = 4;

    public int $heading = self::WEST;

    public function __construct() {}

    public function turnObject(string $object_type, string $arg, Turn $turn)
    {
        $old = $this->heading;
        $order = [self::NORTH, self::EAST, self::SOUTH, self::WEST];
        $index = array_search($this->heading, $order, true);
        $step = $turn === Turn::RIGHT ? 1 : ($turn === Turn::LEFT ? 3 : 2);
        $this->heading = $order[($index + $step) % 4];
        return ["type" => "turn", "old_heading" => $old, "new_heading" => $this->heading];
    }

    public function isObjectOnWhirlpool(string $object_type, string $arg): bool { return true; }
    public function getGustAtObjectLocation(string $object_type, string $arg) { return null; }
}

class PivotOrderUT extends SeasOfHavocUT
{
    public PivotBoard $board;

    public function __construct()
    {
        parent::__construct();
        $this->board = new PivotBoard();
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this, $this->board);
    }

    public function getActivePlayerId(): string { return '1'; }
    public function getPlayerNameById(int $player_id): string { return 'TestPlayer'; }
    public function collectShipwrecksAtPlayer(int $player_id): array
    {
        return ["shipwreck_event" => null, "booty_card" => null];
    }
    // The collision is over by the time these run; they need the database otherwise.
    public function collisionPenaltyState(): int { return STATE_RESOLVE_COLLISION; }
    public function postCollisionFireState(): ?int { return null; }
}

final class CollisionPivotOrderTest extends TestCase
{
    private PivotOrderUT $game;

    protected function setUp(): void
    {
        $this->game = new PivotOrderUT();
        $this->game->setGameStateValue('seafeature_effects_attempted', 0);
    }

    public function testPivotResolvesBeforeTheWhirlpool(): void
    {
        $this->game->actPivotPickedInDialog('pivot left');

        $chain = $this->game->debugLastNotif['args']['moveChain'];
        $this->assertCount(2, $chain);
        $this->assertSame(
            [PivotBoard::WEST, PivotBoard::SOUTH],
            [$chain[0]['old_heading'], $chain[0]['new_heading']],
            'the pivot is applied first, from the heading the ship collided on',
        );
        $this->assertSame(
            [PivotBoard::SOUTH, PivotBoard::WEST],
            [$chain[1]['old_heading'], $chain[1]['new_heading']],
            'the whirlpool then spins the ship from where the pivot left it',
        );
    }

    /** The chain is animated in order, so a gap between one move's end and the next means the
     *  client draws the ship facing somewhere the server never put it. */
    public function testEveryMoveStartsWhereTheLastOneEnded(): void
    {
        $this->game->actPivotPickedInDialog('pivot right');

        $heading = PivotBoard::WEST;
        foreach ($this->game->debugLastNotif['args']['moveChain'] as $move) {
            $this->assertSame($heading, $move['old_heading']);
            $heading = $move['new_heading'];
        }
        $this->assertSame($this->game->board->heading, $heading, 'the chain ends on the real heading');
    }

    public function testDecliningThePivotStillResolvesTheWhirlpool(): void
    {
        $this->game->actPivotPickedInDialog('no pivot');

        $chain = $this->game->debugLastNotif['args']['moveChain'];
        $this->assertCount(1, $chain);
        $this->assertSame(PivotBoard::WEST, $chain[0]['old_heading']);
        $this->assertSame(PivotBoard::NORTH, $chain[0]['new_heading']);
    }
}
