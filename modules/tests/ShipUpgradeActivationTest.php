<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class ShipUpgradeActivationUT extends SeasOfHavocUT
{
    public array $mockPlayerResources = [];
    public array $resourceAdjustments = [];
    public array $occupiedSlots = [];
    public array $mockPlayerShipUpgrades = [];
    public array $activatedUpgrades = [];
    public array $mockIslandSlots = [
        "workshop" => [
            "n1" => ["occupying_player_id" => null, "disabled" => false],
            "n2" => ["occupying_player_id" => null, "disabled" => false],
        ],
    ];
    public array $infamyAwards = [];

    public function __construct()
    {
        parent::__construct();
        $this->setGameStateValue("pending_workshop_player", 0);
        $this->setGameStateValue("pending_workshop_slot", 0);
    }

    public function getGameResourcesHierarchical(?int $player_id = null)
    {
        $pid = $player_id ?? (int) $this->gamestate->getActivePlayerId();
        return [$pid => $this->mockPlayerResources];
    }

    public function playerGainResources($player_id, $resources)
    {
        $this->resourceAdjustments[] = $resources;
        foreach ($resources as $resource => $amount) {
            $this->mockPlayerResources[$resource] = ($this->mockPlayerResources[$resource] ?? 0) + $amount;
        }
    }

    public function occupyIslandSlot(string $player_id, string $slot_name, string $number)
    {
        $this->occupiedSlots[] = [
            "player_id" => $player_id,
            "slot_name" => $slot_name,
            "slot_number" => $number,
        ];
        $this->mockIslandSlots[$slot_name][$number]["occupying_player_id"] = $player_id;
    }

    public function getIslandSlots()
    {
        return $this->mockIslandSlots;
    }

    public function getPlayerShipUpgrades($player_id)
    {
        return $this->mockPlayerShipUpgrades[$player_id] ?? [];
    }

    public function markShipUpgradeActivated(string $player_id, string $upgrade_key): void
    {
        $this->activatedUpgrades[] = ["player_id" => $player_id, "upgrade_key" => $upgrade_key];
        foreach ($this->mockPlayerShipUpgrades[$player_id] ?? [] as &$upgrade) {
            if ($upgrade["upgrade_key"] === $upgrade_key) {
                $upgrade["is_activated"] = 1;
            }
        }
    }

    public function loadPlayersBasicInfos(): array
    {
        return [
            1 => ["player_name" => "TestPlayer"],
            2 => ["player_name" => "OtherPlayer"],
        ];
    }

    public function scoreInfamy(string $player_id, int $amount, string $message = "")
    {
        $this->infamyAwards[] = ["player_id" => $player_id, "amount" => $amount];
    }
}

final class ShipUpgradeActivationTest extends TestCase
{
    private ShipUpgradeActivationUT $game;

    protected function setUp(): void
    {
        $this->game = new ShipUpgradeActivationUT();
        $this->game->mockPlayerResources = [
            "sail" => 2,
            "cannonball" => 2,
            "doubloon" => 2,
            "skiff" => 1,
        ];
        $this->game->mockPlayerShipUpgrades["1"] = [
            ["upgrade_key" => "xebec_lateen_rigging", "is_activated" => 0],
            ["upgrade_key" => "xebec_swift_hull", "is_activated" => 0],
        ];
    }

    public function testPlacingSkiffOnWorkshopStartsPendingSelectionFlow(): void
    {
        $this->game->actPlaceSkiff("workshop", "n1");

        $this->assertSame(1, $this->game->getGameStateValue("pending_workshop_player"));
        $this->assertSame(1, $this->game->getGameStateValue("pending_workshop_slot"));
        $this->assertSame("showWorkshopDialog", $this->game->debugLastNotif["type"]);
        $this->assertEmpty($this->game->resourceAdjustments);
        $this->assertEmpty($this->game->occupiedSlots);
    }

    public function testPlacingSkiffOnWorkshopWithNoInactiveUpgradesIsBlocked(): void
    {
        $this->game->mockPlayerShipUpgrades["1"] = [
            ["upgrade_key" => "xebec_lateen_rigging", "is_activated" => 1],
            ["upgrade_key" => "xebec_swift_hull", "is_activated" => 1],
        ];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actPlaceSkiff("workshop", "n1");
    }

    public function testPlacingSkiffOnWorkshopWithNoAffordableUpgradesIsBlocked(): void
    {
        $this->game->mockPlayerResources = ["sail" => 0, "cannonball" => 0, "doubloon" => 0, "skiff" => 1];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actPlaceSkiff("workshop", "n1");

        $this->assertEmpty($this->game->resourceAdjustments);
        $this->assertEmpty($this->game->occupiedSlots);
    }

    public function testActivateShipUpgradeRequiresPendingSelection(): void
    {
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actActivateShipUpgrade("xebec_lateen_rigging");
    }

    public function testActivateShipUpgradeRejectsAlreadyActivatedUpgrade(): void
    {
        $this->game->setGameStateValue("pending_workshop_player", 1);
        $this->game->setGameStateValue("pending_workshop_slot", 1);
        $this->game->mockPlayerShipUpgrades["1"][0]["is_activated"] = 1;

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actActivateShipUpgrade("xebec_lateen_rigging");
    }

    public function testActivateShipUpgradeRejectsUnknownUpgrade(): void
    {
        $this->game->setGameStateValue("pending_workshop_player", 1);
        $this->game->setGameStateValue("pending_workshop_slot", 1);

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actActivateShipUpgrade("not_a_real_upgrade");
    }

    public function testActivateShipUpgradeFailsWhenUnaffordable(): void
    {
        $this->game->setGameStateValue("pending_workshop_player", 1);
        $this->game->setGameStateValue("pending_workshop_slot", 1);
        $this->game->mockPlayerResources["sail"] = 0;

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actActivateShipUpgrade("xebec_lateen_rigging");

        $this->assertEmpty($this->game->activatedUpgrades);
    }

    public function testActivateShipUpgradePaysCostAndActivates(): void
    {
        $this->game->setGameStateValue("pending_workshop_player", 1);
        $this->game->setGameStateValue("pending_workshop_slot", 1);

        $transition = $this->game->actActivateShipUpgrade("xebec_lateen_rigging");

        $this->assertSame("islandTurnDone", $transition);
        $this->assertSame([["player_id" => "1", "upgrade_key" => "xebec_lateen_rigging"]], $this->game->activatedUpgrades);
        $this->assertSame(0, $this->game->mockPlayerResources["sail"]); // paid 2 sail cost
        $this->assertSame(0, $this->game->mockPlayerResources["skiff"]); // skiff consumed
        $this->assertCount(1, $this->game->occupiedSlots);
        $this->assertSame("workshop", $this->game->occupiedSlots[0]["slot_name"]);
        $this->assertSame(0, $this->game->getGameStateValue("pending_workshop_player"));
        $this->assertSame(0, $this->game->getGameStateValue("pending_workshop_slot"));
        $this->assertSame("shipUpgradeActivated", $this->game->debugLastNotif["type"]);
    }

    public function testAwardShipUpgradeEndgameInfamySumsActivatedUpgrades(): void
    {
        $this->game->mockPlayerShipUpgrades["1"] = [
            ["upgrade_key" => "xebec_lateen_rigging", "is_activated" => 1], // infamy 3
            ["upgrade_key" => "xebec_swift_hull", "is_activated" => 0],
        ];
        $this->game->mockPlayerShipUpgrades["2"] = [
            ["upgrade_key" => "war_junk_rockets", "is_activated" => 1], // no infamy defined
        ];

        $this->game->awardShipUpgradeEndgameInfamy();

        $this->assertSame([["player_id" => "1", "amount" => 3]], $this->game->infamyAwards);
    }
}
