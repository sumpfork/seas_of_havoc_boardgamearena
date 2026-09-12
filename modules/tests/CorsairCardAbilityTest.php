<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class CorsairCardUT extends SeasOfHavocUT {
    public array $mockCaptains = [];
    public array $infamyAwards = [];
    public array $resourceGainCalls = [];
    // [player_id => [resource => amount]]
    public array $mockAllResources = [];
    public int $mockHuntBountyTarget = 0;

    public function __construct() {
        parent::__construct();
        $mockDeck = new MockCardDeck();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($this, $mockDeck);
    }

    public function getMockCards(): MockCardDeck {
        return (new ReflectionProperty(SeasOfHavoc::class, "cards"))->getValue($this);
    }

    public function getPlayerCaptain($player_id): ?string {
        return $this->mockCaptains[$player_id] ?? null;
    }

    public function getPlayerNameById(int $player_id): string {
        return "Player $player_id";
    }

    public function getGameResourcesHierarchical(?int $player_id = null): array {
        $pid = $player_id ?? (int) $this->gamestate->getActivePlayerId();
        return [$pid => $this->mockAllResources[$pid] ?? []];
    }

    public function playerGainResources($player_id, $resources): void {
        $this->resourceGainCalls[] = ["player_id" => (string) $player_id, "resources" => $resources];
        $pid = (int) $player_id;
        foreach ($resources as $res => $amount) {
            $this->mockAllResources[$pid][$res] = ($this->mockAllResources[$pid][$res] ?? 0) + $amount;
        }
    }

    public function scoreInfamy(string $player_id, int $amount, string $message = ""): void {
        $this->infamyAwards[] = ["player_id" => $player_id, "amount" => $amount];
    }

    public function loadPlayersBasicInfos(): array {
        return $this->players;
    }

    // Override getBoardingPartyTargets so tests don't need seaboard
    public array $mockBoardingPartyTargets = [];

    protected function getBoardingPartyTargets(string $player_id): array {
        return $this->mockBoardingPartyTargets;
    }

    // Override getHuntTheBountyTargets so tests don't need seaboard/DB
    public array $mockHuntTheBountyTargets = ["2"];

    protected function getHuntTheBountyTargets(string $player_id): array {
        return $this->mockHuntTheBountyTargets;
    }
}

final class CorsairCardAbilityTest extends TestCase {
    private CorsairCardUT $game;

    protected function setUp(): void {
        $this->game = new CorsairCardUT();
        $this->game->mockCaptains = [1 => "corsair", 2 => "pirate_queen"];
    }

    // --- Boarding Party ---

    public function testProcessBoardingPartyReturnsStateWhenTargetsExist(): void {
        $this->game->mockBoardingPartyTargets = [
            ["player_id" => "2", "resources" => ["sail" => 1], "booty_token_count" => 0],
        ];

        $result = $this->game->processBoardingParty("1");

        $this->assertSame(STATE_BOARDING_PARTY, $result);
    }

    public function testProcessBoardingPartyNoOpWhenNoTargets(): void {
        $this->game->mockBoardingPartyTargets = [];

        $result = $this->game->processBoardingParty("1");

        $this->assertIsArray($result);
        $this->assertEmpty($result["action_chain"]);
    }

    public function testActBoardingPartyStealResource(): void {
        $this->game->mockBoardingPartyTargets = [
            ["player_id" => "2", "resources" => ["sail" => 2], "booty_token_count" => 0],
        ];
        $this->game->mockAllResources = [2 => ["sail" => 2]];

        $result = $this->game->actBoardingPartySteal("2", "sail");

        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
        // Target loses 1 sail, self gains 1 sail
        $this->assertSame([["player_id" => "2", "resources" => ["sail" => -1]]], array_slice($this->game->resourceGainCalls, 0, 1));
        $this->assertSame([["player_id" => "1", "resources" => ["sail" => 1]]], array_slice($this->game->resourceGainCalls, 1, 1));
        // Gained 1 infamy
        $this->assertSame([["player_id" => "1", "amount" => 1]], $this->game->infamyAwards);
    }

    public function testActBoardingPartyStealBootyToken(): void {
        $this->game->mockBoardingPartyTargets = [
            ["player_id" => "2", "resources" => [], "booty_token_count" => 1],
        ];
        $this->game->getMockCards()->locations["booty_player_2"] = [
            ["id" => 55, "location" => "booty_player", "location_arg" => "2"],
        ];

        $result = $this->game->actBoardingPartySteal("2", "booty_token");

        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
        $this->assertSame([["card_id" => 55, "location" => "booty_player"]], $this->game->getMockCards()->moveCardCalls);
        $this->assertSame([["player_id" => "1", "amount" => 1]], $this->game->infamyAwards);
    }

    public function testActBoardingPartyStealRejectsInvalidTarget(): void {
        $this->game->mockBoardingPartyTargets = [
            ["player_id" => "2", "resources" => ["sail" => 1], "booty_token_count" => 0],
        ];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actBoardingPartySteal("3", "sail"); // player 3 not in targets
    }

    public function testActBoardingPartyStealRejectsNoCorsair(): void {
        $this->game->mockCaptains[1] = "merchant";
        $this->game->mockBoardingPartyTargets = [
            ["player_id" => "2", "resources" => ["sail" => 1], "booty_token_count" => 0],
        ];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actBoardingPartySteal("2", "sail");
    }

    public function testActBoardingPartyStealRejectsTargetWithoutResource(): void {
        $this->game->mockBoardingPartyTargets = [
            ["player_id" => "2", "resources" => ["sail" => 0], "booty_token_count" => 0],
        ];
        $this->game->mockAllResources = [2 => ["sail" => 0]];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actBoardingPartySteal("2", "sail");
    }

    public function testActBoardingPartyStealRejectsSkiff(): void {
        $this->game->mockBoardingPartyTargets = [
            ["player_id" => "2", "resources" => ["sail" => 1], "booty_token_count" => 0],
        ];
        $this->game->mockAllResources = [2 => ["skiff" => 1]];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actBoardingPartySteal("2", "skiff");
    }

    public function testActBoardingPartyStealRejectsEmptyBootyTokens(): void {
        $this->game->mockBoardingPartyTargets = [
            ["player_id" => "2", "resources" => [], "booty_token_count" => 0],
        ];
        $this->game->getMockCards()->locations["booty_player_2"] = [];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actBoardingPartySteal("2", "booty_token");
    }

    public function testActSkipBoardingParty(): void {
        $result = $this->game->actSkipBoardingParty();
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }

    // --- Hunt the Bounty ---

    public function testProcessHuntTheBountyReturnsState(): void {
        $result = $this->game->processHuntTheBounty("1");
        $this->assertSame(STATE_HUNT_THE_BOUNTY, $result);
    }

    public function testActHuntTheBountyChooseTarget(): void {
        $result = $this->game->actHuntTheBountyChooseTarget("2");

        $this->assertSame(STATE_SEA_TURN, $result);
        // Verify stored target is retrievable
        $this->assertSame(2, (int) $this->game->getGameStateValue("hunt_the_bounty_target"));
    }

    public function testActHuntTheBountyChooseTargetRejectsNonCorsair(): void {
        $this->game->mockCaptains[1] = "merchant";

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actHuntTheBountyChooseTarget("2");
    }

    public function testActHuntTheBountyChooseTargetRejectsInvalidTarget(): void {
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actHuntTheBountyChooseTarget("99");
    }

    public function testActSkipHuntTheBounty(): void {
        $result = $this->game->actSkipHuntTheBounty();
        $this->assertSame(STATE_SEA_TURN, $result);
    }
}
