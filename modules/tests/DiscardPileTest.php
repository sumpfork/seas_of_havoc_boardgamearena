<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/SeasOfHavocTest.php";

/**
 * Each player's discard is an ordered pile at its own location, and their deck reforms from it
 * when it runs dry. Both facts are relied on elsewhere (Watertight Bulkheads reads the top card),
 * so they are pinned down here against the real Deck.
 */
class DiscardPileUT extends SeasOfHavocUT
{
    public Deck $deck;
    public array $notifs = [];

    public function __construct()
    {
        parent::__construct();
        $this->deck = new Deck();
        $this->deck->autoreshuffle = true;
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($this, $this->deck);
    }

    public function getPlayerNameById(int $player_id): string { return "Player$player_id"; }

    /** Create a loose card and return its id. */
    public function makeCard(int $type): int
    {
        $this->deck->createCards([["type" => $type, "type_arg" => 0, "nbr" => 1]], "limbo", 0);
        return (int) array_key_last($this->deck->getCardsInLocation("limbo"));
    }

    public function makeDeckCards(string $player_id, int $count): void
    {
        $this->deck->createCards(
            [["type" => 5, "type_arg" => 0, "nbr" => $count]],
            $this->playerDeckName($player_id),
        );
    }
}

final class DiscardPileTest extends TestCase
{
    private DiscardPileUT $game;

    protected function setUp(): void
    {
        $this->game = new DiscardPileUT();
    }

    public function testDiscardPileKeepsTheOrderCardsWereAddedIn(): void
    {
        $first = $this->game->makeCard(5);
        $second = $this->game->makeCard(6);
        $this->game->discardCardToPlayer($first, "1");
        $this->game->discardCardToPlayer($second, "1");

        $this->assertSame($second, (int) $this->game->deck->getCardOnTop("player_discard_1")["id"]);
        $this->assertSame(
            [$first, $second],
            array_map(fn($c) => (int) $c["id"], $this->game->getPlayerDiscard("1")),
        );
    }

    public function testEachPlayerHasTheirOwnPile(): void
    {
        $mine = $this->game->makeCard(5);
        $theirs = $this->game->makeCard(5);
        $this->game->discardCardToPlayer($mine, "1");
        $this->game->discardCardToPlayer($theirs, "2");

        $this->assertSame([$mine], array_map(fn($c) => (int) $c["id"], $this->game->getPlayerDiscard("1")));
        $this->assertSame([$theirs], array_map(fn($c) => (int) $c["id"], $this->game->getPlayerDiscard("2")));
    }

    public function testDrawingPastTheEndOfTheDeckReshufflesTheDiscardIn(): void
    {
        $this->game->makeDeckCards("1", 1);
        foreach ([$this->game->makeCard(5), $this->game->makeCard(6)] as $card_id) {
            $this->game->discardCardToPlayer($card_id, "1");
        }

        $this->game->drawCards("1", 3);

        $this->assertSame(3, $this->game->deck->countCardInLocation("hand", 1));
        $this->assertSame(0, $this->game->deck->countCardInLocation("player_discard_1"));
        $this->assertSame(0, $this->game->deck->countCardInLocation("player_deck_1"));
    }

    public function testReshufflingOnlyPullsInThatPlayersOwnDiscard(): void
    {
        $this->game->makeDeckCards("1", 1);
        $this->game->discardCardToPlayer($this->game->makeCard(5), "1");
        $theirs = $this->game->makeCard(6);
        $this->game->discardCardToPlayer($theirs, "2");

        $this->game->drawCards("1", 2);

        $this->assertSame(2, $this->game->deck->countCardInLocation("hand", 1));
        $this->assertSame("player_discard_2", $this->game->deck->getCard($theirs)["location"]);
    }

    public function testTheReshuffleIsAnnouncedToTheDrawingPlayer(): void
    {
        $this->game->makeDeckCards("1", 1);
        $this->game->discardCardToPlayer($this->game->makeCard(5), "1");

        $this->game->drawCards("1", 2);

        // cardDrawn is the last notification; the reshuffle is announced before it.
        $this->assertSame("cardDrawn", $this->game->debugLastNotif["type"]);
        $this->assertSame(2, $this->game->debugLastNotif["num_cards"]);
    }

    public function testDrawingFromAnEmptyDeckAndDiscardDrawsNothing(): void
    {
        $this->game->drawCards("1", 2);

        $this->assertSame(0, $this->game->deck->countCardInLocation("hand", 1));
    }

    public function testDrawingWithinTheDeckDoesNotTouchTheDiscard(): void
    {
        $this->game->makeDeckCards("1", 3);
        $kept = $this->game->makeCard(5);
        $this->game->discardCardToPlayer($kept, "1");

        $this->game->drawCards("1", 2);

        $this->assertSame(2, $this->game->deck->countCardInLocation("hand", 1));
        $this->assertSame([$kept], array_map(fn($c) => (int) $c["id"], $this->game->getPlayerDiscard("1")));
    }
}
