<?php declare(strict_types=1);

use Bga\Games\SeasOfHavoc\CardActionOutcome;
use Bga\Games\SeasOfHavoc\PrimitiveCardPlayAction;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/RebelAndTreasureSeekerCardAbilityTest.php';

class NimbleHullUT extends RebelAndTreasureSeekerCardUT
{
    public array $moves = [];
    public array $used = [];
    public function processSimpleAction(PrimitiveCardPlayAction $action_type): CardActionOutcome {
        $this->moves[] = $action_type->value;
        return new CardActionOutcome();
    }
    public function getActiveShipUpgrades($player_id): array {
        return $player_id == 1 ? ['sloop_of_war_nimble_hull' => true] : [];
    }
    public function hasUsedUpgradeThisPhase($player_id, string $upgrade_key): bool {
        return isset($this->used[$player_id][$upgrade_key]);
    }
    public function markUpgradeUsedThisPhase($player_id, string $upgrade_key): void {
        $this->used[$player_id][$upgrade_key] = true;
    }
    public function clearUpgradeUses(array $upgrade_keys): void { $this->used = []; }
    public function getFirstPlayerTokenOwner() { return 1; }
    public function getPlayerCaptain($player_id) { return 'rebel'; }
    public function getPlayerInfo(?int $player_id = null) { return $this->players; }
}

final class NimbleHullTest extends TestCase
{
    private function card(NimbleHullUT $game, int $imageId): array {
        return array_values(array_filter($game->playable_cards, fn($c) => $c['image_id'] === $imageId))[0];
    }

    public function testRepeatedNestedManeuversConsumeSeparateDecisions(): void {
        $game = new NimbleHullUT();
        $actions = $game->upgradedCardActions($this->card($game, 22), '1');
        $outcome = $game->processCardActions($actions, [SeasOfHavoc::NIMBLE_HULL_CHOICE, 'forward', 'skip', 'right']);
        $this->assertSame(['forward', 'forward', 'pivot right', 'forward'], $game->moves);
        $this->assertSame([], $outcome->cost);
        $this->assertTrue($game->hasUsedUpgradeThisPhase('1', 'sloop_of_war_nimble_hull'));
    }

    public function testOptionalForwardOnFirstManeuverDoesNotConsumeSecondManeuversSkip(): void {
        $game = new NimbleHullUT();
        $actions = $game->upgradedCardActions($this->card($game, 0), '1');
        $outcome = $game->processCardActions($actions, [SeasOfHavoc::NIMBLE_HULL_CHOICE, 'forward', 'skip']);
        $this->assertSame(['forward', 'forward', 'forward'], $game->moves);
        $this->assertSame(['sail' => 1], $outcome->cost);
    }

    public function testImprovisationCanUseNimbleHullOnCopiedSailingCard(): void {
        $game = new NimbleHullUT();
        $game->runEngine = true;
        $type = array_key_first(array_filter($game->playable_cards, fn($c) => $c['image_id'] === 22));
        $id = $game->addCard($type, 'player_discard');
        $game->start('improvisation');
        $game->actResolveCaptainCard(['card_id' => $id], [SeasOfHavoc::NIMBLE_HULL_CHOICE, 'forward', 'skip', 'right']);
        $this->assertSame(['forward', 'forward', 'pivot right', 'forward'], $game->moves);
        $this->assertSame(1, $game->seaEffects);
        $this->assertSame($type, (int) $game->getGameStateValue('pending_card_flag_type'));
    }

    public function testNewSeaPhaseRestoresNimbleHullInTheClientCardDefinitions(): void {
        $game = new NimbleHullUT();
        $game->useNimbleHull('1');
        $game->bga->notify->sent = [];
        $game->stSeaPhaseSetup();
        $updates = array_values(array_filter($game->bga->notify->sent, fn($n) => $n['type'] === 'playableCardsUpdated'));
        $this->assertCount(1, $updates);
        $this->assertSame(1, (int) $updates[0]['player_id']);
        $this->assertSame(SeasOfHavoc::NIMBLE_HULL_CHOICE, $updates[0]['playable_cards'][1]['actions'][0]['choices'][1]['name']);
    }
}
