<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

namespace Bga\Games\SeasOfHavoc;

/**
 * Resources: reading, gaining, paying and logging player resources, including paying with a booty token.
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait Resources
{
    /*
        getGameResources:
        
        Gather all relevant resources about current game situation (visible by the current player).
    */
    function getGameResources(?int $player_id = null)
    {
        $sql = "
                SELECT
                    player_id, resource_key, resource_count
                FROM resource
            ";
        if ($player_id != null) {
            $sql .= " WHERE player_id = $player_id";
        }
        $game_resources = $this->getObjectListFromDB($sql);
        return $game_resources;
    }

    function getGameResourcesHierarchical(?int $player_id = null)
    {
        $resources = $this->getGameResources($player_id);
        $hierarchical_resources = [];
        foreach ($resources as $row) {
            if (!array_key_exists($row["player_id"], $hierarchical_resources)) {
                $hierarchical_resources[$row["player_id"]] = [];
            }
            $hierarchical_resources[$row["player_id"]][$row["resource_key"]] = intval($row["resource_count"]);
        }
        return $hierarchical_resources;
    }

    function sum_array_by_key(array ...$arrays)
    {
        $out = [];

        foreach ($arrays as $a) {
            foreach ($a as $key => $value) {
                if (array_key_exists($key, $out)) {
                    $out[$key] += $value;
                } else {
                    $out[$key] = $value;
                }
            }
        }

        return $out;
    }

    function makeCostNegative($cost)
    {
        return array_map(fn($value): int => -$value, $cost);
    }

    function canPayFor($cost, $resources)
    {
        $cost = $this->makeCostNegative($cost);
        $result = $this->sum_array_by_key($resources, $cost);
        #php 8.4: return array_any($result, fn($value): bool => $value < 0);
        // Check that no resource goes negative (all values >= 0)
        return array_reduce($result, fn($carry, $value): bool => $carry && $value >= 0, true);
    }

    /**
     * Resolve booty "resources" for payment.
     * If $player_choice is set, all "choice" units are assigned to that resource type.
     * Otherwise, auto-assign each "choice" unit to whichever cost resource has the
     * highest remaining need after fixed resources are applied.
     */
    private function resolveBootyResourcesForPayment(
        array $resources,
        array $cost,
        ?string $player_choice = null,
    ): array {
        // First, collect fixed (non-choice) resources
        $out = [];
        $choiceAmount = 0;
        foreach ($resources as $key => $amount) {
            if ($key === "choice") {
                $choiceAmount += $amount;
            } else {
                $out[$key] = ($out[$key] ?? 0) + $amount;
            }
        }

        if ($choiceAmount <= 0) {
            return $out;
        }

        // If player explicitly chose a valid resource type, use it
        if (
            $player_choice !== null &&
            $player_choice !== "" &&
            in_array($player_choice, $this->resource_types, true) &&
            $player_choice !== "skiff"
        ) {
            $out[$player_choice] = ($out[$player_choice] ?? 0) + $choiceAmount;
            return $out;
        }

        // Auto-assign each "choice" unit to the cost resource with the highest remaining need
        for ($i = 0; $i < $choiceAmount; $i++) {
            $bestRes = null;
            $bestNeed = 0;
            foreach ($cost as $res => $need) {
                $coveredByFixed = $out[$res] ?? 0;
                $remaining = $need - $coveredByFixed;
                if ($remaining > $bestNeed) {
                    $bestNeed = $remaining;
                    $bestRes = $res;
                }
            }
            if ($bestRes !== null) {
                $out[$bestRes] = ($out[$bestRes] ?? 0) + 1;
            }
            // If no remaining need, the choice is wasted (no refund)
        }

        return $out;
    }

    /**
     * Pay a cost, optionally using the player's booty token to cover some or all of it.
     * If use_booty_card_id is set, that booty card is applied to reduce the cost (no refund for excess), then discarded to booty_discard.
     * $booty_choice is the player's chosen resource type for "choice" (wild) resources. If null, auto-resolved.
     */
    function payWithOptionalBooty(
        int $player_id,
        array $cost,
        ?int $use_booty_card_id = null,
        ?string $booty_choice = null,
    ): void {
        if (empty($cost)) {
            return;
        }
        $player_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
        $remaining_cost = $cost;

        if ($use_booty_card_id !== null && $use_booty_card_id > 0) {
            $booty_card = $this->cards->getCard($use_booty_card_id);
            if (
                !$booty_card ||
                $booty_card["location"] !== "booty_player" ||
                (int) $booty_card["location_arg"] !== (int) $player_id
            ) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid booty token"));
            }
            $config = $this->getBootyTokenConfigByTypeArg((int) $booty_card["type_arg"]);
            if (!$config || empty($config["resources"])) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid booty token"));
            }
            $booty_resources = $this->resolveBootyResourcesForPayment($config["resources"], $cost, $booty_choice);
            // Reduce cost by booty (no refund: only subtract up to cost amount per resource)
            $remaining_cost = [];
            foreach ($cost as $res => $need) {
                $have_from_booty = $booty_resources[$res] ?? 0;
                $remaining_cost[$res] = max(0, $need - $have_from_booty);
            }
            if (!$this->canPayFor($remaining_cost, $player_resources)) {
                throw new \Bga\GameFramework\UserException(clienttranslate("You cannot afford this cost even with the booty token"));
            }
            $this->pay($player_id, $remaining_cost);
            $this->cards->moveCard($use_booty_card_id, "booty_discard", 0);
            // Build a description of what the booty token covered, capped at cost
            $booty_parts = [];
            foreach ($booty_resources as $res => $amount) {
                $used = min($amount, $cost[$res] ?? 0);
                for ($i = 0; $i < $used; $i++) {
                    $booty_parts[] = "[$res]";
                }
            }
            $booty_desc = implode(" + ", $booty_parts);
            $this->bga->notify->all(
                "bootyTokenUsed",
                clienttranslate('${player_name} uses a booty token as ${booty_usage}'),
                [
                    "player_id" => $player_id,
                    "player_name" => $this->getPlayerNameById($player_id),
                    "booty_card" => $booty_card,
                    "booty_tokens" => $this->getBootyTokensForPlayer($player_id),
                    "booty_usage" => $booty_desc,
                ],
            );
            return;
        }

        $this->pay($player_id, $remaining_cost);
    }

    function pay($player_id, $cost)
    {
        $player_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
        if (!$this->canPayFor($cost, $player_resources)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You cannot afford this action"));
        }
        $cost = $this->makeCostNegative($cost);
        $this->playerGainResources($player_id, $cost);
    }

    function playerGainResources($player_id, $resources)
    {
        $this->mytrace("playerGainResources");
        $this->mydump("incoming resources", $resources);
        $current_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
        $this->mydump("current resources", $current_resources);

        $summed_resources = $this->sum_array_by_key($resources, $current_resources);
        $this->mydump("summed resources", $summed_resources);

        $sql = "REPLACE INTO resource (player_id, resource_key, resource_count) VALUES ";
        $values = [];
        foreach ($summed_resources as $resource_type => $resource_count) {
            $values[] = "('" . $player_id . "','$resource_type','" . $resource_count . "')";
        }
        $sql .= implode(",", $values);
        $this->mytrace("gain resources sql: $sql");

        self::DbQuery($sql);
        $msg = $this->formatResourceChangeMessage($resources);
        $log = $msg ? clienttranslate('${player_name} ${resource_change}') : "";
        $this->bga->notify->all("resourcesChanged", $log, [
            "player_name" => self::getPlayerNameById($player_id),
            "resources" => $this->getGameResources(),
            "resource_change" => $msg,
        ]);
    }

    function playerSetResourceCount($player_id, $resource_type, $count)
    {
        $this->mytrace("playerSetResourceCount $player_id $resource_type $count");
        $current = $this->getGameResourcesHierarchical($player_id)[$player_id];
        $old_count = $current[$resource_type] ?? 0;
        $diff = $count - $old_count;
        self::DbQuery(
            "REPLACE INTO resource (player_id, resource_key, resource_count) VALUES ('$player_id','$resource_type','$count')",
        );
        $msg = $this->formatResourceChangeMessage([$resource_type => $diff]);
        $log = $msg ? clienttranslate('${player_name} ${resource_change}') : "";
        $this->bga->notify->all("resourcesChanged", $log, [
            "player_name" => self::getPlayerNameById($player_id),
            "resources" => $this->getGameResources(),
            "resource_change" => $msg,
        ]);
    }

    /**
     * Build a human-readable string describing resource changes, using [resource] markers
     * that the client replaces with icons.
     * e.g. ["sail" => -2, "cannonball" => 1] => "pays 2 [sail], gains 1 [cannonball]"
     */
    /**
     * The "pays 1 [sail], gains 2 [cannonball]" part of a resource log, as a nested log so each
     * phrase is translated. [resource] markers become icons on the client (bgaFormatText).
     */
    private function formatResourceChangeMessage(array $resources): array|string
    {
        $list = fn(array $amounts) => implode(" ", array_map(fn($type) => abs($amounts[$type]) . " [$type]", array_keys($amounts)));
        $skiffs = $resources["skiff"] ?? 0;
        unset($resources["skiff"]);
        $losses = array_filter($resources, fn($amount) => $amount < 0);
        $gains = array_filter($resources, fn($amount) => $amount > 0);
        $parts = [];
        if ($skiffs != 0) {
            $parts[] = [
                "log" => $skiffs > 0 ? clienttranslate('retrieves ${resource_list}') : clienttranslate('places ${resource_list}'),
                "args" => ["resource_list" => abs($skiffs) . " [skiff]"],
            ];
        }
        if (!empty($losses)) {
            $parts[] = ["log" => clienttranslate('pays ${resource_list}'), "args" => ["resource_list" => $list($losses)]];
        }
        if (!empty($gains)) {
            $parts[] = ["log" => clienttranslate('gains ${resource_list}'), "args" => ["resource_list" => $list($gains)]];
        }
        if (empty($parts)) {
            return "";
        }
        $args = [];
        foreach ($parts as $i => $part) {
            $args["part$i"] = $part;
        }
        return ["log" => implode(", ", array_map(fn($key) => '${' . $key . '}', array_keys($args))), "args" => $args];
    }
}
