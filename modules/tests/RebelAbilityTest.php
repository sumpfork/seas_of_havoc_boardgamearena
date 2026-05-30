<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

class RebelActionUT extends SeasOfHavocUT {
    public array $mockCaptains = [];
    public array $drawCalls = [];
    public array $discardCalls = [];
    public ?string $mockFirstPlayerTokenOwner = "2";

    public function loadPlayersBasicInfos() {
        return $this->players;
    }

    public function getPlayerCaptain($player_id) {
        return $this->mockCaptains[$player_id] ?? null;
    }

    public function drawCards(string $player_id, int $num_cards = 1) {
        $this->drawCalls[] = [
            "player_id" => $player_id,
            "num_cards" => $num_cards,
        ];
    }

    public function notifyAllPlayers(string $notificationType, string $notificationLog, array $notificationArgs): void {}

    public function getPlayerNameById(int $player_id): string {
        return $this->players[$player_id]["player_name"] ?? "Player $player_id";
    }

    public function discardCards(string $player_id, array $card_ids) {
        $this->discardCalls[] = [
            "player_id" => $player_id,
            "card_ids" => $card_ids,
        ];
    }

    public function getFirstPlayerTokenOwner() {
        return $this->mockFirstPlayerTokenOwner;
    }
}

final class RebelAbilityTest extends TestCase {
    private RebelActionUT $game;

    protected function setUp(): void {
        $this->game = new RebelActionUT();
        $this->game->mockCaptains = [
            1 => "rebel",
            2 => "merchant",
        ];
    }

    public function testGetRebelPlayerIdReturnsRebelCaptain(): void {
        $this->assertSame("1", $this->game->getRebelPlayerId());
    }

    public function testGetRebelPlayerIdReturnsNullWhenNoRebel(): void {
        $this->game->mockCaptains = [
            1 => "merchant",
            2 => "admiral",
        ];

        $this->assertNull($this->game->getRebelPlayerId());
    }

    public function testRebelDrawsOneAdditionalCardAtIslandPhaseStart(): void {
        $rebel_id = $this->game->applyRebelIslandPhaseStartDraw();

        $this->assertSame("1", $rebel_id);
        $this->assertSame([["player_id" => "1", "num_cards" => 1]], $this->game->drawCalls);
    }

    public function testNonRebelCaptainDoesNotDrawAtIslandPhaseStart(): void {
        $this->game->mockCaptains = [
            1 => "merchant",
            2 => "admiral",
        ];

        $rebel_id = $this->game->applyRebelIslandPhaseStartDraw();

        $this->assertNull($rebel_id);
        $this->assertSame([], $this->game->drawCalls);
    }

    public function testActRebelDiscardCardDiscardsFromHandAndHandsOffToFirstPlayer(): void {
        $this->game->gamestate->_setStates([
            12 => [
                "id" => 12,
                "name" => "rebelDiscard",
                "type" => "activeplayer",
                "active_player" => "1",
                "transitions" => ["cardDiscarded" => 3],
            ],
            3 => [
                "id" => 3,
                "name" => "islandTurn",
                "type" => "activeplayer",
                "active_player" => "2",
                "transitions" => [],
            ],
        ]);
        $this->game->gamestate->jumpToState(12);

        $this->game->actRebelDiscardCard(42);

        $this->assertSame(
            [["player_id" => "1", "card_ids" => [42]]],
            $this->game->discardCalls,
        );
        $this->assertSame("2", $this->game->gamestate->getActivePlayerId());
        $this->assertSame(3, $this->game->gamestate->state_id());
    }

    public function testNonRebelCannotUseRebelDiscardAction(): void {
        $this->game->mockCaptains = [
            1 => "merchant",
            2 => "admiral",
        ];
        $this->game->gamestate->_setStates([
            12 => [
                "id" => 12,
                "name" => "rebelDiscard",
                "type" => "activeplayer",
                "active_player" => "1",
                "transitions" => ["cardDiscarded" => 3],
            ],
        ]);
        $this->game->gamestate->jumpToState(12);

        $this->expectException(BgaUserException::class);
        $this->game->actRebelDiscardCard(42);
    }
}
