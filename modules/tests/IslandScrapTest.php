<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/TradingPostTest.php";

final class IslandScrapTest extends TestCase
{
    public function testDeepCoveScrapsTwoCardsAndRefundsTheirCosts(): void
    {
        $game = new TradingPostActionUT();
        $game->mockIslandSlots["deep_cove"]["n1"] = ["disabled" => false, "occupying_player_id" => null];
        $deck = new MockCardDeck();
        (new ReflectionProperty(SeasOfHavoc::class, "cards"))->setValue($game, $deck);
        $type = array_key_first(array_filter($game->playable_cards, fn($card) => !empty($card["cost"])));
        foreach ([41, 42] as $id) {
            $deck->cards[$id] = ["id" => $id, "type" => $type, "location" => "hand", "location_arg" => 1];
        }
        $this->assertSame("scrapCard", (new ReflectionMethod(SeasOfHavoc::class, "actPlaceSkiff"))->invoke($game, "deep_cove", "n1"));
        $this->assertSame(2, $game->getGameStateValue("island_scraps_remaining"));
        $this->assertSame("scrapAgain", $game->actScrapCard(41));
        $this->assertSame("cardScrapped", $game->actScrapCard(42));
        $this->assertSame([["skiff" => -1], $game->playable_cards[$type]["cost"], $game->playable_cards[$type]["cost"]], $game->resourceAdjustments);
        $this->assertCount(1, $game->occupiedSlots);
        $this->assertSame(0, $game->getGameStateValue("island_scraps_remaining"));
    }

    public function testIslandScrappingCanBeSkipped(): void
    {
        $game = new TradingPostActionUT();
        $game->setGameStateValue("island_scraps_remaining", 2);
        $this->assertSame("cardScrapped", $game->actSkipIslandScrap());
        $this->assertSame(0, $game->getGameStateValue("island_scraps_remaining"));
    }
}
