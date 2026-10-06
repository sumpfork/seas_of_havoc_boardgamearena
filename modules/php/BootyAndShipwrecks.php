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
 * Booty tokens (drawing, capacity, discarding) and shipwrecks (placement, pickup, the Treasure Seeker's adjustment).
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait BootyAndShipwrecks
{
    private function getBootyTokensForPlayer(int $player_id): array
    {
        // array_values ensures JSON encodes as a JS array, not an object keyed by card id
        return array_values($this->cards->getCardsInLocation("booty_player", $player_id));
    }

    private function getPlayersWithBooty(): array
    {
        $players_with_booty = [];
        $player_info = $this->getPlayerInfo();
        foreach ($player_info as $player_id => $player) {
            if ($this->cards->countCardInLocation("booty_player", $player_id) > 0) {
                $players_with_booty[] = $player_id;
            }
        }
        return $players_with_booty;
    }

    /** $location lets callers that only reveal a token (Unearth Riches) keep it out of the hold. */
    private function drawBootyToken(int $player_id, string $location = "booty_player"): ?array
    {
        $remaining = $this->cards->countCardInLocation("booty_deck");
        if ($remaining == 0) {
            // Refill deck from discard, then shuffle
            $discard = $this->cards->getCardsInLocation("booty_discard");
            if (empty($discard)) {
                return null;
            }
            foreach ($discard as $card) {
                $this->cards->moveCard($card["id"], "booty_deck", 0);
            }
            $this->cards->shuffle("booty_deck");
            $this->mytrace("Booty deck refilled from discard and shuffled");
        }
        $card = $this->cards->pickCardForLocation("booty_deck", $location, $player_id);
        $this->mydump("drawBootyToken picked", $card);
        return $card;
    }

    /**
     * A hold takes 1 booty token, or 2 with the Galleon's Treasure Hold upgrade. A player who picks
     * up more than fits chooses which to drop, so this returns the state that asks them rather than
     * dropping one for them. Checked where a turn hands back to the state machine, because pickups
     * happen deep inside card resolution.
     */
    private function bootyOverflowState(int $return_state): ?int
    {
        $player_id = (int) $this->getActivePlayerId();
        if (count($this->getBootyTokensForPlayer($player_id)) <= $this->bootyCapacity($player_id)) {
            return null;
        }
        $this->setGameStateValue("booty_discard_return_state", $return_state);
        return STATE_BOOTY_DISCARD;
    }

    private function discardBootyToken(int $player_id, int $card_id): void
    {
        $this->cards->moveCard($card_id, "booty_discard");
        $this->bga->notify->all(
            "log",
            clienttranslate('${player_name} has no room in their hold and discards a booty token'),
            ["player_name" => $this->getPlayerNameById($player_id), "player_id" => $player_id],
        );
        $this->notifyBootyTokensChanged($player_id);
    }

    /** The held tokens are secret: only their owner gets the updated list. */
    function notifyBootyTokensChanged(int $player_id): void
    {
        $this->bga->notify->player($player_id, "bootyTokenUsed", "", [
            "player_id" => $player_id,
            "booty_tokens" => $this->getBootyTokensForPlayer($player_id),
        ]);
    }

    /** The held tokens are secret, so the choice is offered privately. */
    function argBootyDiscard(): array
    {
        $player_id = (int) $this->getActivePlayerId();
        $tokens = array_map(
            fn($token) => [
                "id" => (int) $token["id"],
                "image_id" => (int) $token["type_arg"],
                "resources" => $this->getBootyTokenConfigByTypeArg((int) $token["type_arg"])["resources"] ?? [],
            ],
            $this->getBootyTokensForPlayer($player_id),
        );
        return ["_private" => [$player_id => ["booty_tokens" => $tokens]]];
    }

    function actDiscardBootyToken(int $card_id): mixed
    {
        $player_id = (int) $this->getActivePlayerId();
        $held = array_map(fn($token) => (int) $token["id"], $this->getBootyTokensForPlayer($player_id));
        if (!in_array($card_id, $held, true)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose a booty token from your hold"));
        }
        $this->discardBootyToken($player_id, $card_id);
        $return_state = (int) $this->getGameStateValue("booty_discard_return_state");
        // A hold can overflow by more than one, so keep asking until it fits.
        return $this->bootyOverflowState($return_state) ?? $return_state;
    }

    /** Zombie/timeout fallback: drop the least valuable token so the game can continue. */
    function discardLeastValuableBootyToken(int $player_id): void
    {
        $held = $this->getBootyTokensForPlayer($player_id);
        usort($held, fn($a, $b) => $this->bootyTokenValue($a) <=> $this->bootyTokenValue($b));
        while (count($held) > $this->bootyCapacity($player_id)) {
            $dropped = array_shift($held);
            $this->discardBootyToken($player_id, (int) $dropped["id"]);
        }
    }

    private function bootyTokenValue(array $token): int
    {
        $config = $this->getBootyTokenConfigByTypeArg((int) $token["type_arg"]);
        return array_sum($config["resources"] ?? []);
    }

    private function collectShipwrecksAtPlayer(int $player_id): array
    {
        $ship_info = $this->seaboard->findObject("player_ship", $this->activeShipArg($player_id));
        if (!$ship_info) {
            return ["shipwreck_event" => null, "booty_card" => null];
        }
        $this->mydump("collectShipwrecksAtPlayer ship_info", $ship_info);
        $x = $ship_info["x"];
        $y = $ship_info["y"];
        $shipwrecks = $this->seaboard->getObjectsOfTypes($x, $y, ["shipwreck"]);
        $this->mydump("collectShipwrecksAtPlayer shipwrecks", $shipwrecks);
        if (empty($shipwrecks)) {
            return ["shipwreck_event" => null, "booty_card" => null];
        }
        $shipwreck = $shipwrecks[0];
        $this->seaboard->removeObject($x, $y, "shipwreck", $shipwreck["arg"]);
        $new_position = $this->findEmptyBoardPosition($this->shipwreckPlacementBlockingTypes());
        $this->mydump("collectShipwrecksAtPlayer new_position", $new_position);
        $this->placeShipwreck($shipwreck["arg"], $new_position["x"], $new_position["y"]);
        $event = [
            "shipwreck_arg" => $shipwreck["arg"],
            "old_x" => $x,
            "old_y" => $y,
            "new_x" => $new_position["x"],
            "new_y" => $new_position["y"],
        ];
        $booty_card = $this->drawBootyToken($player_id);
        $this->mydump("collectShipwrecksAtPlayer result", ["event" => $event, "booty_card" => $booty_card]);
        return ["shipwreck_event" => $event, "booty_card" => $booty_card];
    }

    /** Number of booty tokens a player may keep in their hold. */
    function bootyCapacity($player_id): int
    {
        return $this->hasShipUpgrade($player_id, "galleon_treasure_hold") ? 2 : 1;
    }

    private function shipwreckPlacementBlockingTypes(): array
    {
        // Rulebook: shipwrecks may be on gusts/whirlpools, but not rocks, ships, or other shipwrecks.
        return ["player_ship", "rock", "shipwreck", "sea_monster_part"];
    }

    function getTreasureSeekerPlayerId(): ?string
    {
        foreach ($this->loadPlayersBasicInfos() as $player_id => $_) {
            if ($this->getPlayerCaptain($player_id) === "treasure_seeker") {
                return (string) $player_id;
            }
        }

        return null;
    }

    function getSurroundingBoardPositions(int $x, int $y): array
    {
        return $this->seaboard->getSurroundingPositions($x, $y);
    }

    function isValidShipwreckBoardPosition(int $x, int $y): bool
    {
        return empty($this->seaboard->getObjectsOfTypes($x, $y, $this->shipwreckPlacementBlockingTypes()));
    }

    function getValidTreasureSeekerShipwreckPositions(int $x, int $y): array
    {
        return array_values(
            array_filter(
                $this->getSurroundingBoardPositions($x, $y),
                fn($pos) => $this->isValidShipwreckBoardPosition($pos["x"], $pos["y"]),
            ),
        );
    }

    function placeShipwreck(string $arg, int $x, int $y): void
    {
        $this->seaboard->placeObject($x, $y, [
            "type" => "shipwreck",
            "arg" => $arg,
            "heading" => Heading::NO_HEADING,
        ]);
    }

    function moveShipwreck(string $arg, int $from_x, int $from_y, int $to_x, int $to_y): array
    {
        $this->seaboard->removeObject($from_x, $from_y, "shipwreck", $arg);
        $this->placeShipwreck($arg, $to_x, $to_y);

        return [
            "shipwreck_arg" => $arg,
            "old_x" => $from_x,
            "old_y" => $from_y,
            "new_x" => $to_x,
            "new_y" => $to_y,
        ];
    }

    function tryBeginTreasureSeekerShipwreckAdjust(string $shipwreck_arg, int $x, int $y, int $resume): bool
    {
        $treasure_seeker_id = $this->getTreasureSeekerPlayerId();
        if ($treasure_seeker_id === null) {
            return false;
        }

        $valid_positions = $this->getValidTreasureSeekerShipwreckPositions($x, $y);
        if (empty($valid_positions)) {
            return false;
        }

        $this->setGameStateValue("pending_shipwreck_arg", (int) $shipwreck_arg);
        $this->setGameStateValue("pending_shipwreck_x", $x);
        $this->setGameStateValue("pending_shipwreck_y", $y);
        $this->setGameStateValue("pending_treasure_seeker_resume", $resume);
        $this->setGameStateValue("pending_treasure_seeker_player", (int) $this->getActivePlayerId());

        $this->gamestate->changeActivePlayer((int) $treasure_seeker_id);
        $this->giveExtraTime((int) $treasure_seeker_id);
        // Note: jumpToState is removed - callers return the TreasureSeekerAdjust state class to trigger the transition
        return true;
    }

    private function maybeDeferSeaPhaseForTreasureSeeker(?array $shipwreck_event, bool $collision_pending): bool
    {
        if ($shipwreck_event === null) {
            return false;
        }

        $resume = $collision_pending
            ? self::TREASURE_SEEKER_RESUME_COLLISION
            : self::TREASURE_SEEKER_RESUME_SEA_TURN_DONE;

        return $this->tryBeginTreasureSeekerShipwreckAdjust(
            (string) $shipwreck_event["shipwreck_arg"],
            (int) $shipwreck_event["new_x"],
            (int) $shipwreck_event["new_y"],
            $resume,
        );
    }

    private function completeTreasureSeekerAdjust(): mixed
    {
        $resume = (int) $this->getGameStateValue("pending_treasure_seeker_resume");
        $this->gamestate->changeActivePlayer((int) $this->getGameStateValue("pending_treasure_seeker_player"));
        $this->giveExtraTime((int) $this->getGameStateValue("pending_treasure_seeker_player"));
        $this->setGameStateValue("pending_treasure_seeker_player", 0);
        $this->setGameStateValue("pending_shipwreck_arg", 0);
        $this->setGameStateValue("pending_shipwreck_x", 0);
        $this->setGameStateValue("pending_shipwreck_y", 0);
        $this->setGameStateValue("pending_treasure_seeker_resume", 0);

        switch ($resume) {
            case self::TREASURE_SEEKER_RESUME_SEA_TURN_DONE:
                return STATE_NEXT_PLAYER_SEA_PHASE;
            case self::TREASURE_SEEKER_RESUME_COLLISION_RESOLVED:
                return $this->postCollisionFireState() ?? STATE_NEXT_PLAYER_SEA_PHASE;
            case self::TREASURE_SEEKER_RESUME_COLLISION:
                return $this->collisionPenaltyState();
            default:
                throw new \Bga\GameFramework\SystemException("Unknown treasure seeker resume value: $resume");
        }
    }

    private function getShipwrecksOnBoard(): array
    {
        $shipwrecks = array_values(
            array_filter($this->seaboard->getAllObjectsFlat(), fn($entry) => $entry["type"] === "shipwreck"),
        );
        usort($shipwrecks, fn($a, $b) => ((int) $a["arg"]) <=> ((int) $b["arg"]));
        return $shipwrecks;
    }

    /** Get booty token config (including "resources") by card type_arg (image_id). */
    private function getBootyTokenConfigByTypeArg(int $type_arg): ?array
    {
        foreach ($this->booty_tokens as $cfg) {
            if (($cfg["image_id"] ?? null) === $type_arg) {
                return $cfg;
            }
        }
        return null;
    }

    function argTreasureSeekerAdjust()
    {
        $this->mytrace("argTreasureSeekerAdjust");
        $player_id = self::getActivePlayerId();
        if ($this->getPlayerCaptain($player_id) !== "treasure_seeker") {
            throw new \Bga\GameFramework\SystemException("Only the Treasure Seeker can adjust shipwreck placement");
        }

        $x = (int) $this->getGameStateValue("pending_shipwreck_x");
        $y = (int) $this->getGameStateValue("pending_shipwreck_y");

        return [
            "shipwreck_arg" => (string) $this->getGameStateValue("pending_shipwreck_arg"),
            "x" => $x,
            "y" => $y,
            "valid_positions" => $this->getValidTreasureSeekerShipwreckPositions($x, $y),
        ];
    }

    function actAdjustShipwreck(int $x, int $y)
    {
        $this->mytrace("actAdjustShipwreck: x=$x y=$y");
        $player_id = self::getActivePlayerId();

        if ($this->getPlayerCaptain($player_id) !== "treasure_seeker") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Treasure Seeker can use this action"));
        }

        $shipwreck_arg = (string) $this->getGameStateValue("pending_shipwreck_arg");
        $from_x = (int) $this->getGameStateValue("pending_shipwreck_x");
        $from_y = (int) $this->getGameStateValue("pending_shipwreck_y");

        $valid = false;
        foreach ($this->getValidTreasureSeekerShipwreckPositions($from_x, $from_y) as $position) {
            if ($position["x"] === $x && $position["y"] === $y) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            throw new \Bga\GameFramework\UserException(clienttranslate("That is not a valid surrounding space for the shipwreck"));
        }

        $event = $this->moveShipwreck($shipwreck_arg, $from_x, $from_y, $x, $y);
        $this->bga->notify->all(
            "shipwreckAdjusted",
            clienttranslate('${player_name}\'s Treasure Seeker ability: moves the shipwreck'),
            [
                "player_name" => $this->getPlayerNameById((int) $player_id),
                "player_id" => (int) $player_id,
                "shipwreck_event" => $event,
            ],
        );
        return $this->completeTreasureSeekerAdjust();
    }

    function actSkipTreasureSeekerAdjust(): mixed
    {
        $this->mytrace("actSkipTreasureSeekerAdjust");
        $player_id = self::getActivePlayerId();

        if ($this->getPlayerCaptain($player_id) !== "treasure_seeker") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Treasure Seeker can use this action"));
        }

        return $this->completeTreasureSeekerAdjust();
    }
}
