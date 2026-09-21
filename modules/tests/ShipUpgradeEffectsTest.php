<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../ShipUpgrades.php";

/** Loads material.inc.php on its own so the upgrade definitions can be inspected without the game. */
class ShipUpgradeMaterial
{
    public array $non_playable_cards = [];
    public array $playable_cards = [];
    public array $resource_types = [];
    public array $token_names = [];
    public array $booty_tokens = [];

    public function __construct()
    {
        include __DIR__ . "/../material.inc.php";
    }
}

/**
 * The action-tree rewriting is the piece both the play dialog and processCardActions depend on,
 * so it is the one worth pinning down: every decision string the dialog can produce has to parse
 * back into the variant it came from.
 */
final class ShipUpgradeEffectsTest extends TestCase
{
    private const FIRE = ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]];
    private const FIRE2 = ["action" => "2 x fire", "range" => 2, "cost" => ["cannonball" => 2]];

    public function testNoUpgradesLeavesFireActionUntouched(): void
    {
        $rewritten = ShipUpgrades::rewriteActions([self::FIRE], [])[0];

        $this->assertArrayNotHasKey("variants", $rewritten);
        [$variant, $side] = ShipUpgrades::parseFireDecision($rewritten, "fire left");
        $this->assertSame(3, $variant["range"]);
        $this->assertSame(["cannonball" => 1], $variant["cost"]);
        $this->assertSame("left", $side);
    }

    public function testCarronadeAddsAFreeShortRangeShot(): void
    {
        $rewritten = ShipUpgrades::rewriteActions([self::FIRE], ["brig_carronade" => true])[0];

        [$variant] = ShipUpgrades::parseFireDecision($rewritten, "carronade right");
        $this->assertSame(1, $variant["range"]);
        $this->assertSame([], $variant["cost"]);
        // The plain shot is still on offer.
        $this->assertSame(3, ShipUpgrades::parseFireDecision($rewritten, "fire right")[0]["range"]);
    }

    public function testHeavyGunsCostTwoCannonballsAndReachFive(): void
    {
        $rewritten = ShipUpgrades::rewriteActions([self::FIRE], ["ship_of_the_line_heavy_guns" => true])[0];

        [$variant] = ShipUpgrades::parseFireDecision($rewritten, "heavy guns left");
        $this->assertSame(5, $variant["range"]);
        $this->assertSame(["cannonball" => 2], $variant["cost"]);
        $this->assertSame("heavy", $variant["shot"]);
    }

    public function testChasersFireForeAndAftInsteadOfSideways(): void
    {
        $rewritten = ShipUpgrades::rewriteActions([self::FIRE], ["galleon_bow_and_stern_chasers" => true])[0];

        $this->assertSame("fore", ShipUpgrades::parseFireDecision($rewritten, "chaser fore")[1]);
        $this->assertSame("aft", ShipUpgrades::parseFireDecision($rewritten, "chaser aft")[1]);
        $this->expectException(\Bga\GameFramework\UserException::class);
        ShipUpgrades::parseFireDecision($rewritten, "chaser left");
    }

    public function testSingleShotUpgradesDoNotApplyToMultiCannonActions(): void
    {
        $active = ["brig_carronade" => true, "war_junk_rockets" => true, "sloop_of_war_chain_shot" => true];
        $rewritten = ShipUpgrades::rewriteActions([self::FIRE2], $active)[0];

        $this->assertArrayNotHasKey("variants", $rewritten);
    }

    public function testDoubleGunCrewsSplitsShotsAcrossBothSidesAtMinusOneRange(): void
    {
        $rewritten = ShipUpgrades::rewriteActions(
            [self::FIRE2],
            ["ship_of_the_line_double_gun_crews" => true],
        )[0];

        [$variant, $side] = ShipUpgrades::parseFireDecision($rewritten, "both sides left");
        $this->assertSame(1, $variant["range"]); // 2 - 1
        $this->assertSame(["left", "right"], ShipUpgrades::shotSides($variant, $side));

        // An odd number of cannon puts the extra shot on the chosen side.
        $three = ShipUpgrades::rewriteActions(
            [["action" => "3 x fire", "range" => 3, "cost" => ["cannonball" => 3]]],
            ["ship_of_the_line_double_gun_crews" => true],
        )[0];
        [$odd] = ShipUpgrades::parseFireDecision($three, "both sides right");
        $this->assertSame(["right", "right", "left"], ShipUpgrades::shotSides($odd, "right"));
    }

    public function testDoubleGunCrewsCombinesWithHeavyGuns(): void
    {
        $rewritten = ShipUpgrades::rewriteActions(
            [self::FIRE2],
            ["ship_of_the_line_double_gun_crews" => true, "ship_of_the_line_heavy_guns" => true],
        )[0];

        [$variant] = ShipUpgrades::parseFireDecision($rewritten, "heavy guns both sides left");
        $this->assertSame("heavy", $variant["shot"]);
        $this->assertSame(["cannonball" => 4], $variant["cost"]); // 2 cannonballs per cannon
        $this->assertSame(4, $variant["range"]);
    }

    public function testPlainShotFiresEveryCannonToTheChosenSide(): void
    {
        $rewritten = ShipUpgrades::rewriteActions([self::FIRE2], [])[0];
        [$variant, $side] = ShipUpgrades::parseFireDecision($rewritten, "2 x fire left");

        $this->assertSame(["left", "left"], ShipUpgrades::shotSides($variant, $side));
    }

    public function testLateenRiggingEmptiesSailCostsButKeepsTheManeuverOptional(): void
    {
        $actions = [
            ["action" => "forward"],
            ["action" => "forward", "cost" => ["sail" => 1]],
            ["action" => "choice", "choices" => [["action" => "left"]], "cost" => ["sail" => 1, "doubloon" => 1]],
        ];

        $rewritten = ShipUpgrades::rewriteActions($actions, ["xebec_lateen_rigging" => true]);

        // The cost key survives so the dialog still offers "skip"; it just costs nothing.
        $this->assertSame([], $rewritten[1]["cost"]);
        $this->assertSame(["doubloon" => 1], $rewritten[2]["cost"]);
        $this->assertArrayNotHasKey("cost", $rewritten[0]);
    }

    public function testLateenRiggingLeavesFiringCostsAlone(): void
    {
        $rewritten = ShipUpgrades::rewriteActions([self::FIRE], ["xebec_lateen_rigging" => true])[0];

        $this->assertSame(["cannonball" => 1], $rewritten["cost"]);
    }

    public function testUpgradesReachIntoNestedChoicesAndSequences(): void
    {
        $actions = [
            [
                "action" => "choice",
                "choices" => [
                    ["action" => "sequence", "actions" => [self::FIRE], "name" => "shoot"],
                    ["action" => "left"],
                ],
            ],
        ];

        $rewritten = ShipUpgrades::rewriteActions($actions, ["brig_carronade" => true]);
        $nested = $rewritten[0]["choices"][0]["actions"][0];

        $this->assertSame(1, ShipUpgrades::parseFireDecision($nested, "carronade left")[0]["range"]);
    }

    public function testSailingCardsAreRecognisedByTypeOrByMovement(): void
    {
        $this->assertTrue(ShipUpgrades::isSailingCard(["type" => ["sailing", "firing"], "actions" => []]));
        $this->assertFalse(ShipUpgrades::isSailingCard(["type" => ["firing"], "actions" => [self::FIRE]]));
        // Starting-deck cards carry no "type", so the actions decide.
        $this->assertTrue(ShipUpgrades::isSailingCard(["actions" => [["action" => "forward"]]]));
        $this->assertFalse(ShipUpgrades::isSailingCard(["actions" => [["action" => "pivot left"]]]));
        $this->assertTrue(ShipUpgrades::isSailingCard([
            "actions" => [["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]]]],
        ]));
    }

    public function testManeuverActionsDropFiringButKeepPivots(): void
    {
        $actions = [
            ["action" => "forward"],
            self::FIRE,
            ["action" => "choice", "choices" => [["action" => "pivot left"], ["action" => "pivot right"]]],
            ["action" => "choice", "choices" => [self::FIRE, self::FIRE2]],
        ];

        $maneuver = ShipUpgrades::maneuverActions($actions);

        $this->assertCount(2, $maneuver);
        $this->assertSame("forward", $maneuver[0]["action"]);
        $this->assertSame("choice", $maneuver[1]["action"]);
        $this->assertSame("pivot left", $maneuver[1]["choices"][0]["action"]);
    }

    public function testEveryShipUpgradeHasADisplayName(): void
    {
        // The workshop button reads ["name"] with no fallback, so a missing one is a runtime break.
        $material = new ShipUpgradeMaterial();
        $upgrades = array_filter($material->non_playable_cards, fn($c) => ($c["category"] ?? "") === "ship_upgrade");

        $this->assertCount(12, $upgrades);
        foreach ($upgrades as $key => $card) {
            $this->assertArrayHasKey("name", $card, "$key has no display name");
            $this->assertNotSame("", trim($card["name"]), "$key has an empty display name");
            $this->assertStringNotContainsString("_", $card["name"], "$key shows a raw key, not a name");
        }
    }

    public function testEveryGeneratedDecisionStringParsesBack(): void
    {
        $all = [
            "brig_carronade" => true,
            "sloop_of_war_chain_shot" => true,
            "war_junk_rockets" => true,
            "ship_of_the_line_heavy_guns" => true,
            "galleon_bow_and_stern_chasers" => true,
            "ship_of_the_line_double_gun_crews" => true,
        ];
        foreach ([self::FIRE, self::FIRE2] as $action) {
            $rewritten = ShipUpgrades::rewriteActions([$action], $all)[0];
            foreach ($rewritten["variants"] ?? [] as $variant) {
                foreach ($variant["sides"] as $side) {
                    // This is exactly the string the play dialog builds.
                    [$parsed, $parsed_side] = ShipUpgrades::parseFireDecision(
                        $rewritten,
                        $variant["name"] . " " . $side,
                    );
                    $this->assertSame($variant, $parsed);
                    $this->assertSame($side, $parsed_side);
                    $this->assertCount($variant["count"], ShipUpgrades::shotSides($variant, $side));
                }
            }
        }
    }
}
