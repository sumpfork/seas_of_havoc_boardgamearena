<?php
/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

namespace Bga\Games\SeasOfHavoc;

/**
 * Ship upgrades in play: which a player has and has activated, the workshop, and the upgrades that act during a turn (Nimble Hull, Swift Hull, Extra Rations). The card rewriting they cause is in ShipUpgrades.
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait ShipUpgradeRules
{
    function assignShipUpgradesToPlayer($player_id, $ship_name)
    {
        // Get ship upgrade cards matching the player's ship
        $ship_upgrades = array_filter($this->non_playable_cards, function ($card) use ($ship_name) {
            return isset($card["category"]) &&
                $card["category"] == "ship_upgrade" &&
                isset($card["ship_name"]) &&
                $card["ship_name"] == $ship_name;
        });

        // Take first 2 upgrades for this ship (or all if less than 2)
        $upgrade_keys = array_keys($ship_upgrades);
        $selected_upgrades = array_slice($upgrade_keys, 0, 2);

        foreach ($selected_upgrades as $upgrade_key) {
            $sql = "INSERT INTO player_ship_upgrades (player_id, upgrade_key, is_activated) VALUES ('$player_id', '$upgrade_key', 0)";
            self::DbQuery($sql);
        }
    }

    function getPlayerShipUpgrades($player_id)
    {
        $sql = "SELECT upgrade_key, is_activated FROM player_ship_upgrades WHERE player_id = '$player_id'";
        return self::getObjectListFromDb($sql);
    }

    function markShipUpgradeActivated(string $player_id, string $upgrade_key): void
    {
        self::DbQuery(
            "UPDATE player_ship_upgrades SET is_activated = 1 WHERE player_id = '$player_id' AND upgrade_key = '$upgrade_key'",
        );
        $this->bga->playerStats->inc("upgrades_activated", 1, (int) $player_id);
    }

    /** @return array<string,bool> upgrade_key => true for every activated upgrade of this player */
    function getActiveShipUpgrades($player_id): array
    {
        $active = [];
        foreach ($this->getPlayerShipUpgrades($player_id) as $upgrade) {
            if ($upgrade["is_activated"]) {
                $active[$upgrade["upgrade_key"]] = true;
            }
        }
        return $active;
    }

    function hasShipUpgrade($player_id, string $upgrade_key): bool
    {
        return !empty($this->getActiveShipUpgrades($player_id)[$upgrade_key]);
    }

    /** Once-per-phase upgrade bookkeeping (Nimble Hull, Extra Rations). */
    function hasUsedUpgradeThisPhase($player_id, string $upgrade_key): bool
    {
        return (int) self::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM upgrade_uses WHERE player_id = '$player_id' AND upgrade_key = '$upgrade_key'",
        ) > 0;
    }

    function markUpgradeUsedThisPhase($player_id, string $upgrade_key): void
    {
        self::DbQuery("REPLACE INTO upgrade_uses (player_id, upgrade_key) VALUES ('$player_id', '$upgrade_key')");
    }

    function clearUpgradeUses(array $upgrade_keys): void
    {
        $list = implode(",", array_map(fn($k) => "'$k'", $upgrade_keys));
        self::DbQuery("DELETE FROM upgrade_uses WHERE upgrade_key IN ($list)");
    }

    /**
     * Rewrite a card's actions for the upgrades this player has activated.
     * $context lets callers that rewrite many cards look the player's state up once.
     */
    function upgradedCardActions(array $card, $player_id, ?array $context = null): array
    {
        $context ??= $this->shipUpgradeContext($player_id);
        $active = $context["active"];
        $actions = ShipUpgrades::rewriteActions($card["actions"], $active);

        if ($context["nimble_hull_available"] && ShipUpgrades::isSailingCard($card)) {
            $maneuver = ShipUpgrades::maneuverActions($actions);
            if (!empty($maneuver)) {
                $actions = [
                    [
                        "action" => PrimitiveCardPlayAction::CHOICE->value,
                        "choices" => [
                            [
                                "action" => PrimitiveCardPlayAction::SEQUENCE->value,
                                "actions" => $actions,
                                "name" => "resolve once",
                            ],
                            [
                                "action" => PrimitiveCardPlayAction::SEQUENCE->value,
                                "actions" => array_merge($maneuver, $actions),
                                "name" => self::NIMBLE_HULL_CHOICE,
                            ],
                        ],
                    ],
                ];
            }
        }
        return $actions;
    }

    /** The per-player upgrade state every card rewrite needs, looked up once. */
    function shipUpgradeContext($player_id): array
    {
        $active = $this->getActiveShipUpgrades($player_id);
        return [
            "active" => $active,
            "nimble_hull_available" =>
                !empty($active["sloop_of_war_nimble_hull"]) &&
                !$this->hasUsedUpgradeThisPhase($player_id, "sloop_of_war_nimble_hull"),
        ];
    }

    /** playable_cards with every card's actions rewritten for this player's active upgrades. */
    function upgradedPlayableCards($player_id): array
    {
        $context = $this->shipUpgradeContext($player_id);
        $cards = $this->playable_cards;
        foreach ($cards as $type => $card) {
            $cards[$type]["actions"] = $this->upgradedCardActions($card, $player_id, $context);
        }
        return $cards;
    }

    function useNimbleHull($player_id): void
    {
        $this->markUpgradeUsedThisPhase($player_id, "sloop_of_war_nimble_hull");
        $this->bga->notify->all("log", clienttranslate('${player_name}\'s Nimble Hull: resolves the maneuver twice'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
        ]);
        $this->bga->notify->player($player_id, "playableCardsUpdated", "", [
            "playable_cards" => $this->upgradedPlayableCards($player_id),
        ]);
    }

    private function setPendingWorkshopSelection(?int $player_id, ?string $slot_number): void
    {
        $slot_value = 0;
        if ($slot_number !== null) {
            $slot_value = (int) ltrim($slot_number, "n");
        }

        $this->setGameStateValue("pending_workshop_player", $player_id ?? 0);
        $this->setGameStateValue("pending_workshop_slot", $slot_value);
    }

    private function getPendingWorkshopSelection(): ?array
    {
        $player_id = (int) $this->getGameStateValue("pending_workshop_player");
        $slot_value = (int) $this->getGameStateValue("pending_workshop_slot");

        if ($player_id <= 0 || $slot_value <= 0) {
            return null;
        }

        return [
            "player_id" => $player_id,
            "slot_number" => "n" . $slot_value,
        ];
    }

    private function assertPendingWorkshopSelection(int $player_id): array
    {
        $pending = $this->getPendingWorkshopSelection();
        if ($pending === null || (int) $pending["player_id"] !== $player_id) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You must place a skiff on the workshop first"));
        }
        return $pending;
    }

    function actExtraRations(?int $use_booty_card_id = null): mixed
    {
        $player_id = self::getActivePlayerId();
        if (!$this->hasShipUpgrade($player_id, "brig_extra_rations")) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You do not have Extra Rations"));
        }
        if ($this->hasUsedUpgradeThisPhase($player_id, "brig_extra_rations")) {
            throw new \Bga\GameFramework\UserException(
                clienttranslate("You have already used Extra Rations this Island Phase"),
            );
        }
        $this->payWithOptionalBooty((int) $player_id, ["doubloon" => 1], $use_booty_card_id);
        $this->markUpgradeUsedThisPhase($player_id, "brig_extra_rations");
        $this->bga->notify->all("log", clienttranslate('${player_name} uses Extra Rations to draw a card'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
        ]);
        $this->drawCards($player_id);
        // Free action: re-enter the island turn so the player still makes their skiff placement,
        // and so getArgs recomputes and drops the now-spent Extra Rations button.
        return STATE_ISLAND_TURN;
    }

    /** True when the active player may still use Extra Rations this Island Phase. */
    function canUseExtraRations($player_id): bool
    {
        if (!$this->hasShipUpgrade($player_id, "brig_extra_rations")) {
            return false;
        }
        if ($this->hasUsedUpgradeThisPhase($player_id, "brig_extra_rations")) {
            return false;
        }
        return $this->canPayWithOptionalBooty((int) $player_id, ["doubloon" => 1]);
    }

    function actActivateShipUpgrade(string $upgrade_key, ?int $use_booty_card_id = null): mixed
    {
        $player_id = self::getActivePlayerId();
        $pending = $this->assertPendingWorkshopSelection((int) $player_id);

        $upgrade = null;
        foreach ($this->getPlayerShipUpgrades($player_id) as $candidate) {
            if ($candidate["upgrade_key"] === $upgrade_key) {
                $upgrade = $candidate;
                break;
            }
        }
        if ($upgrade === null || $upgrade["is_activated"]) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid ship upgrade selection"));
        }

        $card = $this->non_playable_cards[$upgrade_key];
        $this->payWithOptionalBooty((int) $player_id, $card["cost"] ?? [], $use_booty_card_id);
        $this->playerGainResources($player_id, ["skiff" => -1]);

        $this->markShipUpgradeActivated($player_id, $upgrade_key);
        $this->occupyIslandSlot($player_id, "workshop", $pending["slot_number"]);
        $this->setPendingWorkshopSelection(null, null);

        // The card actions this player sees depend on their upgrades, so resend them.
        $this->bga->notify->player($player_id, "playableCardsUpdated", "", [
            "playable_cards" => $this->upgradedPlayableCards($player_id),
        ]);
        $this->bga->notify->all("shipUpgradeActivated", clienttranslate('${player_name} activates a ship upgrade'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
            "upgrade_key" => $upgrade_key,
            "infamy" => $card["infamy"] ?? 0,
        ]);

        return "islandTurnDone";
    }

    /** Sums infamy from all activated ship upgrades and scores it per player, at final scoring. */
    function awardShipUpgradeEndgameInfamy(): void
    {
        foreach ($this->loadPlayersBasicInfos() as $player_id => $_) {
            $infamy_total = 0;
            foreach ($this->getPlayerShipUpgrades($player_id) as $upgrade) {
                if (!$upgrade["is_activated"]) {
                    continue;
                }
                $infamy_total += $this->non_playable_cards[$upgrade["upgrade_key"]]["infamy"];
            }
            if ($infamy_total > 0) {
                $this->scoreInfamy(
                    (string) $player_id,
                    $infamy_total,
                    "upgrades",
                    clienttranslate('${player_name} scores ${score_increment} infamy from ship upgrades'),
                );
            }
        }
    }

    /** The pending workshop choice for the island turn's args: its slot and the upgrades on offer. */
    function getPendingWorkshopChoice(): ?array
    {
        $pending = $this->getPendingWorkshopSelection();
        if ($pending === null) {
            return null;
        }
        return [
            "slot_number" => $pending["slot_number"],
            "upgrades" => $this->workshopUpgradeOptions($pending["player_id"]),
        ];
    }

    /** The inactive ship upgrades the player can afford to activate at the workshop. */
    private function workshopUpgradeOptions(int $player_id): array
    {
        $options = [];
        foreach ($this->getPlayerShipUpgrades($player_id) as $upgrade) {
            $card = $this->non_playable_cards[$upgrade["upgrade_key"]];
            if (!$upgrade["is_activated"] && $this->canPayWithOptionalBooty($player_id, $card["cost"] ?? [])) {
                $options[] = [
                    "upgrade_key" => $upgrade["upgrade_key"],
                    "name" => $card["name"],
                    "cost" => $card["cost"] ?? [],
                    "infamy" => $card["infamy"] ?? 0,
                ];
            }
        }
        return $options;
    }

    function canUseSwiftHull($player_id): bool
    {
        $type = (int) $this->getGameStateValue("swift_hull_card_type");
        if ($type === 0 || !$this->hasShipUpgrade($player_id, "xebec_swift_hull")) {
            return false;
        }
        if ($this->cards->countCardInLocation("hand", $player_id) == 0) {
            return false;
        }
        return $this->canPayWithOptionalBooty((int) $player_id, ["sail" => 1]);
    }

    function argSwiftHull(): array
    {
        return ["card_type" => (int) $this->getGameStateValue("swift_hull_card_type")];
    }

    function actUseSwiftHull(?int $use_booty_card_id = null): int
    {
        $player_id = $this->getActivePlayerId();
        if (!$this->canUseSwiftHull($player_id)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You cannot use Swift Hull right now"));
        }
        $this->payWithOptionalBooty((int) $player_id, ["sail" => 1], $use_booty_card_id);
        $this->setGameStateValue("swift_hull_card_type", 0);
        $this->bga->notify->all("log", clienttranslate('${player_name}\'s Swift Hull: plays another card immediately'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
        ]);
        return STATE_SEA_TURN;
    }

    function actSkipSwiftHull(): int
    {
        $this->setGameStateValue("swift_hull_card_type", 0);
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }
}
