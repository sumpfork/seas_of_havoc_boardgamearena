<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SeasOfHavocTest.php';

/** Deck stub that keeps locations on the cards, so moves are visible to getCardsInLocation. */
class BootyCapacityDeck
{
    public array $cards = [];

    public function add(int $id, int $typeArg, string $location, int $arg = 0): void
    {
        $this->cards[$id] = ["id" => $id, "type" => "booty", "type_arg" => $typeArg,
            "location" => $location, "location_arg" => $arg];
    }

    public function getCardsInLocation(string $location, $location_arg = null, ?string $order_by = null): array
    {
        return array_filter($this->cards, fn($c) => $c["location"] === $location &&
            ($location_arg === null || (int) $c["location_arg"] === (int) $location_arg));
    }

    public function countCardInLocation(string $location, $location_arg = null): int
    {
        return count($this->getCardsInLocation($location, $location_arg));
    }

    public function moveCard(int $card_id, string $location, $location_arg = null): void
    {
        $this->cards[$card_id]["location"] = $location;
        if ($location_arg !== null) {
            $this->cards[$card_id]["location_arg"] = $location_arg;
        }
    }

    public function pickCardForLocation(string $from, string $to, $location_arg = 0): ?array
    {
        $cards = $this->getCardsInLocation($from);
        if (empty($cards)) {
            return null;
        }
        $card = reset($cards);
        $this->moveCard((int) $card["id"], $to, $location_arg);
        return $this->cards[(int) $card["id"]];
    }

    public function shuffle(string $location): void {}
}

class BootyCapacityUT extends SeasOfHavocUT
{
    public bool $treasureHold = false;
    public BootyCapacityDeck $deck;

    public function __construct()
    {
        parent::__construct();
        $this->deck = new BootyCapacityDeck();
        (new ReflectionProperty(SeasOfHavoc::class, 'cards'))->setValue($this, $this->deck);
        (new ReflectionProperty(SeasOfHavoc::class, 'booty_tokens'))->setValue($this, $this->booty_tokens);
    }

    public function getActivePlayerId(): string { return '1'; }
    public function getPlayerNameById(int $player_id): string { return 'TestPlayer'; }
    public function hasShipUpgrade($player_id, string $upgrade_key): bool
    {
        return $upgrade_key === 'galleon_treasure_hold' && $this->treasureHold;
    }

    public function callOverflowState(int $returnState): ?int
    {
        return (new ReflectionMethod(SeasOfHavoc::class, 'bootyOverflowState'))->invoke($this, $returnState);
    }

    public function callDrawBootyToken(int $player_id, string $location = 'booty_player'): ?array
    {
        return (new ReflectionMethod(SeasOfHavoc::class, 'drawBootyToken'))->invoke($this, $player_id, $location);
    }
}

final class BootyCapacityTest extends TestCase
{
    private BootyCapacityUT $game;

    protected function setUp(): void
    {
        $this->game = new BootyCapacityUT();
    }

    /** Token image 0 is sail x3, image 1 is a single resource - values differ, so order is testable. */
    private function fillHold(int ...$typeArgs): void
    {
        foreach ($typeArgs as $i => $typeArg) {
            $this->game->deck->add(100 + $i, $typeArg, 'booty_player', 1);
        }
    }

    public function testHoldWithinCapacityAsksNothing(): void
    {
        $this->fillHold(0);
        $this->assertNull($this->game->callOverflowState(STATE_NEXT_PLAYER_SEA_PHASE));
    }

    public function testOverflowingHoldAsksThePlayerWhichToDrop(): void
    {
        $this->fillHold(0, 1);
        $this->assertSame(STATE_BOOTY_DISCARD, $this->game->callOverflowState(STATE_NEXT_PLAYER_SEA_PHASE));
        $this->assertSame(STATE_NEXT_PLAYER_SEA_PHASE, $this->game->getGameStateValue('booty_discard_return_state'));
    }

    public function testTreasureHoldCarriesTwo(): void
    {
        $this->fillHold(0, 1);
        $this->game->treasureHold = true;
        $this->assertNull($this->game->callOverflowState(STATE_NEXT_PLAYER_SEA_PHASE));
        $this->game->deck->add(102, 2, 'booty_player', 1);
        $this->assertSame(STATE_BOOTY_DISCARD, $this->game->callOverflowState(STATE_NEXT_PLAYER_SEA_PHASE));
    }

    public function testDiscardingTheChosenTokenResumesTheInterruptedTurn(): void
    {
        $this->fillHold(0, 1);
        $this->game->callOverflowState(STATE_NEXT_PLAYER_ISLAND_PHASE);
        $resume = $this->game->actDiscardBootyToken(100);
        $this->assertSame(STATE_NEXT_PLAYER_ISLAND_PHASE, $resume, 'the turn continues where it was interrupted');
        $this->assertSame('booty_discard', $this->game->deck->cards[100]['location']);
        $this->assertSame('booty_player', $this->game->deck->cards[101]['location'], 'the player keeps their pick');
    }

    public function testCannotDiscardATokenThePlayerDoesNotHold(): void
    {
        $this->fillHold(0, 1);
        $this->game->deck->add(200, 3, 'booty_player', 2);
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->actDiscardBootyToken(200);
    }

    /** Unearth Riches only reveals a token, so drawing for it must leave the hold alone. */
    public function testRevealingATokenDoesNotTouchTheHold(): void
    {
        $this->fillHold(0);
        $this->game->deck->add(300, 4, 'booty_deck');
        $drawn = $this->game->callDrawBootyToken(1, 'captain_reward');
        $this->assertSame(300, (int) $drawn['id']);
        $this->assertSame('captain_reward', $this->game->deck->cards[300]['location']);
        $this->assertSame('booty_player', $this->game->deck->cards[100]['location']);
        $this->assertNull($this->game->callOverflowState(STATE_NEXT_PLAYER_SEA_PHASE));
    }
}
