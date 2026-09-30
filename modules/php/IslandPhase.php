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

use Bga\GameFramework\Actions\Types\JsonParam;

/**
 * The island phase: skiff placement and what each island slot gives, the market and purchases, the trading post, flags and tokens, extra turns and island scrapping.
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait IslandPhase
{
    private function flagTokenKeys(): array
    {
        return ["green_flag", "tan_flag", "blue_flag", "red_flag"];
    }

    /** Players who put a skiff on a Market card, and so have something to buy. */
    private function getMarketClaimants(): array
    {
        $claimants = [];
        foreach ($this->getIslandSlots()["market"] ?? [] as $slot) {
            if ($slot["occupying_player_id"] !== null) {
                $claimants[(int) $slot["occupying_player_id"]] = true;
            }
        }
        return array_keys($claimants);
    }

    function getUniqueTokens()
    {
        $sql = "
    		SELECT
    		    player_id, token_key
    		FROM unique_tokens
    	";
        $tokens = $this->getObjectListFromDB($sql);
        $indexed_tokens = [];
        foreach ($tokens as $token) {
            $indexed_tokens[$token["token_key"]] = $token["player_id"];
        }
        return $indexed_tokens;
    }

    function getFirstPlayerTokenOwner()
    {
        $sql = "SELECT player_id FROM unique_tokens WHERE token_key = 'first_player_token'";
        $result = $this->getObjectFromDB($sql);
        return $result ? $result["player_id"] : null;
    }

    function getTokenOwner(string $token_key)
    {
        $sql = "SELECT player_id FROM unique_tokens WHERE token_key = '$token_key'";
        $result = $this->getObjectFromDB($sql);
        return $result ? $result["player_id"] : null;
    }

    function getIslandSlots()
    {
        $sql = "
    		SELECT
    		    slot_key, number, occupying_player_id, corsair_occupying_player_id, disabled
    		FROM islandslots
    	";
        $slots = $this->getObjectListFromDB($sql);
        $indexed_slots = [];
        foreach ($slots as $slot) {
            $indexed_slots[$slot["slot_key"]][$slot["number"]] = [
                "occupying_player_id" => $slot["occupying_player_id"],
                "corsair_occupying_player_id" => $slot["corsair_occupying_player_id"],
                "disabled" => (bool) $slot["disabled"],
            ];
        }
        return $indexed_slots;
    }

    /** An island slot's name as players read it in the log, marked for translation. */
    function islandSlotLabel(string $slot_name): string
    {
        return match ($slot_name) {
            "capitol" => clienttranslate("the Capitol"),
            "bank" => clienttranslate("the Bank"),
            "shipyard" => clienttranslate("the Shipyard"),
            "sailmaker" => clienttranslate("the Sail Maker"),
            "blacksmith" => clienttranslate("the Blacksmith"),
            "workshop" => clienttranslate("the Workshop"),
            "trading_post" => clienttranslate("the Trading Post"),
            "market" => clienttranslate("the Market"),
            "deep_cove" => clienttranslate("the Deep Cove"),
            "green_flag" => clienttranslate("the Green Purser's Flag"),
            "tan_flag" => clienttranslate("the Tan Bosun's Flag"),
            "red_flag" => clienttranslate("the Red Shipwright's Flag"),
            "blue_flag" => clienttranslate("the Blue Sailor's Flag"),
        };
    }

    function occupyIslandSlot(string $player_id, string $slot_name, string $number)
    {
        // Preserve any existing overlay occupant when replacing the main occupant.
        $sql = "SELECT disabled, corsair_occupying_player_id FROM islandslots WHERE slot_key = '$slot_name' AND number = '$number'";
        $current_slot = $this->getObjectFromDB($sql);
        $disabled = $current_slot ? $current_slot["disabled"] : 0;
        $corsair_occupying_player_id =
            $current_slot && $current_slot["corsair_occupying_player_id"] !== null
                ? "'" . $current_slot["corsair_occupying_player_id"] . "'"
                : "null";

        self::DbQuery(
            "REPLACE INTO islandslots (slot_key, number, occupying_player_id, corsair_occupying_player_id, disabled) VALUES ('$slot_name', '$number', '$player_id', $corsair_occupying_player_id, $disabled)",
        );
        $this->bga->notify->all("skiffPlaced", clienttranslate('${player_name} placed a skiff on ${slot_label}'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
            "player_color" => $this->getPlayerColor($player_id),
            "slot_name" => $slot_name,
            "slot_label" => $this->islandSlotLabel($slot_name),
            "i18n" => ["slot_label"],
            "slot_number" => $number,
            "is_corsair_overlay" => false,
        ]);
    }

    function occupyIslandSlotAsCorsairOverlay(string $player_id, string $slot_name, string $number)
    {
        self::DbQuery(
            "UPDATE islandslots SET corsair_occupying_player_id = '$player_id' WHERE slot_key = '$slot_name' AND number = '$number'",
        );
        $this->bga->notify->all(
            "skiffPlaced",
            clienttranslate('${player_name} placed a skiff on occupied ${slot_label}'),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "player_color" => $this->getPlayerColor($player_id),
                "slot_name" => $slot_name,
                "slot_label" => $this->islandSlotLabel($slot_name),
                "i18n" => ["slot_label"],
                "slot_number" => $number,
                "is_corsair_overlay" => true,
            ],
        );
    }

    /** Brig Extra Rations: once per Island Phase, pay 1 doubloon to draw 1 card. */
    /**
     * The market as a 5-slot list: index i is slot n(i+1), null where the slot is empty. A card's
     * location_arg is its slot number, so cards stay put under the skiffs that claimed them.
     */
    function getMarketSlots(): array
    {
        $slots = array_fill(0, 5, null);
        foreach ($this->cards->getCardsInLocation("market") as $card) {
            $slot = (int) $card["location_arg"];
            if ($slot < 1 || $slot > 5 || $slots[$slot - 1] !== null) {
                throw new \Bga\GameFramework\SystemException("Bad market slot $slot for card {$card["id"]}");
            }
            $slots[$slot - 1] = $card;
        }
        return $slots;
    }

    /** Deal a card from the market deck into every empty market slot. */
    function refillMarket(): void
    {
        foreach ($this->getMarketSlots() as $i => $card) {
            if ($card === null) {
                $this->cards->pickCardForLocation("market_deck", "market", $i + 1);
            }
        }
    }

    /** True when the active player may restock the market: once, with an unclaimed card to replace. */
    function canRestockMarket(): bool
    {
        if ($this->getGameStateValue("market_restocked")) {
            return false;
        }
        $market_slots = $this->getIslandSlots()["market"];
        foreach ($this->getMarketSlots() as $i => $card) {
            if ($card !== null && $market_slots["n" . ($i + 1)]["occupying_player_id"] === null) {
                return true;
            }
        }
        return false;
    }

    /**
     * "Before placing their Skiff on a card in the market, a player may scrap all unclaimed cards
     * and replenish the market." The skiff must then go on one of the new cards.
     */
    function actRestockMarket(): mixed
    {
        $player_id = self::getActivePlayerId();
        if (!$this->canRestockMarket()) {
            throw new \Bga\GameFramework\UserException(clienttranslate("The market cannot be restocked now"));
        }
        $market_slots = $this->getIslandSlots()["market"];
        $scrapped = [];
        foreach ($this->getMarketSlots() as $i => $card) {
            if ($card !== null && $market_slots["n" . ($i + 1)]["occupying_player_id"] === null) {
                $this->cards->moveCard((int) $card["id"], "scrap");
                $card["location"] = "scrap";
                $scrapped[] = $card;
            }
        }
        $this->refillMarket();
        $this->setGameStateValue("market_restocked", 1);

        $this->bga->notify->all("marketUpdated", clienttranslate('${player_name} restocks the Market'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "market" => $this->getMarketSlots(),
            "scrapped" => $scrapped,
        ]);
        return STATE_ISLAND_TURN;
    }

    private function setPendingTradingPostSelection(?int $player_id, ?string $slot_number): void
    {
        $slot_value = 0;
        if ($slot_number !== null) {
            $slot_value = (int) ltrim($slot_number, "n");
        }

        $this->setGameStateValue("pending_trading_post_player", $player_id ?? 0);
        $this->setGameStateValue("pending_trading_post_slot", $slot_value);
    }

    private function getPendingTradingPostSelection(): ?array
    {
        $player_id = (int) $this->getGameStateValue("pending_trading_post_player");
        $slot_value = (int) $this->getGameStateValue("pending_trading_post_slot");

        if ($player_id <= 0 || $slot_value <= 0) {
            return null;
        }

        return [
            "player_id" => $player_id,
            "slot_number" => "n" . $slot_value,
        ];
    }

    private function assertPendingTradingPostSelection(int $player_id, string $slot_number): void
    {
        $pending = $this->getPendingTradingPostSelection();
        if ($pending === null) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You must place a skiff on the trading post first"));
        }

        if ((int) $pending["player_id"] !== $player_id || $pending["slot_number"] !== $slot_number) {
            throw new \Bga\GameFramework\UserException(clienttranslate("This trading post exchange is no longer valid"));
        }

        $occupancies = $this->getIslandSlots();
        $slot_state = $occupancies["trading_post"][$slot_number] ?? null;
        if ($slot_state === null || $slot_state["disabled"]) {
            throw new \Bga\GameFramework\UserException(clienttranslate("This trading post is not available"));
        }
        if ($slot_state["occupying_player_id"] !== null) {
            throw new \Bga\GameFramework\UserException(clienttranslate("There is already a skiff on trading_post"));
        }
    }

    function acquireToken(string $player_id, string $token_key)
    {
        // Check if the player already owns this token
        $current_owner = $this->getTokenOwner($token_key);
        if ($current_owner === $player_id) {
            // Player already owns this token, no need to notify
            $this->mytrace("Player $player_id already owns token $token_key, skipping notification");
            return;
        }

        $taken_from_another_player = $current_owner !== null;

        self::DbQuery("REPLACE INTO unique_tokens (player_id, token_key) VALUES ('$player_id', '$token_key')");
        $token_name = $this->token_names[$token_key];
        $this->bga->notify->all("tokenAcquired", clienttranslate('${player_name} acquired the ${token_name}'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "token_name" => $token_name,
            "i18n" => ["token_name"],
            "player_id" => $player_id,
            "token_key" => $token_key,
            // Taken off another player's board rather than off the island: the front end animates
            // the token from that player's panel.
            "from_player_id" => $current_owner,
        ]);

        // Admiral ability: rewards when taking tokens from other players
        $this->applyAdmiralTokenTakenAbilities($player_id, $token_key, $taken_from_another_player);
    }

    function grantExtraTurn(string $player_id, string $phase)
    {
        self::DbQuery("REPLACE INTO extra_turns (player_id, phase) VALUES ('$player_id', '$phase')");
        $this->mytrace("Granted extra turn to player $player_id for phase $phase");
    }

    function hasExtraTurn(string $player_id, string $phase)
    {
        $result = self::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM extra_turns WHERE player_id = '$player_id' AND phase = '$phase'",
        );
        return $result > 0;
    }

    function consumeExtraTurn(string $player_id, string $phase)
    {
        self::DbQuery("DELETE FROM extra_turns WHERE player_id = '$player_id' AND phase = '$phase'");
        $this->mytrace("Consumed extra turn for player $player_id in phase $phase");
    }

    function clearExtraTurns(string $phase)
    {
        self::DbQuery("DELETE FROM extra_turns WHERE phase = '$phase'");
        $this->mytrace("Cleared all extra turns for phase $phase");
    }

    function clearIslandSlots()
    {
        $player_count = $this->getPlayersNumber();

        // Define which slots should be disabled based on player count
        $disabled_slots = [];

        // Corsair occupied placement can be used once per island phase.
        $this->setGameStateValue("corsair_occupied_placement_used", 0);

        if ($player_count < 3) {
            $disabled_slots["sailmaker"]["n1"] = true;
            $disabled_slots["trading_post"]["n1"] = true;
        }

        if ($player_count < 4) {
            $disabled_slots["blacksmith"]["n2"] = true;
            $disabled_slots["workshop"]["n2"] = true;
        }

        if ($player_count < 5) {
            $disabled_slots["trading_post"]["n2"] = true;
        }

        foreach (
            [
                ["capitol", "n1"],
                ["bank", "n1"],
                ["workshop", "n1"],
                ["workshop", "n2"],
                ["trading_post", "n1"],
                ["trading_post", "n2"],
                ["shipyard", "n1"],
                ["blacksmith", "n1"],
                ["blacksmith", "n2"],
                ["sailmaker", "n1"],
                ["deep_cove", "n1"],
                ["deep_cove", "n2"],
                ["green_flag", "n1"],
                ["tan_flag", "n1"],
                ["red_flag", "n1"],
                ["blue_flag", "n1"],
                ["market", "n1"],
                ["market", "n2"],
                ["market", "n3"],
                ["market", "n4"],
                ["market", "n5"],
            ]
            as [$slot_key, $number]
        ) {
            $disabled = isset($disabled_slots[$slot_key][$number]) ? 1 : 0;
            self::DbQuery(
                "REPLACE INTO islandslots (slot_key, number, occupying_player_id, corsair_occupying_player_id, disabled) VALUES ('$slot_key', '$number', null, null, $disabled)",
            );
        }
        $player_infos = $this->getPlayerInfo();

        foreach ($player_infos as $playerid => $player) {
            $this->playerSetResourceCount($playerid, "skiff", 3);
        }
    }

    /**
     * Record that the active player's skiff placement waits on a resource choice. It lives on the
     * server, not in a one-off notification, so a page refresh brings the choice back (see
     * IslandTurn::getArgs) and the choice is checked against the placement actually made.
     */
    function showResourceChoiceDialog(string $context, string $context_number)
    {
        $index = array_search($context, self::RESOURCE_CHOICE_CONTEXTS, true);
        if ($index === false) {
            throw new \Bga\GameFramework\SystemException("bad resource choice context: $context");
        }
        $this->setGameStateValue("pending_resource_context", $index + 1);
        $this->setGameStateValue("pending_resource_slot", (int) ltrim($context_number, "n"));
    }

    /** The pending resource choice as ["context" => ..., "number" => "nX"], or null. */
    function getPendingResourceChoice(): ?array
    {
        $index = (int) $this->getGameStateValue("pending_resource_context");
        if ($index === 0) {
            return null;
        }
        return [
            "context" => self::RESOURCE_CHOICE_CONTEXTS[$index - 1],
            "number" => "n" . (int) $this->getGameStateValue("pending_resource_slot"),
        ];
    }

    function actPlaceSkiff(string $slotname, string $number)
    {
        $player_id = self::getActivePlayerId();
        $this->mytrace("placeSkiff: $player_id slotname: $slotname number: $number");
        if ($this->getPendingTradingPostSelection() !== null) {
            throw new \Bga\GameFramework\UserException(
                clienttranslate("Finish the trading post exchange before placing another skiff"),
            );
        }
        if ($this->getPendingResourceChoice() !== null || $this->getPendingWorkshopSelection() !== null) {
            throw new \Bga\GameFramework\UserException(
                clienttranslate("Finish your current placement before placing another skiff"),
            );
        }
        $occupancies = $this->getIslandSlots();

        $this->mydump("occupancies", $occupancies);
        $this->mydump("slotnames", $occupancies[$slotname]);

        // "If a player restocks the market, they must place their Skiff on one of the newly
        // revealed cards." Restock replaced every unclaimed card, so any free market slot is new.
        $restocked = (bool) $this->getGameStateValue("market_restocked");
        if ($restocked && ($slotname !== "market" || $occupancies[$slotname][$number]["occupying_player_id"] != null)) {
            throw new \Bga\GameFramework\UserException(
                clienttranslate("After restocking you must place your skiff on a newly revealed Market card"),
            );
        }

        // Check if slot is disabled
        if ($occupancies[$slotname][$number]["disabled"]) {
            throw new \Bga\GameFramework\UserException(clienttranslate("This slot is not available for the current number of players"));
            return;
        }

        // Check if slot is already occupied
        if ($occupancies[$slotname][$number]["occupying_player_id"] != null) {
            if ($this->canUseCorsairOccupiedPlacement($player_id)) {
                $resolved = $this->resolveCorsairOccupiedPlacement($player_id, $slotname, $number);
                if ($resolved) {
                    return "islandTurnDone";
                }
                return STATE_ISLAND_TURN; // re-entered so the args offer the resource choice
            }
            throw new \Bga\GameFramework\UserException(clienttranslate("There is already a skiff on this slot"));
        }

        switch ($slotname) {
            case "capitol":
                $this->acquireToken($player_id, "first_player_token");
                $this->showResourceChoiceDialog($slotname, $number);
                return STATE_ISLAND_TURN; // re-entered so the args offer the resource choice
            case "bank":
                $this->showResourceChoiceDialog($slotname, $number);
                return STATE_ISLAND_TURN; // re-entered so the args offer the resource choice
            case "shipyard":
                $this->playerGainResources($player_id, [
                    "sail" => 2,
                    "cannonball" => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "blacksmith":
                $this->playerGainResources($player_id, [
                    "cannonball" => 2,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "sailmaker":
                $this->playerGainResources($player_id, [
                    "sail" => 3,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "market":
                $this->setGameStateValue("market_restocked", 0);
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "trading_post":
                $this->setPendingTradingPostSelection((int) $player_id, $number);
                $this->bga->notify->player($player_id, "showTradingPostDialog", "", [
                    "slot_number" => $number,
                ]);
                return null; // Dialog shown, waiting for actTradingPostExchange
            case "green_flag":
                $this->showResourceChoiceDialog($slotname, $number);
                return STATE_ISLAND_TURN; // re-entered so the args offer the resource choice
            case "tan_flag":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->drawCards($player_id);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "red_flag":
                $this->setGameStateValue("island_scraps_remaining", 1);
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                // Transition to scrap card state instead of completing turn
                return "scrapCard";
            case "deep_cove":
                $this->setGameStateValue("island_scraps_remaining", 2);
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "scrapCard";
            case "blue_flag":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->grantExtraTurn($player_id, "island");
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "workshop":
                if (empty($this->workshopUpgradeOptions((int) $player_id))) {
                    throw new \Bga\GameFramework\UserException(
                        clienttranslate("You have no ship upgrade you can afford to activate"),
                    );
                }
                $this->setPendingWorkshopSelection((int) $player_id, $number);
                return STATE_ISLAND_TURN; // re-entered so the args offer the upgrades
            default:
                throw new \Bga\GameFramework\SystemException("bad skiff slot: $slotname");
        }
    }

    function actResourcePickedInDialog(string $resource): mixed
    {
        $player_id = $this->getActivePlayerId();
        $pending = $this->getPendingResourceChoice();
        if ($pending === null) {
            throw new \Bga\GameFramework\UserException(clienttranslate("There is no resource to choose"));
        }
        if (!in_array($resource, ["sail", "cannonball", "doubloon"], true)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose a resource"));
        }
        $context = $pending["context"];
        $number = $pending["number"];
        $this->setGameStateValue("pending_resource_context", 0);
        $this->setGameStateValue("pending_resource_slot", 0);
        switch ($context) {
            case "capitol":
                $this->playerGainResources($player_id, [
                    $resource => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $context, $number);
                return "islandTurnDone";
            case "bank":
                $this->playerGainResources($player_id, [$resource => 1]);
                $this->playerGainResources($player_id, [
                    "doubloon" => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $context, $number);
                return "islandTurnDone";
            case "green_flag":
                $this->playerGainResources($player_id, [
                    $resource => 1,
                    "skiff" => -1,
                ]);
                $this->acquireToken($player_id, $context);
                $this->occupyIslandSlot($player_id, $context, $number);
                return "islandTurnDone";
            case "corsair_occupied_capitol":
                $this->finalizeCorsairOccupiedPlacement($player_id, "capitol", $number, [$resource => 1]);
                return "islandTurnDone";
            case "corsair_occupied_bank":
                $this->finalizeCorsairOccupiedPlacement($player_id, "bank", $number, ["doubloon" => 1, $resource => 1]);
                return "islandTurnDone";
            case "corsair_occupied_green_flag":
                $this->finalizeCorsairOccupiedPlacement($player_id, "green_flag", $number, [$resource => 1]);
                return "islandTurnDone";
            default:
                throw new \Bga\GameFramework\SystemException("bad context: $context");
        }
    }

    function actTradingPostExchange(
        #[JsonParam] array $resources_spent,
        #[JsonParam] array $resources_gained,
        string $slot_number,
        ?int $use_booty_card_id = null,
    ): mixed {
        $player_id = self::getActivePlayerId();
        $valid_resources = ["sail", "cannonball", "doubloon"];

        $this->assertPendingTradingPostSelection((int) $player_id, $slot_number);

        foreach ($resources_spent as $res) {
            if (!in_array($res, $valid_resources)) {
                throw new \Bga\GameFramework\SystemException("Invalid resource type in resources_spent: $res");
            }
        }
        foreach ($resources_gained as $res) {
            if (!in_array($res, $valid_resources)) {
                throw new \Bga\GameFramework\SystemException("Invalid resource type in resources_gained: $res");
            }
        }

        if ($use_booty_card_id !== null && $use_booty_card_id > 0) {
            // Booty trade: consume the token and gain 2 resources
            if (count($resources_spent) > 0) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Cannot spend resources when using a booty token"));
            }
            if (count($resources_gained) !== 2) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Must gain exactly 2 resources when using a booty token"));
            }

            $booty_card = $this->cards->getCard($use_booty_card_id);
            if (
                !$booty_card ||
                $booty_card["location"] !== "booty_player" ||
                (int) $booty_card["location_arg"] !== (int) $player_id
            ) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid booty token"));
            }
            $this->cards->moveCard($use_booty_card_id, "booty_discard", 0);
            $this->bga->notify->all(
                "bootyTokenUsed",
                clienttranslate('${player_name} uses a booty token at the trading post'),
                [
                    "player_id" => $player_id,
                    "player_name" => self::getPlayerNameById($player_id),
                    "booty_card" => $booty_card,
                    "booty_tokens" => $this->getBootyTokensForPlayer($player_id),
                    "booty_usage" => "trading post",
                ],
            );
        } else {
            // Normal trade: spend 1-2 resources, gain the same number
            if (count($resources_spent) < 1 || count($resources_spent) > 2) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Must spend 1 or 2 resources"));
            }
            if (count($resources_gained) !== count($resources_spent)) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Must gain the same number of resources as spent"));
            }

            $spend_counts = [];
            foreach ($resources_spent as $res) {
                $spend_counts[$res] = ($spend_counts[$res] ?? 0) + 1;
            }
            $player_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
            foreach ($spend_counts as $res => $count) {
                if (($player_resources[$res] ?? 0) < $count) {
                    throw new \Bga\GameFramework\UserException(clienttranslate("You don't have enough resources"));
                }
            }

            $negative_spend = [];
            foreach ($spend_counts as $res => $count) {
                $negative_spend[$res] = -$count;
            }
            $this->playerGainResources($player_id, $negative_spend);
        }

        // Gain the chosen resources
        $gain_counts = [];
        foreach ($resources_gained as $res) {
            $gain_counts[$res] = ($gain_counts[$res] ?? 0) + 1;
        }
        $this->playerGainResources($player_id, $gain_counts);

        // Spend skiff and occupy slot
        $this->playerGainResources($player_id, ["skiff" => -1]);
        $this->occupyIslandSlot($player_id, "trading_post", $slot_number);
        $this->setPendingTradingPostSelection(null, null);
        return "islandTurnDone";
    }

    function actCompletePurchases(#[JsonParam] array $cards_purchased)
    {
        $player_id = $this->getCurrentPlayerId();
        $this->mydump("cards_purchased", $cards_purchased);

        // Check if player has already completed purchases (is in pending_purchases table)
        $existing = self::getObjectFromDB(
            "SELECT player_id FROM pending_purchases WHERE player_id = '$player_id' LIMIT 1",
        );
        if ($existing) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You have already completed your purchases"));
        }

        // Validate and store purchases in pending_purchases table
        // Each item: int card_id or { card_id, use_booty_card_id?, doubloons_as_cannonballs? }
        foreach ($cards_purchased as $item) {
            $card_id = is_array($item) ? $item["card_id"] ?? null : $item;
            $use_booty_card_id = is_array($item) ? $item["use_booty_card_id"] ?? null : null;
            $doubloons_as_cannonballs = is_array($item) ? intval($item["doubloons_as_cannonballs"] ?? 0) : 0;
            $doubloons_as_sails = is_array($item) ? intval($item["doubloons_as_sails"] ?? 0) : 0;
            if ($card_id === null) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card purchase"));
            }
            $card = $this->cards->getCard($card_id);
            if (!$card || $card["location"] != "market") {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card purchase"));
            }
            // Validate Merchant substitution
            if ($doubloons_as_cannonballs > 0 || $doubloons_as_sails > 0) {
                if ($this->getPlayerCaptain($player_id) !== "merchant") {
                    throw new \Bga\GameFramework\UserException(clienttranslate("Only the Merchant can spend doubloons as other resources"));
                }
                $market_card = $this->playable_cards[$card["type"]];
                if ($doubloons_as_cannonballs > ($market_card["cost"]["cannonball"] ?? 0)) {
                    throw new \Bga\GameFramework\UserException(
                        clienttranslate("Cannot substitute more doubloons than the cannonball cost"),
                    );
                }
                if ($doubloons_as_sails > ($market_card["cost"]["sail"] ?? 0)) {
                    throw new \Bga\GameFramework\UserException(
                        clienttranslate("Cannot substitute more doubloons than the sail cost"),
                    );
                }
            }
            $use_sql = "NULL";
            if ($use_booty_card_id !== null && $use_booty_card_id > 0) {
                $use_sql = "'" . intval($use_booty_card_id) . "'";
            }
            self::DbQuery(
                "INSERT INTO pending_purchases (player_id, card_id, use_booty_card_id, doubloons_as_cannonballs, doubloons_as_sails) VALUES ('$player_id', '" .
                    intval($card_id) .
                    "', $use_sql, $doubloons_as_cannonballs, $doubloons_as_sails)",
            );
        }

        $this->mytrace("active player list before: " . implode(", ", $this->gamestate->getActivePlayerList()));

        // Transition this player to the "completed purchases" private state
        $this->gamestate->nextPrivateState($player_id, "completedPurchases");

        // Deactivate this player (they've completed their purchases)
        // The transition name "cardPurchasesDone" will be used when all players are done
        $this->gamestate->setPlayerNonMultiactive($player_id, "cardPurchasesDone");
    }

    function commitAllPurchases()
    {
        $this->mytrace("Committing all purchases");

        // Get all pending purchases (including optional booty usage and Merchant substitution)
        $pending = self::getObjectListFromDB(
            "SELECT player_id, card_id, use_booty_card_id, doubloons_as_cannonballs, doubloons_as_sails FROM pending_purchases",
        );

        // Group by player for notification
        $purchases_by_player = [];
        $purchased_card_ids = [];

        foreach ($pending as $purchase) {
            $player_id = $purchase["player_id"];
            $card_id = (int) $purchase["card_id"];
            $use_booty_card_id =
                isset($purchase["use_booty_card_id"]) &&
                $purchase["use_booty_card_id"] !== null &&
                $purchase["use_booty_card_id"] !== ""
                    ? (int) $purchase["use_booty_card_id"]
                    : null;

            $card = $this->cards->getCard($card_id);
            if (!$card || $card["location"] != "market") {
                $this->mytrace("Warning: Card $card_id is not in market, skipping");
                continue;
            }

            $market_card = $this->playable_cards[$card["type"]];
            $cost = $market_card["cost"];

            // Merchant ability: shift cannonball/sail cost to doubloon cost
            $doubloons_as_cannonballs = intval($purchase["doubloons_as_cannonballs"] ?? 0);
            if ($doubloons_as_cannonballs > 0) {
                $cost["cannonball"] = ($cost["cannonball"] ?? 0) - $doubloons_as_cannonballs;
                $cost["doubloon"] = ($cost["doubloon"] ?? 0) + $doubloons_as_cannonballs;
                if ($cost["cannonball"] <= 0) {
                    unset($cost["cannonball"]);
                }
            }
            $doubloons_as_sails = intval($purchase["doubloons_as_sails"] ?? 0);
            if ($doubloons_as_sails > 0) {
                $cost["sail"] = ($cost["sail"] ?? 0) - $doubloons_as_sails;
                $cost["doubloon"] = ($cost["doubloon"] ?? 0) + $doubloons_as_sails;
                if ($cost["sail"] <= 0) {
                    unset($cost["sail"]);
                }
            }

            // Pay for the card (optionally using booty token)
            $this->payWithOptionalBooty($player_id, $cost, $use_booty_card_id);

            // Move card to player's hand
            $this->cards->moveCard($card_id, "hand", $player_id);
            $this->bga->playerStats->inc("cards_bought", 1, (int) $player_id);

            // Track for notification
            if (!isset($purchases_by_player[$player_id])) {
                $purchases_by_player[$player_id] = [];
            }
            $purchases_by_player[$player_id][] = $card_id;
            $purchased_card_ids[] = $card_id;
        }

        // Notify all players about purchases
        $this->bga->notify->all("cardsPurchased", clienttranslate("Card purchases completed"), [
            "purchases" => $purchases_by_player,
            "purchased_card_ids" => $purchased_card_ids,
        ]);

        // Clear pending purchases table
        self::DbQuery("DELETE FROM pending_purchases");

        // Clear island slots and refill market
        $this->clearIslandSlots();
        $this->refillMarket();

        // Notify all players about the updated market
        $updated_market = $this->getMarketSlots();
        $this->bga->notify->all("marketUpdated", clienttranslate("Market has been refilled"), [
            "market" => $updated_market,
            "islandslots" => $this->getIslandSlots(),
        ]);
    }

    function argScrapCard()
    {
        $this->mytrace("argScrapCard");
        $player_id = self::getActivePlayerId();

        // Get cards from hand and discard pile
        $hand_cards = $this->cards->getPlayerHand($player_id);
        $discard_cards = $this->normalizeCardLocations($this->getPlayerDiscard($player_id));

        // Combine and prepare for scrollable stock
        $available_cards = array_merge($hand_cards, $discard_cards);

        return [
            "available_cards" => $available_cards,
        ];
    }

    function actScrapCard(int $card_id)
    {
        $this->scrapCardAndRefund($card_id, self::getActivePlayerId());
        $remaining = max(0, (int) $this->getGameStateValue("island_scraps_remaining") - 1);
        $this->setGameStateValue("island_scraps_remaining", $remaining);
        return $remaining > 0 ? "scrapAgain" : "cardScrapped";
    }

    function actSkipIslandScrap(): string
    {
        $this->setGameStateValue("island_scraps_remaining", 0);
        return "cardScrapped";
    }
}
