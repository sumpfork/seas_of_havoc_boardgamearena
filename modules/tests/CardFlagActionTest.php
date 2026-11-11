<?php declare(strict_types=1);

use Bga\Games\SeasOfHavoc\SeaBoard;
use Bga\Games\SeasOfHavoc\CardActionOutcome;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/RebelAndTreasureSeekerCardAbilityTest.php';

class CardFlagUT extends RebelAndTreasureSeekerCardUT
{
    public array $owners = [];
    public array $extraTurns = [];
    function grantExtraTurn(string $player_id, string $phase) { $this->extraTurns["$player_id/$phase"] = true; }
    function hasExtraTurn(string $player_id, string $phase) { return isset($this->extraTurns["$player_id/$phase"]); }
    function consumeExtraTurn(string $player_id, string $phase) { unset($this->extraTurns["$player_id/$phase"]); }
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
        $this->assertSame('player_discard_1', $this->game->deck->getCard($id)['location']);
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
        $this->game->addCard(1, 'hand', 2);
        $this->game->playFlag('blue');
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->actResolveCardFlag());
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase());
        $this->game->addCard(1, 'hand');
        $this->game->playFlag('blue');
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->actResolveCardFlag());
        $this->assertSame(STATE_SEA_TURN, $this->game->stNextPlayerSeaPhase());
    }

    public function testBluePromptIsSkippedWhenHandIsEmpty(): void {
        $this->game->addCard(1, 'hand', 2);
        $this->game->playFlag('blue');
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase());
        $this->assertSame(0, $this->game->getGameStateValue('pending_card_flag_type'));
    }

    public function testBluePromptRemainsWhenAnotherCardCanBePlayed(): void {
        $this->game->addCard(1, 'hand');
        $this->game->playFlag('blue');
        $this->assertSame(STATE_CARD_FLAG, $this->game->stNextPlayerSeaPhase());
    }

    public function testBluePromptIsSkippedAfterCollisionDiscardsTheLastCard(): void {
        $this->game->addCard(1, 'hand', 2);
        $last = $this->game->addCard(1, 'hand');
        $this->game->playFlag('blue');
        $this->game->actCollisionDiscardCard($last);
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase());
    }

    public function testExtortionBlueExtraSeaTurnNeedsACardInHand(): void {
        $this->game->addCard(1, 'hand', 2);
        $this->game->grantExtraTurn('1', 'sea');
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase());
        $this->game->addCard(1, 'hand');
        $this->game->grantExtraTurn('1', 'sea');
        $this->assertSame(STATE_SEA_TURN, $this->game->stNextPlayerSeaPhase());
        $this->assertSame([], $this->game->extraTurns);
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
            $this->assertSame('player_discard_1', $this->game->deck->getCard($id)['location']);
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
        $board->method('moveObjectForward')->willReturn(
            ['type' => 'collision', 'colliders' => [['type' => 'rock', 'arg' => 0]]],
        );
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
        // Empty hand, so the collision penalty discard is skipped and the collision resolves directly.
        $this->assertSame(STATE_RESOLVE_COLLISION, $this->game->playFlagActions('green', [['action' => 'forward']]));
        $this->assertSame(['1'], $this->game->damaged, 'hitting a rock damages the colliding player');
        $this->assertSame([], $this->game->gains);
        $this->assertSame('collisionResolved', $this->game->actPivotPickedInDialog('no pivot'));
        $this->assertSame(STATE_CARD_FLAG, $this->game->stNextPlayerSeaPhase());
        $this->assertSame(0, $this->game->seaEffects, 'the flag action is part of the card');
        $this->game->addCard(1, 'hand', 2);
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->actResolveCardFlag('sail'));
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase());
        $this->assertSame(1, $this->game->seaEffects, 'whirlpools and gusts resolve after it');
    }

    /** e.g. a red flag cannot scrap the damage card from the rock a gust is about to push you into. */
    public function testFlagActionResolvesBeforeWhirlpoolsAndGusts(): void {
        $this->game->addCard(1, 'hand', 2);
        $this->game->playFlag('red');
        $this->assertSame(STATE_CARD_FLAG, $this->game->stNextPlayerSeaPhase());
        $this->assertSame(0, $this->game->seaEffects);
        $this->game->actSkipCardFlag();
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase());
        $this->assertSame(1, $this->game->seaEffects);
    }

    /** Blue's "take another turn" is queued: this card's whirlpool or gust comes before the next card. */
    public function testBlueFlagExtraTurnWaitsForWhirlpoolsAndGusts(): void {
        $this->game->addCard(1, 'hand');
        $this->game->playFlag('blue');
        $this->assertSame(STATE_CARD_FLAG, $this->game->stNextPlayerSeaPhase());
        $this->game->actResolveCardFlag();
        $this->assertSame(0, $this->game->seaEffects);
        $this->assertSame(STATE_SEA_TURN, $this->game->stNextPlayerSeaPhase());
        $this->assertSame(1, $this->game->seaEffects);
    }

    /** Captain abilities that stop for the player's input still get their whirlpool or gust. */
    public function testCaptainAbilityWaitingForInputStillResolvesWhirlpoolsAndGusts(): void {
        $this->game->runEngine = true;
        $type = array_key_first(array_filter($this->game->playable_cards, fn($c) => ($c['actions'][0]['ability'] ?? null) === 'barter'));
        $this->assertSame(STATE_BARTER, $this->game->actPlayCard($type, $this->game->addCard($type, 'hand'), []));
        $this->assertSame(0, $this->game->seaEffects);
        $this->game->addCard(1, 'hand', 2);
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase()); // as when Barter is done
        $this->assertSame(1, $this->game->seaEffects);
    }

    /** Every card played triggers them, even one whose ability turns out to do nothing. */
    public function testCaptainAbilityThatDoesNothingStillResolvesWhirlpoolsAndGusts(): void {
        $this->game->runEngine = true;
        $this->game->owners = []; // Extortion with no flags to use
        $type = array_key_first(array_filter($this->game->playable_cards, fn($c) => ($c['actions'][0]['ability'] ?? null) === 'extortion'));
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->actPlayCard($type, $this->game->addCard($type, 'hand'), []));
        $this->game->addCard(1, 'hand', 2);
        $this->assertSame('nextPlayer', $this->game->stNextPlayerSeaPhase());
        $this->assertSame(1, $this->game->seaEffects);
    }

    public function testHuntTheBountyExtraPlayWaitsForWhirlpoolsAndGusts(): void {
        $this->game->addCard(1, 'hand');
        $this->game->setGameStateValue('seafeatures_pending', 1);
        $this->game->grantExtraTurn('1', 'hunt_the_bounty');
        $this->assertSame(STATE_HUNT_THE_BOUNTY_EXTRA_PLAY, $this->game->stNextPlayerSeaPhase());
        $this->assertSame(1, $this->game->seaEffects);
    }

    public function testShipwreckInterruptionRestoresCardPlayerBeforeExtraTurn(): void {
        $game = new class extends CardFlagUT {
            public function getActivePlayerId(): string { return (string) $this->gamestate->getActivePlayerId(); }
            public function getTreasureSeekerPlayerId(): ?string { return '2'; }
            public function getPlayerCaptain($player_id): ?string { return $player_id == 2 ? 'treasure_seeker' : null; }
            public function getValidTreasureSeekerShipwreckPositions(int $x, int $y): array { return [['x' => 1, 'y' => 1]]; }
            public function applySeafeatureEffects($player_id): CardActionOutcome {
                return new CardActionOutcome(shipwreckEvents: [['shipwreck_arg' => '1', 'old_x' => 0, 'old_y' => 0]]);
            }
        };
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()
            ->onlyMethods(['getObjectsOfTypes', 'placeObject'])->getMock();
        $board->method('getObjectsOfTypes')->willReturn([]);
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($game, $board);
        $game->owners['blue_flag'] = '1';
        $game->addCard(1, 'hand');
        $this->assertSame('seaTurnDone', $game->playFlagActions('blue', []));
        $game->bga->globals->set('salvaged_shipwrecks', ['1']); // as collectShipwrecksAtPlayer() records it
        $this->assertSame(STATE_CARD_FLAG, $game->stNextPlayerSeaPhase(), 'the flag is part of the card');
        $game->actResolveCardFlag();
        $this->assertSame(STATE_TREASURE_SEEKER_ADJUST, $game->stNextPlayerSeaPhase(), 'placed before the extra turn');
        $this->assertSame('2', $game->getActivePlayerId());
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $game->actSkipTreasureSeekerAdjust());
        $this->assertSame('1', $game->getActivePlayerId());
        $this->assertSame(STATE_SEA_TURN, $game->stNextPlayerSeaPhase());
    }

    public function testRowingDiscardsTwoCardsAndMovesBackward(): void {
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()
            ->onlyMethods(['moveObjectBackward', 'moveObjectForward'])->getMock();
        $board->expects($this->never())->method('moveObjectForward');
        $board->expects($this->once())->method('moveObjectBackward')->willReturn(
            ['type' => 'collision', 'colliders' => [['type' => 'rock', 'arg' => 0]]],
        );
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
        $this->game->runEngine = true;
        $a = $this->game->addCard(1, 'hand');
        $b = $this->game->addCard(1, 'hand');
        $this->assertSame(STATE_RESOLVE_COLLISION, $this->game->actRow($a, $b, 'backward'));
        $this->assertSame('player_discard_1', $this->game->deck->getCard($a)['location']);
        $this->assertSame('player_discard_1', $this->game->deck->getCard($b)['location']);
        $this->assertSame(['1'], $this->game->damaged);
        $this->assertSame(0, $this->game->getGameStateValue('pending_card_flag_type'));
    }

    public function testRowingLeavesTheSecondDiscardOnTop(): void {
        $a = $this->game->addCard(1, 'hand');
        $b = $this->game->addCard(2, 'hand');
        $this->game->actRow($b, $a, 'forward');
        $this->assertSame($a, (int) $this->game->deck->getCardOnTop('player_discard_1')['id']);
    }

    public function testRowingNeedsTwoDifferentCardsFromHand(): void {
        $a = $this->game->addCard(1, 'hand');
        try {
            $this->game->actRow($a, $a, 'forward');
            $this->fail('one card cannot pay for rowing twice');
        } catch (\Bga\GameFramework\UserException) {}
        $other = $this->game->addCard(1, 'hand', 2);
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actRow($a, $other, 'forward');
    }

    public function testRowingRejectsManeuversRowingDoesNotHave(): void {
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actRow($this->game->addCard(1, 'hand'), $this->game->addCard(1, 'hand'), 'pivot 180');
    }

    public function testExtortionScrappingAlsoRefundsTheCardCost(): void {
        $id = $this->game->addCard($this->game->flagType('red'), 'hand');
        $this->game->setGameStateValue('extortion_pending_flags', 2);
        $this->game->actExtortionScrapCard($id);
        $this->assertSame([$this->game->playable_cards[$this->game->flagType('red')]['cost']], $this->game->gains);
    }
}
