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
 * Zombie turns (BGA "random zombie", level 1): a random legal skiff placement in the Island Phase
 * and a random card with random, affordable choices in the Sea Phase.
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
            $resources = ["sail", "cannonball", "doubloon"];
            return $this->actResourcePickedInDialog($resources[array_rand($resources)]);
        }
        $options = $this->workshopUpgradeOptions($player_id);
        return $this->actActivateShipUpgrade($options[array_rand($options)]["upgrade_key"]);
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
        $ship = $this->hasSecondShip($player_id) ? random_int(1, 2) : 1;
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
