<?php declare(strict_types=1);

use Bga\Games\SeasOfHavoc\Heading;
use Bga\Games\SeasOfHavoc\SeaBoard;
use Bga\Games\SeasOfHavoc\States\ResolveCollision;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SeasOfHavocTest.php';

class CollisionGustUT extends SeasOfHavocUT
{
    public SeaBoard $board;
    public Deck $deck;
    public array $damaged = [];

    public function __construct()
    {
        parent::__construct();
        $this->board = new SeaBoard(fn($sql) => [], $this);
        $this->deck = new Deck();
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this, $this->board);
        (new ReflectionProperty(SeasOfHavoc::class, 'cards'))->setValue($this, $this->deck);
    }

    public function getActivePlayerId(): string { return '1'; }
    public function getPlayerNameById(int $player_id): string { return 'Player'; }
    public function dealDamageCard(string $hit_player_id): void { $this->damaged[] = $hit_player_id; }

    public function sail(): mixed
    {
        $this->deck->createCards([['type' => 1, 'type_arg' => 0, 'nbr' => 1]], 'hand', 1);
        $hand = $this->deck->getCardsInLocation('hand', 1);
        $card = reset($hand);
        return $this->resolvePlayedCard(1, (int) $card['id'], [], null, [
            ['action' => 'forward'], ['action' => 'forward'],
        ]);
    }
}

final class CollisionGustTest extends TestCase
{
    private CollisionGustUT $game;

    protected function setUp(): void
    {
        $this->game = new CollisionGustUT();
        $this->game->board->placeObject(1, 2, ['type' => 'player_ship', 'arg' => '1', 'heading' => Heading::EAST]);
        $this->game->board->placeObject(2, 2, ['type' => 'gust', 'arg' => '0', 'heading' => Heading::NORTH]);
        $this->game->board->placeObject(3, 2, ['type' => 'rock', 'arg' => '0', 'heading' => Heading::NO_HEADING]);
        // A previous card already resolved its sea features.
        $this->game->setGameStateValue('seafeature_effects_attempted', 1);
    }

    public function testCollisionOnGustPushesAfterDecliningPivot(): void
    {
        $this->assertSame(STATE_RESOLVE_COLLISION, $this->game->sail());
        $this->assertSame(2, $this->game->board->findObject('player_ship', '1')['y']);
        $this->assertSame('collisionResolved', $this->game->actPivotPickedInDialog('no pivot'));

        $ship = $this->game->board->findObject('player_ship', '1');
        $this->assertSame([2, 1, Heading::EAST], [$ship['x'], $ship['y'], $ship['object']['heading']]);
        $this->assertSame(['1'], $this->game->damaged);
        $this->assertSame(['move'], array_column($this->game->debugLastNotif['args']['moveChain'], 'type'));
    }

    public function testCollisionPivotHappensBeforeGustWithoutChangingPushDirection(): void
    {
        $this->game->sail();
        $this->game->actPivotPickedInDialog('pivot right');

        $ship = $this->game->board->findObject('player_ship', '1');
        $this->assertSame([2, 1, Heading::SOUTH], [$ship['x'], $ship['y'], $ship['object']['heading']]);
        $this->assertSame(['turn', 'move'], array_column($this->game->debugLastNotif['args']['moveChain'], 'type'));
    }

    public function testSkippedPlayerStillResolvesGustAfterCollision(): void
    {
        $this->game->sail();
        $state = new ResolveCollision($this->game);
        $this->assertSame('collisionResolved', $state->zombie(1));

        $ship = $this->game->board->findObject('player_ship', '1');
        $this->assertSame([2, 1, Heading::EAST], [$ship['x'], $ship['y'], $ship['object']['heading']]);
    }

    public function testBlockedGustCausesOneMoreCollisionWithoutRepeating(): void
    {
        $this->game->board->placeObject(2, 1, ['type' => 'rock', 'arg' => '1', 'heading' => Heading::NO_HEADING]);
        $this->game->sail();
        $this->assertSame(STATE_RESOLVE_COLLISION, $this->game->actPivotPickedInDialog('no pivot'));
        $this->assertSame(['1', '1'], $this->game->damaged);
        $this->assertSame('collisionResolved', $this->game->actPivotPickedInDialog('no pivot'));
        $this->assertSame(['1', '1'], $this->game->damaged);
        $this->assertSame(2, $this->game->board->findObject('player_ship', '1')['y']);
    }

    public function testGustDoesNotTriggerAnotherGustItPushesOnto(): void
    {
        $this->game->board->placeObject(2, 1, ['type' => 'gust', 'arg' => '1', 'heading' => Heading::NORTH]);
        $this->game->sail();
        $this->assertSame('collisionResolved', $this->game->actPivotPickedInDialog('no pivot'));
        $this->assertSame(1, $this->game->board->findObject('player_ship', '1')['y']);
    }
}
