<?php

require_once __DIR__ . "/PrimitiveCardPlayAction.php";

/**
 * Ship upgrade effects that are pure rewrites of a card's action tree.
 *
 * The same rewritten tree is sent to the client (to build the play dialog) and used by the
 * server when resolving the played card, so the decision strings produced by the dialog and
 * the ones consumed by processCardActions always line up.
 */
class ShipUpgrades
{
    /** Fire action name => number of cannon fired. */
    const FIRE_COUNTS = ["fire" => 1, "2 x fire" => 2, "3 x fire" => 3];

    const SIDES_BROADSIDE = ["left", "right"];
    const SIDES_CHASER = ["fore", "aft"];

    /** @param array<string,bool> $active upgrade_key => true for each activated upgrade */
    public static function rewriteActions(array $actions, array $active): array
    {
        return array_map(fn($action) => self::rewriteAction($action, $active), $actions);
    }

    private static function rewriteAction(array $action, array $active): array
    {
        $name = self::actionName($action);

        if (isset(self::FIRE_COUNTS[$name])) {
            $variants = self::fireVariants($action, $active);
            if (count($variants) > 1) {
                $action["variants"] = $variants;
            }
            return $action;
        }

        // Lateen Rigging: extended maneuvers cost no sail. The cost key is kept (emptied) so the
        // maneuver stays optional - dropping it entirely would make the client force the maneuver.
        if (!empty($active["xebec_lateen_rigging"]) && isset($action["cost"]["sail"])) {
            unset($action["cost"]["sail"]);
        }

        if ($name === PrimitiveCardPlayAction::CHOICE->value) {
            $action["choices"] = self::rewriteActions($action["choices"], $active);
        } elseif ($name === PrimitiveCardPlayAction::SEQUENCE->value) {
            $action["actions"] = self::rewriteActions($action["actions"], $active);
        }
        return $action;
    }

    /**
     * The shot options available for one fire action. The first entry is always the plain shot,
     * named after the action itself so that existing "fire left"/"2 x fire right" decisions keep
     * working for players without upgrades.
     */
    public static function fireVariants(array $action, array $active): array
    {
        $name = self::actionName($action);
        $count = self::FIRE_COUNTS[$name];
        $range = (int) $action["range"];
        $cost = $action["cost"] ?? [];

        $variants = [self::variant($name, $range, $cost, $count)];

        if ($count === 1) {
            if (!empty($active["brig_carronade"])) {
                $variants[] = self::variant("carronade", 1, [], 1);
            }
            if (!empty($active["sloop_of_war_chain_shot"])) {
                $variants[] = self::variant("chain shot", 2, $cost, 1, ["shot" => "chain"]);
            }
            if (!empty($active["war_junk_rockets"])) {
                $variants[] = self::variant("rocket", 3, $cost, 1, ["shot" => "rocket"]);
            }
            if (!empty($active["ship_of_the_line_heavy_guns"])) {
                $variants[] = self::variant("heavy guns", 5, ["cannonball" => 2], 1, ["shot" => "heavy"]);
            }
            if (!empty($active["galleon_bow_and_stern_chasers"])) {
                $variants[] = self::variant("chaser", 3, $cost, 1, ["sides" => self::SIDES_CHASER]);
            }
        } elseif (!empty($active["ship_of_the_line_double_gun_crews"])) {
            // Double Gun Crews: split the cannon between both sides at -1 range. The chosen side
            // gets the extra shot when the count is odd.
            $variants[] = self::variant("both sides", max(1, $range - 1), $cost, $count, ["both_sides" => true]);
            if (!empty($active["ship_of_the_line_heavy_guns"])) {
                $variants[] = self::variant("heavy guns both sides", 4, ["cannonball" => 2 * $count], $count, [
                    "shot" => "heavy",
                    "both_sides" => true,
                ]);
            }
        }

        return $variants;
    }

    private static function variant(string $name, int $range, array $cost, int $count, array $overrides = []): array
    {
        return $overrides + [
            "name" => $name,
            "range" => $range,
            "cost" => $cost,
            "count" => $count,
            "shot" => "cannon",
            "sides" => self::SIDES_BROADSIDE,
            "both_sides" => false,
        ];
    }

    /**
     * Resolve a "<variant name> <side>" decision back into the variant it came from.
     * @return array{0: array, 1: string} the variant and the side fired to
     */
    public static function parseFireDecision(array $action, string $decision): array
    {
        $variants = $action["variants"] ?? self::fireVariants($action, []);
        foreach ($variants as $variant) {
            foreach ($variant["sides"] as $side) {
                if ($decision === $variant["name"] . " " . $side) {
                    return [$variant, $side];
                }
            }
        }
        throw new \Bga\GameFramework\UserException("Invalid firing choice: " . $decision);
    }

    /** The sides each of a variant's shots is fired to, in order. */
    public static function shotSides(array $variant, string $side): array
    {
        $count = $variant["count"];
        if (!$variant["both_sides"]) {
            return array_fill(0, $count, $side);
        }
        $on_chosen_side = intdiv($count + 1, 2);
        return array_merge(
            array_fill(0, $on_chosen_side, $side),
            array_fill(0, $count - $on_chosen_side, self::oppositeSide($side)),
        );
    }

    public static function oppositeSide(string $side): string
    {
        return match ($side) {
            "left" => "right",
            "right" => "left",
            "fore" => "aft",
            "aft" => "fore",
        };
    }

    /**
     * A card counts as a sailing card if any of its actions move the ship. Starting-deck cards
     * carry no "type" field, so the actions are the only reliable signal.
     */
    public static function isSailingCard(array $card): bool
    {
        if (isset($card["type"])) {
            return in_array("sailing", $card["type"], true);
        }
        return self::containsMovement($card["actions"] ?? []);
    }

    const MOVEMENT_ACTIONS = ["forward", "left", "right"];
    const MANEUVER_ACTIONS = ["forward", "left", "right", "pivot left", "pivot right", "pivot 180"];

    private static function containsMovement(array $actions): bool
    {
        return self::containsAny($actions, self::MOVEMENT_ACTIONS);
    }

    private static function containsAny(array $actions, array $wanted): bool
    {
        foreach ($actions as $action) {
            $name = self::actionName($action);
            if (in_array($name, $wanted, true)) {
                return true;
            }
            if ($name === PrimitiveCardPlayAction::CHOICE->value && self::containsAny($action["choices"], $wanted)) {
                return true;
            }
            if ($name === PrimitiveCardPlayAction::SEQUENCE->value && self::containsAny($action["actions"], $wanted)) {
                return true;
            }
        }
        return false;
    }

    /** The maneuver part of a card's actions: the ones that move or turn the ship. */
    public static function maneuverActions(array $actions): array
    {
        return array_values(array_filter($actions, fn($a) => self::containsAny([$a], self::MANEUVER_ACTIONS)));
    }

    public static function actionName(array $action): string
    {
        $value = $action["action"];
        return is_string($value) ? $value : $value->value;
    }
}
