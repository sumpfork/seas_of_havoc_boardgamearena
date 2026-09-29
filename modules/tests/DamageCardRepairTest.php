<?php declare(strict_types=1);

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
}
