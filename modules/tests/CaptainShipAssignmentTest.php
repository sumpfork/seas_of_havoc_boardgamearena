<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

/** Snake draft with the DB replaced by arrays: players 1, 2, 3 in table order. */
class DraftUT extends SeasOfHavocUT
{
    public array $order = ["1", "2", "3"];
    public string $active = "2";
    public array $captains = [];
    public array $ships = [];
    public bool $finished = false;

    public function getActivePlayerId(): string { return $this->active; }
    public function activeNextPlayer(): int|string { return $this->active = $this->step(1); }
    public function activePrevPlayer(): void { $this->active = $this->step(-1); }
    private function step(int $by): string
    {
        $i = array_search($this->active, $this->order, true);
        return $this->order[($i + $by + count($this->order)) % count($this->order)];
    }
    public function getPlayersNumber(): int { return count($this->order); }
    public function giveExtraTime(int $playerId, ?int $specificTime = null): void {}
    public function getPlayerNameById(int $player_id): string { return "Player $player_id"; }
    public function assignCaptainToPlayer($player_id, $captain_key) { $this->captains[$player_id] = $captain_key; }
    protected function draftedCaptains(): array { return array_values($this->captains); }
    protected function draftedShips(): array { return array_values($this->ships); }
    protected function setDraftedShip(int $player_id, string $ship): void { $this->ships[$player_id] = $ship; }
    public function finishDraft(): int { $this->finished = true; return STATE_CHOOSE_HEADING; }
}

final class CaptainShipAssignmentTest extends TestCase
{
    public function testFirstGamePairsGiveEachShipAndCaptainOnce(): void
    {
        $this->assertEqualsCanonicalizing(SeasOfHavoc::STAT_SHIPS, array_column(SeasOfHavoc::FIRST_GAME_PAIRS, 0));
        $this->assertEqualsCanonicalizing(SeasOfHavoc::STAT_CAPTAINS, array_column(SeasOfHavoc::FIRST_GAME_PAIRS, 1));
    }

    /** First player (2) picks the first captain; the last captain picker (1) picks the first ship. */
    public function testSnakeDraftOrder(): void
    {
        $game = new DraftUT();
        $picks = [];
        foreach (["corsair", "rebel", "admiral"] as $captain) {
            $picks[] = $game->active;
            $state = $game->actDraftCaptain($captain);
        }
        $this->assertSame(STATE_DRAFT_SHIP, $state);
        $this->assertEquals(["1" => "admiral", "2" => "corsair", "3" => "rebel"], $game->captains);
        $this->assertSame(["pirate_queen", "merchant", "treasure_seeker"], $game->argDraftCaptain()["captains"]);

        foreach (["Brig", "Xebec", "Galleon"] as $ship) {
            $picks[] = $game->active;
            $state = $game->actDraftShip($ship);
        }
        $this->assertSame(["2", "3", "1", "1", "3", "2"], $picks);
        $this->assertSame(STATE_CHOOSE_HEADING, $state);
        $this->assertTrue($game->finished);
        $this->assertSame(["Ship-of-the-Line", "Sloop of War", "War Junk"], $game->argDraftShip()["ships"]);
    }

    public function testCannotDraftATakenCaptain(): void
    {
        $game = new DraftUT();
        $game->actDraftCaptain("corsair");
        $this->expectException(\Bga\GameFramework\SystemException::class);
        $game->actDraftCaptain("corsair");
    }

    public function testCaptainsHaveDisplayNames(): void
    {
        $game = new SeasOfHavocUT();
        foreach (SeasOfHavoc::STAT_CAPTAINS as $captain) {
            $this->assertNotEmpty($game->non_playable_cards[$captain]["name"]);
        }
    }
}
