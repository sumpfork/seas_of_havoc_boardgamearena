<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/ShipUpgradeEffectsTest.php";

/** End-game scoring reads infamy straight from material, so a missing value silently scores nothing. */
final class MaterialInfamyTest extends TestCase
{
    public function testEveryShipUpgradeIsWorthThreeInfamy(): void
    {
        $upgrades = array_filter((new ShipUpgradeMaterial())->non_playable_cards, fn($c) => ($c["category"] ?? null) === "ship_upgrade");
        $this->assertCount(12, $upgrades);
        foreach ($upgrades as $key => $card) {
            $this->assertSame(3, $card["infamy"] ?? null, $key);
        }
    }

    public function testEveryMarketCardHasPositiveInfamy(): void
    {
        $market = array_filter((new ShipUpgradeMaterial())->playable_cards, fn($c) => $c["category"] === "market_card");
        $this->assertNotEmpty($market);
        foreach ($market as $card) {
            $this->assertIsInt($card["infamy"] ?? null, "market card image {$card["image_id"]}");
            $this->assertGreaterThan(0, $card["infamy"], "market card image {$card["image_id"]}");
        }
    }
}
