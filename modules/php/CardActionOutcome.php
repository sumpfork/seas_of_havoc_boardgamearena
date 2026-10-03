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
 * What resolving (part of) a card's actions did: the board moves and shots to animate, what it
 * costs, and the events the rest of the turn reacts to. Nested actions (sequences, choices,
 * left/right maneuvers, sea features after the card) each produce one and are folded into their
 * parent with absorb().
 */
final class CardActionOutcome
{
    public function __construct(
        /** Move, turn, collision and shot results in play order, sent to the client as moveChain. */
        public array $actionChain = [],
        /** Resource => amount still to be paid for the actions taken. */
        public array $cost = [],
        public bool $collisionOccurred = false,
        public ?array $shipwreckEvent = null,
        public ?array $bootyCard = null,
        /** Set when a captain ability needs the player's input: the state to go to instead. */
        public ?int $captainState = null,
    ) {
    }

    /**
     * Fold a nested outcome into this one, adding $cost for the action that produced it. The first
     * shipwreck and booty token picked up are the ones reported.
     */
    public function absorb(self $other, array $cost = []): void
    {
        $this->actionChain = array_merge($this->actionChain, $other->actionChain);
        foreach ([$cost, $other->cost] as $amounts) {
            foreach ($amounts as $resource => $amount) {
                $this->cost[$resource] = ($this->cost[$resource] ?? 0) + $amount;
            }
        }
        $this->collisionOccurred = $this->collisionOccurred || $other->collisionOccurred;
        $this->shipwreckEvent ??= $other->shipwreckEvent;
        $this->bootyCard ??= $other->bootyCard;
    }

    /** Record what collectShipwrecksAtPlayer() picked up, unless something was already picked up. */
    public function addPickup(array $pickup): void
    {
        $this->shipwreckEvent ??= $pickup["shipwreck_event"] ?? null;
        $this->bootyCard ??= $pickup["booty_card"] ?? null;
    }
}
