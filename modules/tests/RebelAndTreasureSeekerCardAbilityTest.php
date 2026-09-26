<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SeasOfHavocTest.php';

class RebelAndTreasureSeekerCardUT extends SeasOfHavocUT
{
    public Deck $deck;
    public array $draws = [];
    public array $gains = [];
    public array $resolved = [];
    public bool $runEngine = false;
    public int $seaEffects = 0;
    public array $paid = [];

    public function __construct()
    {
        parent::__construct();
        $this->deck = new Deck();
        (new ReflectionProperty(SeasOfHavoc::class, 'cards'))->setValue($this, $this->deck);
        (new ReflectionProperty(SeasOfHavoc::class, 'booty_tokens'))->setValue($this, $this->booty_tokens);
    }

    public array $damaged = [];
    public array $infamy = [];

    public function drawCards(string $player_id, int $num_cards = 1) { $this->draws[] = $num_cards; }
    public function dealDamageCard(string $hit_player_id): void { $this->damaged[] = $hit_player_id; }
    public function scoreInfamy(string $player_id, int $amount, string $message = "") { $this->infamy[] = [$player_id, $amount]; }
    public function playerGainResources($player_id, $resources) { $this->gains[] = $resources; }
    public function getActivePlayerId(): string { return '1'; }
    public function getPlayerNameById(int $player_id): string { return 'Player'; }
    public function payWithOptionalBooty(int $player_id, array $cost, ?int $use_booty_card_id = null, ?string $booty_choice = null): void { $this->paid[] = $cost; }
    public function applySeafeatureEffects($player_id) {
        $this->seaEffects++;
        return ['moves' => [], 'collision' => false, 'shipwreck_event' => null, 'booty_card' => null];
    }
    protected function resolvePlayedCard(int $card_type, int $card_id, array $decisions, ?int $use_booty_card_id = null, ?array $actions = null) {
        $this->resolved = compact('card_type', 'card_id', 'decisions', 'use_booty_card_id', 'actions');
        return $this->runEngine ? parent::resolvePlayedCard($card_type, $card_id, $decisions, $use_booty_card_id, $actions) : 'seaTurnDone';
    }
    public function addCard(int $type, string $location, int $arg = 1): int {
        if ($location === 'player_discard') {
            // Discards are per-player ordered piles now; $arg names the player, not the position.
            $location = $this->playerDiscardName($arg);
            $arg = $this->deck->countCardInLocation($location) + 1;
        }
        $this->deck->createCards([['type' => $type, 'type_arg' => 0, 'nbr' => 1]], $location, $arg);
        return max(array_keys($this->deck->getCardsInLocation($location, $arg)));
    }
    public function start(string $ability): mixed {
        $type = array_key_first(array_filter($this->playable_cards, fn($c) => ($c['actions'][0]['ability'] ?? null) === $ability));
        $id = $this->addCard($type, 'hand');
        $result = $this->processCaptainAbility($ability);
        $this->discardCardToPlayer($id, 1);
        $this->setGameStateValue('pending_captain_card', $id);
        return $result;
    }
}

final class RebelAndTreasureSeekerCardAbilityTest extends TestCase
{
    private RebelAndTreasureSeekerCardUT $game;
    protected function setUp(): void { $this->game = new RebelAndTreasureSeekerCardUT(); }

    public function testRetaliationScrapsHandDamageAndUsesFreeRangeThreeShot(): void {
        $id = $this->game->addCard(0, 'hand');
        $this->assertSame(STATE_CAPTAIN_CARD, $this->game->start('retaliation'));
        $this->game->actResolveCaptainCard(['card_id' => $id, 'fire' => 'fire left']);
        $this->assertSame('scrap', $this->game->deck->getCard($id)['location']);
        $this->assertSame([['action' => 'fire', 'range' => 3]], $this->game->resolved['actions']);
        $this->assertSame(['fire left'], $this->game->resolved['decisions']);
    }

    /** Damage cards are all the same card, so Retaliation does not ask which one to scrap. */
    public function testRetaliationPicksTheDamageCardItself(): void {
        $id = $this->game->addCard(0, 'hand');
        $this->game->start('retaliation');

        $this->game->actResolveCaptainCard(['fire' => 'fire right']);

        $this->assertSame('scrap', $this->game->deck->getCard($id)['location']);
        $this->assertSame(['fire right'], $this->game->resolved['decisions']);
    }

    public function testRetaliationPrefersTheDamageCardInHand(): void {
        $inDiscard = $this->game->addCard(0, 'player_discard');
        $inHand = $this->game->addCard(0, 'hand');
        $this->game->start('retaliation');

        $this->game->actResolveCaptainCard(['fire' => 'skip']);

        $this->assertSame('scrap', $this->game->deck->getCard($inHand)['location']);
        $this->assertSame('player_discard_1', $this->game->deck->getCard($inDiscard)['location']);
    }

    public function testRetaliationCanScrapDiscardDamageWithoutFiring(): void {
        $id = $this->game->addCard(0, 'player_discard');
        $this->game->start('retaliation');
        $this->game->actResolveCaptainCard(['card_id' => $id, 'fire' => 'skip']);
        $this->assertSame('scrap', $this->game->deck->getCard($id)['location']);
        $this->assertSame([], $this->game->resolved['actions']);
    }

    public function testRetaliationWithoutDamageDoesNotOfferFreeFire(): void {
        $this->assertIsArray($this->game->start('retaliation'));
    }

    public function testRetaliationRejectsAnotherPlayersDamage(): void {
        $this->game->addCard(0, 'hand');
        $other = $this->game->addCard(0, 'hand', 2);
        $this->game->start('retaliation');
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actResolveCaptainCard(['card_id' => $other, 'fire' => 'fire left']);
    }

    public function testImprovisationCopiesStartingAndMarketCardsWithoutMovingOriginal(): void {
        foreach ([1, 19] as $type) {
            $id = $this->game->addCard($type, 'player_discard');
            $this->assertSame(STATE_CAPTAIN_CARD, $this->game->start('improvisation'));
            $this->game->actResolveCaptainCard(['card_id' => $id], ['skip']);
            $this->assertSame($this->game->playable_cards[$type]['actions'], $this->game->resolved['actions']);
            $this->assertSame('player_discard_1', $this->game->deck->getCard($id)['location']);
            $this->assertNotSame($id, $this->game->resolved['card_id']);
        }
    }

    public function testImprovisationDrawsWhenOnlyDamageOrCaptainCardsAreDiscarded(): void {
        $this->game->addCard(0, 'player_discard');
        $this->assertIsArray($this->game->start('improvisation'));
        $this->assertSame([1], $this->game->draws);
        $this->assertIsArray($this->game->start('improvisation'));
        $this->assertSame([1, 1], $this->game->draws);
    }

    public function testSpyglassKeepsOneAndReturnsOthersInChosenOrderPrivately(): void {
        $ids = [];
        foreach ([1, 2, 3, 4] as $type) $ids[] = $this->game->addCard($type, 'player_deck_1', $type);
        $this->assertSame(STATE_CAPTAIN_CARD, $this->game->start('spyglass'));
        $args = $this->game->argCaptainCard();
        $this->assertArrayNotHasKey('available_cards', $args);
        $this->assertEqualsCanonicalizing([$ids[3], $ids[2], $ids[1]], array_column($args['_private'][1]['available_cards'], 'id'));
        $this->game->actResolveCaptainCard(['order' => [$ids[2], $ids[1], $ids[3]]]);
        $this->assertSame('hand', $this->game->deck->getCard($ids[2])['location']);
        $this->assertSame([$ids[1], $ids[3], $ids[0]], array_column($this->game->deck->getCardsOnTop(3, 'player_deck_1'), 'id'));
        $this->assertCount(0, $this->game->deck->getCardsInLocation('spyglass', 1));
    }

    public function testSpyglassRefillsFromOnlyOwnDiscardAndHandlesShortDeck(): void {
        $top = $this->game->addCard(1, 'player_deck_1');
        $discard = $this->game->addCard(2, 'player_discard');
        $other = $this->game->addCard(3, 'player_discard', 2);
        $this->game->start('spyglass');
        $options = $this->game->argCaptainCard()['_private'][1]['available_cards'];
        $this->assertEqualsCanonicalizing([$top, $discard], array_column($options, 'id'));
        // the other player's discard is a separate pile and stays put
        $this->assertSame('player_discard_2', $this->game->deck->getCard($other)['location']);
        $this->game->actResolveCaptainCard(['order' => [$top, $discard]]);
        $this->assertSame($discard, $this->game->deck->getCardOnTop('player_deck_1')['id']);
    }

    public function testSpyglassWithNoDeckOrDiscardFinishesWithoutChoosing(): void {
        $this->assertIsArray($this->game->start('spyglass'));
        $this->assertSame(0, $this->game->deck->countCardInLocation('spyglass', 1));
    }

    public function testSpyglassRejectsDuplicateIds(): void {
        $id = $this->game->addCard(1, 'player_deck_1');
        $this->game->addCard(2, 'player_deck_1');
        $this->game->start('spyglass');
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actResolveCaptainCard(['order' => [$id, $id]]);
    }

    public function testFreeShotAndCopiedShotUseTheNormalEngineAndFinishOnce(): void {
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()->onlyMethods(['resolveCannonFire'])->getMock();
        $board->expects($this->exactly(2))->method('resolveCannonFire')
            ->with('1', Turn::LEFT, 3, ['rock', 'player_ship'])->willReturn(['type' => 'fire_miss']);
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
        $this->game->runEngine = true;
        $damage = $this->game->addCard(0, 'hand');
        $this->game->start('retaliation');
        $this->assertSame('seaTurnDone', $this->game->actResolveCaptainCard(['card_id' => $damage, 'fire' => 'fire left']));
        $this->assertSame([], $this->game->paid);
        $this->assertSame(1, $this->game->seaEffects);
        $firing = $this->game->addCard(3, 'player_discard');
        $this->game->start('improvisation');
        $this->assertSame('seaTurnDone', $this->game->actResolveCaptainCard(['card_id' => $firing], ['fire left']));
        $this->assertSame([['cannonball' => 1]], $this->game->paid);
        $this->assertSame(2, $this->game->seaEffects);
    }

    public function testFiringVariantsLogCountDirectionAndRangeEvenOnAMiss(): void {
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()->onlyMethods(['resolveCannonFire'])->getMock();
        // 1 + 2 + 3 shots: "Fire 2 cannon" really fires two cannonballs.
        $board->expects($this->exactly(6))->method('resolveCannonFire')->willReturn(['type' => 'fire_miss']);
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
        foreach (['fire', '2 x fire', '3 x fire'] as $index => $action) {
            $direction = $index === 1 ? 'right' : 'left';
            $this->game->processCardActions([['action' => $action, 'range' => 3 - $index]], [$action . ' ' . $direction]);
            $log = $this->game->debugLastNotif;
            $this->assertSame('log', $log['type']);
            $this->assertStringContainsString('fires ${cannon_count}', $log['message']);
            $this->assertSame($index + 1, $log['args']['cannon_count']);
            $this->assertSame($direction, $log['args']['direction']);
            $this->assertSame(3 - $index, $log['args']['range']);
        }
        $lastLog = $this->game->debugLastNotif;
        $this->game->processCardActions([['action' => 'fire', 'range' => 3, 'cost' => ['cannonball' => 1]]], ['skip']);
        $this->assertSame($lastLog, $this->game->debugLastNotif);
    }

    public function testCostNestedInsideAChoiceIsStillCharged(): void {
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()->onlyMethods(['resolveCannonFire'])->getMock();
        $board->expects($this->exactly(2))->method('resolveCannonFire')->willReturn(['type' => 'fire_miss']);
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
        $result = $this->game->processCardActions([
            ['action' => 'choice', 'choices' => [
                ['action' => 'fire', 'range' => 3, 'cost' => ['cannonball' => 1]],
                ['action' => '2 x fire', 'range' => 2, 'cost' => ['cannonball' => 2]],
            ]],
        ], ['2 x fire', '2 x fire left']);
        $this->assertSame(['cannonball' => 2], $result['cost']);
    }

    /** Final scoring reads these: market cards add infamy, damage subtracts, the rest score none. */
    public function testEveryCardCarriesItsPrintedInfamy(): void {
        $seen = [];
        foreach ($this->game->playable_cards as $card) {
            $infamy = $card['infamy'] ?? 0;
            $seen[$card['category']][] = $infamy;
            if ($card['category'] === 'market_card') {
                $this->assertContains($infamy, [1, 2, 3], "market card {$card['image_id']} must show 1-3 skulls");
            } elseif ($card['category'] === 'damage') {
                $this->assertSame(-1, $infamy);
            } else {
                $this->assertSame(0, $infamy, "{$card['category']} cards carry no skull banner");
            }
        }
        $this->assertCount(52, $seen['market_card']);
        $this->assertCount(18, $seen['starting_card']);
        $this->assertCount(12, $seen['captain']);
    }

    public function testEveryMaterialActionUsesACanonicalValue(): void {
        $check = function (array $actions) use (&$check): void {
            foreach ($actions as $action) {
                $this->assertNotNull(PrimitiveCardPlayAction::tryFrom($action['action']));
                $check($action['actions'] ?? []);
                $check($action['choices'] ?? []);
            }
        };
        foreach ($this->game->playable_cards as $card) $check($card['actions']);
    }

    public function testStartingAndMarketPivotsExecuteDirectlyAndThroughImprovisation(): void {
        $turns = ['pivot left' => Turn::LEFT, 'pivot right' => Turn::RIGHT, 'pivot 180' => Turn::AROUND];
        $cases = [];
        foreach ($this->game->playable_cards as $type => $card) {
            foreach ($card['actions'] as $action) {
                foreach ($action['choices'] ?? [] as $choice) {
                    if (isset($turns[$choice['action']])) $cases[] = [$type, $choice['action'], $turns[$choice['action']]];
                }
            }
        }
        $this->assertNotEmpty($cases);
        foreach ($cases as [$type, $decision, $turn]) {
            $this->game = new RebelAndTreasureSeekerCardUT();
            $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()->onlyMethods(['turnObject'])->getMock();
            $board->expects($this->exactly(2))->method('turnObject')->with('player_ship', '1', $turn)
                ->willReturn(['type' => 'turn']);
            (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
            $this->game->processCardActions($this->game->playable_cards[$type]['actions'], [$decision]);
            $this->game->runEngine = true;
            $id = $this->game->addCard($type, 'player_discard');
            $this->game->start('improvisation');
            $this->assertSame('seaTurnDone', $this->game->actResolveCaptainCard(['card_id' => $id], [$decision]));
            $this->assertSame('player_discard_1', $this->game->deck->getCard($id)['location']);
        }
    }

    public function testInvalidPivotChoiceIsRejected(): void {
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->processCardActions($this->game->playable_cards[12]['actions'], ['pivot_right']);
    }

    public function testSpyglassThroughPlayActionDoesNotRevealOrReshuffleItself(): void {
        $this->game->runEngine = true;
        $kept = $this->game->addCard(1, 'player_deck_1');
        $type = array_key_first(array_filter($this->game->playable_cards, fn($c) => ($c['actions'][0]['ability'] ?? null) === 'spyglass'));
        $id = $this->game->addCard($type, 'hand');
        $this->assertSame(STATE_CAPTAIN_CARD, $this->game->actPlayCard($type, $id, []));
        $this->assertSame(0, $this->game->seaEffects);
        $this->assertSame([$kept], array_column($this->game->argCaptainCard()['_private'][1]['available_cards'], 'id'));
        $this->assertSame('seaTurnDone', $this->game->actResolveCaptainCard(['order' => [$kept]]));
        $this->assertSame(1, $this->game->seaEffects);
        $this->assertSame('player_discard_1', $this->game->deck->getCard($id)['location']);
    }

    public function testPlayRejectsForgedCaptainCardType(): void {
        $id = $this->game->addCard(1, 'hand');
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actPlayCard(2, $id, []);
    }

    private function board(bool $rock): void {
        $board = $this->getMockBuilder(SeaBoard::class)->disableOriginalConstructor()->onlyMethods(['findObject', 'getObjectsOfTypes', 'syncFromDB'])->getMock();
        $board->method('findObject')->willReturn(['x' => 0, 'y' => 0]);
        // Diagonal across both wrapped board edges is a surrounding space.
        $board->method('getObjectsOfTypes')->willReturnCallback(fn($x, $y, $types) =>
            $rock && $x === SeaBoard::WIDTH - 1 && $y === SeaBoard::HEIGHT - 1 ? [['type' => 'rock']] : []);
        (new ReflectionProperty(SeasOfHavoc::class, 'seaboard'))->setValue($this->game, $board);
    }

    public function testUnearthRichesRewardsWrappedDiagonalRockAndDiscardsToken(): void {
        $this->board(true);
        $this->game->deck->createCards([['type' => 'booty', 'type_arg' => 3, 'nbr' => 1]], 'booty_deck');
        $this->assertSame(STATE_CAPTAIN_CARD, $this->game->start('unearth_riches'));
        $this->game->actResolveCaptainCard(['resource' => 'sail']);
        $this->assertSame([['doubloon' => 1, 'sail' => 1]], $this->game->gains);
        $this->assertSame(1, $this->game->deck->countCardInLocation('booty_discard'));
        $this->assertSame(0, $this->game->deck->countCardInLocation('booty_player', 1));
    }

    public function testUnearthRichesWithoutRockDoesNotDrawToken(): void {
        $this->board(false);
        $this->game->deck->createCards([['type' => 'booty', 'type_arg' => 0, 'nbr' => 1]], 'booty_deck');
        $this->assertIsArray($this->game->start('unearth_riches'));
        $this->assertSame(1, $this->game->deck->countCardInLocation('booty_deck'));
    }
}
