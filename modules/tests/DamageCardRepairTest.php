<?php declare(strict_types=1);

use Bga\Games\SeasOfHavoc\SeaBoard;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/CardFlagActionTest.php';

/**
 * Damage card: "Repair: When you would play this card, scrap it instead." Playing one used to reach
 * processSimpleAction, which has no case for "scrap self" and threw "Unknown action type".
 */
final class DamageCardRepairTest extends TestCase
{
    private CardFlagUT $game;

    protected function setUp(): void {
        $this->game = new CardFlagUT();
        $this->game->runEngine = true;
    }

    public function testPlayingADamageCardScrapsIt(): void {
        $type = $this->game->damageCardType();
        $id = $this->game->addCard($type, 'hand');

        $result = $this->game->actPlayCard($type, $id, []);

        $this->assertSame('seaTurnDone', $result);
        $this->assertSame('scrap', $this->game->deck->getCard($id)['location']);
    }

    public function testPassingWithADamageCardStillScrapsIt(): void {
        $type = $this->game->damageCardType();
        $id = $this->game->addCard($type, 'hand');

        $this->game->actPlayCard($type, $id, ['pass']);

        $this->assertSame('scrap', $this->game->deck->getCard($id)['location']);
    }

    /** Whirlpools and gusts resolve "after each card they play", and the repair is a card played. */
    public function testWhirlpoolsAndGustsResolveAfterARepair(): void {
        $type = $this->game->damageCardType();
        $id = $this->game->addCard($type, 'hand');

        $this->game->actPlayCard($type, $id, []);

        $this->assertSame(1, $this->game->seaEffects);
    }

    /** "...this includes rowing and passing by discarding a card." */
    public function testWhirlpoolsAndGustsResolveAfterPassingWithACard(): void {
        $id = $this->game->addCard(1, 'hand');

        $this->game->actPlayCard(1, $id, ['pass']);

        $this->assertSame(1, $this->game->seaEffects);
    }

    public function testWhirlpoolsAndGustsResolveAfterRowing(): void {
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()->onlyMethods(['moveObjectForward', 'findObject'])->getMock();
        $board->method('moveObjectForward')->willReturn(['type' => 'move', 'new_x' => 1, 'new_y' => 1]);
        $board->method('findObject')->willReturn(null); // no shipwreck to collect
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);

        $this->game->actRow($this->game->addCard(1, 'hand'), $this->game->addCard(1, 'hand'), 'forward');

        $this->assertSame(1, $this->game->seaEffects);
    }
}
