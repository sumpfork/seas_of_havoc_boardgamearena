<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/EndGameScoringTest.php';

class MarketRestockUT extends EndGameUT
{
    public array $gsv = ["market_restocked" => 0, "pending_trading_post_player" => 0];
    public array $claimed = [];

    public function getActivePlayerId(): string { return '1'; }
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

    public function testNoRestockWhenEveryCardIsClaimed(): void
    {
        $this->game->claimed = ["n1", "n2", "n3", "n4", "n5"];
        $this->assertFalse($this->game->canRestockMarket());
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actRestockMarket();
    }
}
