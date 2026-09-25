<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SeasOfHavocTest.php';

/**
 * "After resolving the collision, Cannon fire depicted at the next ship outline may be resolved."
 */
class PostCollisionFireUT extends SeasOfHavocUT
{
    public ?string $stored = null;
    public array $paid = [];
    public array $bootyUsed = [];

    // The framework's DbQuery/getUniqueValueFromDB are final static, so the wrappers are the seam.
    protected function setNextActionOnCard(int $player_id, array $action): void
    {
        if ($action['action'] instanceof \PrimitiveCardPlayAction) {
            $action['action'] = $action['action']->value;
        }
        $this->stored = json_encode($action);
    }

    protected function clearNextActionOnCard(int $player_id): void
    {
        $this->stored = null;
    }

    protected function readNextActionOnCard(int $player_id): ?string
    {
        return $this->stored;
    }

    public function getActivePlayerId(): string { return '1'; }
    public function getPlayerNameById(int $player_id): string { return 'TestPlayer'; }
    public function payWithOptionalBooty(int $player_id, array $cost, ?int $use_booty_card_id = null, ?string $booty_choice = null): void
    {
        $this->paid[] = $cost;
        $this->bootyUsed[] = $use_booty_card_id;
    }

    public function useCollidingBoard(): void
    {
        $board = $this->createMock(SeaBoard::class);
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this, $board);
    }
}

final class PostCollisionFireTest extends TestCase
{
    private PostCollisionFireUT $game;

    private const FIRE = ['action' => 'fire', 'range' => 3, 'cost' => ['cannonball' => 1]];

    protected function setUp(): void
    {
        $this->game = new PostCollisionFireUT();
    }

    /** A board whose forward move always collides, so card resolution stops at the first action. */
    private function collidingBoard(): void
    {
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()
            ->onlyMethods(['moveObjectForward', 'resolveCannonFire'])->getMock();
        $board->method('moveObjectForward')->willReturn(['type' => 'collision']);
        $board->method('resolveCannonFire')->willReturn(['type' => 'fire_miss']);
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
    }

    public function testCollisionKeepsTheCannonAtTheNextOutline(): void
    {
        $this->collidingBoard();
        $outcome = $this->game->processCardActions([['action' => 'forward'], self::FIRE], ['skip']);
        $this->assertTrue($outcome['collision_occurred']);
        $this->assertSame(self::FIRE, json_decode($this->game->stored, true), 'range and cost must survive');
    }

    public function testCollisionOnTheLastActionLeavesNothingToFire(): void
    {
        $this->collidingBoard();
        $this->game->stored = json_encode(self::FIRE); // stale row from an earlier card
        $this->game->processCardActions([['action' => 'forward']], []);
        $this->assertNull($this->game->stored);
    }

    public function testOnlyCannonFireResumes(): void
    {
        $this->collidingBoard();
        $this->game->processCardActions(
            [['action' => 'forward'], ['action' => 'choice', 'choices' => [['action' => 'pivot left']]]],
            [],
        );
        $this->assertNotNull($this->game->stored, 'the next action is still recorded');
        $this->expectException(\Bga\GameFramework\SystemException::class);
        $this->game->argPostCollisionFire();
    }

    public function testFiringAfterTheCollisionResolvesAndCharges(): void
    {
        $this->collidingBoard();
        $this->game->processCardActions([['action' => 'forward'], self::FIRE], ['skip']);
        $this->assertSame(self::FIRE, $this->game->argPostCollisionFire()['action']);

        $resume = $this->game->actPostCollisionFire('fire left');
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $resume);
        $this->assertSame([['cannonball' => 1]], $this->game->paid);
        $this->assertNull($this->game->stored, 'the pending shot is consumed');
    }

    public function testTheShotCanBePaidWithABootyToken(): void
    {
        $this->collidingBoard();
        $this->game->processCardActions([['action' => 'forward'], self::FIRE], ['skip']);

        $this->game->actPostCollisionFire('fire left', 42);
        $this->assertSame([42], $this->game->bootyUsed, 'the chosen booty token pays for the shot');
    }

    public function testDecliningTheShotCostsNothing(): void
    {
        $this->collidingBoard();
        $this->game->processCardActions([['action' => 'forward'], self::FIRE], ['skip']);

        $resume = $this->game->actPostCollisionFire('skip');
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $resume);
        $this->assertSame([], $this->game->paid);
        $this->assertNull($this->game->stored);
    }
}
