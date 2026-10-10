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
 * Captains: their passive abilities and their captain cards (Rally the Flags, Extortion, Barter, Timely Trading, Boarding Party, Hunt the Bounty, and the cards that pick from hand or discard).
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait CaptainAbilities
{
    function assignCaptainToPlayer($player_id, $captain_key)
    {
        $sql = "INSERT INTO player_captain (player_id, captain_key) VALUES ('$player_id', '$captain_key')";
        self::DbQuery($sql);
    }

    function getPlayerCaptain($player_id)
    {
        $sql = "SELECT captain_key FROM player_captain WHERE player_id = '$player_id'";
        return self::getUniqueValueFromDb($sql);
    }

    private function corsairOccupiedPlacementBenefits(): array
    {
        return [
            "capitol" => ["fixed" => [], "choice_count" => 1],
            "bank" => ["fixed" => ["doubloon" => 1], "choice_count" => 1],
            "green_flag" => ["fixed" => [], "choice_count" => 1],
            "shipyard" => ["fixed" => ["sail" => 2, "cannonball" => 1], "choice_count" => 0],
            "blacksmith" => ["fixed" => ["cannonball" => 2], "choice_count" => 0],
            "sailmaker" => ["fixed" => ["sail" => 3], "choice_count" => 0],
        ];
    }

    private function corsairOccupiedPlacementSlotNames(): array
    {
        return array_keys($this->corsairOccupiedPlacementBenefits());
    }

    private function corsairOccupiedPlacementBenefitForSlot(string $slot_name): ?array
    {
        $benefits = $this->corsairOccupiedPlacementBenefits();
        return $benefits[$slot_name] ?? null;
    }

    private function canUseCorsairOccupiedPlacement(string $player_id): bool
    {
        if ($this->getPlayerCaptain($player_id) !== "corsair") {
            return false;
        }
        return (int) $this->getGameStateValue("corsair_occupied_placement_used") === 0;
    }

    function getPlayerFlagCounts(): array
    {
        $counts = [];
        foreach ($this->loadPlayersBasicInfos() as $player_id => $_) {
            $counts[$player_id] = 0;
        }

        $flag_keys = array_flip($this->flagTokenKeys());
        foreach ($this->getUniqueTokens() as $token_key => $player_id) {
            if ($player_id !== null && isset($flag_keys[$token_key])) {
                $counts[$player_id] = ($counts[$player_id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    function applyPirateQueenIslandPhaseStartAbilities(): void
    {
        $flag_counts = $this->getPlayerFlagCounts();

        foreach ($this->loadPlayersBasicInfos() as $player_id => $_) {
            if ($this->getPlayerCaptain($player_id) !== "pirate_queen") {
                continue;
            }

            $player_flags = $flag_counts[$player_id] ?? 0;
            $other_max = 0;
            foreach ($flag_counts as $other_id => $count) {
                if ($other_id != $player_id) {
                    $other_max = max($other_max, $count);
                }
            }

            if ($player_flags <= $other_max) {
                continue;
            }

            $this->scoreInfamy(
                $player_id,
                2,
                "captain",
                clienttranslate(
                    '${player_name}\'s Pirate Queen ability: gains 2 infamy for controlling the most flags',
                ),
            );
        }
    }

    function getRebelPlayerId(): ?string
    {
        foreach ($this->loadPlayersBasicInfos() as $player_id => $_) {
            if ($this->getPlayerCaptain($player_id) === "rebel") {
                return (string) $player_id;
            }
        }

        return null;
    }

    function applyRebelIslandPhaseStartDraw(): ?string
    {
        $rebel_id = $this->getRebelPlayerId();
        if ($rebel_id === null) {
            return null;
        }

        $this->drawCards($rebel_id, 1);
        $this->bga->notify->all(
            "log",
            clienttranslate('${player_name}\'s Rebel ability: draws 1 additional card'),
            [
                "player_name" => $this->getPlayerNameById($rebel_id),
            ],
        );

        return $rebel_id;
    }

    function applyAdmiralTokenTakenAbilities(
        string $player_id,
        string $token_key,
        bool $taken_from_another_player,
    ): void {
        if (!$taken_from_another_player || $this->getPlayerCaptain($player_id) !== "admiral") {
            return;
        }

        if ($token_key === "first_player_token") {
            $this->drawCards($player_id);
            $this->bga->notify->all(
                "log",
                clienttranslate(
                    '${player_name}\'s Admiral ability: draws a card for taking the first player token',
                ),
                [
                    "player_name" => $this->getPlayerNameById($player_id),
                ],
            );
            return;
        }

        if (str_ends_with($token_key, "_flag")) {
            $this->scoreInfamy(
                $player_id,
                1,
                "captain",
                clienttranslate('${player_name}\'s Admiral ability: gains 1 infamy for taking a flag'),
            );
        }
    }

    private function finalizeCorsairOccupiedPlacement(
        string $player_id,
        string $slot_name,
        string $number,
        array $resources,
    ): void {
        $this->playerGainResources($player_id, $this->sum_array_by_key($resources, ["skiff" => -1]));
        $this->occupyIslandSlotAsCorsairOverlay($player_id, $slot_name, $number);
        $this->setGameStateValue("corsair_occupied_placement_used", 1);
        $this->bga->notify->all(
            "log",
            clienttranslate(
                '${player_name} uses Corsair to place on occupied ${slot_label} and gains only resources shown there',
            ),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "slot_name" => $slot_name,
                "slot_label" => $this->islandSlotLabel($slot_name),
                "i18n" => ["slot_label"],
                "slot_number" => $number,
            ],
        );
    }

    private function resolveCorsairOccupiedPlacement(string $player_id, string $slot_name, string $number): bool
    {
        $benefit = $this->corsairOccupiedPlacementBenefitForSlot($slot_name);
        if ($benefit === null) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Corsair can only use occupied spaces that show resources"));
        }

        if ((int) ($benefit["choice_count"] ?? 0) > 0) {
            $this->showResourceChoiceDialog("corsair_occupied_" . $slot_name, $number);
            return false;
        }

        $this->finalizeCorsairOccupiedPlacement($player_id, $slot_name, $number, $benefit["fixed"] ?? []);
        return true;
    }

    function processCaptainAbility(string $ability): CardActionOutcome|int
    {
        $this->mytrace("processing captain ability: $ability");
        $player_id = $this->getActivePlayerId();
        $this->mytrace("player id: $player_id");
        switch ($ability) {
            case "government_funding":
                return $this->processGovernmentFunding($player_id);
            case "inspire":
                return $this->processInspire($player_id);
            case "rally_the_flags":
                return $this->processRallyTheFlags($player_id);
            case "extortion":
                return $this->processExtortion($player_id);
            case "barter":
                return $this->processBarter($player_id);
            case "timely_trading":
                return $this->processTimelyTrading($player_id);
            case "boarding_party":
                return $this->processBoardingParty($player_id);
            case "hunt_the_bounty":
                return $this->processHuntTheBounty($player_id);
            case "retaliation":
            case "improvisation":
            case "spyglass":
            case "unearth_riches":
                return $this->beginCaptainCard($player_id, $ability);
            default:
                throw new \Bga\GameFramework\SystemException("Unknown captain ability: $ability");
        }
    }

    protected function captainCardOptions(string $player_id, string $ability): array
    {
        $discard = $this->normalizeCardLocations($this->getPlayerDiscard($player_id));
        if ($ability === "retaliation") {
            return array_values(array_filter(
                array_merge($this->cards->getCardsInLocation("hand", $player_id), $discard),
                fn($c) => $this->playable_cards[$c["type"]]["category"] === "damage",
            ));
        }
        if ($ability === "improvisation") {
            return array_values(array_filter($discard, function ($c) {
                $definition = $this->playable_cards[$c["type"]];
                // All starting ship cards are sailing, pivot, or firing cards.
                return $definition["category"] === "starting_card" ||
                    !empty(array_intersect($definition["type"] ?? [], ["sailing", "pivot", "firing"]));
            }));
        }
        return array_values($this->cards->getCardsInLocation(
            $ability === "spyglass" ? "spyglass" : "captain_reward", $player_id,
        ));
    }

    protected function beginCaptainCard(string $player_id, string $ability): CardActionOutcome|int
    {
        if ($ability === "retaliation" || $ability === "improvisation") {
            if (empty($this->captainCardOptions($player_id, $ability))) {
                if ($ability === "improvisation") {
                    $this->drawCards($player_id);
                }
                return new CardActionOutcome();
            }
        } elseif ($ability === "spyglass") {
            $deck = $this->playerDeckName($player_id);
            $cards = $this->cards->pickCardsForLocation(3, $deck, "spyglass", (int) $player_id, true) ?? [];
            if (count($cards) < 3 && $this->cards->countCardInLocation($this->playerDiscardName($player_id)) > 0) {
                $this->cards->moveAllCardsInLocation($this->playerDiscardName($player_id), $deck);
                $this->cards->shuffle($deck);
                $this->bga->notify->player((int) $player_id, "deckReshuffled", "", [
                    "player_id" => $player_id, "deck_size" => $this->cards->countCardInLocation($deck),
                ]);
                $this->cards->pickCardsForLocation(3 - count($cards), $deck, "spyglass", (int) $player_id, true);
            }
            if (empty($this->captainCardOptions($player_id, $ability))) {
                return new CardActionOutcome();
            }
        } else {
            $ship = $this->seaboard->findObject("player_ship", $this->activeShipArg($player_id));
            if ($ship === null) {
                throw new \Bga\GameFramework\SystemException("Treasure Seeker ship is missing");
            }
            $rock = false;
            foreach ($this->seaboard->getSurroundingPositions($ship["x"], $ship["y"]) as $pos) {
                $rock = $rock || !empty($this->seaboard->getObjectsOfTypes($pos["x"], $pos["y"], ["rock"]));
            }
            if (!$rock) {
                return new CardActionOutcome();
            }
            $token = $this->drawBootyToken((int) $player_id, "captain_reward");
            if ($token === null) {
                return new CardActionOutcome(); // The supply and its discard can both be exhausted by held tokens.
            }
            $this->bga->notify->all("log", clienttranslate('${player_name} reveals a shipwreck token for Unearth Riches'), [
                "player_name" => $this->getPlayerNameById((int) $player_id), "token" => $token,
                "resources" => $this->getBootyTokenConfigByTypeArg((int) $token["type_arg"])["resources"],
            ]);
        }
        return STATE_CAPTAIN_CARD;
    }

    function argCaptainCard(): array
    {
        $player_id = $this->getActivePlayerId();
        $card = $this->cards->getCard((int) $this->getGameStateValue("pending_captain_card"));
        if (!$card || $card["location"] !== $this->playerDiscardName($player_id)) {
            throw new \Bga\GameFramework\SystemException("Pending captain card is missing from the active player's discard");
        }
        $ability = $this->playable_cards[$card["type"]]["actions"][0]["ability"];
        $options = $this->captainCardOptions($player_id, $ability);
        $private = ["available_cards" => $options];
        if ($ability === "unearth_riches") {
            $private["resources"] = $this->getBootyTokenConfigByTypeArg((int) $options[0]["type_arg"])["resources"];
        }
        // Which ship acts, for the client's board preview (Retaliation's shot).
        return ["ability" => $ability, "ship" => $this->activeShipArg($player_id), "_private" => [$player_id => $private]];
    }

    function actResolveCaptainCard(array $choices, array $decisions = [], ?int $use_booty_card_id = null): mixed
    {
        $player_id = $this->getActivePlayerId();
        $args = $this->argCaptainCard();
        $ability = $args["ability"];
        $options = $args["_private"][$player_id]["available_cards"];
        $by_id = array_column($options, null, "id");
        $actions = [];
        if ($ability === "retaliation" || $ability === "improvisation") {
            $selected = $choices["card_id"] ?? null;
            // Retaliation only ever offers damage cards, and every damage card is the same card,
            // so there is nothing to choose between them: take the first, from the hand if any.
            if ($ability === "retaliation" && $selected === null) {
                $selected = (int) $options[0]["id"];
            }
            if (!is_int($selected) || !isset($by_id[$selected])) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Choose an available card"));
            }
            $chosen = $by_id[$selected];
            if ($ability === "retaliation") {
                $fire = $choices["fire"] ?? null;
                if (!in_array($fire, ["fire left", "fire right", "skip"], true)) {
                    throw new \Bga\GameFramework\UserException(clienttranslate("Choose a firing side or skip firing"));
                }
                $this->scrapCardAndRefund($selected, $player_id);
                if ($fire !== "skip") {
                    $actions = [["action" => "fire", "range" => 3]];
                    $decisions = [$fire];
                }
            } else {
                $actions = $this->upgradedCardActions($this->playable_cards[$chosen["type"]], $player_id);
            }
        } elseif ($ability === "spyglass") {
            // The first id is kept; remaining ids are ordered topmost first.
            $order = $choices["order"] ?? [];
            if (!is_array($order) || count($order) !== count($by_id) ||
                array_filter($order, fn($id) => !is_int($id) || !isset($by_id[$id])) ||
                count(array_unique($order)) !== count($order)) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Choose one card to keep and order all remaining cards"));
            }
            $kept = $by_id[array_shift($order)];
            $this->cards->moveCard($kept["id"], "hand", (int) $player_id);
            foreach (array_reverse($order) as $id) {
                $this->cards->insertCardOnExtremePosition($id, $this->playerDeckName($player_id), true);
            }
            $kept["location"] = "hand";
            $kept["location_arg"] = (int) $player_id;
            $this->bga->notify->player((int) $player_id, "cardDrawn", clienttranslate("You keep a card from Spyglass"), [
                "player_id" => $player_id, "cards" => [$kept], "num_cards" => 1,
                "deck_size" => $this->cards->countCardInLocation($this->playerDeckName($player_id)),
            ]);
        } elseif ($ability === "unearth_riches") {
            $resources = $args["_private"][$player_id]["resources"];
            if (isset($resources["choice"])) {
                $resource = $choices["resource"] ?? null;
                if (!in_array($resource, ["sail", "cannonball", "doubloon"], true)) {
                    throw new \Bga\GameFramework\UserException(clienttranslate("Choose a resource"));
                }
                $resources[$resource] = ($resources[$resource] ?? 0) + $resources["choice"];
                unset($resources["choice"]);
            }
            $this->playerGainResources($player_id, $resources);
            $this->cards->moveCard($options[0]["id"], "booty_discard");
        } else {
            throw new \Bga\GameFramework\SystemException("Unexpected pending captain ability: $ability");
        }
        $card_id = (int) $this->getGameStateValue("pending_captain_card");
        $card = $this->cards->getCard($card_id);
        $this->setGameStateValue("pending_captain_card", 0);
        $result = $this->resolvePlayedCard((int) $card["type"], $card_id, $decisions, $use_booty_card_id, $actions);
        if ($ability === "improvisation") {
            $this->setGameStateValue("pending_card_flag_type", isset($this->playable_cards[$chosen["type"]]["flag"]) ? (int) $chosen["type"] : 0);
        }
        return $result;
    }

    function processGovernmentFunding(string $player_id): CardActionOutcome
    {
        $standard_resources = array_filter($this->resource_types, fn($r) => $r !== "skiff");
        $current = $this->getGameResourcesHierarchical((int) $player_id)[$player_id] ?? [];
        $to_gain = [];
        foreach ($standard_resources as $r) {
            if (($current[$r] ?? 0) === 0) {
                $to_gain[$r] = 1;
            }
        }
        if (empty($to_gain)) {
            $this->drawCards($player_id);
            $this->bga->notify->all(
                "log",
                clienttranslate(
                    '${player_name}\'s Government Funding: draws a card (already owns all resource types)',
                ),
                ["player_name" => $this->getPlayerNameById($player_id)],
            );
        } else {
            $this->playerGainResources($player_id, $to_gain);
            $this->bga->notify->all(
                "log",
                clienttranslate('${player_name}\'s Government Funding: gains 1 of each resource type not owned'),
                ["player_name" => $this->getPlayerNameById($player_id)],
            );
        }
        return new CardActionOutcome();
    }

    function processInspire(string $player_id): CardActionOutcome
    {
        $discard_cards = $this->getPlayerDiscard($player_id);
        $damage_cards = array_filter(
            $discard_cards,
            fn($c) => ($this->playable_cards[$c["type"]]["category"] ?? "") === "damage",
        );
        if (empty($damage_cards)) {
            $this->scoreInfamy(
                $player_id,
                1,
                "captain",
                clienttranslate('${player_name}\'s Inspire: gains 1 infamy'),
            );
            $this->drawCards($player_id);
        } else {
            $damage_card = reset($damage_cards);
            $this->scrapCardAndRefund((int) $damage_card["id"], $player_id);
        }
        return new CardActionOutcome();
    }

    protected function getRallyTheFlagsOptions(string $player_id): array
    {
        $flag_keys = $this->flagTokenKeys();
        $tokens = $this->getUniqueTokens();
        $available = [];

        foreach ($flag_keys as $fk) {
            if (!isset($tokens[$fk]) || $tokens[$fk] === null) {
                $available[] = $fk;
            }
        }

        $ship = $this->seaboard->findObject("player_ship", $this->activeShipArg($player_id));
        if ($ship) {
            foreach ($this->seaboard->getSurroundingPositions($ship["x"], $ship["y"]) as $pos) {
                foreach ($this->seaboard->getObjectsOfTypes($pos["x"], $pos["y"], ["player_ship"]) as $obj) {
                    $other_id = self::shipOwner($obj["arg"]);
                    if ($other_id === (string) $player_id) {
                        continue; // your own other ship (2 Ship Variant)
                    }
                    foreach ($flag_keys as $fk) {
                        if (isset($tokens[$fk]) && $tokens[$fk] == $other_id) {
                            $available[] = $fk;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($available));
    }

    function processRallyTheFlags(string $player_id): CardActionOutcome|int
    {
        if (empty($this->getRallyTheFlagsOptions($player_id))) {
            return new CardActionOutcome();
        }
        return STATE_RALLY_THE_FLAGS;
    }

    /**
     * A flag can come off the board or off a neighbouring player's panel, and the player needs to
     * know which before they choose, so each option carries its current holder.
     */
    function argRallyTheFlagsChooseFlag(): array
    {
        $tokens = $this->getUniqueTokens();
        $flags = [];
        foreach ($this->getRallyTheFlagsOptions(self::getActivePlayerId()) as $flag_key) {
            $owner = $tokens[$flag_key] ?? null;
            $flags[] = [
                "flag_key" => $flag_key,
                "owner_id" => $owner,
                "owner_name" => $owner === null ? null : $this->getPlayerNameById($owner),
            ];
        }
        return ["available_flags" => $flags];
    }

    function actRallyTheFlagsChooseFlag(string $flag_key): mixed
    {
        $player_id = self::getActivePlayerId();
        if ($this->getPlayerCaptain($player_id) !== "pirate_queen") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Pirate Queen can use this action"));
        }
        if (!in_array($flag_key, $this->getRallyTheFlagsOptions($player_id))) {
            throw new \Bga\GameFramework\UserException(clienttranslate("That flag is not available to take"));
        }

        $this->acquireToken($player_id, $flag_key);

        $flag_counts = $this->getPlayerFlagCounts();
        $my_flags = $flag_counts[$player_id] ?? 0;
        $others = array_filter($flag_counts, fn($id) => $id != $player_id, ARRAY_FILTER_USE_KEY);
        if ($my_flags > 0 && $my_flags > (empty($others) ? 0 : max($others))) {
            $this->scoreInfamy(
                $player_id,
                1,
                "captain",
                clienttranslate('${player_name}\'s Rally the Flags: gains 1 infamy for controlling the most flags'),
            );
        }

        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    /** "Use the action of each flag you control in any order." The player picks the order. */
    function processExtortion(string $player_id): int
    {
        $tokens = $this->getUniqueTokens();
        $pending = 0;
        foreach (self::EXTORTION_FLAG_BITS as $flag => $bit) {
            if (isset($tokens[$flag . "_flag"]) && $tokens[$flag . "_flag"] == $player_id) {
                $pending |= $bit;
            }
        }

        if ($pending === 0) {
            return STATE_NEXT_PLAYER_SEA_PHASE;
        }
        $this->setGameStateValue("extortion_pending_flags", $pending);
        return STATE_EXTORTION;
    }

    function argExtortion(): array
    {
        $pending = (int) $this->getGameStateValue("extortion_pending_flags");
        $player_id = self::getActivePlayerId();
        $result = [
            "pending_flags" => array_values(array_filter(
                array_keys(self::EXTORTION_FLAG_BITS),
                fn($flag) => (bool) ($pending & self::EXTORTION_FLAG_BITS[$flag]),
            )),
        ];
        if ($pending & self::EXTORTION_FLAG_BITS["red"]) {
            $result["available_cards"] = array_merge(
                array_values($this->cards->getPlayerHand($player_id)),
                array_values($this->normalizeCardLocations($this->getPlayerDiscard($player_id))),
            );
        }
        return $result;
    }

    /** Resolve one of the pending flags, chosen by the player; the rest stay pending. */
    function actExtortionUseFlag(string $flag, string $resource = ""): mixed
    {
        $player_id = self::getActivePlayerId();
        $this->requirePendingExtortionFlag($flag);
        switch ($flag) {
            case "green":
                if (!in_array($resource, ["sail", "cannonball", "doubloon"], true)) {
                    throw new \Bga\GameFramework\UserException(clienttranslate("Choose a resource"));
                }
                $this->playerGainResources($player_id, [$resource => 1]);
                $this->bga->notify->all("log", clienttranslate('${player_name}\'s Extortion: gains 1 ${resource} (Green Purser\'s Flag)'), [
                    "player_name" => $this->getPlayerNameById($player_id),
                    "resource" => "[$resource]", // shown as its icon
                ]);
                break;
            case "tan":
                $this->drawCards($player_id, 1);
                $this->bga->notify->all("log", clienttranslate('${player_name}\'s Extortion: draws a card (Tan Bosun\'s Flag)'), [
                    "player_name" => $this->getPlayerNameById($player_id),
                ]);
                break;
            case "blue":
                $this->grantExtraTurn($player_id, "island");
                $this->bga->notify->all("log", clienttranslate('${player_name}\'s Extortion: gains an extra island turn (Blue Sailor\'s Flag)'), [
                    "player_name" => $this->getPlayerNameById($player_id),
                ]);
                break;
            default:
                throw new \Bga\GameFramework\UserException(clienttranslate("Choose a card to scrap for the Red Shipwright's Flag"));
        }
        return $this->clearExtortionFlag($flag);
    }

    function actExtortionScrapCard(int $card_id): mixed
    {
        $player_id = self::getActivePlayerId();
        $this->requirePendingExtortionFlag("red");
        $this->scrapCardAndRefund($card_id, $player_id);
        return $this->clearExtortionFlag("red");
    }

    private function requirePendingExtortionFlag(string $flag): void
    {
        $bit = self::EXTORTION_FLAG_BITS[$flag] ?? 0;
        if (!$bit || !((int) $this->getGameStateValue("extortion_pending_flags") & $bit)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You do not have that flag effect pending"));
        }
    }

    /** Back to the choice for whatever is left, or on with the sea phase. */
    private function clearExtortionFlag(string $flag): mixed
    {
        $pending = (int) $this->getGameStateValue("extortion_pending_flags") & ~self::EXTORTION_FLAG_BITS[$flag];
        $this->setGameStateValue("extortion_pending_flags", $pending);
        return $pending === 0 ? STATE_NEXT_PLAYER_SEA_PHASE : STATE_EXTORTION;
    }

    function actSkipExtortion(): mixed
    {
        $this->setGameStateValue("extortion_pending_flags", 0);
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function actSkipRallyTheFlags(): mixed
    {
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function processBarter(string $player_id): int
    {
        return STATE_BARTER;
    }

    function argBarter(): array
    {
        $player_id = $this->getActivePlayerId();
        $resources = $this->getGameResourcesHierarchical((int) $player_id)[$player_id];
        $infamy = $this->getPlayerInfamy($player_id);
        return ["resources" => $resources, "infamy" => $infamy];
    }

    function actBarterExchange(string $resource, string $direction, ?int $use_booty_card_id = null): mixed
    {
        $player_id = $this->getActivePlayerId();
        if ($this->getPlayerCaptain($player_id) !== "merchant") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Merchant can use Barter"));
        }
        if (!isset(self::BARTER_RATES[$resource])) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid resource for Barter"));
        }
        $infamy_amount = self::BARTER_RATES[$resource];
        if ($direction === "resource_to_infamy") {
            $this->payWithOptionalBooty((int) $player_id, [$resource => 1], $use_booty_card_id);
            $this->scoreInfamy($player_id, $infamy_amount, "captain",
                clienttranslate('${player_name}\'s Barter: gains ${score_increment} infamy'),
            );
        } elseif ($direction === "infamy_to_resource") {
            $current_infamy = $this->getPlayerInfamy($player_id);
            if ($current_infamy < $infamy_amount) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Not enough infamy for this exchange"));
            }
            $this->scoreInfamy($player_id, -$infamy_amount, "captain",
                clienttranslate('${player_name}\'s Barter: spends infamy'),
            );
            $this->playerGainResources($player_id, [$resource => 1]);
        } else {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid direction for Barter"));
        }
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function actSkipBarter(): mixed
    {
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function processTimelyTrading(string $player_id): int
    {
        return STATE_TIMELY_TRADING;
    }

    function argTimelyTrading(): array
    {
        $player_id = $this->getActivePlayerId();
        $market = array_values($this->cards->getCardsInLocation("market"));
        $resources = $this->getGameResourcesHierarchical((int) $player_id)[$player_id];
        return ["market" => $market, "resources" => $resources];
    }

    function actTimelyTradingGainDoubloons(): mixed
    {
        $player_id = $this->getActivePlayerId();
        if ($this->getPlayerCaptain($player_id) !== "merchant") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Merchant can use Timely Trading"));
        }
        $this->playerGainResources($player_id, ["doubloon" => 2]);
        $this->bga->notify->all("log", clienttranslate('${player_name}\'s Timely Trading: gains 2 doubloons'), [
            "player_name" => $this->getPlayerNameById($player_id),
        ]);
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function actTimelyTradingPurchaseCard(
        int $card_id,
        int $doubloons_as_cannonballs = 0,
        int $doubloons_as_sails = 0,
        ?int $use_booty_card_id = null,
    ): mixed {
        $player_id = $this->getActivePlayerId();
        if ($this->getPlayerCaptain($player_id) !== "merchant") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Merchant can use Timely Trading"));
        }
        $card = $this->cards->getCard($card_id);
        if (!$card || $card["location"] !== "market") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card"));
        }
        $market_card = $this->playable_cards[$card["type"]];
        $cost = $market_card["cost"] ?? [];

        if ($doubloons_as_cannonballs > 0) {
            if ($doubloons_as_cannonballs > ($cost["cannonball"] ?? 0)) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Cannot substitute more doubloons than the cannonball cost"));
            }
            $cost["cannonball"] = ($cost["cannonball"] ?? 0) - $doubloons_as_cannonballs;
            if ($cost["cannonball"] <= 0) {
                unset($cost["cannonball"]);
            }
            $cost["doubloon"] = ($cost["doubloon"] ?? 0) + $doubloons_as_cannonballs;
        }
        if ($doubloons_as_sails > 0) {
            if ($doubloons_as_sails > ($cost["sail"] ?? 0)) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Cannot substitute more doubloons than the sail cost"));
            }
            $cost["sail"] = ($cost["sail"] ?? 0) - $doubloons_as_sails;
            if ($cost["sail"] <= 0) {
                unset($cost["sail"]);
            }
            $cost["doubloon"] = ($cost["doubloon"] ?? 0) + $doubloons_as_sails;
        }

        $this->payWithOptionalBooty((int) $player_id, $cost, $use_booty_card_id);
        $this->cards->moveCard($card_id, "hand", $player_id);
        $this->bga->playerStats->inc("cards_bought", 1, (int) $player_id);

        $this->refillMarket();
        $updated_market = $this->getMarketSlots();

        $this->bga->notify->all("cardsPurchased", clienttranslate('${player_name}\'s Timely Trading: purchases a card'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "purchases" => [$player_id => [$card_id]],
            "purchased_card_ids" => [$card_id],
        ]);
        $this->bga->notify->all("marketUpdated", clienttranslate("Market updated"), [
            "market" => $updated_market,
        ]);
        $card["location"] = "hand";
        $card["location_arg"] = (int) $player_id;
        $this->bga->notify->player((int) $player_id, "cardDrawn", "", [
            "player_id" => $player_id, "cards" => [$card], "num_cards" => 1,
            "deck_size" => $this->cards->countCardInLocation($this->playerDeckName($player_id)),
        ]);
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function actSkipTimelyTrading(): mixed
    {
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function processBoardingParty(string $player_id): CardActionOutcome|int
    {
        $targets = $this->getBoardingPartyTargets($player_id);
        if (empty($targets)) {
            return new CardActionOutcome();
        }
        return STATE_BOARDING_PARTY;
    }

    protected function getBoardingPartyTargets(string $player_id): array
    {
        $ship = $this->seaboard->findObject("player_ship", $this->activeShipArg($player_id));
        if (!$ship) return [];
        $targets = [];
        foreach ($this->seaboard->getSurroundingPositions($ship["x"], $ship["y"]) as $pos) {
            foreach ($this->seaboard->getObjectsOfTypes($pos["x"], $pos["y"], ["player_ship"]) as $obj) {
                $other_id = self::shipOwner($obj["arg"]);
                // Both of an opponent's ships can be alongside: they are still one player to board.
                if ($other_id === (string) $player_id || isset($targets[$other_id])) continue;
                $resources = $this->getGameResourcesHierarchical((int) $other_id)[$other_id] ?? [];
                $stealable = array_filter(
                    array_intersect_key($resources, array_flip(["sail", "cannonball", "doubloon"])),
                    fn($v) => $v > 0,
                );
                $booty_count = $this->cards->countCardInLocation("booty_player", $other_id);
                if (!empty($stealable) || $booty_count > 0) {
                    $targets[$other_id] = [
                        "player_id" => $other_id,
                        "resources" => $stealable,
                        "booty_token_count" => $booty_count,
                    ];
                }
            }
        }
        return array_values($targets);
    }

    function argBoardingParty(): array
    {
        $player_id = $this->getActivePlayerId();
        return ["targets" => $this->getBoardingPartyTargets($player_id)];
    }

    function actBoardingPartySteal(string $target_player_id, string $item): mixed
    {
        $player_id = $this->getActivePlayerId();
        if ($this->getPlayerCaptain($player_id) !== "corsair") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Corsair can use Boarding Party"));
        }
        $targets = $this->getBoardingPartyTargets($player_id);
        $valid_ids = array_column($targets, "player_id");
        if (!in_array($target_player_id, $valid_ids)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid target for Boarding Party"));
        }
        if ($item === "booty_token") {
            $booty_cards = $this->cards->getCardsInLocation("booty_player", $target_player_id);
            if (empty($booty_cards)) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Target has no booty tokens"));
            }
            $card = reset($booty_cards);
            $this->cards->moveCard((int) $card["id"], "booty_player", $player_id);
        } else {
            if (!in_array($item, ["sail", "cannonball", "doubloon"])) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid resource for Boarding Party"));
            }
            $target_resources = $this->getGameResourcesHierarchical((int) $target_player_id)[$target_player_id] ?? [];
            if (($target_resources[$item] ?? 0) === 0) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Target does not have that resource"));
            }
            $this->playerGainResources($target_player_id, [$item => -1]);
            $this->playerGainResources($player_id, [$item => 1]);
        }
        $this->scoreInfamy($player_id, 1, "captain", clienttranslate('${player_name}\'s Boarding Party: gains 1 infamy'));
        $this->bga->notify->all("log",
            clienttranslate('${player_name} uses Boarding Party and steals from ${target_name}'),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "target_name" => $this->getPlayerNameById((int) $target_player_id),
            ],
        );
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function actSkipBoardingParty(): mixed
    {
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function processHuntTheBounty(string $player_id): int
    {
        return STATE_HUNT_THE_BOUNTY;
    }

    protected function getHuntTheBountyTargets(string $player_id): array
    {
        $targets = [];
        foreach ($this->getPlayerInfo() as $other_id => $info) {
            if ((string) $other_id === $player_id) continue;
            foreach ([(string) $other_id => $info["player_ship"], self::secondShipArg($other_id) => $info["player_ship2"]] as $ship => $name) {
                if ($ship === self::secondShipArg($other_id) && $name === null) continue;
                $targets[] = [
                    "ship" => (string) $ship,
                    "ship_name" => $name,
                    "player_id" => (string) $other_id,
                    "player_name" => $info["player_name"],
                ];
            }
        }
        return $targets;
    }

    function argHuntTheBounty(): array
    {
        return ["targets" => $this->getHuntTheBountyTargets($this->getActivePlayerId())];
    }

    function actHuntTheBountyChooseTarget(string $target_ship): mixed
    {
        $player_id = $this->getActivePlayerId();
        if ($this->getPlayerCaptain($player_id) !== "corsair") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Corsair can use Hunt the Bounty"));
        }
        $targets = $this->getHuntTheBountyTargets($player_id);
        $index = array_search($target_ship, array_column($targets, "ship"), true);
        if ($index === false) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid target for Hunt the Bounty"));
        }
        $target = $targets[$index];
        // The integer state value stores the owner: positive for their first ship, negative for their second.
        $this->setGameStateValue("hunt_the_bounty_target",
            (int) $target["player_id"] * ($target_ship === $target["player_id"] ? 1 : -1));
        $this->bga->notify->all("log",
            clienttranslate('${player_name} declares ${target_name} (${ship_name}) as their Hunt the Bounty target'),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "target_name" => $target["player_name"],
                "ship_name" => $target["ship_name"],
                "i18n" => ["ship_name"],
            ],
        );
        return $this->huntTheBountyDone($player_id);
    }

    private function scoreHuntTheBounty(string $player_id, string $hit_ship): void
    {
        $target = (int) $this->getGameStateValue("hunt_the_bounty_target");
        $target_ship = $target < 0 ? self::secondShipArg(-$target) : (string) $target;
        if ($target !== 0 && $hit_ship === $target_ship && $this->getPlayerCaptain($player_id) === "corsair") {
            $this->scoreInfamy(
                $player_id,
                1,
                "captain",
                clienttranslate('${player_name}\'s Hunt the Bounty: gains 1 infamy'),
            );
        }
    }

    /**
     * "You may play another card immediately." - the player is asked, rather than pushed straight
     * back into a sea turn, and is not asked at all with an empty hand.
     */
    private function huntTheBountyDone(string $player_id): int
    {
        return $this->cards->countCardInLocation("hand", $player_id) > 0
            ? STATE_HUNT_THE_BOUNTY_EXTRA_PLAY
            : STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function actHuntTheBountyPlayAnother(): int
    {
        $player_id = $this->getActivePlayerId();
        if ($this->cards->countCardInLocation("hand", $player_id) == 0) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You have no cards left to play"));
        }
        return STATE_SEA_TURN;
    }

    function actSkipHuntTheBountyExtraPlay(): int
    {
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    /** @see \Bga\Games\SeasOfHavoc\States\SeaTurn::onEnteringState() */
    function assertActivePlayerHasCards(int $player_id): void
    {
        if ($this->cards->countCardInLocation("hand", $player_id) == 0) {
            throw new \Bga\GameFramework\SystemException(
                "Sea turn started for player $player_id with an empty hand",
            );
        }
    }

    function actSkipHuntTheBounty(): mixed
    {
        return $this->huntTheBountyDone($this->getActivePlayerId());
    }

    function argRebelDiscard()
    {
        $this->mytrace("argRebelDiscard");
        $player_id = self::getActivePlayerId();

        return [
            "available_cards" => $this->cards->getPlayerHand($player_id),
        ];
    }

    function actRebelDiscardCard(int $card_id)
    {
        $this->mytrace("actRebelDiscardCard: card_id=$card_id");
        $player_id = self::getActivePlayerId();

        if ($this->getPlayerCaptain($player_id) !== "rebel") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Rebel can use this action"));
        }

        $this->discardCards($player_id, [$card_id]);

        $first_player_token_owner = $this->getFirstPlayerTokenOwner();
        if ($first_player_token_owner === null) {
            throw new \Bga\GameFramework\SystemException("No player has the first player token - this should never happen");
        }

        $this->gamestate->changeActivePlayer($first_player_token_owner);
        $this->giveExtraTime((int) $first_player_token_owner);
        return "cardDiscarded";
    }
}
