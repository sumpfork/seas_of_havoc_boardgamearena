<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/EndGameScoringTest.php';

class MarketRestockUT extends EndGameUT
{
    public array $gsv = ["market_restocked" => 0, "pending_trading_post_player" => 0];
    public array $claimed = [];

    public function getActivePlayerId(): string { return '1'; }
    public function playable_cards_for_test(): array { return $this->playable_cards; }
    public function playerGainResources($player_id, $resources) {}
    public function getIslandSlots()
    {
        $slot = fn($n) => ["occupying_player_id" => in_array($n, $this->claimed) ? "2" : null,
            "corsair_occupying_player_id" => null, "disabled" => false];
        return [
            "market" => array_combine(["n1", "n2", "n3", "n4", "n5"], array_map($slot, ["n1", "n2", "n3", "n4", "n5"])),
        ];
    }
}

final class MarketRestockTest extends TestCase
{
    private MarketRestockUT $game;

    protected function setUp(): void
    {
        $this->game = new MarketRestockUT();
        $type = $this->game->marketCardTypeWithInfamy(1);
        for ($i = 1; $i <= 5; $i++) {
            $this->game->deck->add($i, $type, 'market', $i);
        }
        for ($i = 11; $i <= 20; $i++) {
            $this->game->deck->add($i, $type, 'market_deck');
        }
    }

    private function marketIds(): array
    {
        return array_map(fn($c) => $c["id"], $this->game->getMarketSlots());
    }

    public function testRestockReplacesOnlyUnclaimedCardsInPlace(): void
    {
        $this->game->claimed = ["n2"];
        $this->game->actRestockMarket();

        $ids = $this->marketIds();
        $this->assertSame(2, $ids[1], 'the claimed card stays under its skiff');
        foreach ([0, 2, 3, 4] as $i) {
            $this->assertGreaterThan(10, $ids[$i], 'unclaimed slots get fresh cards');
        }
        $this->assertCount(4, $this->game->deck->getCardsInLocation('scrap'));
    }

    /** Empty the market deck and leave one scrapped card of each kind; returns the market card's id. */
    private function emptyMarketDeckWithScrappedCards(): int
    {
        for ($i = 11; $i <= 20; $i++) {
            $this->game->deck->moveCard($i, 'player_discard_1');
        }
        $categories = array_column($this->game->playable_cards_for_test(), 'category');
        $this->game->deck->add(31, array_search('starting_card', $categories, true), 'scrap');
        $this->game->deck->add(32, array_search('captain', $categories, true), 'scrap');
        $this->game->deck->add(33, $this->game->damageCardType(), 'scrap');
        $this->game->deck->add(34, $this->game->marketCardTypeWithInfamy(1), 'scrap');
        return 34;
    }

    public function testEmptyMarketDeckTakesBackOnlyScrappedMarketCards(): void
    {
        $market_card = $this->emptyMarketDeckWithScrappedCards();
        $this->game->deck->moveCard(1, 'hand', 1);

        $reshuffled = $this->game->refillMarket();

        $this->assertSame([$market_card], array_map(fn($c) => $c['id'], $reshuffled));
        $this->assertSame($market_card, $this->marketIds()[0]);
        $this->assertSame(['market_deck'], $this->game->deck->shuffled);
        foreach ([31, 32, 33] as $other) {
            $this->assertSame('scrap', $this->game->deck->getCard($other)['location']);
        }
        $this->assertCount(1, array_filter($this->game->bga->notify->sent, fn($n) => $n['type'] === 'message'));
    }

    public function testNoReshuffleWhileTheMarketIsFullOrTheDeckHasCards(): void
    {
        $this->game->deck->add(34, $this->game->marketCardTypeWithInfamy(1), 'scrap');
        $this->assertSame([], $this->game->refillMarket(), 'the market is full');
        $this->game->deck->moveCard(1, 'hand', 1);
        $this->assertSame([], $this->game->refillMarket(), 'the deck still has cards');
        $this->assertSame('scrap', $this->game->deck->getCard(34)['location']);
        $this->assertSame([], $this->game->deck->shuffled);
    }

    public function testEmptyMarketSlotStaysEmptyWithNothingToReshuffle(): void
    {
        for ($i = 11; $i <= 20; $i++) {
            $this->game->deck->moveCard($i, 'player_discard_1');
        }
        $this->game->deck->moveCard(1, 'hand', 1);
        $this->assertSame([], $this->game->refillMarket());
        $this->assertNull($this->game->getMarketSlots()[0]);
    }

    public function testRestockWithAnEmptyDeckCanDealTheCardsItJustScrapped(): void
    {
        for ($i = 11; $i <= 20; $i++) {
            $this->game->deck->moveCard($i, 'player_discard_1');
        }
        $this->game->actRestockMarket();

        $this->assertCount(5, array_filter($this->marketIds()), 'the market is full again');
        $sent = array_values(array_filter($this->game->bga->notify->sent, fn($n) => $n['type'] === 'marketUpdated'));
        $this->assertCount(5, $sent[0]['args']['scrapped']);
        $this->assertCount(5, $sent[0]['args']['reshuffled'], 'the client takes them back out of its pile');
    }

    public function testNoRestockWhenEveryCardIsClaimed(): void
    {
        $this->game->claimed = ["n1", "n2", "n3", "n4", "n5"];
        $this->assertFalse($this->game->canRestockMarket());
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actRestockMarket();
    }

    public function testNoRestockWhileTradingPostPending(): void
    {
        $this->game->setGameStateValue("pending_trading_post_player", 1);
        $this->game->setGameStateValue("pending_trading_post_slot", 1);
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actRestockMarket();
    }
}
