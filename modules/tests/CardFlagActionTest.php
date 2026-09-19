<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/RebelAndTreasureSeekerCardAbilityTest.php';

class CardFlagUT extends RebelAndTreasureSeekerCardUT
{
    public array $owners = [];
    public function getTokenOwner(string $token_key) { return $this->owners[$token_key] ?? null; }
    public function activeNextPlayer(): int|string { return 2; }
    public function giveExtraTime(int $playerId, ?int $specificTime = null): void {}
    public function flagType(string $flag): int {
        return array_key_first(array_filter($this->playable_cards, fn($card) => ($card['flag'] ?? null) === $flag));
    }
    public function playFlag(string $flag): int {
        $this->runEngine = true;
        $type = $this->flagType($flag);
        $id = $this->addCard($type, 'hand');
        $this->actPlayCard($type, $id, ['pass']);
        return $id;
    }
    public function playFlagActions(string $flag, array $actions): mixed {
        $this->runEngine = true;
        $type = $this->flagType($flag);
        return $this->resolvePlayedCard($type, $this->addCard($type, 'hand'), [], null, $actions);
    }
}

final class CardFlagActionTest extends TestCase
{
    private CardFlagUT $game;
    protected function setUp(): void {
        $this->game = new CardFlagUT();
        foreach (['green', 'tan', 'red', 'blue'] as $flag) $this->game->owners[$flag . '_flag'] = '1';
    }

    public function testPassedCardOffersItsMatchingFlagBeforeChangingPlayers(): void {
        $id = $this->game->playFlag('green');
        $this->assertSame('player_discard', $this->game->deck->getCard($id)['location']);
        $this->assertSame(STATE_CARD_FLAG, $this->game->stNextPlayerSeaPhase());
        $this->assertSame('green', $this->game->argCardFlag()['flag']);
        $this->assertSame([], $this->game->gains);
    }

    public function testUnownedFlagIsNotOffered(): void {
        $this->game->owners['green_flag'] = '2';
        $this->game->addCard(1, 'hand', 2);
        $this->game->playFlag('green');
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase());
        $this->assertSame(0, $this->game->getGameStateValue('pending_card_flag_type'));
    }

    public function testEachResourceCanBeGainedOnlyOnceAndFlagIsKept(): void {
        foreach (['sail', 'cannonball', 'doubloon'] as $resource) {
            $this->game->playFlag('green');
            $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->actResolveCardFlag($resource));
            $this->assertSame([$resource => 1], end($this->game->gains));
            $this->assertSame('1', $this->game->owners['green_flag']);
            $this->assertSame(0, $this->game->getGameStateValue('pending_card_flag_type'));
        }
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actResolveCardFlag('sail');
    }

    public function testGreenRejectsInvalidResource(): void {
        $this->game->playFlag('green');
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actResolveCardFlag('skiff');
    }

    public function testTanDrawsOneCard(): void {
        $this->game->playFlag('tan');
        $this->game->actResolveCardFlag();
        $this->assertSame([1], $this->game->draws);
    }

    public function testBlueKeepsTurnOnlyWhenAnotherCardIsInHand(): void {
        $this->game->playFlag('blue');
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->actResolveCardFlag());
        $this->game->addCard(1, 'hand');
        $this->game->playFlag('blue');
        $this->assertSame(STATE_SEA_TURN, $this->game->actResolveCardFlag());
    }

    public function testRedCanScrapThePlayedCardAndRefundItsCost(): void {
        $id = $this->game->playFlag('red');
        $args = $this->game->argCardFlag();
        $this->assertArrayNotHasKey('available_cards', $args);
        $this->assertContains($id, array_column($args['_private'][1]['available_cards'], 'id'));
        $this->game->actResolveCardFlag(card_id: $id);
        $this->assertSame('scrap', $this->game->deck->getCard($id)['location']);
        $this->assertSame([$this->game->playable_cards[$this->game->flagType('red')]['cost']], $this->game->gains);
    }

    public function testRedCanScrapFromHandButRejectsAnotherPlayersCard(): void {
        $id = $this->game->addCard(1, 'hand');
        $this->game->playFlag('red');
        $this->game->actResolveCardFlag(card_id: $id);
        $this->assertSame('scrap', $this->game->deck->getCard($id)['location']);
        $other = $this->game->addCard(1, 'hand', 2);
        $this->game->playFlag('red');
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actResolveCardFlag(card_id: $other);
    }

    public function testAllFlagActionsCanBeSkippedWithoutEffects(): void {
        foreach (['green', 'tan', 'red', 'blue'] as $flag) {
            $id = $this->game->playFlag($flag);
            $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->actSkipCardFlag());
            $this->assertSame('player_discard', $this->game->deck->getCard($id)['location']);
            $this->assertSame(0, $this->game->getGameStateValue('pending_card_flag_type'));
        }
        $this->assertSame([], $this->game->gains);
        $this->assertSame([], $this->game->draws);
    }

    public function testImprovisationUsesTheCopiedCardsFlag(): void {
        $type = $this->game->flagType('green');
        $id = $this->game->addCard($type, 'player_discard');
        $this->game->runEngine = true;
        $this->game->start('improvisation');
        $this->game->actResolveCaptainCard(['card_id' => $id], ['pass']);
        $this->assertSame(STATE_CARD_FLAG, $this->game->stNextPlayerSeaPhase());
        $this->assertSame('green', $this->game->argCardFlag()['flag']);
    }

    public function testCollisionResolvesBeforeCardFlagIsOffered(): void {
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()->onlyMethods(['moveObjectForward'])->getMock();
        $board->method('moveObjectForward')->willReturn(['type' => 'collision']);
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
        $this->assertSame('collisionOccurred', $this->game->playFlagActions('green', [['action' => 'forward']]));
        $this->assertSame([], $this->game->gains);
        $this->assertSame('collisionResolved', $this->game->actPivotPickedInDialog('no pivot'));
        $this->assertSame(STATE_CARD_FLAG, $this->game->stNextPlayerSeaPhase());
        $this->assertSame(1, $this->game->seaEffects);
    }

    public function testShipwreckInterruptionRestoresCardPlayerBeforeFlagChoice(): void {
        $game = new class extends CardFlagUT {
            public function getActivePlayerId(): string { return (string) $this->gamestate->getActivePlayerId(); }
            public function getTreasureSeekerPlayerId(): ?string { return '2'; }
            public function getPlayerCaptain($player_id): ?string { return $player_id == 2 ? 'treasure_seeker' : null; }
            public function getValidTreasureSeekerShipwreckPositions(int $x, int $y): array { return [['x' => 1, 'y' => 1]]; }
            public function applySeafeatureEffects($player_id) {
                return ['moves' => [], 'collision' => false, 'shipwreck_event' => ['shipwreck_arg' => '1', 'new_x' => 0, 'new_y' => 0]];
            }
        };
        $game->owners['green_flag'] = '1';
        $this->assertSame(STATE_TREASURE_SEEKER_ADJUST, $game->playFlagActions('green', []));
        $this->assertSame('2', $game->getActivePlayerId());
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $game->actSkipTreasureSeekerAdjust());
        $this->assertSame('1', $game->getActivePlayerId());
        $this->assertSame(STATE_CARD_FLAG, $game->stNextPlayerSeaPhase());
    }

    public function testExtortionScrappingAlsoRefundsTheCardCost(): void {
        $id = $this->game->addCard($this->game->flagType('red'), 'hand');
        $this->game->setGameStateValue('extortion_pending_flags', 2);
        $this->game->actExtortionScrapCard($id);
        $this->assertSame([$this->game->playable_cards[$this->game->flagType('red')]['cost']], $this->game->gains);
    }
}
