<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class PirateQueenCardUT extends SeasOfHavocUT {
    public array $mockUniqueTokens = [];
    public array $mockCaptains = [];
    public array $infamyAwards = [];
    public array $drawCalls = [];
    public array $extraTurns = [];
    public array $acquireCalls = [];
    public array $resourceGainCalls = [];
    public array $showResourceCalls = [];
    public ?array $mockAvailableFlags = null;

    public function __construct() {
        parent::__construct();
        $mockDeck = new MockCardDeck();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($this, $mockDeck);
    }

    public function getMockCards(): MockCardDeck {
        return (new ReflectionProperty(SeasOfHavoc::class, "cards"))->getValue($this);
    }

    public function loadPlayersBasicInfos(): array { return $this->players; }

    public function getUniqueTokens(): array { return $this->mockUniqueTokens; }

    public function getPlayerCaptain($player_id): ?string {
        return $this->mockCaptains[$player_id] ?? null;
    }

    public function getPlayerNameById(int $player_id): string {
        return "Player $player_id";
    }

    protected function getRallyTheFlagsOptions(string $player_id): array {
        if ($this->mockAvailableFlags !== null) {
            return $this->mockAvailableFlags;
        }
        $flag_keys = ["green_flag", "tan_flag", "blue_flag", "red_flag"];
        $available = [];
        foreach ($flag_keys as $fk) {
            if (!isset($this->mockUniqueTokens[$fk]) || $this->mockUniqueTokens[$fk] === null) {
                $available[] = $fk;
            }
        }
        return $available;
    }

    public function scoreInfamy(string $player_id, int $amount, string $message = ""): void {
        $this->infamyAwards[] = ["player_id" => $player_id, "amount" => $amount];
    }

    public function drawCards(string $player_id, int $num_cards = 1): void {
        $this->drawCalls[] = ["player_id" => $player_id, "num_cards" => $num_cards];
    }

    public function grantExtraTurn(string $player_id, string $phase): void {
        $this->extraTurns[] = ["player_id" => $player_id, "phase" => $phase];
    }

    public function acquireToken(string $player_id, string $token_key): void {
        $this->acquireCalls[] = ["player_id" => $player_id, "token_key" => $token_key];
        $this->mockUniqueTokens[$token_key] = $player_id;
    }

    public function playerGainResources($player_id, $resources): void {
        $this->resourceGainCalls[] = $resources;
    }

    public function showResourceChoiceDialog(string $context, string $context_number): void {
        $this->showResourceCalls[] = ["context" => $context, "number" => $context_number];
    }
}

final class PirateQueenCardAbilityTest extends TestCase {
    private PirateQueenCardUT $game;

    protected function setUp(): void {
        $this->game = new PirateQueenCardUT();
        $this->game->mockCaptains = [
            1 => "pirate_queen",
            2 => "merchant",
        ];
    }

    // --- Rally the Flags ---

    public function testRallyTheFlagsReturnsStateWhenFlagsAvailable(): void {
        $this->game->mockUniqueTokens = [
            "green_flag" => null,
            "tan_flag" => "2",
            "blue_flag" => "2",
            "red_flag" => "2",
        ];

        $result = $this->game->processRallyTheFlags("1");

        $this->assertSame(STATE_RALLY_THE_FLAGS, $result);
    }

    public function testRallyTheFlagsNoOpWhenAllFlagsOwned(): void {
        $this->game->mockUniqueTokens = [
            "green_flag" => "2",
            "tan_flag" => "2",
            "blue_flag" => "2",
            "red_flag" => "2",
        ];

        $result = $this->game->processRallyTheFlags("1");

        $this->assertIsArray($result);
    }

    public function testArgRallyTheFlagsReturnsUnownedFlags(): void {
        $this->game->mockUniqueTokens = [
            "green_flag" => null,
            "tan_flag" => null,
            "blue_flag" => "2",
            "red_flag" => "2",
        ];

        $args = $this->game->argRallyTheFlagsChooseFlag();

        $this->assertSame(["green_flag", "tan_flag"], $args["available_flags"]);
    }

    public function testActRallyTheFlagsChooseFlagAcquiresFlag(): void {
        $this->game->mockAvailableFlags = ["green_flag"];

        $result = $this->game->actRallyTheFlagsChooseFlag("green_flag");

        $this->assertSame([["player_id" => "1", "token_key" => "green_flag"]], $this->game->acquireCalls);
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }

    public function testActRallyTheFlagsChooseFlagGainsInfamyWhenLeading(): void {
        $this->game->mockAvailableFlags = ["green_flag"];
        $this->game->mockUniqueTokens = [
            "green_flag" => null,
            "tan_flag" => "1",  // player 1 already has 1 flag
            "blue_flag" => null,
            "red_flag" => null,
        ];

        $this->game->actRallyTheFlagsChooseFlag("green_flag");

        $this->assertSame([["player_id" => "1", "amount" => 1]], $this->game->infamyAwards);
    }

    public function testActRallyTheFlagsChooseFlagNoInfamyWhenTied(): void {
        $this->game->mockAvailableFlags = ["green_flag"];
        $this->game->mockUniqueTokens = [
            "green_flag" => null,
            "tan_flag" => "2",  // player 2 has 1 flag; after acquire, both have 1
            "blue_flag" => null,
            "red_flag" => null,
        ];

        $this->game->actRallyTheFlagsChooseFlag("green_flag");

        $this->assertSame([], $this->game->infamyAwards);
    }

    public function testActRallyTheFlagsChooseFlagRejectsInvalidFlag(): void {
        $this->game->mockAvailableFlags = ["green_flag"];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actRallyTheFlagsChooseFlag("tan_flag");
    }

    public function testActRallyTheFlagsChooseFlagRejectsNonPirateQueen(): void {
        $this->game->mockCaptains[1] = "merchant";
        $this->game->mockAvailableFlags = ["green_flag"];

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actRallyTheFlagsChooseFlag("green_flag");
    }

    // --- Extortion ---

    public function testExtortionTanFlagDrawsCard(): void {
        $this->game->mockUniqueTokens = ["tan_flag" => "1"];

        $this->game->processExtortion("1");

        $this->assertSame([["player_id" => "1", "num_cards" => 1]], $this->game->drawCalls);
    }

    public function testExtortionBlueFlagGrantsExtraTurn(): void {
        $this->game->mockUniqueTokens = ["blue_flag" => "1"];

        $this->game->processExtortion("1");

        $this->assertSame([["player_id" => "1", "phase" => "island"]], $this->game->extraTurns);
    }

    public function testExtortionGreenFlagReturnsPendingState(): void {
        $this->game->mockUniqueTokens = ["green_flag" => "1"];

        $result = $this->game->processExtortion("1");

        $this->assertSame(STATE_EXTORTION, $result);
        $this->assertSame(
            [],
            $this->game->showResourceCalls,
        );
    }

    public function testExtortionRedFlagReturnsPendingState(): void {
        $this->game->mockUniqueTokens = ["red_flag" => "1"];

        $result = $this->game->processExtortion("1");

        $this->assertSame(STATE_EXTORTION, $result);
        $this->assertSame([], $this->game->showResourceCalls); // green dialog only shown for green flag
    }

    public function testExtortionNoInteractiveFlagsReturnsNextState(): void {
        $this->game->mockUniqueTokens = [];

        $result = $this->game->processExtortion("1");

        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame([], $this->game->extraTurns);
    }

    public function testExtortionTanAndGreenBothApply(): void {
        $this->game->mockUniqueTokens = ["tan_flag" => "1", "green_flag" => "1"];

        $result = $this->game->processExtortion("1");

        $this->assertSame([["player_id" => "1", "num_cards" => 1]], $this->game->drawCalls);
        $this->assertSame(STATE_EXTORTION, $result);
    }

    public function testExtortionDoesNotApplyFlagsOwnedByOtherPlayers(): void {
        $this->game->mockUniqueTokens = [
            "tan_flag" => "2",
            "blue_flag" => "2",
            "green_flag" => "2",
            "red_flag" => "2",
        ];

        $result = $this->game->processExtortion("1");

        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame([], $this->game->extraTurns);
    }

    public function testActExtortionScrapCardScrapsCard(): void {
        $this->game->setGameStateValue("extortion_pending_flags", 2); // EXTORTION_RED_FLAG
        $this->game->getMockCards()->cards[42] = [
            "id" => 42, "type" => 1, "location" => "hand", "location_arg" => "1",
        ];

        $result = $this->game->actExtortionScrapCard(42);

        $this->assertSame([["card_id" => 42, "location" => "scrap"]], $this->game->getMockCards()->moveCardCalls);
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
        $this->assertSame(0, $this->game->getGameStateValue("extortion_pending_flags"));
    }

    public function testActExtortionScrapCardRejectsWhenNoPending(): void {
        $this->game->setGameStateValue("extortion_pending_flags", 0);

        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actExtortionScrapCard(42);
    }

    public function testExtortionGreenResourcePickedClearsAndAdvances(): void {
        $this->game->setGameStateValue("extortion_pending_flags", 1); // EXTORTION_GREEN_FLAG

        $result = $this->game->actResourcePickedInDialog("sail", "extortion_green_flag", "0");

        $this->assertSame([["sail" => 1]], $this->game->resourceGainCalls);
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $result);
    }

    public function testExtortionGreenResourcePickedStaysWhenRedPending(): void {
        $this->game->setGameStateValue("extortion_pending_flags", 3); // GREEN=1 | RED=2

        $result = $this->game->actResourcePickedInDialog("cannonball", "extortion_green_flag", "0");

        $this->assertSame([["cannonball" => 1]], $this->game->resourceGainCalls);
        $this->assertSame(STATE_EXTORTION, $result);
        $this->assertSame(2, $this->game->getGameStateValue("extortion_pending_flags"));
    }
}
