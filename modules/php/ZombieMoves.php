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
     * Island Phase: finish a placement left half-done, else place a skiff on a random free slot.
     * The Trading Post is left out - its exchange is a dialog of its own.
     */
    function zombieIslandTurn(int $player_id): mixed
    {
        $this->setGameStateValue("market_restocked", 0);
        if ($this->getPendingResourceChoice() !== null || $this->getPendingWorkshopSelection() !== null) {
            return $this->zombieFinishPlacement($player_id);
        }
        // An exchange the player left open is dropped; its skiff was not placed yet.
        $this->setPendingTradingPostSelection(null, null);

        $free = [];
        foreach ($this->getIslandSlots() as $slotname => $numbers) {
            foreach ($numbers as $number => $slot) {
                if ($slot["disabled"] || $slot["occupying_player_id"] !== null || $slotname === "trading_post") {
                    continue;
                }
                if ($slotname === "workshop" && empty($this->workshopUpgradeOptions($player_id))) {
                    continue;
                }
                $free[] = [$slotname, (string) $number];
            }
        }
        if (empty($free)) {
            // Nowhere to go: give up the remaining skiffs, or the phase would keep coming back here.
            $this->playerSetResourceCount($player_id, "skiff", 0);
            return "islandTurnDone";
        }
        [$slotname, $number] = $free[array_rand($free)];
        $result = $this->actPlaceSkiff($slotname, $number);
        if ($result === STATE_ISLAND_TURN) {
            return $this->zombieFinishPlacement($player_id);
        }
        return $result;
    }

    /** The choice a placement opens: a random resource, or a random affordable upgrade. */
    private function zombieFinishPlacement(int $player_id): mixed
    {
        if ($this->getPendingResourceChoice() !== null) {
            return $this->actResourcePickedInDialog($this->randomResource());
        }
        $options = $this->workshopUpgradeOptions($player_id);
        $option = $options[array_rand($options)];
        return $this->actActivateShipUpgrade($option["upgrade_key"], $this->bootyToPay($player_id, $option["cost"]));
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

    /** 2 doubloons, or a random market card it can afford. */
    function zombieTimelyTrading(int $player_id): mixed
    {
        $moves = [fn() => $this->actSkipTimelyTrading(), fn() => $this->actTimelyTradingGainDoubloons()];
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

    function zombieSwiftHull(int $player_id): mixed
    {
        $moves = [fn() => $this->actSkipSwiftHull()];
        if ($this->canUseSwiftHull($player_id)) {
            $moves[] = fn() => $this->actUseSwiftHull($this->bootyToPay($player_id, ["sail" => 1]));
        }
        return $this->zombiePick($moves);
    }

    /** Card Purchases: buy every card the player claimed, in market order, while the resources last. */
    function zombiePurchases(int $player_id): void
    {
        $budget = $this->getGameResourcesHierarchical($player_id)[$player_id] ?? [];
        $market_slots = $this->getIslandSlots()["market"];
        $bought = [];
        foreach ($this->getMarketSlots() as $i => $card) {
            if ($card === null || $market_slots["n" . ($i + 1)]["occupying_player_id"] != $player_id) {
                continue;
            }
            $cost = $this->playable_cards[$card["type"]]["cost"];
            if ($this->canPayFor($cost, $budget)) {
                foreach ($cost as $resource => $amount) {
                    $budget[$resource] -= $amount;
                }
                $bought[] = (int) $card["id"];
            }
        }
        $this->actCompletePurchases($bought);
    }

    /** Sea Phase: a random card from the hand, played with random choices it can afford. */
    function zombieSeaTurn(int $player_id): mixed
    {
        $hand = array_values($this->cards->getCardsInLocation("hand", $player_id));
        $card = $hand[array_rand($hand)];
        $type = (int) $card["type"];
        $budget = $this->getGameResourcesHierarchical($player_id)[$player_id] ?? [];
        unset($budget["skiff"]);
        $decisions = $this->randomCardDecisions(
            $this->upgradedCardActions($this->playable_cards[$type], $player_id),
            $budget,
        );
        $ship = $this->hasSecondShip($player_id) ? mt_rand(1, 2) : 1;
        return $this->actPlayCard($type, (int) $card["id"], $decisions, null, $ship);
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
            // Nimble Hull is a once-per-phase upgrade, not a free random pick.
            $options = array_filter(self::actionOptions($action), fn($o) => $o["name"] !== self::NIMBLE_HULL_CHOICE);
            $picked = $this->pickAffordable($options, $budget);
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
}
