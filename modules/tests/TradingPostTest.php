<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class TradingPostActionUT extends SeasOfHavocUT {
    public array $mockPlayerResources = [];
    public array $resourceAdjustments = [];
    public array $occupiedSlots = [];
    public array $mockIslandSlots = [
        'market' => [
            'n1' => ['occupying_player_id' => null, 'disabled' => false],
        ],
        'trading_post' => [
            'n1' => ['occupying_player_id' => null, 'disabled' => false],
            'n2' => ['occupying_player_id' => null, 'disabled' => false],
        ],
    ];
    public array $booty_tokens = [];

    public function __construct() {
        parent::__construct();
        $this->setGameStateValue('pending_trading_post_player', 0);
        $this->setGameStateValue('pending_trading_post_slot', 0);
    }

    public function getGameResourcesHierarchical(?int $player_id = null) {
        $pid = $player_id ?? (int) $this->gamestate->getActivePlayerId();
        return [$pid => $this->mockPlayerResources];
    }

    public function playerGainResources($player_id, $resources) {
        $this->resourceAdjustments[] = $resources;
        foreach ($resources as $resource => $amount) {
            $this->mockPlayerResources[$resource] = ($this->mockPlayerResources[$resource] ?? 0) + $amount;
        }
    }

    public function occupyIslandSlot(string $player_id, string $slot_name, string $number) {
        $this->occupiedSlots[] = [
            'player_id' => $player_id,
            'slot_name' => $slot_name,
            'slot_number' => $number,
        ];
        $this->mockIslandSlots[$slot_name][$number]['occupying_player_id'] = $player_id;
    }

    public function getIslandSlots() {
        return $this->mockIslandSlots;
    }

}

final class TradingPostTest extends TestCase {
    private TradingPostActionUT $game;

    protected function setUp(): void {
        $this->game = new TradingPostActionUT();
        $this->game->mockPlayerResources = [
            'sail' => 1,
            'cannonball' => 0,
            'doubloon' => 0,
            'skiff' => 1,
        ];
    }

    public function testPlacingSkiffStartsPendingTradingPostFlow(): void {
        $this->game->actPlaceSkiff('trading_post', 'n1');

        $this->assertSame(1, $this->game->getGameStateValue('pending_trading_post_player'));
        $this->assertSame(1, $this->game->getGameStateValue('pending_trading_post_slot'));
        $this->assertNotNull($this->game->debugLastNotif);
        $this->assertSame('showTradingPostDialog', $this->game->debugLastNotif['type']);
        $this->assertSame(1, $this->game->debugLastNotif['player_id']);
        $this->assertEmpty($this->game->resourceAdjustments);
        $this->assertEmpty($this->game->occupiedSlots);
    }

    public function testCannotPlaceAnotherSkiffWhileTradingPostPending(): void {
        $this->game->setGameStateValue('pending_trading_post_player', 1);
        $this->game->setGameStateValue('pending_trading_post_slot', 1);

        $this->expectException(BgaUserException::class);
        $this->game->actPlaceSkiff('market', 'n1');
    }

    public function testTradingPostExchangeRequiresPendingSelection(): void {
        $this->expectException(BgaUserException::class);
        $this->game->actTradingPostExchange(['sail'], ['cannonball'], 'n1');
    }

    public function testTradingPostExchangeRejectsMismatchedPendingSlot(): void {
        $this->game->setGameStateValue('pending_trading_post_player', 1);
        $this->game->setGameStateValue('pending_trading_post_slot', 2);

        $this->expectException(BgaUserException::class);
        $this->game->actTradingPostExchange(['sail'], ['cannonball'], 'n1');
    }

    public function testTradingPostExchangeCompletesAndClearsPendingSelection(): void {
        $this->game->setGameStateValue('pending_trading_post_player', 1);
        $this->game->setGameStateValue('pending_trading_post_slot', 1);

        $this->game->actTradingPostExchange(['sail'], ['cannonball'], 'n1');

        $this->assertSame(0, $this->game->getGameStateValue('pending_trading_post_player'));
        $this->assertSame(0, $this->game->getGameStateValue('pending_trading_post_slot'));
        $this->assertSame(0, $this->game->mockPlayerResources['sail']);
        $this->assertSame(1, $this->game->mockPlayerResources['cannonball']);
        $this->assertSame(0, $this->game->mockPlayerResources['skiff']);
        $this->assertCount(1, $this->game->occupiedSlots);
        $this->assertSame('trading_post', $this->game->occupiedSlots[0]['slot_name']);
        $this->assertSame('n1', $this->game->occupiedSlots[0]['slot_number']);
        $this->assertSame(3, $this->game->gamestate->state_id());
    }
}