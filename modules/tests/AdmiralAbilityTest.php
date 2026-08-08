<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class AdmiralActionUT extends SeasOfHavocUT {
    public array $mockCaptains = [];
    public array $drawCalls = [];
    public array $infamyAwards = [];
    public array $logNotifications = [];

    public function getPlayerCaptain($player_id) {
        return $this->mockCaptains[$player_id] ?? null;
    }

    public function drawCards(string $player_id, int $num_cards = 1) {
        $this->drawCalls[] = [
            "player_id" => $player_id,
            "num_cards" => $num_cards,
        ];
    }

    public function scoreInfamy(string $player_id, int $amount, string $message = "") {
        $this->infamyAwards[] = [
            "player_id" => $player_id,
            "amount" => $amount,
            "message" => $message,
        ];
    }

    public function notifyAllPlayers(string $notificationType, string $notificationLog, array $notificationArgs): void {
        if ($notificationType === "log") {
            $this->logNotifications[] = [
                "message" => $notificationLog,
                "args" => $notificationArgs,
            ];
        }
    }

    public function getPlayerNameById(int $player_id): string {
        return $this->players[$player_id]["player_name"] ?? "Player $player_id";
    }
}

final class AdmiralAbilityTest extends TestCase {
    private AdmiralActionUT $game;

    protected function setUp(): void {
        $this->game = new AdmiralActionUT();
        $this->game->mockCaptains = [
            1 => "admiral",
            2 => "merchant",
        ];
    }

    public function testAdmiralDrawsCardWhenTakingFirstPlayerTokenFromAnotherPlayer(): void {
        $this->game->applyAdmiralTokenTakenAbilities("1", "first_player_token", true);

        $this->assertSame([["player_id" => "1", "num_cards" => 1]], $this->game->drawCalls);
        $this->assertSame([], $this->game->infamyAwards);
        $this->assertCount(1, $this->game->logNotifications);
    }

    public function testAdmiralGainsInfamyWhenTakingFlagFromAnotherPlayer(): void {
        $this->game->applyAdmiralTokenTakenAbilities("1", "green_flag", true);

        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame(
            [["player_id" => "1", "amount" => 1]],
            array_map(
                fn($award) => ["player_id" => $award["player_id"], "amount" => $award["amount"]],
                $this->game->infamyAwards,
            ),
        );
    }

    public function testAdmiralDoesNotGainRewardsWhenClaimingUnownedToken(): void {
        $this->game->applyAdmiralTokenTakenAbilities("1", "green_flag", false);
        $this->game->applyAdmiralTokenTakenAbilities("1", "first_player_token", false);

        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame([], $this->game->infamyAwards);
        $this->assertSame([], $this->game->logNotifications);
    }

    public function testNonAdmiralDoesNotGainRewardsWhenTakingTokenFromAnotherPlayer(): void {
        $this->game->mockCaptains = [
            1 => "merchant",
            2 => "admiral",
        ];

        $this->game->applyAdmiralTokenTakenAbilities("1", "first_player_token", true);
        $this->game->applyAdmiralTokenTakenAbilities("1", "tan_flag", true);

        $this->assertSame([], $this->game->drawCalls);
        $this->assertSame([], $this->game->infamyAwards);
        $this->assertSame([], $this->game->logNotifications);
    }
}
