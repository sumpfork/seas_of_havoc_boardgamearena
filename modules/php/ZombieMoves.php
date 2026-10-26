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
 * Zombie turns (BGA "random zombie", level 1): a random legal skiff placement in the Island Phase,
 * a random card with random, affordable choices in the Sea Phase, the claimed cards it can afford,
 * and for optional abilities a random pick between skipping and a random affordable use.
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait ZombieMoves
{
    /**
     * Island Phase: finish a placement left half-done, else one of: a skiff on a random free slot,
     * the Corsair's occupied space, a market restock (and a skiff on a new card), Extra Rations.
     */
    function zombieIslandTurn(int $player_id): mixed
    {
        $this->setGameStateValue("market_restocked", 0);
        if ($this->getPendingResourceChoice() !== null || $this->getPendingWorkshopSelection() !== null ||
            $this->getPendingTradingPostSelection() !== null) {
            return $this->zombieFinishPlacement($player_id);
        }

        $moves = [];
        $corsair = $this->canUseCorsairOccupiedPlacement((string) $player_id);
        $can_trade = $this->tradeableResources($player_id) !== [] || $this->getBootyTokensForPlayer($player_id) !== [];
        foreach ($this->getIslandSlots() as $slotname => $numbers) {
            foreach ($numbers as $number => $slot) {
                if ($slot["disabled"]) {
                    continue;
                }
                if ($slot["occupying_player_id"] !== null) {
                    if ($corsair && $this->corsairOccupiedPlacementBenefitForSlot($slotname) !== null) {
                        $moves[] = fn() => $this->zombiePlaceSkiff($player_id, $slotname, (string) $number);
                    }
                    continue;
                }
                if ($slotname === "workshop" && empty($this->workshopUpgradeOptions($player_id))) {
                    continue;
                }
                if ($slotname === "trading_post" && !$can_trade) {
                    continue;
                }
                $moves[] = fn() => $this->zombiePlaceSkiff($player_id, $slotname, (string) $number);
            }
        }
        if (empty($moves)) {
            // Nowhere to go: give up the remaining skiffs, or the phase would keep coming back here.
            $this->playerSetResourceCount($player_id, "skiff", 0);
            return "islandTurnDone";
        }
        if ($this->canRestockMarket()) {
            $moves[] = function () use ($player_id) {
                $this->actRestockMarket();
                $slots = array_keys(array_filter(
                    $this->getIslandSlots()["market"],
                    fn($slot) => !$slot["disabled"] && $slot["occupying_player_id"] === null,
                ));
                if (empty($slots)) {
                    throw new \Bga\GameFramework\SystemException("No free market slot after a restock");
                }
                return $this->zombiePlaceSkiff($player_id, "market", (string) $slots[array_rand($slots)]);
            };
        }
        if ($this->canUseExtraRations($player_id)) {
            $moves[] = function () use ($player_id) {
                $this->actExtraRations($this->bootyToPay($player_id, ["doubloon" => 1]));
                return $this->zombieIslandTurn($player_id); // a free action: the placement still follows
            };
        }
        return $this->zombiePick($moves);
    }

    private function zombiePlaceSkiff(int $player_id, string $slotname, string $number): mixed
    {
        $result = $this->actPlaceSkiff($slotname, $number);
        return $result === STATE_ISLAND_TURN ? $this->zombieFinishPlacement($player_id) : $result;
    }

    /** The choice a placement opens: a random resource, a random affordable upgrade, or a random exchange. */
    private function zombieFinishPlacement(int $player_id): mixed
    {
        if ($this->getPendingResourceChoice() !== null) {
            return $this->actResourcePickedInDialog($this->randomResource());
        }
        $trading_post = $this->getPendingTradingPostSelection();
        if ($trading_post !== null) {
            return $this->zombieTradingPost($player_id, $trading_post["slot_number"]);
        }
        $options = $this->workshopUpgradeOptions($player_id);
        $option = $options[array_rand($options)];
        return $this->actActivateShipUpgrade($option["upgrade_key"], $this->bootyToPay($player_id, $option["cost"]));
    }

    /** One of each resource unit the player holds, e.g. ["sail", "sail", "doubloon"]. */
    private function tradeableResources(int $player_id): array
    {
        $held = $this->getGameResourcesHierarchical($player_id)[$player_id] ?? [];
        $units = [];
        foreach (["sail", "cannonball", "doubloon"] as $resource) {
            array_push($units, ...array_fill(0, $held[$resource] ?? 0, $resource));
        }
        return $units;
    }

    /** Trading Post: 1 or 2 random held resources for as many random ones, or a booty token for 2. */
    private function zombieTradingPost(int $player_id, string $slot_number): mixed
    {
        $units = $this->tradeableResources($player_id);
        $gain = fn(int $n) => array_map(fn() => $this->randomResource(), range(1, $n));
        $moves = [];
        foreach ([1, 2] as $n) {
            if (count($units) >= $n) {
                $moves[] = function () use ($units, $n, $gain, $slot_number) {
                    shuffle($units);
                    return $this->actTradingPostExchange(array_slice($units, 0, $n), $gain($n), $slot_number);
                };
            }
        }
        foreach ($this->getBootyTokensForPlayer($player_id) as $token) {
            $moves[] = fn() => $this->actTradingPostExchange([], $gain(2), $slot_number, (int) $token["id"]);
        }
        return $this->zombiePick($moves);
    }

    private function randomResource(): string
    {
        $resources = ["sail", "cannonball", "doubloon"];
        return $resources[array_rand($resources)];
    }

    /** The booty token to spend on $cost, null when none is needed; the cost must be affordable. */
    private function bootyToPay(int $player_id, array $cost): ?int
    {
        return ($this->bootyTokenToAfford($player_id, $cost)
            ?? throw new \Bga\GameFramework\SystemException("Zombie cannot afford " . json_encode($cost))) ?: null;
    }

    /** Runs one of $moves (closures, skipping among them) at random. */
    private function zombiePick(array $moves): mixed
    {
        return $moves[array_rand($moves)]();
    }

    /** A random card from $cards to scrap, or nothing when there is none. */
    private function scrapMoves(array $cards, callable $scrap): array
    {
        if (empty($cards)) {
            return [];
        }
        $card = $cards[array_rand($cards)];
        return [fn() => $scrap((int) $card["id"])];
    }

    /** The green flag gains a resource, tan draws a card, red scraps one of $scrappable, blue as given. */
    private function flagMoves(string $flag, callable $use, array $scrappable, callable $scrap): array
    {
        return match ($flag) {
            "green" => [fn() => $use($this->randomResource())],
            "red" => $this->scrapMoves($scrappable, $scrap),
            default => [fn() => $use("")],
        };
    }

    /** Deep Cove or the red flag: scrap a random card from hand or discard (for its refund), or stop. */
    function zombieIslandScrap(int $player_id): mixed
    {
        return $this->zombiePick([
            fn() => $this->actSkipIslandScrap(),
            ...$this->scrapMoves($this->argScrapCard()["available_cards"], fn(int $card_id) => $this->actScrapCard($card_id)),
        ]);
    }

    function zombieCardFlag(int $player_id): mixed
    {
        $args = $this->argCardFlag();
        return $this->zombiePick([
            fn() => $this->actSkipCardFlag(),
            ...$this->flagMoves(
                $args["flag"],
                fn(string $resource) => $this->actResolveCardFlag($resource),
                $args["_private"][$player_id]["available_cards"] ?? [],
                fn(int $card_id) => $this->actResolveCardFlag("", $card_id),
            ),
        ]);
    }

    /** One pending flag, chosen at random, or skip them all. */
    function zombieExtortion(int $player_id): mixed
    {
        $args = $this->argExtortion();
        $moves = [fn() => $this->actSkipExtortion()];
        foreach ($args["pending_flags"] as $flag) {
            array_push($moves, ...$this->flagMoves(
                $flag,
                fn(string $resource) => $this->actExtortionUseFlag($flag, $resource),
                $args["available_cards"] ?? [],
                fn(int $card_id) => $this->actExtortionScrapCard($card_id),
            ));
        }
        return $this->zombiePick($moves);
    }

    /** Trade a resource for infamy, or infamy for a resource, if the player has it. */
    function zombieBarter(int $player_id): mixed
    {
        $moves = [fn() => $this->actSkipBarter()];
        $infamy = $this->getPlayerInfamy($player_id);
        foreach (self::BARTER_RATES as $resource => $rate) {
            if ($this->canPayWithOptionalBooty($player_id, [$resource => 1])) {
                $moves[] = fn() => $this->actBarterExchange($resource, "resource_to_infamy", $this->bootyToPay($player_id, [$resource => 1]));
            }
            if ($infamy >= $rate) {
                $moves[] = fn() => $this->actBarterExchange($resource, "infamy_to_resource");
            }
        }
        return $this->zombiePick($moves);
    }

    /** 2 random resources, or a random market card it can afford. */
    function zombieTimelyTrading(int $player_id): mixed
    {
        $resources = ["sail", "cannonball", "doubloon"];
        $moves = [
            fn() => $this->actSkipTimelyTrading(),
            fn() => $this->actTimelyTradingGainResources($resources[array_rand($resources)], $resources[array_rand($resources)]),
        ];
        foreach ($this->argTimelyTrading()["market"] as $card) {
            $cost = $this->playable_cards[$card["type"]]["cost"] ?? [];
            if ($this->canPayWithOptionalBooty($player_id, $cost)) {
                $moves[] = fn() => $this->actTimelyTradingPurchaseCard((int) $card["id"], 0, 0, $this->bootyToPay($player_id, $cost));
            }
        }
        return $this->zombiePick($moves);
    }

    /** Steal a random resource or booty token from a random ship alongside. */
    function zombieBoardingParty(int $player_id): mixed
    {
        $moves = [fn() => $this->actSkipBoardingParty()];
        foreach ($this->argBoardingParty()["targets"] as $target) {
            $items = array_keys($target["resources"]);
            if ($target["booty_token_count"] > 0) {
                $items[] = "booty_token";
            }
            foreach ($items as $item) {
                $moves[] = fn() => $this->actBoardingPartySteal((string) $target["player_id"], $item);
            }
        }
        return $this->zombiePick($moves);
    }

    function zombieHuntTheBounty(int $player_id): mixed
    {
        $moves = [fn() => $this->actSkipHuntTheBounty()];
        foreach ($this->argHuntTheBounty()["targets"] as $target) {
            $moves[] = fn() => $this->actHuntTheBountyChooseTarget($target["ship"]);
        }
        return $this->zombiePick($moves);
    }

    function zombieHuntTheBountyExtraPlay(int $player_id): mixed
    {
        return $this->zombiePick([fn() => $this->actSkipHuntTheBountyExtraPlay(), fn() => $this->actHuntTheBountyPlayAnother()]);
    }

    function zombieRallyTheFlags(int $player_id): mixed
    {
        $moves = [fn() => $this->actSkipRallyTheFlags()];
        foreach ($this->argRallyTheFlagsChooseFlag()["available_flags"] as $flag) {
            $moves[] = fn() => $this->actRallyTheFlagsChooseFlag($flag["flag_key"]);
        }
        return $this->zombiePick($moves);
    }

    function zombieExtraRations(int $player_id): int
    {
        $this->zombiePick([fn() => null, fn() => $this->actExtraRations($this->bootyToPay($player_id, ["doubloon" => 1]))]);
        return STATE_NEXT_PLAYER_ISLAND_PHASE;
    }

    function zombieSwiftHull(int $player_id): mixed
    {
        $moves = [fn() => $this->actSkipSwiftHull()];
        if ($this->canUseSwiftHull($player_id)) {
            $moves[] = fn() => $this->actUseSwiftHull($this->bootyToPay($player_id, ["sail" => 1]));
        }
        return $this->zombiePick($moves);
    }

    /**
     * Card Purchases: buy every card the player claimed, in market order, while the resources last.
     * The Merchant covers a sail or cannonball shortfall with doubloons; a booty token covers the rest.
     */
    function zombiePurchases(int $player_id): void
    {
        $budget = $this->getGameResourcesHierarchical($player_id)[$player_id] ?? [];
        $tokens = $this->getBootyTokensForPlayer($player_id);
        $merchant = $this->getPlayerCaptain($player_id) === "merchant";
        $market_slots = $this->getIslandSlots()["market"];
        $bought = [];
        foreach ($this->getMarketSlots() as $i => $card) {
            if ($card === null || $market_slots["n" . ($i + 1)]["occupying_player_id"] != $player_id) {
                continue;
            }
            $cost = $this->playable_cards[$card["type"]]["cost"];
            $item = ["card_id" => (int) $card["id"]];
            if ($merchant) {
                foreach (["cannonball" => "doubloons_as_cannonballs", "sail" => "doubloons_as_sails"] as $resource => $key) {
                    $short = max(0, ($cost[$resource] ?? 0) - ($budget[$resource] ?? 0));
                    if ($short > 0) {
                        $item[$key] = $short;
                        $cost[$resource] -= $short;
                        $cost["doubloon"] = ($cost["doubloon"] ?? 0) + $short;
                    }
                }
            }
            if (!$this->canPayFor($cost, $budget)) {
                foreach ($tokens as $t => $token) {
                    $booty = $this->resolveBootyResourcesForPayment(
                        $this->getBootyTokenConfigByTypeArg((int) $token["type_arg"])["resources"], $cost, null, $budget,
                    );
                    $rest = array_map(fn($need, $res) => max(0, $need - ($booty[$res] ?? 0)), $cost, array_keys($cost));
                    $rest = array_combine(array_keys($cost), $rest);
                    if ($this->canPayFor($rest, $budget)) {
                        $item["use_booty_card_id"] = (int) $token["id"];
                        $cost = $rest;
                        unset($tokens[$t]);
                        break;
                    }
                }
            }
            if ($this->canPayFor($cost, $budget)) {
                foreach ($cost as $resource => $amount) {
                    $budget[$resource] = ($budget[$resource] ?? 0) - $amount;
                }
                $bought[] = $item;
            }
        }
        $this->actCompletePurchases($bought);
    }

    /** Sea Phase: a random card from the hand played with random affordable choices, or rowing. */
    function zombieSeaTurn(int $player_id): mixed
    {
        $hand = array_values($this->cards->getCardsInLocation("hand", $player_id));
        $ship = $this->hasSecondShip($player_id) ? mt_rand(1, 2) : 1;
        $moves = [];
        foreach ($hand as $card) {
            $moves[] = function () use ($player_id, $card, $ship) {
                $type = (int) $card["type"];
                [$decisions, $booty] = $this->zombieCardDecisions($player_id, $this->upgradedCardActions($this->playable_cards[$type], $player_id));
                return $this->actPlayCard($type, (int) $card["id"], $decisions, $booty, $ship);
            };
        }
        if (count($hand) >= 2) {
            $moves[] = function () use ($hand, $ship) {
                $discards = array_rand($hand, 2);
                $maneuvers = array_column($this->playable_cards[$this->rowingCardType()]["actions"][0]["choices"], "action");
                return $this->actRow((int) $hand[$discards[0]]["id"], (int) $hand[$discards[1]]["id"], $maneuvers[array_rand($maneuvers)], $ship);
            };
        }
        return $this->zombiePick($moves);
    }

    /**
     * Random affordable decisions for $actions and the booty token to pay with (null for none).
     * Half the time a held token's resources are added to the budget; a "choice" resource counts
     * as any one type, so if the picks only fit by counting it twice, they are redrawn without it.
     */
    function zombieCardDecisions(int $player_id, array $actions): array
    {
        $resources = $this->getGameResourcesHierarchical($player_id)[$player_id] ?? [];
        unset($resources["skiff"]);
        $tokens = $this->getBootyTokensForPlayer($player_id);
        if (!empty($tokens) && mt_rand(0, 1)) {
            $token = $tokens[array_rand($tokens)];
            $budget = $resources;
            foreach ($this->getBootyTokenConfigByTypeArg((int) $token["type_arg"])["resources"] as $resource => $n) {
                foreach ($resource === "choice" ? ["sail", "cannonball", "doubloon"] : [$resource] as $r) {
                    $budget[$r] = ($budget[$r] ?? 0) + $n;
                }
            }
            $start = $budget;
            $decisions = $this->randomCardDecisions($actions, $budget);
            $cost = array_filter(array_map(fn($r) => $start[$r] - $budget[$r], array_combine(array_keys($start), array_keys($start))));
            $booty = $this->bootyTokenToAfford($player_id, $cost);
            if ($booty !== null) {
                return [$decisions, $booty ?: null];
            }
        }
        return [$this->randomCardDecisions($actions, $resources), null];
    }

    /**
     * Random decisions for a card, in the order processCardActions consumes them - the same rows
     * the play dialog offers. A paid option is only picked while the running total stays within
     * $budget, which is reduced by what is picked.
     */
    function randomCardDecisions(array $actions, array &$budget): array
    {
        $decisions = [];
        foreach ($actions as $action) {
            if (ShipUpgrades::actionName($action) === PrimitiveCardPlayAction::SEQUENCE->value) {
                array_push($decisions, ...$this->randomCardDecisions($action["actions"], $budget));
                continue;
            }
            if (!self::needsDecision($action)) {
                continue;
            }
            $picked = $this->pickAffordable(self::actionOptions($action), $budget);
            $decisions[] = $picked["name"];
            if ($picked["choice"] !== null) {
                array_push($decisions, ...$this->randomCardDecisions([$picked["choice"]], $budget));
            }
        }
        return $decisions;
    }

    /** A random affordable option (CardPlay::actionOptions; "skip" is free), paid from $budget. */
    private function pickAffordable(array $options, array &$budget): array
    {
        $affordable = array_values(array_filter($options, fn($o) => $this->canPayFor($o["cost"], $budget)));
        if (empty($affordable)) {
            throw new \Bga\GameFramework\SystemException("Zombie has no affordable option: " . json_encode($options));
        }
        $picked = $affordable[array_rand($affordable)];
        foreach ($picked["cost"] as $resource => $amount) {
            $budget[$resource] = ($budget[$resource] ?? 0) - $amount;
        }
        return $picked;
    }

    /** Captain cards: Retaliation fires or not, Improvisation copies a random card, Spyglass keeps and orders at random, Unearth Riches picks a random resource. */
    function zombieCaptainCard(int $player_id): mixed
    {
        $args = $this->argCaptainCard();
        $cards = $args["_private"][$player_id]["available_cards"];
        $card = $cards[array_rand($cards)];
        $order = array_map("intval", array_column($cards, "id"));
        shuffle($order);
        if ($args["ability"] !== "improvisation") {
            $fire = ["skip", "fire left", "fire right"];
            return $this->actResolveCaptainCard([
                "card_id" => (int) $card["id"], "fire" => $fire[array_rand($fire)], "order" => $order,
                "resource" => $this->randomResource(),
            ]);
        }
        $ship = $this->hasSecondShip($player_id) ? mt_rand(1, 2) : null;
        if ($ship !== null) {
            $this->setActiveShip($ship); // the copied card's upgrades depend on the ship
        }
        [$decisions, $booty] = $this->zombieCardDecisions($player_id, $this->upgradedCardActions($this->playable_cards[$card["type"]], $player_id));
        return $this->actResolveCaptainCard(["card_id" => (int) $card["id"]], $decisions, $booty, $ship);
    }

    /** Fire the collision-interrupted shot, with a random affordable option (skipping is one). */
    function zombiePostCollisionFire(int $player_id): mixed
    {
        [$decisions, $booty] = $this->zombieCardDecisions($player_id, [$this->argPostCollisionFire()["action"]]);
        return $this->actPostCollisionFire($decisions[0], $booty);
    }

    function zombieResolveCollision(int $player_id): mixed
    {
        $pivots = ["no pivot", "pivot left", "pivot right"];
        return $this->actPivotPickedInDialog($pivots[array_rand($pivots)]);
    }

    function zombieTreasureSeekerAdjust(int $player_id): mixed
    {
        $moves = [fn() => $this->actSkipTreasureSeekerAdjust()];
        foreach ($this->argTreasureSeekerAdjust()["valid_positions"] as $pos) {
            $moves[] = fn() => $this->actAdjustShipwreck((int) $pos["x"], (int) $pos["y"]);
        }
        return $this->zombiePick($moves);
    }

    function zombieRebelDiscard(int $player_id): mixed
    {
        $hand = array_values($this->argRebelDiscard()["available_cards"]);
        return $this->actRebelDiscardCard((int) $hand[array_rand($hand)]["id"]);
    }
}
