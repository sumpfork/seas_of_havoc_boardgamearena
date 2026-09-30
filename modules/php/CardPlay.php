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

    function processSimpleAction(PrimitiveCardPlayAction $action_type)
    {
        $player_id = $this->getActivePlayerId();
        $ship = $this->activeShipArg($player_id);
        $outcome = [];
        $collision_occurred = false;
        $shipwreck_event = null;
        $booty_card = null;
        switch ($action_type) {
            case PrimitiveCardPlayAction::FORWARD:
                $result = $this->seaboard->moveObjectForward("player_ship", $ship, ["rock", "player_ship"]);
                $outcome[] = $result;
                if ($result["type"] == "collision") {
                    $collision_occurred = true;
                    $this->applyCollisionPenalty((string) $player_id, $result["colliders"]);
                } else {
                    $pickup = $this->collectShipwrecksAtPlayer($player_id);
                    $shipwreck_event = $pickup["shipwreck_event"] ?? $shipwreck_event;
                    $booty_card = $pickup["booty_card"] ?? $booty_card;
                }
                break;
            case PrimitiveCardPlayAction::PIVOT_LEFT:
                $outcome[] = $this->seaboard->turnObject("player_ship", $ship, Turn::LEFT);
                break;
            case PrimitiveCardPlayAction::PIVOT_AROUND:
                $outcome[] = $this->seaboard->turnObject("player_ship", $ship, Turn::AROUND);
                break;
            case PrimitiveCardPlayAction::PIVOT_RIGHT:
                $outcome[] = $this->seaboard->turnObject("player_ship", $ship, Turn::RIGHT);
                break;
            default:
                throw new \Bga\GameFramework\SystemException("Unknown action type: " . $action_type->value);
        }
        $this->mydump("processSimpleAction outcome", $outcome);
        return [
            "action_chain" => $outcome,
            "collision_occurred" => $collision_occurred,
            "shipwreck_event" => $shipwreck_event,
            "booty_card" => $booty_card,
        ];
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
            } elseif ($collider["type"] === "rock") {
                $this->bga->playerStats->inc("rock_collisions", 1, (int) $player_id);
                $this->dealDamageCard($player_id);
            }
        }
    }

    function merge_results(
        array $result,
        $cost,
        array &$to_send,
        array &$total_cost,
        bool &$collision_occurred,
        ?array &$shipwreck_event,
        ?array &$booty_card,
    ) {
        // Nested calls (choice/sequence/left/right) accumulate their own costs; without adding
        // $result["cost"] here, anything paid for inside a choice or sequence was free.
        $total_cost = $this->sum_array_by_key($total_cost, $cost, $result["cost"] ?? []);
        if (array_key_exists("collision_occurred", $result)) {
            $collision_occurred = $collision_occurred || $result["collision_occurred"];
        }
        if ($shipwreck_event == null && array_key_exists("shipwreck_event", $result)) {
            $shipwreck_event = $result["shipwreck_event"];
        }
        if ($booty_card == null && array_key_exists("booty_card", $result)) {
            $booty_card = $result["booty_card"];
        }
        $to_send = array_merge($to_send, $result["action_chain"]);
    }

    function processCardActions(array $actions, array $decisions)
    {
        $to_send = [];
        $total_cost = [];
        $this->mytrace("processing card actions");
        $this->mydump("actions", $actions);
        $collision_occurred = false;
        $shipwreck_event = null;
        $booty_card = null;
        foreach ($actions as $i => $action) {
            $typed_action =
                gettype($action["action"]) == "string"
                    ? PrimitiveCardPlayAction::from($action["action"])
                    : $action["action"];
            $this->mytrace("handling " . $typed_action->value);
            $this->mydump("to_send", $to_send);
            if (array_key_exists("cost", $action)) {
                $decision = $decisions[0];
                if ($decision == "skip") {
                    $this->mytrace("skipping action with cost due to decision == 'skip': " . $typed_action->value);
                    array_shift($decisions);
                    $this->mytrace("decisions after skipping: " . implode(", ", $decisions));
                    continue;
                }
            }
            $cost = $action["cost"] ?? [];
            switch ($typed_action) {
                case PrimitiveCardPlayAction::SEQUENCE:
                    $this->merge_results(
                        $this->processCardActions($action["actions"], $decisions),
                        $cost,
                        $to_send,
                        $total_cost,
                        $collision_occurred,
                        $shipwreck_event,
                        $booty_card,
                    );
                    break;
                case PrimitiveCardPlayAction::CHOICE:
                    $decision = array_shift($decisions);
                    $choices = $action["choices"];
                    $choice_names = array_map(fn($x) => key_exists("name", $x) ? $x["name"] : $x["action"], $choices);
                    $decision_index = array_search($decision, $choice_names, true);
                    if ($decision_index === false) {
                        throw new \Bga\GameFramework\SystemException("Invalid card action choice: " . $decision);
                    }
                    if ($decision === self::NIMBLE_HULL_CHOICE) {
                        $this->useNimbleHull($this->getActivePlayerId());
                    }
                    $result = $this->processCardActions([$choices[array_keys($choices)[$decision_index]]], $decisions);
                    $this->merge_results(
                        $result,
                        $cost,
                        $to_send,
                        $total_cost,
                        $collision_occurred,
                        $shipwreck_event,
                        $booty_card,
                    );
                    break;
                case PrimitiveCardPlayAction::LEFT:
                    $result = $this->processCardActions(
                        [
                            ["action" => PrimitiveCardPlayAction::FORWARD],
                            ["action" => PrimitiveCardPlayAction::PIVOT_LEFT],
                            ["action" => PrimitiveCardPlayAction::FORWARD],
                        ],
                        $decisions,
                    );
                    $this->merge_results(
                        $result,
                        $cost,
                        $to_send,
                        $total_cost,
                        $collision_occurred,
                        $shipwreck_event,
                        $booty_card,
                    );
                    break;
                case PrimitiveCardPlayAction::RIGHT:
                    $result = $this->processCardActions(
                        [
                            ["action" => PrimitiveCardPlayAction::FORWARD],
                            ["action" => PrimitiveCardPlayAction::PIVOT_RIGHT],
                            ["action" => PrimitiveCardPlayAction::FORWARD],
                        ],
                        $decisions,
                    );
                    $this->merge_results(
                        $result,
                        $cost,
                        $to_send,
                        $total_cost,
                        $collision_occurred,
                        $shipwreck_event,
                        $booty_card,
                    );
                    break;
                case PrimitiveCardPlayAction::FIRE:
                case PrimitiveCardPlayAction::FIRE2:
                case PrimitiveCardPlayAction::FIRE3:
                    $this->mytrace("fire");
                    $decision = array_shift($decisions);
                    $this->mytrace("decision: $decision");
                    [$variant, $side] = ShipUpgrades::parseFireDecision($action, $decision);
                    // The chosen shot, not the action, decides what firing costs.
                    $cost = $variant["cost"];
                    $fire_chain = $this->resolveFireAction($variant, $side);
                    // FIRE actions never cause movement collisions - explicitly set collision_occurred to false
                    $this->merge_results(
                        [
                            "action_chain" => $fire_chain,
                            "collision_occurred" => false,
                            "shipwreck_event" => null,
                            "booty_card" => null,
                        ],
                        $cost,
                        $to_send,
                        $total_cost,
                        $collision_occurred,
                        $shipwreck_event,
                        $booty_card,
                    );
                    break;
                case PrimitiveCardPlayAction::CAPTAIN_ABILITY:
                    $result = $this->processCaptainAbility($action["ability"]);
                    if (is_int($result)) {
                        return [
                            "cost" => $total_cost,
                            "action_chain" => $to_send,
                            "collision_occurred" => false,
                            "shipwreck_event" => null,
                            "booty_card" => null,
                            "captain_state" => $result,
                        ];
                    }
                    $this->merge_results(
                        $result,
                        $cost,
                        $to_send,
                        $total_cost,
                        $collision_occurred,
                        $shipwreck_event,
                        $booty_card,
                    );
                    break;
                default:
                    $result = $this->processSimpleAction($typed_action);
                    $this->merge_results(
                        $result,
                        $cost,
                        $to_send,
                        $total_cost,
                        $collision_occurred,
                        $shipwreck_event,
                        $booty_card,
                    );
                    break;
            }
            if ($collision_occurred) {
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
        return [
            "cost" => $total_cost,
            "action_chain" => $to_send,
            "collision_occurred" => $collision_occurred,
            "shipwreck_event" => $shipwreck_event,
            "booty_card" => $booty_card,
        ];
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
        return ["action" => $action];
    }

    function actPostCollisionFire(string $decision, ?int $use_booty_card_id = null): mixed
    {
        $player_id = (int) $this->getActivePlayerId();
        $action = $this->getPendingFireAction($player_id);
        if ($action === null) {
            throw new \Bga\GameFramework\SystemException("No firing action is pending after the collision");
        }
        $this->clearNextActionOnCard($player_id);

        // processCardActions understands "skip" for a costed action, so declining runs the same path.
        $outcome = $this->processCardActions([$action], [$decision]);
        if (!empty($outcome["cost"])) {
            $this->payWithOptionalBooty($player_id, $outcome["cost"], $use_booty_card_id);
        }
        if (!empty($outcome["action_chain"])) {
            $this->bga->notify->all("cardPlayed", clienttranslate('${player_name} fires after the collision'), [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "ship" => $this->activeShipArg($player_id),
                "moveChain" => $outcome["action_chain"],
                "cost" => $outcome["cost"],
                "shipwreck_event" => null,
            ]);
        }
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    function applyWhirlpoolRotation($player_id)
    {
        // Check if the ship is on a whirlpool
        $ship = $this->activeShipArg($player_id);
        if ($this->seaboard->isObjectOnWhirlpool("player_ship", $ship)) {
            $this->mytrace("Ship is on whirlpool - rotating 90 degrees clockwise");
            $turn_result = $this->seaboard->turnObject("player_ship", $ship, Turn::RIGHT);
            return ["result" => $turn_result, "occurred" => true];
        }
        return ["result" => null, "occurred" => false];
    }

    function applyGustPush($player_id)
    {
        // Check if the ship is on a gust
        $ship = $this->activeShipArg($player_id);
        $gust = $this->seaboard->getGustAtObjectLocation("player_ship", $ship);
        if ($gust) {
            $this->mytrace("Ship is on gust - pushing in direction " . $gust["heading"]->toString());
            $push_result = $this->seaboard->pushObjectInDirection("player_ship", $ship, $gust["heading"], [
                "rock",
                "player_ship",
            ]);

            // Return the result and whether a collision occurred
            return ["result" => $push_result, "collision" => $push_result["type"] == "collision"];
        }
        return ["result" => null, "collision" => false];
    }

    function applySeafeatureEffects($player_id)
    {
        $seafeature_moves = [];
        $collision = false;
        $shipwreck_event = null;
        $booty_card = null;

        // Apply whirlpool rotation first
        $whirlpool_result = $this->applyWhirlpoolRotation($player_id);
        if ($whirlpool_result["occurred"]) {
            $seafeature_moves[] = $whirlpool_result["result"];
        }

        // Then apply gust push (which can cause collision)
        $gust_result = $this->applyGustPush($player_id);
        if ($gust_result["result"] !== null) {
            $seafeature_moves[] = $gust_result["result"];
            $collision = $gust_result["collision"];
            if ($collision) {
                $this->applyCollisionPenalty((string) $player_id, $gust_result["result"]["colliders"]);
            }
            if ($gust_result["result"]["type"] != "collision") {
                $pickup = $this->collectShipwrecksAtPlayer($player_id);
                $shipwreck_event = $pickup["shipwreck_event"] ?? $shipwreck_event;
                $booty_card = $pickup["booty_card"] ?? $booty_card;
            }
        }

        return [
            "moves" => $seafeature_moves,
            "collision" => $collision,
            "shipwreck_event" => $shipwreck_event,
            "booty_card" => $booty_card,
        ];
    }

    function actPlayCard(int $card_type, int $card_id, #[JsonParam] $decisions, ?int $use_booty_card_id = null, int $ship = 1)
    {
        $held = $this->cards->getCard($card_id);
        if (!$held || $held["location"] !== "hand" || $held["location_arg"] != $this->getActivePlayerId() ||
            (int) $held["type"] !== $card_type) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose a card from your hand"));
        }
        if ($ship !== 1 && ($ship !== 2 || !$this->hasSecondShip($this->getActivePlayerId()))) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose one of your ships"));
        }
        // Everything this card goes on to do - including a captain card it opens, the shot after a
        // collision, and the sea features at the end - applies to this ship.
        $this->setGameStateValue("active_ship", $ship);
        return $this->resolvePlayedCard($card_type, $card_id, $decisions, $use_booty_card_id);
    }

    protected function resolvePlayedCard(int $card_type, int $card_id, array $decisions, ?int $use_booty_card_id = null, ?array $actions = null)
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
            $outcome = [
                "action_chain" => [],
                "cost" => [],
                "collision_occurred" => false,
            ];
            $notification_message = clienttranslate('${player_name} has passed (played a card without actions)');
            $all_moves = [];
            $seafeature_collision = false;
            $shipwreck_event = null;
            $booty_card = null;
        } else {
            $outcome = $this->processCardActions($actions ?? $this->upgradedCardActions($card, $player_id), $decisions);

            if (isset($outcome["captain_state"])) {
                if ($outcome["captain_state"] === STATE_CAPTAIN_CARD) {
                    $this->setGameStateValue("pending_captain_card", $card_id);
                }
                $this->discardCardToPlayer($card_id, $player_id);
                $this->bga->notify->all("cardPlayed", clienttranslate('${player_name} has played a card'), [
                    "player_name" => $this->getPlayerNameById($player_id),
                    "player_id" => $player_id,
                    "ship" => $this->activeShipArg($player_id),
                    "moveChain" => [],
                    "cost" => [],
                    "shipwreck_event" => null,
                ]);
                return $outcome["captain_state"];
            }

            $this->mydump("final card play outcome", $outcome);

            // Pay the total cost from all actions (optionally using booty token)
            if (!empty($outcome["cost"])) {
                $this->payWithOptionalBooty($player_id, $outcome["cost"], $use_booty_card_id);
            }

            // Apply seafeature effects (whirlpool rotation and gust push) if no collision occurred
            // If collision occurred, seafeature effects will be applied after collision resolution
            $seafeature_collision = false;
            $all_moves = $outcome["action_chain"];
            $notification_message = clienttranslate('${player_name} has played a card');
            $shipwreck_event = $outcome["shipwreck_event"] ?? null;
            $booty_card = $outcome["booty_card"] ?? null;

            // Track whether seafeature effects have been attempted (to prevent applying them multiple times)
            $this->setGameStateValue("seafeature_effects_attempted", 0);

            if (!$outcome["collision_occurred"]) {
                $seafeature_effects = $this->applySeafeatureEffects($player_id);
                $seafeature_collision = $seafeature_effects["collision"];
                $this->setGameStateValue("seafeature_effects_attempted", 1);
                if ($shipwreck_event == null) {
                    $shipwreck_event = $seafeature_effects["shipwreck_event"] ?? null;
                }
                if ($booty_card == null) {
                    $booty_card = $seafeature_effects["booty_card"] ?? null;
                }

                // Append seafeature moves to the moveChain for sequential animation
                if (!empty($seafeature_effects["moves"])) {
                    $all_moves = array_merge($all_moves, $seafeature_effects["moves"]);

                    // Update notification message to mention seafeature effects
                    if (count($seafeature_effects["moves"]) == 2) {
                        $notification_message = clienttranslate(
                            '${player_name} has played a card and is affected by the whirlpool and gust',
                        );
                    } elseif ($this->seaboard->isObjectOnWhirlpool("player_ship", $this->activeShipArg($player_id))) {
                        $notification_message = clienttranslate(
                            '${player_name} has played a card and is rotated by the whirlpool',
                        );
                    } else {
                        $notification_message = clienttranslate(
                            '${player_name} has played a card and is pushed by the gust',
                        );
                    }
                }
            }
        }

        $this->discardCardToPlayer($card_id, $player_id);

        $this->bga->notify->all("cardPlayed", $notification_message, [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
            "ship" => $this->activeShipArg($player_id),
            "moveChain" => $all_moves,
            "cost" => $outcome["cost"],
            "shipwreck_event" => $shipwreck_event,
        ]);

        if ($booty_card != null) {
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

        if (
            $this->maybeDeferSeaPhaseForTreasureSeeker(
                $shipwreck_event,
                $outcome["collision_occurred"] || $seafeature_collision,
            )
        ) {
            return STATE_TREASURE_SEEKER_ADJUST;
        }

        return $outcome["collision_occurred"] || $seafeature_collision
            ? $this->collisionPenaltyState()
            : "seaTurnDone";
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

    function stResolveCollision()
    {
        $this->mytrace("stResolveCollision");
    }

    function actResolveCollision(string $card_id, string $action_type)
    {
        $this->mytrace("actResolveCollision");
        $this->mydump("card_id", $card_id);
        #$this->gamestate->nextState("seaTurnDone");
    }

    function argResolveCollision()
    {
        $this->mytrace("argResolveCollision");
    }

    function actPivotPickedInDialog(string $direction)
    {
        $this->mytrace("actPivotPickedInDialog");
        $player_id = $this->getActivePlayerId();

        // The pivot is part of resolving the collision; whirlpools and gusts resolve after it, like
        // after any card. Doing it the other way round still produced the right final heading for a
        // rotation, but each move carried headings from the opposite order to the one the client
        // animates them in, so the ship was drawn facing the wrong way until the next reload.
        $pivot_outcome = ["action_chain" => [], "cost" => [], "shipwreck_event" => null, "booty_card" => null];
        if ($direction != "no pivot") {
            $typed_action = PrimitiveCardPlayAction::from($direction);
            $pivot_outcome = $this->processCardActions([["action" => $typed_action]], []);
            $this->mydump("final pivot outcome", $pivot_outcome);

            // Pay the cost for pivot actions (pivots are free, but just in case)
            if (!empty($pivot_outcome["cost"])) {
                $this->payWithOptionalBooty($player_id, $pivot_outcome["cost"]);
            }
        }

        // Only apply seafeature effects if they haven't been attempted yet (once per card play)
        $seafeature_effects = ["moves" => [], "collision" => false];
        $seafeature_collision = false;
        $shipwreck_event = null;
        $booty_card = null;

        $seafeature_effects_attempted = $this->getGameStateValue("seafeature_effects_attempted");
        if ($seafeature_effects_attempted == 0) {
            $seafeature_effects = $this->applySeafeatureEffects($player_id);
            $seafeature_collision = $seafeature_effects["collision"];
            $this->setGameStateValue("seafeature_effects_attempted", 1);
        } else {
            $this->mytrace("Seafeature effects already attempted, skipping");
        }

        if ($direction != "no pivot") {
            $outcome = $pivot_outcome;

            // Combine pivot moves with seafeature moves for sequential animation
            $all_moves = array_merge($outcome["action_chain"], $seafeature_effects["moves"]);
            $notification_message = clienttranslate('${player_name} pivots');
            $shipwreck_event = $outcome["shipwreck_event"] ?? null;
            if ($shipwreck_event == null) {
                $shipwreck_event = $seafeature_effects["shipwreck_event"] ?? null;
            }
            $booty_card = $outcome["booty_card"] ?? null;
            if ($booty_card == null) {
                $booty_card = $seafeature_effects["booty_card"] ?? null;
            }

            if (!empty($seafeature_effects["moves"])) {
                if (count($seafeature_effects["moves"]) == 2) {
                    $notification_message = clienttranslate(
                        '${player_name} pivots and is affected by the whirlpool and gust',
                    );
                } elseif ($this->seaboard->isObjectOnWhirlpool("player_ship", $this->activeShipArg($player_id))) {
                    $notification_message = clienttranslate('${player_name} pivots and is rotated by the whirlpool');
                } else {
                    $notification_message = clienttranslate('${player_name} pivots and is pushed by the gust');
                }
            }

            $this->bga->notify->all("cardPlayed", $notification_message, [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "ship" => $this->activeShipArg($player_id),
                "moveChain" => $all_moves,
                "cost" => $outcome["cost"],
                "shipwreck_event" => $shipwreck_event,
            ]);
        } elseif (!empty($seafeature_effects["moves"])) {
            // No pivot, but we still need to notify about seafeature effects
            $notification_message = clienttranslate('${player_name} resolves collision');
            $shipwreck_event = $seafeature_effects["shipwreck_event"] ?? null;
            $booty_card = $seafeature_effects["booty_card"] ?? null;

            if (count($seafeature_effects["moves"]) == 2) {
                $notification_message = clienttranslate('${player_name} is affected by the whirlpool and gust');
            } elseif ($this->seaboard->isObjectOnWhirlpool("player_ship", $this->activeShipArg($player_id))) {
                $notification_message = clienttranslate('${player_name} is rotated by the whirlpool');
            } else {
                $notification_message = clienttranslate('${player_name} is pushed by the gust');
            }

            $this->bga->notify->all("cardPlayed", $notification_message, [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "ship" => $this->activeShipArg($player_id),
                "moveChain" => $seafeature_effects["moves"],
                "cost" => [],
                "shipwreck_event" => $shipwreck_event,
            ]);
        }

        if ($booty_card != null) {
            $this->bga->notify->all("bootyTokenCollected", clienttranslate('${player_name} collected a booty token'), [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
            ]);
            $this->bga->notify->player($player_id, "bootyTokenRevealed", clienttranslate("You reveal a booty token"), [
                "booty_tokens" => $this->getBootyTokensForPlayer($player_id),
                "new_token" => $booty_card,
            ]);
        }

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
