<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class PirateQueenActionUT extends SeasOfHavocUT {
    public array $mockUniqueTokens = [];
    public array $mockCaptains = [];
    public array $infamyAwards = [];

    public function loadPlayersBasicInfos() {
        return $this->players;
    }

    public function getUniqueTokens() {
        return $this->mockUniqueTokens;
    }

    public function getPlayerCaptain($player_id) {
        return $this->mockCaptains[$player_id] ?? null;
    }

    public function scoreInfamy(string $player_id, int $amount, string $message = "") {
        $this->infamyAwards[] = [
            "player_id" => $player_id,
            "amount" => $amount,
            "message" => $message,
        ];
    }
}

final class PirateQueenAbilityTest extends TestCase {
    private PirateQueenActionUT $game;

    protected function setUp(): void {
        $this->game = new PirateQueenActionUT();
        $this->game->mockCaptains = [
            1 => "pirate_queen",
            2 => "merchant",
        ];
    }

    public function testPirateQueenGainsInfamyWhenControllingMostFlags(): void {
        $this->game->mockUniqueTokens = [
            "green_flag" => "1",
            "tan_flag" => "1",
            "blue_flag" => "2",
            "red_flag" => null,
        ];

        $this->game->applyPirateQueenIslandPhaseStartAbilities();

        $this->assertSame(
            [["player_id" => "1", "amount" => 2]],
            array_map(
                fn($award) => ["player_id" => $award["player_id"], "amount" => $award["amount"]],
                $this->game->infamyAwards,
            ),
        );
    }

    public function testPirateQueenDoesNotGainInfamyWhenTiedForMostFlags(): void {
        $this->game->mockUniqueTokens = [
            "green_flag" => "1",
            "tan_flag" => "2",
            "blue_flag" => "2",
            "red_flag" => null,
        ];

        $this->game->applyPirateQueenIslandPhaseStartAbilities();

        $this->assertSame([], $this->game->infamyAwards);
    }

    public function testPirateQueenDoesNotGainInfamyWhenAnotherPlayerHasMoreFlags(): void {
        $this->game->mockUniqueTokens = [
            "green_flag" => "1",
            "tan_flag" => "2",
            "blue_flag" => "2",
            "red_flag" => "2",
        ];

        $this->game->applyPirateQueenIslandPhaseStartAbilities();

        $this->assertSame([], $this->game->infamyAwards);
    }

    public function testPirateQueenDoesNotGainInfamyWithZeroFlags(): void {
        $this->game->mockUniqueTokens = [
            "green_flag" => null,
            "tan_flag" => null,
            "blue_flag" => null,
            "red_flag" => null,
        ];

        $this->game->applyPirateQueenIslandPhaseStartAbilities();

        $this->assertSame([], $this->game->infamyAwards);
    }

    public function testNonPirateQueenDoesNotGainInfamyEvenWithMostFlags(): void {
        $this->game->mockCaptains = [
            1 => "merchant",
            2 => "admiral",
        ];
        $this->game->mockUniqueTokens = [
            "green_flag" => "1",
            "tan_flag" => "1",
            "blue_flag" => "2",
            "red_flag" => null,
        ];

        $this->game->applyPirateQueenIslandPhaseStartAbilities();

        $this->assertSame([], $this->game->infamyAwards);
    }
}
