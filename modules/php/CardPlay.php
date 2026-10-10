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

use Bga\GameFramework\Actions\Types\JsonParam;

/**
 * The sea phase: playing a card and resolving its actions, moving ships (including the second ship of the 2 Ship Variant), whirlpools and gusts, collisions, and card flag actions.
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait CardPlay
{
    /**
     * 2 Ship Variant. A ship on the board is identified by its owner's player id; a second ship by
     * the player id with "_2" appended. Anything that finds a ship on the board and wants the player
     * behind it goes through shipOwner().
     */
    static function secondShipArg($player_id): string
    {
        return $player_id . "_2";
    }

    static function shipOwner($ship_arg): string
    {
        return explode("_", (string) $ship_arg)[0];
    }

    function hasSecondShip($player_id): bool
    {
        return self::getUniqueValueFromDB("SELECT player_ship2 FROM player WHERE player_id = " . (int) $player_id) !== null;
    }

    /** The ship the player's current card applies to: "Each card you play applies only to one ship." */
    function activeShipArg($player_id): string
    {
        return (int) $this->getGameStateValue("active_ship") === 2 ? self::secondShipArg($player_id) : (string) $player_id;
    }

    function processSimpleAction(PrimitiveCardPlayAction $action_type): CardActionOutcome
    {
        $player_id = $this->getActivePlayerId();
        $ship = $this->activeShipArg($player_id);
        $outcome = new CardActionOutcome();
        switch ($action_type) {
            case PrimitiveCardPlayAction::FORWARD:
            case PrimitiveCardPlayAction::BACKWARD:
                $result = $action_type === PrimitiveCardPlayAction::FORWARD
                    ? $this->seaboard->moveObjectForward("player_ship", $ship, ["rock", "player_ship"])
                    : $this->seaboard->moveObjectBackward("player_ship", $ship, ["rock", "player_ship"]);
                $outcome->actionChain[] = $result;
                if ($result["type"] == "collision") {
                    $outcome->collisionOccurred = true;
                    $this->applyCollisionPenalty((string) $player_id, $result["colliders"]);
                } else {
                    $outcome->addPickup($this->collectShipwrecksAtPlayer($player_id));
                }
                break;
            case PrimitiveCardPlayAction::PIVOT_LEFT:
                $outcome->actionChain[] = $this->seaboard->turnObject("player_ship", $ship, Turn::LEFT);
                break;
            case PrimitiveCardPlayAction::PIVOT_AROUND:
                $outcome->actionChain[] = $this->seaboard->turnObject("player_ship", $ship, Turn::AROUND);
                break;
            case PrimitiveCardPlayAction::PIVOT_RIGHT:
                $outcome->actionChain[] = $this->seaboard->turnObject("player_ship", $ship, Turn::RIGHT);
                break;
            default:
                throw new \Bga\GameFramework\SystemException("Unknown action type: " . $action_type->value);
        }
        $this->mydump("processSimpleAction outcome", $outcome);
        return $outcome;
    }

    /**
     * "Collision with Rocks: The colliding player places a Damage Card into their discard pile.
     *  Collision other Ships (Ramming): The colliding player scores 1 infamy, the rammed ship
     *  places a Damage card into their discard pile."
     * Gust pushes count as collisions the ship caused, so they score ramming hits too.
     */
    private function applyCollisionPenalty(string $player_id, array $colliders): void
    {
        foreach ($colliders as $collider) {
            if ($collider["type"] === "player_ship") {
                $rammed_player_id = self::shipOwner($collider["arg"]);
                // Ramming your own other ship (2 Ship Variant) damages it but earns no infamy.
                if ($rammed_player_id !== (string) $player_id) {
                    $this->bga->playerStats->inc("rams", 1, (int) $player_id);
                    $this->bga->playerStats->inc("rammed", 1, (int) $rammed_player_id);
                    $this->scoreInfamy(
                        $player_id,
                        1,
                        "rams",
                        clienttranslate('${player_name} rams another ship and scores ${score_increment} infamy'),
                    );
                }
                $this->dealDamageCard($rammed_player_id);
                $this->scoreHuntTheBounty($player_id, (string) $collider["arg"]);
            } elseif ($collider["type"] === "rock") {
                $this->bga->playerStats->inc("rock_collisions", 1, (int) $player_id);
                $this->dealDamageCard($player_id);
            }
        }
    }

    function processCardActions(array $actions, array $decisions): CardActionOutcome
    {
        return $this->processCardActionsWithDecisions($actions, $decisions);
    }

    /** Nested maneuvers consume the same decision list as the actions that follow them. */
    private function processCardActionsWithDecisions(array $actions, array &$decisions): CardActionOutcome
    {
        $outcome = new CardActionOutcome();
        $this->mytrace("processing card actions");
        $this->mydump("actions", $actions);
        foreach ($actions as $i => $action) {
            $typed_action =
                gettype($action["action"]) == "string"
                    ? PrimitiveCardPlayAction::from($action["action"])
                    : $action["action"];
            $this->mytrace("handling " . $typed_action->value);
            $this->mydump("action_chain", $outcome->actionChain);
            $optional = array_key_exists("cost", $action);
            $has_options = $typed_action === PrimitiveCardPlayAction::CHOICE || isset(ShipUpgrades::FIRE_COUNTS[$typed_action->value]);
            $decision = null;
            // Consume once before dispatch; fixed moves and sequence wrappers need no decision.
            if ($optional || $has_options) {
                if (empty($decisions)) {
                    throw new \Bga\GameFramework\SystemException("Missing card action choice: " . $typed_action->value);
                }
                $decision = array_shift($decisions);
                if ($optional && $decision === "skip") {
                    $this->mytrace("skipping action with cost due to decision == 'skip': " . $typed_action->value);
                    $this->mytrace("decisions after skipping: " . implode(", ", $decisions));
                    continue;
                }
                if (!$has_options && $decision !== ($action["name"] ?? $typed_action->value)) {
                    throw new \Bga\GameFramework\SystemException("Invalid card action choice: " . $decision);
                }
            }
            $cost = $action["cost"] ?? [];
            switch ($typed_action) {
                case PrimitiveCardPlayAction::SEQUENCE:
                    $outcome->absorb($this->processCardActionsWithDecisions($action["actions"], $decisions), $cost);
                    break;
                case PrimitiveCardPlayAction::CHOICE:
                    $choices = $action["choices"];
                    $choice_names = array_map(fn($x) => key_exists("name", $x) ? $x["name"] : $x["action"], $choices);
                    $decision_index = array_search($decision, $choice_names, true);
                    if ($decision_index === false) {
                        throw new \Bga\GameFramework\SystemException("Invalid card action choice: " . $decision);
                    }
                    if ($decision === self::NIMBLE_HULL_CHOICE) {
                        $this->useNimbleHull($this->getActivePlayerId());
                    }
                    $chosen = [$choices[array_keys($choices)[$decision_index]]];
                    $outcome->absorb($this->processCardActionsWithDecisions($chosen, $decisions), $cost);
                    break;
                case PrimitiveCardPlayAction::LEFT:
                case PrimitiveCardPlayAction::RIGHT:
                    $pivot = $typed_action === PrimitiveCardPlayAction::LEFT
                        ? PrimitiveCardPlayAction::PIVOT_LEFT
                        : PrimitiveCardPlayAction::PIVOT_RIGHT;
                    $maneuver = [
                        ["action" => PrimitiveCardPlayAction::FORWARD],
                        ["action" => $pivot],
                        ["action" => PrimitiveCardPlayAction::FORWARD],
                    ];
                    $outcome->absorb($this->processCardActionsWithDecisions($maneuver, $decisions), $cost);
                    break;
                case PrimitiveCardPlayAction::FIRE:
                case PrimitiveCardPlayAction::FIRE2:
                case PrimitiveCardPlayAction::FIRE3:
                    $this->mytrace("fire");
                    $this->mytrace("decision: $decision");
                    [$variant, $side] = ShipUpgrades::parseFireDecision($action, $decision);
                    // The chosen shot, not the action, decides what firing costs. Firing never
                    // causes a movement collision.
                    $outcome->absorb(new CardActionOutcome($this->resolveFireAction($variant, $side)), $variant["cost"]);
                    break;
                case PrimitiveCardPlayAction::CAPTAIN_ABILITY:
                    $result = $this->processCaptainAbility($action["ability"]);
                    if (is_int($result)) {
                        // The ability needs the player's input; nothing after it on the card resolves.
                        $outcome->captainState = $result;
                        return $outcome;
                    }
                    $outcome->absorb($result, $cost);
                    break;
                default:
                    $outcome->absorb($this->processSimpleAction($typed_action), $cost);
                    break;
            }
            if ($outcome->collisionOccurred) {
                // "After resolving the collision, Cannon fire depicted at the next ship outline may
                // be resolved." Nested calls record their own next action first; the outer level
                // overwrites it, so what survives is the next outline on the card itself.
                $player_id = $this->getActivePlayerId();
                if ($i + 1 < count($actions)) {
                    $this->setNextActionOnCard((int) $player_id, $actions[$i + 1]);
                } else {
                    $this->clearNextActionOnCard((int) $player_id);
                }
                break;
            }
        }
        return $outcome;
    }

    /** Actions are kept whole (range, cost, upgrade variants), so they are stored as JSON. */
    protected function setNextActionOnCard(int $player_id, array $action): void
    {
        if ($action["action"] instanceof \PrimitiveCardPlayAction) {
            $action["action"] = $action["action"]->value;
        }
        $json = json_encode($action);
        $this->DbQuery(
            "REPLACE INTO next_action_on_card (player_id, next_action) VALUES ($player_id, '" .
                addslashes($json) .
                "')",
        );
    }

    protected function clearNextActionOnCard(int $player_id): void
    {
        $this->DbQuery("DELETE FROM next_action_on_card WHERE player_id = $player_id");
    }

    protected function readNextActionOnCard(int $player_id): ?string
    {
        return self::getUniqueValueFromDB(
            "SELECT next_action FROM next_action_on_card WHERE player_id = $player_id",
        ) ?: null;
    }

    /** The action at the next ship outline, but only when it is cannon fire - nothing else resumes. */
    private function getPendingFireAction(int $player_id): ?array
    {
        $json = $this->readNextActionOnCard($player_id);
        if (!$json) {
            return null;
        }
        $action = json_decode($json, true);
        $fire = [
            PrimitiveCardPlayAction::FIRE->value,
            PrimitiveCardPlayAction::FIRE2->value,
            PrimitiveCardPlayAction::FIRE3->value,
        ];
        return is_array($action) && in_array($action["action"] ?? null, $fire, true) ? $action : null;
    }

    /** Called where a resolved collision would otherwise end the turn. */
    private function postCollisionFireState(): ?int
    {
        return $this->getPendingFireAction((int) $this->getActivePlayerId()) === null
            ? null
            : STATE_POST_COLLISION_FIRE;
    }

    function argPostCollisionFire(): array
    {
        $action = $this->getPendingFireAction((int) $this->getActivePlayerId());
        if ($action === null) {
            throw new \Bga\GameFramework\SystemException("No firing action is pending after the collision");
        }
        // Which ship fires, for the client's board preview (two ships in the 2 Ship Variant).
        return ["action" => $action, "ship" => $this->activeShipArg($this->getActivePlayerId())];
    }

    function actPostCollisionFire(string $decision, ?int $use_booty_card_id = null): mixed
    {
        return $this->withInfamyAfterNotifications(fn() => $this->postCollisionFireNow($decision, $use_booty_card_id));
    }

    private function postCollisionFireNow(string $decision, ?int $use_booty_card_id): mixed
    {
        $player_id = (int) $this->getActivePlayerId();
        $action = $this->getPendingFireAction($player_id);
        if ($action === null) {
            throw new \Bga\GameFramework\SystemException("No firing action is pending after the collision");
        }
        $this->clearNextActionOnCard($player_id);

        // processCardActions understands "skip" for a costed action, so declining runs the same path.
        $outcome = $this->processCardActions([$action], [$decision]);
        if (!empty($outcome->cost)) {
            $this->payWithOptionalBooty($player_id, $outcome->cost, $use_booty_card_id);
        }
        if (!empty($outcome->actionChain)) {
            $this->bga->notify->all("cardPlayed", clienttranslate('${player_name} fires after the collision'), [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "ship" => $this->activeShipArg($player_id),
                "moveChain" => $outcome->actionChain,
                "cost" => $outcome->cost,
                "shipwreck_event" => null,
            ]);
        }
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    /** The whirlpool's quarter turn, or null when the ship is not on a whirlpool. */
    function applyWhirlpoolRotation($player_id): ?array
    {
        $ship = $this->activeShipArg($player_id);
        if (!$this->seaboard->isObjectOnWhirlpool("player_ship", $ship)) {
            return null;
        }
        $this->mytrace("Ship is on whirlpool - rotating 90 degrees clockwise");
        return $this->seaboard->turnObject("player_ship", $ship, Turn::RIGHT);
    }

    /** The gust's push (a move or a collision), or null when the ship is not on a gust. */
    function applyGustPush($player_id): ?array
    {
        $ship = $this->activeShipArg($player_id);
        $gust = $this->seaboard->getGustAtObjectLocation("player_ship", $ship);
        if (!$gust) {
            return null;
        }
        $this->mytrace("Ship is on gust - pushing in direction " . $gust["heading"]->toString());
        return $this->seaboard->pushObjectInDirection("player_ship", $ship, $gust["heading"], [
            "rock",
            "player_ship",
        ]);
    }

    /** Whirlpool rotation first, then the gust push, which can collide. */
    function applySeafeatureEffects($player_id): CardActionOutcome
    {
        $outcome = new CardActionOutcome();

        $turn = $this->applyWhirlpoolRotation($player_id);
        if ($turn !== null) {
            $outcome->actionChain[] = $turn;
        }

        $push = $this->applyGustPush($player_id);
        if ($push !== null) {
            $outcome->actionChain[] = $push;
            if ($push["type"] == "collision") {
                $outcome->collisionOccurred = true;
                $this->applyCollisionPenalty((string) $player_id, $push["colliders"]);
            } else {
                $outcome->addPickup($this->collectShipwrecksAtPlayer($player_id));
            }
        }

        return $outcome;
    }

    function actPlayCard(int $card_type, int $card_id, #[JsonParam] $decisions, ?int $use_booty_card_id = null, int $ship = 1)
    {
        $held = $this->cards->getCard($card_id);
        if (!$held || $held["location"] !== "hand" || $held["location_arg"] != $this->getActivePlayerId() ||
            (int) $held["type"] !== $card_type) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose a card from your hand"));
        }
        $this->setActiveShip($ship);
        return $this->resolvePlayedCard($card_type, $card_id, $decisions, $use_booty_card_id);
    }

    private function setActiveShip(int $ship): void
    {
        if ($ship !== 1 && ($ship !== 2 || !$this->hasSecondShip($this->getActivePlayerId()))) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose one of your ships"));
        }
        // Everything this card goes on to do - including a captain card it opens, the shot after a
        // collision, and the sea features at the end - applies to this ship.
        $this->setGameStateValue("active_ship", $ship);
    }

    /** The playable_cards entry describing rowing's maneuvers; never dealt as a card. */
    function rowingCardType(): int
    {
        foreach ($this->playable_cards as $type => $card) {
            if (($card["category"] ?? "") === "rowing") {
                return (int) $type;
            }
        }
        throw new \Bga\GameFramework\SystemException("No rowing card defined");
    }

    /** "Instead of passing or resolving a card, a player may instead row their ship by discarding 2 cards." */
    function actRow(int $discard_card_id_1, int $discard_card_id_2, string $decision, int $ship = 1)
    {
        if ($discard_card_id_1 === $discard_card_id_2) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose two different cards to discard"));
        }
        $type = $this->rowingCardType();
        $maneuvers = array_column($this->playable_cards[$type]["actions"][0]["choices"], "action");
        if (!in_array($decision, $maneuvers, true)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose a rowing maneuver"));
        }
        $this->setActiveShip($ship);
        $player_id = (string) $this->getActivePlayerId();
        // Validates both cards are in the player's hand.
        $this->discardCards($player_id, [$discard_card_id_1, $discard_card_id_2]);
        return $this->resolvePlayedCard($type, null, [$decision]);
    }

    protected function resolvePlayedCard(int $card_type, ?int $card_id, array $decisions, ?int $use_booty_card_id = null, ?array $actions = null)
    {
        return $this->withInfamyAfterNotifications(
            fn() => $this->resolvePlayedCardNow($card_type, $card_id, $decisions, $use_booty_card_id, $actions),
        );
    }

    /** $card_id is null for rowing, whose discards have already been made. */
    private function resolvePlayedCardNow(int $card_type, ?int $card_id, array $decisions, ?int $use_booty_card_id, ?array $actions)
    {
        $this->setGameStateValue("pending_card_flag_type", isset($this->playable_cards[$card_type]["flag"]) ? $card_type : 0);
        $this->setGameStateValue(
            "swift_hull_card_type",
            ShipUpgrades::isSailingCard($this->playable_cards[$card_type]) ? $card_type : 0,
        );
        $this->mydump("card_type", $card_type);
        $this->mydump("decisions", $decisions);
        $card = $this->playable_cards[$card_type];
        $this->mydump("card played", $card);
        $player_id = $this->getActivePlayerId();
        // The cardPlayed notification moves this card out of the client's hand. A captain card's
        // second resolution (actResolveCaptainCard) finds it already discarded.
        $hand_card_id = $card_id !== null && $this->cards->getCard($card_id)["location"] === "hand" ? $card_id : null;

        // Damage card, "Repair: When you would play this card, scrap it instead." The repair is
        // the whole play - no maneuver, no flag action, and the card leaves the deck for good.
        if (in_array(PrimitiveCardPlayAction::SCRAP_SELF->value, array_column($card["actions"], "action"), true)) {
            $this->scrapCardAndRefund($card_id, $player_id);
            return "seaTurnDone";
        }

        // Check if this is a "pass" play (playing card without executing actions)
        $is_pass = !empty($decisions) && $decisions[0] === "pass";

        if ($is_pass) {
            // A passed card resolves no maneuver, so Swift Hull does not trigger.
            $this->setGameStateValue("swift_hull_card_type", 0);
            // Pass: skip all actions, but still discard the card
            $outcome = new CardActionOutcome();
            $notification_message = clienttranslate('${player_name} has passed (played a card without actions) ${card_image}');
        } else {
            $outcome = $this->processCardActions($actions ?? $this->upgradedCardActions($card, $player_id), $decisions);

            if ($outcome->captainState !== null) {
                if ($outcome->captainState === STATE_CAPTAIN_CARD) {
                    $this->setGameStateValue("pending_captain_card", $card_id);
                }
                $this->discardCardToPlayer($card_id, $player_id);
                $this->bga->notify->all("cardPlayed", clienttranslate('${player_name} has played a card ${card_image}'), [
                    "player_name" => $this->getPlayerNameById($player_id),
                    "player_id" => $player_id,
                    "ship" => $this->activeShipArg($player_id),
                    "card_id" => $hand_card_id,
                    "card_type" => $card_type,
                    "card_image" => $card_type,
                    "moveChain" => [],
                    "cost" => [],
                    "shipwreck_event" => null,
                ]);
                return $outcome->captainState;
            }

            $this->mydump("final card play outcome", $outcome);

            // Pay the total cost from all actions (optionally using booty token)
            if (!empty($outcome->cost)) {
                $this->payWithOptionalBooty($player_id, $outcome->cost, $use_booty_card_id);
            }

            // Rowing is not a card play; the rowing card image still shows the maneuver.
            $rowing = $card_id === null;
            $notification_message = $rowing
                ? clienttranslate('${player_name} has rowed ${card_image}')
                : clienttranslate('${player_name} has played a card ${card_image}');

            // Track whether seafeature effects have been attempted (to prevent applying them multiple times)
            $this->setGameStateValue("seafeature_effects_attempted", 0);

            // Whirlpools and gusts apply now unless the card collided; then they apply once the
            // collision is resolved (actPivotPickedInDialog).
            if (!$outcome->collisionOccurred) {
                $seafeature = $this->applySeafeatureEffects($player_id);
                $this->setGameStateValue("seafeature_effects_attempted", 1);
                // Appended to the moveChain for sequential animation
                $outcome->absorb($seafeature);

                if (count($seafeature->actionChain) == 2) {
                    $notification_message = $rowing
                        ? clienttranslate('${player_name} has rowed and is affected by the whirlpool and gust ${card_image}')
                        : clienttranslate(
                            '${player_name} has played a card and is affected by the whirlpool and gust ${card_image}',
                        );
                } elseif (!empty($seafeature->actionChain)) {
                    $whirlpool = $this->seaboard->isObjectOnWhirlpool("player_ship", $this->activeShipArg($player_id));
                    $notification_message = match (true) {
                        $rowing && $whirlpool => clienttranslate('${player_name} has rowed and is rotated by the whirlpool ${card_image}'),
                        $rowing => clienttranslate('${player_name} has rowed and is pushed by the gust ${card_image}'),
                        $whirlpool => clienttranslate('${player_name} has played a card and is rotated by the whirlpool ${card_image}'),
                        default => clienttranslate('${player_name} has played a card and is pushed by the gust ${card_image}'),
                    };
                }
            }
        }

        if ($card_id !== null) {
            $this->discardCardToPlayer($card_id, $player_id);
        }

        $this->bga->notify->all("cardPlayed", $notification_message, [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
            "ship" => $this->activeShipArg($player_id),
            "card_id" => $hand_card_id, // null when rowing
            "card_type" => $card_type,
            "card_image" => $card_type,
            "moveChain" => $outcome->actionChain,
            "cost" => $outcome->cost,
            "shipwreck_event" => $outcome->shipwreckEvent,
        ]);

        $this->notifyBootyCollected($player_id, $outcome->bootyCard);

        if ($this->maybeDeferSeaPhaseForTreasureSeeker($outcome->shipwreckEvent, $outcome->collisionOccurred)) {
            return STATE_TREASURE_SEEKER_ADJUST;
        }

        return $outcome->collisionOccurred ? $this->collisionPenaltyState() : "seaTurnDone";
    }

    private function notifyBootyCollected($player_id, ?array $booty_card): void
    {
        if ($booty_card === null) {
            return;
        }
        $this->mydump("booty collected", $booty_card);
        $this->bga->notify->all("bootyTokenCollected", clienttranslate('${player_name} collected a booty token'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
        ]);
        $this->bga->notify->player($player_id, "bootyTokenRevealed", clienttranslate("You reveal a booty token"), [
            "booty_tokens" => $this->getBootyTokensForPlayer($player_id),
            "new_token" => $booty_card,
        ]);
    }

    /**
     * "The player that initiated the collision discards a card (if their hand is empty, they do not
     * discard)." Gust-pushed collisions count: the rules resolve them as if the ship caused them.
     */
    private function collisionPenaltyState(): int
    {
        $player_id = (int) $this->getActivePlayerId();
        return $this->cards->countCardInLocation("hand", $player_id) > 0
            ? STATE_COLLISION_DISCARD
            : STATE_RESOLVE_COLLISION;
    }

    function argCollisionDiscard(): array
    {
        return ["available_cards" => $this->cards->getPlayerHand((string) $this->getActivePlayerId())];
    }

    function actCollisionDiscardCard(int $card_id): mixed
    {
        $this->discardCards((string) $this->getActivePlayerId(), [$card_id]);
        return "cardDiscarded";
    }

    /** Which ship is pivoting, for the client's board preview. */
    function argResolveCollision(): array
    {
        return ["ship" => $this->activeShipArg($this->getActivePlayerId())];
    }

    function actPivotPickedInDialog(string $direction)
    {
        return $this->withInfamyAfterNotifications(fn() => $this->pivotPickedInDialogNow($direction));
    }

    private function pivotPickedInDialogNow(string $direction)
    {
        $this->mytrace("actPivotPickedInDialog");
        $player_id = $this->getActivePlayerId();

        // The pivot is part of resolving the collision; whirlpools and gusts resolve after it, like
        // after any card. Doing it the other way round still produced the right final heading for a
        // rotation, but each move carried headings from the opposite order to the one the client
        // animates them in, so the ship was drawn facing the wrong way until the next reload.
        $outcome = new CardActionOutcome();
        if ($direction != "no pivot") {
            $typed_action = PrimitiveCardPlayAction::from($direction);
            $outcome = $this->processCardActions([["action" => $typed_action]], []);
            $this->mydump("final pivot outcome", $outcome);

            // Pay the cost for pivot actions (pivots are free, but just in case)
            if (!empty($outcome->cost)) {
                $this->payWithOptionalBooty($player_id, $outcome->cost);
            }
        }

        // Only apply seafeature effects if they haven't been attempted yet (once per card play)
        $seafeature = new CardActionOutcome();
        if ($this->getGameStateValue("seafeature_effects_attempted") == 0) {
            $seafeature = $this->applySeafeatureEffects($player_id);
            $this->setGameStateValue("seafeature_effects_attempted", 1);
        } else {
            $this->mytrace("Seafeature effects already attempted, skipping");
        }
        // Pivot moves, then seafeature moves, for sequential animation
        $outcome->absorb($seafeature);
        $seafeature_moves = count($seafeature->actionChain);
        // A single seafeature move is either the whirlpool's turn or the gust's push.
        $on_whirlpool = $seafeature_moves == 1
            && $this->seaboard->isObjectOnWhirlpool("player_ship", $this->activeShipArg($player_id));

        $notification_message = null;
        if ($direction != "no pivot") {
            $notification_message = clienttranslate('${player_name} pivots');
            if ($seafeature_moves == 2) {
                $notification_message = clienttranslate(
                    '${player_name} pivots and is affected by the whirlpool and gust',
                );
            } elseif ($seafeature_moves == 1) {
                $notification_message = $on_whirlpool
                    ? clienttranslate('${player_name} pivots and is rotated by the whirlpool')
                    : clienttranslate('${player_name} pivots and is pushed by the gust');
            }
        } elseif ($seafeature_moves == 2) {
            $notification_message = clienttranslate('${player_name} is affected by the whirlpool and gust');
        } elseif ($seafeature_moves == 1) {
            $notification_message = $on_whirlpool
                ? clienttranslate('${player_name} is rotated by the whirlpool')
                : clienttranslate('${player_name} is pushed by the gust');
        }

        // With no pivot and no whirlpool or gust there is nothing to show.
        if ($notification_message !== null) {
            $this->bga->notify->all("cardPlayed", $notification_message, [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "ship" => $this->activeShipArg($player_id),
                "moveChain" => $outcome->actionChain,
                "cost" => $outcome->cost,
                "shipwreck_event" => $outcome->shipwreckEvent,
            ]);
        }

        $this->notifyBootyCollected($player_id, $outcome->bootyCard);

        $shipwreck_event = $outcome->shipwreckEvent;
        $seafeature_collision = $seafeature->collisionOccurred;

        if ($shipwreck_event !== null) {
            $resume = $seafeature_collision
                ? self::TREASURE_SEEKER_RESUME_COLLISION
                : self::TREASURE_SEEKER_RESUME_COLLISION_RESOLVED;
            if (
                $this->tryBeginTreasureSeekerShipwreckAdjust(
                    (string) $shipwreck_event["shipwreck_arg"],
                    (int) $shipwreck_event["new_x"],
                    (int) $shipwreck_event["new_y"],
                    $resume,
                )
            ) {
                return STATE_TREASURE_SEEKER_ADJUST;
            }
        }

        // If gust push caused another collision, stay in collision resolution state
        // Note: Seafeature effects are only applied once per card play, so this can only happen
        // when resolving a collision from the initial card play (gust push after collision resolution)
        if ($seafeature_collision) {
            return $this->collisionPenaltyState();
        }
        return $this->postCollisionFireState() ?? "collisionResolved";
    }

    function argCardFlag(): array
    {
        $type = (int) $this->getGameStateValue("pending_card_flag_type");
        $flag = $this->playable_cards[$type]["flag"] ?? null;
        $player_id = $this->getActivePlayerId();
        if ($flag === null || $this->getTokenOwner($flag . "_flag") != $player_id) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You do not own the matching flag for this card"));
        }
        $args = ["flag" => $flag];
        if ($flag === "red") {
            $args["_private"][$player_id] = $this->argScrapCard();
        }
        return $args;
    }

    function actResolveCardFlag(string $resource = '', ?int $card_id = null): int
    {
        $flag = $this->argCardFlag()["flag"];
        $player_id = $this->getActivePlayerId();
        switch ($flag) {
            case "green":
                if (!in_array($resource, ["sail", "cannonball", "doubloon"], true)) {
                    throw new \Bga\GameFramework\UserException(clienttranslate("Choose a resource"));
                }
                $this->playerGainResources($player_id, [$resource => 1]);
                break;
            case "tan":
                $this->drawCards($player_id);
                break;
            case "red":
                if ($card_id === null) {
                    throw new \Bga\GameFramework\UserException(clienttranslate("Choose a card to scrap"));
                }
                $this->scrapCardAndRefund($card_id, $player_id);
                break;
            case "blue":
                break;
            default:
                throw new \Bga\GameFramework\SystemException("Unknown card flag: $flag");
        }
        $this->setGameStateValue("pending_card_flag_type", 0);
        return $flag === "blue" && $this->cards->countCardInLocation("hand", $player_id) > 0
            ? STATE_SEA_TURN : STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function actSkipCardFlag(): int
    {
        $this->argCardFlag();
        $this->setGameStateValue("pending_card_flag_type", 0);
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }
}
