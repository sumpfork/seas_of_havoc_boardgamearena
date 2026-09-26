<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SeasOfHavocTest.php';

/** Only players who put a skiff on a Market card have anything to do in the purchase phase. */
class PurchasePhaseUT extends SeasOfHavocUT
{
    public array $slots = [];

    public function getIslandSlots() { return $this->slots; }

    public function callGetMarketClaimants(): array
    {
        return (new ReflectionMethod(SeasOfHavoc::class, 'getMarketClaimants'))->invoke($this);
    }

    private function slot($occupant, $corsair = null): array
    {
        return ["occupying_player_id" => $occupant, "corsair_occupying_player_id" => $corsair, "disabled" => false];
    }

    public function withSlots(array $market, array $other = []): void
    {
        $this->slots = ["market" => array_map(fn($o) => $this->slot($o), $market)] + $other;
    }
}

final class PurchasePhaseTest extends TestCase
{
    private PurchasePhaseUT $game;

    protected function setUp(): void
    {
        $this->game = new PurchasePhaseUT();
    }

    public function testNobodyClaimedAMarketCard(): void
    {
        $this->game->withSlots(["n1" => null, "n2" => null]);
        $this->assertSame([], $this->game->callGetMarketClaimants(), 'the phase is skipped entirely');
    }

    public function testOnlyTheClaimantsAreListed(): void
    {
        $this->game->withSlots(["n1" => "2", "n2" => null, "n3" => "5"]);
        $this->assertSame([2, 5], $this->game->callGetMarketClaimants());
    }

    public function testAPlayerClaimingTwoCardsIsListedOnce(): void
    {
        $this->game->withSlots(["n1" => "3", "n2" => "3"]);
        $this->assertSame([3], $this->game->callGetMarketClaimants());
    }

    public function testSkiffsOnOtherIslandSlotsDoNotBuyCards(): void
    {
        $this->game->withSlots([], [
            "bank" => ["n1" => ["occupying_player_id" => "4", "corsair_occupying_player_id" => null, "disabled" => false]],
        ]);
        $this->assertSame([], $this->game->callGetMarketClaimants());
    }

    /** A Corsair overlay can share a slot, but never a Market one - it buys nothing. */
    public function testCorsairOverlayOnAMarketSlotDoesNotClaimTheCard(): void
    {
        $this->game->slots = ["market" => ["n1" => [
            "occupying_player_id" => "2", "corsair_occupying_player_id" => "7", "disabled" => false,
        ]]];
        $this->assertSame([2], $this->game->callGetMarketClaimants());
    }
}
