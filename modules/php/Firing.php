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

/**
 * Cannon fire: resolving shots of every type, hits and raking, rocket blasts, and chain shot losses.
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait Firing
{
    private function fireTurn(string $side): Turn
    {
        return match ($side) {
            "left" => Turn::LEFT,
            "right" => Turn::RIGHT,
            "fore" => Turn::NOTURN,
            "aft" => Turn::AROUND,
        };
    }

    /**
     * Fire one chosen shot type (possibly several cannon, possibly out of both sides) and apply
     * every hit. Returns the action_chain entries the client animates.
     */
    function resolveFireAction(array $variant, string $side): array
    {
        $player_id = $this->getActivePlayerId();
        $sides = ShipUpgrades::shotSides($variant, $side);
        $is_plain_shot = isset(ShipUpgrades::FIRE_COUNTS[$variant["name"]]);

        $this->bga->notify->all(
            "log",
            $is_plain_shot
                ? ($variant["count"] === 1
                    ? clienttranslate('${player_name} fires ${cannon_count} cannon to the ${direction} (range ${range})')
                    : clienttranslate('${player_name} fires ${cannon_count} cannons to the ${direction} (range ${range})'))
                : clienttranslate('${player_name} fires ${shot_name} to the ${direction} (range ${range})'),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "cannon_count" => $variant["count"],
                "shot_name" => $variant["name"],
                "direction" => $this->sidesLabel(array_values(array_unique($sides))),
                "range" => $variant["range"],
                "i18n" => ["direction", "shot_name"],
            ],
        );

        $chain = [];
        foreach ($sides as $shot_side) {
            $chain = array_merge($chain, $this->resolveOneShot($player_id, $variant, $shot_side));
        }
        return $chain;
    }

    /** The side(s) a shot goes out of, for the log: "left", or "left and right". */
    private function sidesLabel(array $sides): array|string
    {
        if (count($sides) === 1) {
            return $sides[0];
        }
        return [
            "log" => clienttranslate('${side1} and ${side2}'),
            "args" => ["side1" => $sides[0], "side2" => $sides[1], "i18n" => ["side1", "side2"]],
        ];
    }

    private function resolveOneShot(string $player_id, array $variant, string $side): array
    {
        $direction = $this->fireTurn($side);
        $range = (int) $variant["range"];
        $shot = $variant["shot"];
        $chain = [];
        $from_distance = 0;
        $hit_a_ship = false;
        $this->bga->playerStats->inc("shots_fired", 1, (int) $player_id);

        while (true) {
            $outcome = $this->seaboard->resolveCannonFire(
                $this->activeShipArg($player_id),
                $direction,
                $range,
                ["rock", "player_ship"],
                $from_distance,
            );
            $chain[] = $outcome;
            if ($outcome["type"] != "fire_hit") {
                break;
            }

            $hit_rock = false;
            foreach ($outcome["hit_objects"] as $collider) {
                if ($collider["type"] != "player_ship") {
                    $hit_rock = true;
                    continue;
                }
                $this->applyShipHit($player_id, $collider, $outcome["fire_heading"], $shot === "heavy" ? 1 : 0);
                $hit_a_ship = true;
                if ($shot === "chain") {
                    $this->applyChainShotLoss($player_id, self::shipOwner($collider["arg"]));
                }
            }

            if ($shot === "rocket") {
                // Rockets explode into all surrounding spaces, including after hitting a rock.
                $chain = array_merge(
                    $chain,
                    $this->applyRocketExplosion($player_id, $outcome["hit_x"], $outcome["hit_y"]),
                );
                break;
            }
            // Heavy gun shots travel through ships; rocks still stop them.
            if ($shot !== "heavy" || $hit_rock || $outcome["hit_distance"] >= $range) {
                break;
            }
            $from_distance = $outcome["hit_distance"];
        }

        if (!$hit_a_ship) {
            $this->bga->playerStats->inc("shots_missed", 1, (int) $player_id);
        }
        return $chain;
    }

    /** Score infamy for a hit on a ship and give the target a damage card. */
    private function applyShipHit(string $player_id, array $collider, Heading $fire_heading, int $bonus_infamy): void
    {
        // Hitting your own ship (a rocket blast, or your other ship in the 2 Ship Variant) has its
        // normal effects, except that no infamy is gained.
        $hit_player_id = self::shipOwner($collider["arg"]);
        if ((int) $hit_player_id !== (int) $player_id) {
            // Raking: hitting a ship from directly ahead or astern.
            $raking =
                $collider["heading"] == $fire_heading ||
                $collider["heading"] == SeaBoard::turnHeading($fire_heading, Turn::AROUND);
            $this->bga->playerStats->inc($raking ? "hits_raking" : "hits_broadside", 1, (int) $player_id);
            $this->scoreInfamy($player_id, ($raking ? 3 : 2) + $bonus_infamy, "shots");
        }
        $this->dealDamageCard($hit_player_id);

        $bounty_target = (int) $this->getGameStateValue("hunt_the_bounty_target");
        if (
            $bounty_target !== 0 &&
            (int) $hit_player_id === $bounty_target &&
            $this->getPlayerCaptain($player_id) === "corsair"
        ) {
            $this->scoreInfamy(
                $player_id,
                1,
                "captain",
                clienttranslate('${player_name}\'s Hunt the Bounty: gains 1 infamy'),
            );
        }
    }

    /**
     * War Junk Rockets: after resolving a hit, every ship in a surrounding space takes 1 damage
     * and earns the firing player 1 infamy.
     */
    private function applyRocketExplosion(string $player_id, int $x, int $y): array
    {
        $chain = [];
        foreach ($this->seaboard->getSurroundingPositions($x, $y) as $position) {
            $ships = $this->seaboard->getObjectsOfTypes($position["x"], $position["y"], ["player_ship"]);
            if (empty($ships)) {
                continue;
            }
            $chain[] = ["type" => "explosion", "hit_x" => $position["x"], "hit_y" => $position["y"]];
            foreach ($ships as $ship) {
                $hit_player_id = self::shipOwner($ship["arg"]);
                if ((int) $hit_player_id !== (int) $player_id) {
                    $this->scoreInfamy(
                        $player_id,
                        1,
                        "shots",
                        clienttranslate('${player_name}\'s rocket explosion: gains 1 infamy'),
                    );
                }
                $this->dealDamageCard($hit_player_id);
            }
        }
        return $chain;
    }

    /**
     * Sloop of War Chain Shot: the ship hit loses a resource of its choice. The victim is not the
     * active player, so the hit is queued and they choose once the shot has resolved (see
     * nextChainShotLossState). The queue is the victims' player numbers packed as decimal digits.
     */
    private function applyChainShotLoss(string $player_id, string $hit_player_id): void
    {
        $player_no = (int) $this->getPlayerNoById((int) $hit_player_id);
        $queue = (int) $this->getGameStateValue("pending_chain_shot_victims");
        $this->setGameStateValue("pending_chain_shot_victims", $queue * 10 + $player_no);
    }

    /** The resources a chain shot victim can choose to lose: those they have any of, skiffs aside. */
    function chainShotLossOptions(int $player_id): array
    {
        $resources = $this->getGameResourcesHierarchical($player_id)[$player_id] ?? [];
        unset($resources["skiff"]);
        return array_keys(array_filter($resources, fn($count) => $count > 0));
    }

    /**
     * Hand the turn to the next queued chain shot victim, or return null once none are left. A
     * victim with nothing, or only one kind of resource, has no choice to make and is settled here.
     */
    private function nextChainShotLossState(): ?int
    {
        while (($queue = (int) $this->getGameStateValue("pending_chain_shot_victims")) !== 0) {
            $victim_no = $queue % 10;
            $victim_id = (int) array_key_first(array_filter(
                $this->loadPlayersBasicInfos(),
                fn($player) => (int) $player["player_no"] === $victim_no,
            ));
            $options = $this->chainShotLossOptions($victim_id);
            if (count($options) > 1) {
                $this->setGameStateValue("chain_shot_shooter", (int) $this->getActivePlayerId());
                $this->gamestate->changeActivePlayer($victim_id);
                $this->giveExtraTime($victim_id);
                return STATE_CHAIN_SHOT_LOSS;
            }
            $this->setGameStateValue("pending_chain_shot_victims", intdiv($queue, 10));
            if (count($options) === 1) {
                $this->loseChainShotResource($victim_id, $options[0]);
            }
        }
        return null;
    }

    function actChainShotLose(string $resource): mixed
    {
        $player_id = (int) $this->getActivePlayerId();
        if (!in_array($resource, $this->chainShotLossOptions($player_id), true)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose a resource you have"));
        }
        $this->loseChainShotResource($player_id, $resource);
        $this->setGameStateValue(
            "pending_chain_shot_victims",
            intdiv((int) $this->getGameStateValue("pending_chain_shot_victims"), 10),
        );
        // Back to the shooter; the next-player step hands over to any further victims first.
        $this->gamestate->changeActivePlayer((int) $this->getGameStateValue("chain_shot_shooter"));
        $this->giveExtraTime((int) $this->getGameStateValue("chain_shot_shooter"));
        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    /** Zombie fallback: lose one of whatever the victim has most of. */
    function actChainShotLoseLargest(): mixed
    {
        $player_id = (int) $this->getActivePlayerId();
        $resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
        $options = $this->chainShotLossOptions($player_id);
        usort($options, fn($a, $b) => $resources[$b] <=> $resources[$a]);
        return $this->actChainShotLose($options[0]);
    }

    private function loseChainShotResource(int $player_id, string $resource): void
    {
        $this->playerGainResources($player_id, [$resource => -1]);
        $this->bga->notify->all(
            "log",
            clienttranslate('${player_name} loses 1 ${resource} to chain shot'),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "resource" => "[$resource]", // shown as its icon
            ],
        );
    }
}
