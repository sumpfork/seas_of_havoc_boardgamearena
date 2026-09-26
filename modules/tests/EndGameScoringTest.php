<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SeasOfHavocTest.php';

/** Deck stub that keeps each card's location on the card, so moves are visible to lookups. */
class EndGameDeck
{
    public array $cards = [];
    private int $next = 1000;

    public function add(int $id, int $type, string $location, int $arg = 0): void
    {
        $this->cards[$id] = ["id" => $id, "type" => $type, "type_arg" => 0,
            "location" => $location, "location_arg" => $arg];
    }

    public function getCard(int $card_id): ?array { return $this->cards[$card_id] ?? null; }

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

    public function createCards(array $specs, string $location, $location_arg = 0): void
    {
        foreach ($specs as $spec) {
            for ($i = 0; $i < ($spec["nbr"] ?? 1); $i++) {
                $this->add($this->next++, (int) $spec["type"], $location, (int) $location_arg);
            }
        }
    }

    public function insertCardOnExtremePosition(int $card_id, string $location, bool $bOnTop): void
    {
        $this->moveCard($card_id, $location, (int) $this->cards[$card_id]["location_arg"]);
    }

    public function getCardOnTop(string $location): ?array
    {
        $cards = $this->getCardsInLocation($location);
        return $cards ? reset($cards) : null;
    }
}

class EndGameUT extends SeasOfHavocUT
{
    public EndGameDeck $deck;
    public array $scored = [];
    public array $resources = [];
    public array $upgrades = [];

    public function __construct()
    {
        parent::__construct();
        $this->deck = new EndGameDeck();
        (new ReflectionProperty(SeasOfHavoc::class, 'cards'))->setValue($this, $this->deck);
    }

    public function scoreInfamy(string $player_id, int $amount, string $message = "")
    {
        $this->scored[] = [$player_id, $amount];
    }
    public function getGameResourcesHierarchical(?int $player_id = null)
    {
        return $this->resources;
    }
    public function getPlayerShipUpgrades($player_id): array { return $this->upgrades[$player_id] ?? []; }
    public function getPlayerNameById(int $player_id): string { return "Player$player_id"; }
    public function hasShipUpgrade($player_id, string $upgrade_key): bool { return false; }

    public function marketCardTypeWithInfamy(int $infamy): int
    {
        foreach ($this->playable_cards as $type => $card) {
            if (($card['category'] ?? '') === 'market_card' && ($card['infamy'] ?? 0) === $infamy) {
                return $type;
            }
        }
        throw new \RuntimeException("no market card worth $infamy");
    }
}

final class EndGameScoringTest extends TestCase
{
    private EndGameUT $game;

    protected function setUp(): void
    {
        $this->game = new EndGameUT();
        $this->game->resources = [1 => ["sail" => 2, "doubloon" => 1], 2 => ["sail" => 0]];
    }

    private function scoreFor(string $player_id): int
    {
        $total = 0;
        foreach ($this->game->scored as [$id, $amount]) {
            if ($id === $player_id) {
                $total += $amount;
            }
        }
        return $total;
    }

    public function testCardsInDeckHandAndDiscardAllCount(): void
    {
        $three = $this->game->marketCardTypeWithInfamy(3);
        $two = $this->game->marketCardTypeWithInfamy(2);
        $one = $this->game->marketCardTypeWithInfamy(1);
        $this->game->deck->add(1, $three, 'player_deck_1', 1);
        $this->game->deck->add(2, $two, 'hand', 1);
        $this->game->deck->add(3, $one, 'player_discard_1', 1);

        $this->assertSame(STATE_END_GAME, $this->game->stFinalScoring());
        $this->assertSame(6, $this->scoreFor('1'));
    }

    public function testDamageCardsSubtract(): void
    {
        $three = $this->game->marketCardTypeWithInfamy(3);
        $this->game->deck->add(1, $three, 'player_discard_1', 1);
        $this->game->deck->add(2, $this->game->damageCardType(), 'player_discard_1', 1);
        $this->game->deck->add(3, $this->game->damageCardType(), 'player_deck_1', 1);

        $this->game->stFinalScoring();
        $this->assertSame(1, $this->scoreFor('1'), '3 infamy less two damage cards');
    }

    public function testCardsInTheScrapPileAreGone(): void
    {
        $this->game->deck->add(1, $this->game->marketCardTypeWithInfamy(3), 'scrap');
        $this->game->stFinalScoring();
        $this->assertSame(0, $this->scoreFor('1'), 'scrapped cards left the deck and score nothing');
    }

    public function testTiebreakPrefersResourcesThenFewerDamageCards(): void
    {
        $this->game->deck->add(1, $this->game->damageCardType(), 'player_discard_1', 1);
        $this->game->stFinalScoring();
        // 3 resources, 1 damage card: resources dominate, damage breaks the remainder.
        $this->assertSame(3 * 100 + 98, $this->game->bga->playerScoreAux->get(1));
        $this->assertSame(0 * 100 + 99, $this->game->bga->playerScoreAux->get(2));
    }

    public function testSeaPhaseEndsTheGameOnlyWhenTheDamageDeckIsEmpty(): void
    {
        $game = new class extends EndGameUT {
            public function getActivePlayerId(): string { return '1'; }
            public function activeNextPlayer(): int|string { return 1; }
            public function giveExtraTime(int $playerId, ?int $specificTime = null): void {}
            public function canUseSwiftHull($player_id): bool { return false; }
        };
        $game->deck->add(1, $game->damageCardType(), 'damage_deck');
        $this->assertSame('seaPhaseDone', $game->stNextPlayerSeaPhase());

        $game->deck->moveCard(1, 'player_discard_1', 1);
        $this->assertSame(STATE_FINAL_SCORING, $game->stNextPlayerSeaPhase());
    }

    public function testTheLastSeaPhaseKeepsDealingDamageFromTheScrapPile(): void
    {
        $this->game->deck->add(7, $this->game->damageCardType(), 'scrap');
        $this->game->dealDamageCard('1');
        $this->assertSame('player_discard_1', $this->game->deck->getCard(7)['location']);
        $this->assertSame(0, $this->game->deck->countCardInLocation('damage_deck'), 'the deck stays empty');
    }

    public function testWithNothingInTheScrapPileASpareDamageCardIsMade(): void
    {
        $this->game->dealDamageCard('1');
        $discarded = $this->game->deck->getCardsInLocation('player_discard_1');
        $this->assertCount(1, $discarded);
        $this->assertSame($this->game->damageCardType(), (int) reset($discarded)['type']);
    }
}
