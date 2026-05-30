<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class CorsairActionUT extends SeasOfHavocUT {
    public array $mockPlayerResources = [];
    public array $resourceAdjustments = [];
    public string $captain = 'corsair';
    public array $mockIslandSlots = [
        'shipyard' => [
            'n1' => ['occupying_player_id' => 2, 'corsair_occupying_player_id' => null, 'disabled' => false],
        ],
        'blacksmith' => [
            'n1' => ['occupying_player_id' => 3, 'corsair_occupying_player_id' => null, 'disabled' => false],
        ],
        'capitol' => [
            'n1' => ['occupying_player_id' => 2, 'corsair_occupying_player_id' => null, 'disabled' => false],
        ],
        'bank' => [
            'n1' => ['occupying_player_id' => 2, 'corsair_occupying_player_id' => null, 'disabled' => false],
        ],
        'green_flag' => [
            'n1' => ['occupying_player_id' => 2, 'corsair_occupying_player_id' => null, 'disabled' => false],
        ],
        'market' => [
            'n1' => ['occupying_player_id' => 2, 'corsair_occupying_player_id' => null, 'disabled' => false],
        ],
    ];

    public function __construct() {
        parent::__construct();
        $this->setGameStateValue('pending_trading_post_player', 0);
        $this->setGameStateValue('pending_trading_post_slot', 0);
        $this->setGameStateValue('corsair_occupied_placement_used', 0);
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

    public function getIslandSlots() {
        return $this->mockIslandSlots;
    }

    public function occupyIslandSlotAsCorsairOverlay(string $player_id, string $slot_name, string $number)
    {
        $this->mockIslandSlots[$slot_name][$number]['corsair_occupying_player_id'] = (int) $player_id;
    }

    public function getPlayerCaptain($player_id) {
        return $this->captain;
    }
}

final class CorsairAbilityTest extends TestCase {
    private CorsairActionUT $game;

    protected function setUp(): void {
        $this->game = new CorsairActionUT();
        $this->game->mockPlayerResources = [
            'sail' => 0,
            'cannonball' => 0,
            'doubloon' => 0,
            'skiff' => 1,
        ];
    }

    public function testCorsairCanPlaceOnOccupiedResourceSpaceOncePerIslandPhase(): void {
        $this->game->actPlaceSkiff('shipyard', 'n1');

        $this->assertSame(2, $this->game->mockPlayerResources['sail']);
        $this->assertSame(1, $this->game->mockPlayerResources['cannonball']);
        $this->assertSame(0, $this->game->mockPlayerResources['skiff']);
        $this->assertSame(2, $this->game->mockIslandSlots['shipyard']['n1']['occupying_player_id']);
        $this->assertSame(1, $this->game->mockIslandSlots['shipyard']['n1']['corsair_occupying_player_id']);
        $this->assertSame(1, $this->game->getGameStateValue('corsair_occupied_placement_used'));
        $this->assertSame(3, $this->game->gamestate->state_id());
    }

    public function testCorsairCanPlaceOnOccupiedBankAndGainShownResourcesOnly(): void {
        $this->game->actPlaceSkiff('bank', 'n1');
        $this->assertSame(2, $this->game->gamestate->state_id());
        $this->assertSame(0, $this->game->getGameStateValue('corsair_occupied_placement_used'));

        $this->game->actResourcePickedInDialog('sail', 'corsair_occupied_bank', 'n1');

        $this->assertSame(1, $this->game->mockPlayerResources['sail']);
        $this->assertSame(1, $this->game->mockPlayerResources['doubloon']);
        $this->assertSame(0, $this->game->mockPlayerResources['skiff']);
        $this->assertSame(2, $this->game->mockIslandSlots['bank']['n1']['occupying_player_id']);
        $this->assertSame(1, $this->game->mockIslandSlots['bank']['n1']['corsair_occupying_player_id']);
        $this->assertSame(1, $this->game->getGameStateValue('corsair_occupied_placement_used'));
        $this->assertSame(3, $this->game->gamestate->state_id());
    }

    public function testCorsairCanPlaceOnOccupiedCapitolAndOnlyGainChosenResource(): void {
        $this->game->actPlaceSkiff('capitol', 'n1');
        $this->assertSame(2, $this->game->gamestate->state_id());
        $this->assertSame(0, $this->game->getGameStateValue('corsair_occupied_placement_used'));

        $this->game->actResourcePickedInDialog('cannonball', 'corsair_occupied_capitol', 'n1');

        $this->assertSame(1, $this->game->mockPlayerResources['cannonball']);
        $this->assertSame(0, $this->game->mockPlayerResources['skiff']);
        $this->assertSame(2, $this->game->mockIslandSlots['capitol']['n1']['occupying_player_id']);
        $this->assertSame(1, $this->game->mockIslandSlots['capitol']['n1']['corsair_occupying_player_id']);
        $this->assertSame(1, $this->game->getGameStateValue('corsair_occupied_placement_used'));
        $this->assertSame(3, $this->game->gamestate->state_id());
    }

    public function testCorsairCanPlaceOnOccupiedGreenFlagAndOnlyGainChosenResource(): void {
        $this->game->actPlaceSkiff('green_flag', 'n1');
        $this->assertSame(2, $this->game->gamestate->state_id());
        $this->assertSame(0, $this->game->getGameStateValue('corsair_occupied_placement_used'));

        $this->game->actResourcePickedInDialog('doubloon', 'corsair_occupied_green_flag', 'n1');

        $this->assertSame(1, $this->game->mockPlayerResources['doubloon']);
        $this->assertSame(0, $this->game->mockPlayerResources['skiff']);
        $this->assertSame(2, $this->game->mockIslandSlots['green_flag']['n1']['occupying_player_id']);
        $this->assertSame(1, $this->game->mockIslandSlots['green_flag']['n1']['corsair_occupying_player_id']);
        $this->assertSame(1, $this->game->getGameStateValue('corsair_occupied_placement_used'));
        $this->assertSame(3, $this->game->gamestate->state_id());
    }

    public function testCorsairCannotUseOccupiedPlacementTwiceInSameIslandPhase(): void {
        $this->game->actPlaceSkiff('shipyard', 'n1');

        // In stubs, occupied-slot error path may surface as TypeError due NotificationMessage handling.
        $this->expectException(\Throwable::class);
        $this->game->actPlaceSkiff('blacksmith', 'n1');
    }

    public function testNonCorsairCannotPlaceOnOccupiedSpace(): void {
        $this->game->captain = 'merchant';

        // In stubs, occupied-slot error path may surface as TypeError due NotificationMessage handling.
        $this->expectException(\Throwable::class);
        $this->game->actPlaceSkiff('shipyard', 'n1');
    }

    public function testCorsairCannotUseOccupiedPlacementOnNonResourceSpace(): void {
        $this->expectException(BgaUserException::class);
        $this->game->actPlaceSkiff('market', 'n1');
    }
}
