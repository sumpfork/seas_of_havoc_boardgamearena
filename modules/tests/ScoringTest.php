<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

/** Records the notification the score counter is asked to send, which the stub otherwise drops. */
class CapturingPlayerCounter extends \Bga\GameFramework\Components\Counters\StubPlayerCounter
{
    public array $messages = [];

    public function inc(
        int $playerId,
        int $inc,
        ?\Bga\GameFramework\NotificationMessage $message = new \Bga\GameFramework\NotificationMessage(),
    ): int {
        $this->messages[] = $message;
        return parent::inc($playerId, $inc, $message);
    }
}

/**
 * Infamy is the BGA player score. Every other test stubs scoreInfamy out at the seam, so this is
 * the only place the real implementation runs — it exists to keep scoring on the framework counter
 * rather than raw UPDATEs against player_score.
 */
class ScoringUT extends SeasOfHavocUT
{
    public function getPlayerNameById(int $player_id): string { return "Player$player_id"; }
    public function getActivePlayerId(): string { return "1"; }

    /** getPlayerInfamy is protected; expose it so the counter read-back can be asserted. */
    public function readPlayerInfamy(string $player_id): int { return $this->getPlayerInfamy($player_id); }
}

final class ScoringTest extends TestCase
{
    private ScoringUT $game;
    private CapturingPlayerCounter $counter;

    protected function setUp(): void
    {
        $this->game = new ScoringUT();
        $this->counter = new CapturingPlayerCounter();
        $this->game->bga->playerScore = $this->counter;
        $this->counter->initDb([1, 2]);
    }

    public function testScoringGoesThroughTheFrameworkCounter(): void
    {
        $this->game->scoreInfamy("1", 2);

        $this->assertSame(2, $this->counter->get(1));
        $this->assertSame(0, $this->counter->get(2), "other players are untouched");
    }

    public function testScoresAccumulate(): void
    {
        $this->game->scoreInfamy("1", 2);
        $this->game->scoreInfamy("1", 3);

        $this->assertSame(5, $this->counter->get(1));
    }

    public function testScoringAcceptsNegativeAmounts(): void
    {
        // Barter trades infamy back for a resource.
        $this->game->scoreInfamy("1", 5);
        $this->game->scoreInfamy("1", -3);

        $this->assertSame(2, $this->counter->get(1));
    }

    public function testGetPlayerInfamyReadsTheCounterBack(): void
    {
        $this->game->scoreInfamy("1", 4);

        $this->assertSame(4, $this->game->readPlayerInfamy("1"));
    }

    public function testTheDefaultLogLineKeepsItsIncrementArgument(): void
    {
        $this->game->scoreInfamy("1", 3);

        $message = $this->counter->messages[0];
        $this->assertStringContainsString('${score_increment}', $message->message);
        $this->assertSame(3, $message->args["score_increment"]);
        $this->assertSame("Player1", $message->args["player_name"]);
    }

    public function testACustomLogLineIsPassedThroughUnchanged(): void
    {
        // e.g. "${player_name}'s Hunt the Bounty: gains 1 infamy"
        $this->game->scoreInfamy("2", 1, 'custom ${player_name} line');

        $this->assertSame('custom ${player_name} line', $this->counter->messages[0]->message);
        $this->assertSame("Player2", $this->counter->messages[0]->args["player_name"]);
    }
}
