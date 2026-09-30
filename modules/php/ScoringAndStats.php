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
 * Infamy (the score), final scoring, and the game statistics.
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait ScoringAndStats
{
    /**
     * "Players add the Infamy scored on their active upgrades and acquired Market cards (deck, hand,
     * and discard pile) to their total. Damage cards reduce Infamy and purchased cards add to it."
     */
    function stFinalScoring(): mixed
    {
        $this->awardShipUpgradeEndgameInfamy();

        foreach (array_keys($this->loadPlayersBasicInfos()) as $player_id) {
            $infamy = 0;
            $damage = 0;
            foreach ($this->getPlayerOwnedCards((string) $player_id) as $card) {
                $definition = $this->playable_cards[(int) $card["type"]];
                $infamy += $definition["infamy"] ?? 0;
                if (($definition["category"] ?? "") === "damage") {
                    $damage++;
                }
            }
            if ($infamy !== 0) {
                $this->scoreInfamy(
                    (string) $player_id,
                    $infamy,
                    "cards",
                    clienttranslate('${player_name} scores ${score_increment} infamy from their cards'),
                );
            }
            // "In the case of a tie, the player with the most resources wins. If there is still a
            // tie, the player with the least Damage wins." BGA compares a single tiebreak number,
            // so the two are packed: resources dominate, fewer damage cards break the remainder.
            // Skiffs share the resource table but are workers, not resources.
            $held = $this->getGameResourcesHierarchical((int) $player_id)[$player_id] ?? [];
            unset($held["skiff"]);
            $resources = array_sum($held);
            $this->bga->playerScoreAux->set((int) $player_id, $resources * 100 + max(0, 99 - $damage));
        }
        $this->recordWinnerStats();

        return STATE_END_GAME;
    }

    /**
     * Infamy is the player score. The framework counter owns the DB write and the notification
     * that refreshes the score on the front end, so do not touch the score column directly.
     */
    /** The player's (first) ship type, e.g. "Brig". */
    function getPlayerShipName(int $player_id): string
    {
        return self::getUniqueValueFromDB("SELECT player_ship FROM player WHERE player_id = $player_id");
    }

    /** The value the captain and ship stats store for a captain: its 1-based place in STAT_CAPTAINS. */
    function captainStatValue(string $captain_key): int
    {
        $index = array_search($captain_key, self::STAT_CAPTAINS, true);
        if ($index === false) {
            throw new \Bga\GameFramework\SystemException("Captain missing from STAT_CAPTAINS: $captain_key");
        }
        return $index + 1;
    }

    function shipStatValue(string $ship_name): int
    {
        $index = array_search($ship_name, self::STAT_SHIPS, true);
        if ($index === false) {
            throw new \Bga\GameFramework\SystemException("Ship missing from STAT_SHIPS: $ship_name");
        }
        return $index + 1;
    }

    /**
     * Who won with what, ranked as BGA ranks the table: infamy, then the tiebreak. A tie on both
     * credits the first of the tied players.
     */
    private function recordWinnerStats(): void
    {
        $standings = [];
        foreach (array_keys($this->loadPlayersBasicInfos()) as $player_id) {
            $standings[] = [
                "id" => (int) $player_id,
                "score" => (int) $this->bga->playerScore->get((int) $player_id),
                "aux" => (int) $this->bga->playerScoreAux->get((int) $player_id),
            ];
        }
        usort($standings, fn($a, $b) => [$b["score"], $b["aux"]] <=> [$a["score"], $a["aux"]]);
        $winner = $standings[0];
        $captain = $this->captainStatValue($this->getPlayerCaptain($winner["id"]));
        $ship = $this->shipStatValue($this->getPlayerShipName($winner["id"]));
        $this->bga->tableStats->set("winning_captain", $captain);
        $this->bga->tableStats->set("winning_ship", $ship);
        // Labelled "<captain> / <ship>" in stats.jsonc, captains in the outer order.
        $this->bga->tableStats->set("winning_captain_ship", ($captain - 1) * count(self::STAT_SHIPS) + $ship);
        $this->bga->tableStats->set("winner_infamy", $winner["score"]);
        $this->bga->tableStats->set("winning_margin", $winner["score"] - ($standings[1]["score"] ?? 0));
    }

    /** @param string $source one of INFAMY_SOURCES: where the infamy came from, for the stats. */
    function scoreInfamy(string $player_id, int $amount, string $source, string $message = "")
    {
        if (!in_array($source, self::INFAMY_SOURCES, true)) {
            throw new \Bga\GameFramework\SystemException("Unknown infamy source: $source");
        }
        $this->bga->playerStats->inc("infamy_from_$source", $amount, (int) $player_id);
        if ($message === "") {
            $message = clienttranslate('${player_name} scored ${score_increment} infamy');
        }
        $this->bga->playerScore->inc(
            (int) $player_id,
            $amount,
            new \Bga\GameFramework\NotificationMessage($message, [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "score_increment" => $amount,
            ]),
        );
    }

    protected function getPlayerInfamy(string $player_id): int
    {
        return $this->bga->playerScore->get((int) $player_id);
    }
}
