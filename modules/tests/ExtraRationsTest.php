<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

/** Island Phase turn order with the DB stubbed out; player 1 holds an unspent Extra Rations. */
class ExtraRationsUT extends SeasOfHavocUT
{
    public array $skiffs = [1 => 0, 2 => 0];
    public bool $rationsAvailable = true;

    public function __construct()
    {
        parent::__construct();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($this, new MockCardDeck());
    }
    function getGameResourcesHierarchical(?int $player_id = null)
    {
        return array_map(fn($n) => ["skiff" => $n], $this->skiffs);
    }
    function canUseExtraRations($player_id): bool { return $this->rationsAvailable && $player_id == 1; }
    function hasExtraTurn(string $player_id, string $phase) { return false; }
    function clearExtraTurns(string $phase) {}
    function getActiveShipUpgrades($player_id): array { return []; }
}

final class ExtraRationsTest extends TestCase
{
    public function testOfferedAtTheEndOfTheOwnersTurn(): void
    {
        $game = new ExtraRationsUT();
        $game->skiffs[2] = 1;
        $this->assertSame(STATE_EXTRA_RATIONS, $game->stNextPlayerIslandPhase());
        $this->assertSame("nextPlayer", $game->stNextPlayerIslandPhase(), "skipping moves play on");
        $this->assertEquals(2, $game->getActivePlayerId());
        $this->assertSame("nextPlayer", $game->stNextPlayerIslandPhase(), "not offered after another player's turn");
    }

    public function testOfferedToTheOwnerAtTheEndOfThePhase(): void
    {
        $game = new ExtraRationsUT();
        $game->gamestate->changeActivePlayer(2);
        $this->assertSame(STATE_EXTRA_RATIONS, $game->stNextPlayerIslandPhase());
        $this->assertEquals(1, $game->getActivePlayerId());
        $this->assertSame("islandPhaseDone", $game->stNextPlayerIslandPhase());
        $this->assertSame(0, $game->getGameStateValue("extra_rations_offered"));
    }

    public function testOfferedOnceWhenTheOwnersTurnEndsThePhase(): void
    {
        $game = new ExtraRationsUT();
        $this->assertSame(STATE_EXTRA_RATIONS, $game->stNextPlayerIslandPhase());
        $this->assertSame("islandPhaseDone", $game->stNextPlayerIslandPhase());
    }

    public function testNotOfferedOnceUsed(): void
    {
        $game = new ExtraRationsUT();
        $game->rationsAvailable = false;
        $this->assertSame("islandPhaseDone", $game->stNextPlayerIslandPhase());
    }
}
