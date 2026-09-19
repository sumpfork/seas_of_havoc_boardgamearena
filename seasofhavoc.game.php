<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SeasOfHavoc implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * seasofhavoc.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

require_once "modules/SeaBoard.php";

use Bga\GameFramework\Actions\Types\JsonParam;
use Bga\GameFramework\Table;

require_once __DIR__ . "/modules/PrimitiveCardPlayAction.php";

class SeasOfHavoc extends Table
{
    // Debug flag: give each player a booty token at game start (one with a wild resource)
    private const DEBUG_START_WITH_BOOTY = true;

    private const TREASURE_SEEKER_RESUME_SEA_TURN_DONE = 2;
    private const TREASURE_SEEKER_RESUME_COLLISION = 3;
    private const TREASURE_SEEKER_RESUME_COLLISION_RESOLVED = 4;

    private const EXTORTION_GREEN_FLAG = 1;
    private const EXTORTION_RED_FLAG = 2;

    private SeaBoard $seaboard;
    private $cards;
    private array $playable_cards;
    private array $resource_types;
    private array $token_names;
    private array $non_playable_cards;
    private array $booty_tokens = [];

    function __construct()
    {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        require "modules/material.inc.php";

        self::initGameStateLabels([
            "seafeature_effects_attempted" => 10,
            "pending_trading_post_player" => 11,
            "pending_trading_post_slot" => 12,
            "corsair_occupied_placement_used" => 13,
            "pending_shipwreck_arg" => 16,
            "pending_shipwreck_x" => 17,
            "pending_shipwreck_y" => 18,
            "pending_treasure_seeker_resume" => 19,
            "extortion_pending_flags" => 20,
            "hunt_the_bounty_target" => 21,
            "pending_captain_card" => 22,
        ]);

        $this->cards = $this->deckFactory->createDeck("card");
        $this->cards->autoreshuffle = true;

        $this->seaboard = new SeaBoard("SeasOfHavoc::DBQuery", $this);

        foreach (array_keys($this->playable_cards) as $t) {
            $this->playable_cards[$t]["card_type"] = $t;
        }
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "seasofhavoc";
    }

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos["player_colors"];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql =
            "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar, player_ship) VALUES ";
        $values = [];
        $this->dump("default_colours", $default_colors);
        $this->dump("players", $players);
        foreach ($players as $player_id => $player) {
            $ship_colour = array_splice($default_colors, 0, 1);
            $ship = array_key_first($ship_colour);
            $color = $ship_colour[$ship];
            $values[] =
                "('" .
                $player_id .
                "','$color','" .
                $player["player_canal"] .
                "','" .
                addslashes($player["player_name"]) .
                "','" .
                addslashes($player["player_avatar"]) .
                "','$ship'" .
                ")";
        }
        $sql .= implode(",", $values);
        self::DbQuery($sql);
        //self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        $sql = "INSERT INTO resource (player_id, resource_key, resource_count) VALUES ";
        $base_resources = array_fill_keys($this->resource_types, 1);
        $base_resources["skiff"] = 3;

        $this->dump("base resources", $base_resources);
        $player_infos = $this->loadPlayersBasicInfos();

        $values = [];
        foreach ($player_infos as $playerid => $player) {
            $player_resources = $base_resources;

            switch ($player["player_no"]) {
                case 1:
                    break;
                case 2:
                    $player_resources["sail"] += 1;
                    break;
                case 3:
                    $player_resources["cannonball"] += 1;
                    break;
                case 4:
                    $player_resources["sail"] += 1;
                    $player_resources["cannonball"] += 1;
                    break;
                case 5:
                    $player_resources["cannonball"] += 1;
                    $player_resources["doubloon"] += 1;
                    break;
                default:
                    throw new Exception("Unknonwn player number" . $player["player_no"]);
            }
            foreach ($player_resources as $resource_type => $resource_count) {
                $values[] = "('" . $playerid . "','$resource_type','" . $resource_count . "')";
            }
        }
        $sql .= implode(",", $values);
        self::DbQuery($sql);

        // Assign first player token to a random player
        $player_ids = array_keys($player_infos);
        $random_first_player = $player_ids[array_rand($player_ids)];
        self::DbQuery(
            "INSERT INTO unique_tokens (player_id, token_key) VALUES ('$random_first_player', 'first_player_token')",
        );

        // Notify players who got the first player token
        $this->bga->notify->all(
            "tokenAcquired",
            clienttranslate('${player_name} starts the game with the ${token_name}'),
            [
                "player_name" => $this->getPlayerNameById($random_first_player),
                "token_name" => $this->token_names["first_player_token"],
                "player_id" => $random_first_player,
                "token_key" => "first_player_token",
            ],
        );

        // Create other tokens without owners
        foreach (["green_flag", "tan_flag", "blue_flag", "red_flag"] as $token) {
            self::DbQuery("INSERT INTO unique_tokens (player_id, token_key) VALUES (NULL, '$token')");
        }

        $this->clearIslandSlots();

        $player_infos = $this->getPlayerInfo();
        uasort($player_infos, fn($a, $b) => ((int) $a["player_no"]) <=> ((int) $b["player_no"]));

        // Get all captain cards for random assignment
        $captain_cards = array_filter(
            $this->non_playable_cards,
            fn($card) => isset($card["category"]) && $card["category"] == "captain",
        );
        $captain_keys = array_keys($captain_cards);
        shuffle($captain_keys);

        // Place seafeatures on the board based on player count
        $num_players = count($player_infos);
        $num_rocks = $num_players <= 3 ? 3 : 2;
        $num_gusts = $num_players <= 3 ? 2 : 3;
        $num_whirlpools = 1;
        $num_shipwrecks = 2;

        // Pick a random heading for all gusts (they all face the same direction)
        $all_headings = [Heading::NORTH, Heading::EAST, Heading::SOUTH, Heading::WEST];
        $gust_heading = $all_headings[array_rand($all_headings)];

        // Place rocks
        for ($i = 0; $i < $num_rocks; $i++) {
            $position = $this->findEmptyBoardPosition([
                "player_ship",
                "rock",
                "gust",
                "whirlpool",
                "shipwreck",
                "sea_monster_part",
            ]);
            $this->seaboard->placeObject($position["x"], $position["y"], [
                "type" => "rock",
                "arg" => strval($i),
                "heading" => Heading::NO_HEADING,
            ]);
        }

        // Place gusts (all with the same random heading)
        for ($i = 0; $i < $num_gusts; $i++) {
            $position = $this->findEmptyBoardPosition([
                "player_ship",
                "rock",
                "gust",
                "whirlpool",
                "shipwreck",
                "sea_monster_part",
            ]);
            $this->seaboard->placeObject($position["x"], $position["y"], [
                "type" => "gust",
                "arg" => strval($i),
                "heading" => $gust_heading,
            ]);
        }

        // Place whirlpools
        for ($i = 0; $i < $num_whirlpools; $i++) {
            $position = $this->findEmptyBoardPosition([
                "player_ship",
                "rock",
                "gust",
                "whirlpool",
                "shipwreck",
                "sea_monster_part",
            ]);
            $this->seaboard->placeObject($position["x"], $position["y"], [
                "type" => "whirlpool",
                "arg" => strval($i),
                "heading" => Heading::NO_HEADING,
            ]);
        }

        // Place shipwrecks
        for ($i = 0; $i < $num_shipwrecks; $i++) {
            $position = $this->findEmptyBoardPosition($this->shipwreckPlacementBlockingTypes());
            $this->placeShipwreck(strval($i), $position["x"], $position["y"]);
        }

        // Create and shuffle the booty token deck
        $booty_deck = [];
        foreach ($this->booty_tokens as $token) {
            $booty_deck[] = [
                "type" => "booty",
                "type_arg" => $token["image_id"],
                "nbr" => 1,
            ];
        }
        $this->cards->createCards($booty_deck, "booty_deck");
        $this->cards->shuffle("booty_deck");

        $forced_captains_for_testing = []; //["corsair", "merchant", "admiral"];
        $player_index = 0;

        foreach ($player_infos as $playerid => $player) {
            // Find an empty position for the ship (avoiding other ships, rocks, and sea monster parts)
            // Ships CAN start on gusts and whirlpools
            $position = $this->findEmptyBoardPosition(["player_ship", "rock", "shipwreck", "sea_monster_part"]);

            // Find a safe random heading that doesn't face rocks or other obstacles
            $heading = $this->findSafeHeadingAtPosition($position["x"], $position["y"], ["rock", "shipwreck"]);

            $this->seaboard->placeObject($position["x"], $position["y"], [
                "type" => "player_ship",
                "arg" => $playerid,
                "heading" => $heading,
            ]);

            // TEMP HACK: force first players to specific captains for ability testing.
            if (isset($forced_captains_for_testing[$player_index])) {
                $captain_key = $forced_captains_for_testing[$player_index];
                $captain_keys = array_values(array_filter($captain_keys, fn($k) => $k !== $captain_key));
            } else {
                $captain_key = array_pop($captain_keys);
            }
            $this->assignCaptainToPlayer($playerid, $captain_key);

            // Assign ship upgrade cards matching player's ship
            $this->assignShipUpgradesToPlayer($playerid, $player["player_ship"]);

            // Get ship starting cards
            $player_starting_cards = array_filter(
                array_filter($this->playable_cards, fn($x) => $x["category"] == "starting_card"),
                function ($v) use ($player) {
                    return $v["ship_name"] == $player["player_ship"];
                },
            );

            // Get captain starting cards
            $captain_starting_cards = array_filter(
                array_filter($this->playable_cards, fn($x) => $x["category"] == "captain"),
                function ($v) use ($captain_key) {
                    return isset($v["captain_key"]) && $v["captain_key"] == $captain_key;
                },
            );

            // Combine ship and captain starting cards
            $all_starting_cards = array_merge($player_starting_cards, $captain_starting_cards);

            $start_deck = [];
            foreach ($all_starting_cards as $starting_card) {
                $start_deck[] = [
                    "type" => $starting_card["card_type"],
                    "type_arg" => 0,
                    "nbr" => $starting_card["count"],
                ];
            }
            $this->cards->createCards($start_deck, $this->playerDeckName($playerid));
            $this->cards->shuffle($this->playerDeckName($playerid));

            $player_index++;
        }

        $market_deck = [];
        foreach (array_filter($this->playable_cards, fn($x) => $x["category"] == "market_card") as $market_card) {
            $market_deck[] = [
                "type" => $market_card["card_type"],
                "type_arg" => 0,
                "nbr" => $market_card["count"],
            ];
        }
        $this->cards->createCards($market_deck, "market_deck");
        $this->cards->shuffle("market_deck");
        $this->cards->pickCardsForLocation(5, "market_deck", "market");

        $damage_card = array_filter($this->playable_cards, fn($x) => $x["category"] == "damage")[0];
        $this->cards->createCards(
            [
                [
                    "type" => $damage_card["card_type"],
                    "type_arg" => 0,
                    "nbr" => $this->calculateNumDamageCards(count($player_infos)),
                ],
            ],
            "damage_deck",
        );

        // Debug: give each player a starting booty token
        if (self::DEBUG_START_WITH_BOOTY) {
            $player_ids = array_keys($player_infos);
            // First player gets a token with a wild "choice" resource (image_id 3 = doubloon + choice)
            // Other players get a regular token (image_id 6 = doubloon + cannonball)
            $wild_image_id = 3;
            $regular_image_id = 6;
            foreach ($player_ids as $i => $pid) {
                $target_image_id = $i === 0 ? $wild_image_id : $regular_image_id;
                $card = self::getObjectFromDB(
                    "SELECT card_id FROM card WHERE card_location = 'booty_deck' AND card_type_arg = '$target_image_id' LIMIT 1",
                );
                if ($card) {
                    $this->cards->moveCard($card["card_id"], "booty_player", $pid);
                    $this->trace("DEBUG: Gave player $pid booty token image_id=$target_image_id");
                }
            }
        }

        /************ End of the game initialization *****/
        $this->activeNextPlayer();
    }

    function playerDeckName($player_id)
    {
        return "player_deck_" . $player_id;
    }

    function calculateNumDamageCards($num_players)
    {
        return 10 + $num_players * 5;
    }

    function assignCaptainToPlayer($player_id, $captain_key)
    {
        $sql = "INSERT INTO player_captain (player_id, captain_key) VALUES ('$player_id', '$captain_key')";
        self::DbQuery($sql);
    }

    function assignShipUpgradesToPlayer($player_id, $ship_name)
    {
        // Get ship upgrade cards matching the player's ship
        $ship_upgrades = array_filter($this->non_playable_cards, function ($card) use ($ship_name) {
            return isset($card["category"]) &&
                $card["category"] == "ship_upgrade" &&
                isset($card["ship_name"]) &&
                $card["ship_name"] == $ship_name;
        });

        // Take first 2 upgrades for this ship (or all if less than 2)
        $upgrade_keys = array_keys($ship_upgrades);
        $selected_upgrades = array_slice($upgrade_keys, 0, 2);

        foreach ($selected_upgrades as $upgrade_key) {
            $sql = "INSERT INTO player_ship_upgrades (player_id, upgrade_key, is_activated) VALUES ('$player_id', '$upgrade_key', 0)";
            self::DbQuery($sql);
        }
    }

    function findEmptyBoardPosition(array $collision_types)
    {
        // Try to find a position on the board that doesn't contain any of the collision types
        // Maximum attempts to avoid infinite loop
        $max_attempts = 100;
        $attempts = 0;

        while ($attempts < $max_attempts) {
            $x = rand(0, SeaBoard::WIDTH - 1);
            $y = rand(0, SeaBoard::HEIGHT - 1);

            // Check if this position has any objects of the collision types
            $colliding_objects = $this->seaboard->getObjectsOfTypes($x, $y, $collision_types);
            if (empty($colliding_objects)) {
                return ["x" => $x, "y" => $y];
            }

            $attempts++;
        }

        // If we couldn't find a random empty spot, search systematically
        for ($x = 0; $x < SeaBoard::WIDTH; $x++) {
            for ($y = 0; $y < SeaBoard::HEIGHT; $y++) {
                $colliding_objects = $this->seaboard->getObjectsOfTypes($x, $y, $collision_types);
                if (empty($colliding_objects)) {
                    return ["x" => $x, "y" => $y];
                }
            }
        }

        // Should never happen unless the board is completely full
        throw new \Bga\GameFramework\SystemException(
            "Could not find an empty position on the board that doesn't collide with: " .
                implode(", ", $collision_types),
        );
    }

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

    private function drawBootyToken(int $player_id): ?array
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
            $this->trace("Booty deck refilled from discard and shuffled");
        }
        $card = $this->cards->pickCardForLocation("booty_deck", "booty_player", $player_id);
        $this->dump("drawBootyToken picked", $card);
        return $card;
    }

    private function collectShipwrecksAtPlayer(int $player_id): array
    {
        $ship_info = $this->seaboard->findObject("player_ship", $player_id);
        if (!$ship_info) {
            return ["shipwreck_event" => null, "booty_card" => null];
        }
        $this->dump("collectShipwrecksAtPlayer ship_info", $ship_info);
        $x = $ship_info["x"];
        $y = $ship_info["y"];
        $shipwrecks = $this->seaboard->getObjectsOfTypes($x, $y, ["shipwreck"]);
        $this->dump("collectShipwrecksAtPlayer shipwrecks", $shipwrecks);
        if (empty($shipwrecks)) {
            return ["shipwreck_event" => null, "booty_card" => null];
        }
        $shipwreck = $shipwrecks[0];
        $this->seaboard->removeObject($x, $y, "shipwreck", $shipwreck["arg"]);
        $new_position = $this->findEmptyBoardPosition($this->shipwreckPlacementBlockingTypes());
        $this->dump("collectShipwrecksAtPlayer new_position", $new_position);
        $this->placeShipwreck($shipwreck["arg"], $new_position["x"], $new_position["y"]);
        $event = [
            "shipwreck_arg" => $shipwreck["arg"],
            "old_x" => $x,
            "old_y" => $y,
            "new_x" => $new_position["x"],
            "new_y" => $new_position["y"],
        ];
        $booty_card = $this->drawBootyToken($player_id);
        $this->dump("collectShipwrecksAtPlayer result", ["event" => $event, "booty_card" => $booty_card]);
        return ["shipwreck_event" => $event, "booty_card" => $booty_card];
    }

    function findSafeHeadingAtPosition(int $x, int $y, array $avoid_types)
    {
        // Get all possible headings
        $all_headings = [Heading::NORTH, Heading::EAST, Heading::SOUTH, Heading::WEST];
        shuffle($all_headings);

        // Try each heading and see if it faces an object to avoid
        foreach ($all_headings as $heading) {
            // Check what's in front of this position with this heading
            $forward_pos = $this->seaboard->getForwardPosition($x, $y, $heading);
            $objects_ahead = $this->seaboard->getObjectsOfTypes($forward_pos["x"], $forward_pos["y"], $avoid_types);

            if (empty($objects_ahead)) {
                // This heading is safe
                return $heading;
            }
        }

        // If no heading is safe, return a random one anyway
        // (this might happen on a very crowded board)
        return $all_headings[0];
    }

    function getPlayerCaptain($player_id)
    {
        $sql = "SELECT captain_key FROM player_captain WHERE player_id = '$player_id'";
        return self::getUniqueValueFromDb($sql);
    }

    function getPlayerShipUpgrades($player_id)
    {
        $sql = "SELECT upgrade_key, is_activated FROM player_ship_upgrades WHERE player_id = '$player_id'";
        return self::getObjectListFromDb($sql);
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

    private function flagTokenKeys(): array
    {
        return ["green_flag", "tan_flag", "blue_flag", "red_flag"];
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
                clienttranslate('${player_name}\'s Admiral ability: gains 1 infamy for taking a flag'),
            );
        }
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

        $this->gamestate->changeActivePlayer((int) $treasure_seeker_id);
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
        $this->setGameStateValue("pending_shipwreck_arg", 0);
        $this->setGameStateValue("pending_shipwreck_x", 0);
        $this->setGameStateValue("pending_shipwreck_y", 0);
        $this->setGameStateValue("pending_treasure_seeker_resume", 0);

        switch ($resume) {
            case self::TREASURE_SEEKER_RESUME_SEA_TURN_DONE:
            case self::TREASURE_SEEKER_RESUME_COLLISION_RESOLVED:
                return STATE_NEXT_PLAYER_SEA_PHASE;
            case self::TREASURE_SEEKER_RESUME_COLLISION:
                return STATE_RESOLVE_COLLISION;
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
                '${player_name} uses Corsair to place on occupied ${slot_name} and gains only resources shown there',
            ),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "slot_name" => $slot_name,
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

    function stIslandPhaseSetup()
    {
        $this->mytrace("stIslandPhaseSetup");
        $this->applyPirateQueenIslandPhaseStartAbilities();

        $player_infos = $this->getPlayerInfo();

        foreach ($player_infos as $playerid => $player) {
            // Draw 4 new cards
            $this->drawCards($playerid, 4);
        }

        $rebel_id = $this->applyRebelIslandPhaseStartDraw();

        // Clear any leftover extra turns from previous phases
        $this->clearExtraTurns("island");

        // Set the first player token holder as the active player for the island phase
        $first_player_token_owner = $this->getFirstPlayerTokenOwner();
        if ($first_player_token_owner === null) {
            throw new \Bga\GameFramework\SystemException("No player has the first player token - this should never happen");
        }

        if ($rebel_id !== null) {
            $this->mytrace("Rebel player ($rebel_id) must discard before island phase begins");
            $this->gamestate->changeActivePlayer($rebel_id);
            return "rebelDiscard";
        }

        $this->mytrace(
            "Setting first player token owner ($first_player_token_owner) as active player for island phase",
        );
        $this->gamestate->changeActivePlayer($first_player_token_owner);

        return "islandTurn";
    }

    function stNextPlayerIslandPhase()
    {
        $this->mytrace("stNextPlayerIslandPhase");

        $resources = $this->getGameResourcesHierarchical();
        $this->dump("fetched resources:", $resources);

        $current_player = $this->getActivePlayerId();

        // Check if current player has an extra turn
        if ($this->hasExtraTurn($current_player, "island")) {
            $this->mytrace("Current player $current_player has an extra turn");
            $this->consumeExtraTurn($current_player, "island");

            // Check if current player still has skiffs
            $current_player_skiffs = $resources[$current_player]["skiff"] ?? 0;
            if ($current_player_skiffs > 0) {
                $this->mytrace("Current player has skiffs, giving extra turn");
                $this->giveExtraTime($current_player);
                return "nextPlayer";
            } else {
                $this->mytrace("Current player has no skiffs, skipping extra turn");
            }
        }

        // Find next player with skiffs
        $starting_player = $current_player;
        $active_player = $this->activeNextPlayer();

        while (($resources[$active_player]["skiff"] ?? 0) == 0) {
            // Prevent infinite loop if we've gone through all players
            if ($active_player == $starting_player) {
                $this->mytrace("All players checked, no one has skiffs");
                $this->clearExtraTurns("island");
                return "islandPhaseDone";
            }

            $this->mytrace("Player $active_player has no skiffs, skipping");
            $active_player = $this->activeNextPlayer();
        }

        $this->mytrace("Next player with skiffs: $active_player");
        $this->giveExtraTime($active_player);
        return "nextPlayer";
    }

    function stCardPurchases()
    {
        // Clear any pending purchases from previous round
        self::DbQuery("DELETE FROM pending_purchases");
        $this->gamestate->setAllPlayersMultiactive();

        // Initialize all players to the "making purchases" private state
        $this->gamestate->initializePrivateStateForAllActivePlayers();
    }

    function stCommitPurchases(): string
    {
        // All players have completed purchases - commit them now
        $this->commitAllPurchases();
        return "";
    }

    function stSeaPhaseSetup()
    {
        $this->mytrace("stSeaPhaseSetup");

        // Set the first player token holder as the active player for the sea phase
        $first_player_token_owner = $this->getFirstPlayerTokenOwner();
        if ($first_player_token_owner === null) {
            throw new \Bga\GameFramework\SystemException("No player has the first player token - this should never happen");
        }

        $this->mytrace("Setting first player token owner ($first_player_token_owner) as active player for sea phase");
        $this->gamestate->changeActivePlayer($first_player_token_owner);

        $this->setGameStateValue("hunt_the_bounty_target", 0);

        if ($this->getPlayerCaptain($first_player_token_owner) === "admiral") {
            $this->playerGainResources($first_player_token_owner, ["doubloon" => 1]);
            $this->bga->notify->all(
                "log",
                clienttranslate('${player_name}\'s Admiral ability: gains 1 doubloon for holding the first player token'),
                ["player_name" => $this->getPlayerNameById($first_player_token_owner)],
            );
        }

        return "";
    }

    function stNextPlayerSeaPhase(): string
    {
        $current_player = $this->getActivePlayerId();
        $active_player = $this->activeNextPlayer();
        $num_cards = $this->cards->countCardInLocation("hand", $active_player);
        $this->trace("$active_player num cards in hand: $num_cards");
        while ($num_cards == 0) {
            $active_player = $this->activeNextPlayer();
            $num_cards = $this->cards->countCardInLocation("hand", $active_player);
            $this->trace("$active_player num cards in hand: $num_cards");
            if ($active_player == $current_player) {
                $this->trace("$active_player is current player");
                break;
            }
        }
        $this->trace("final num cards: $num_cards");
        if ($num_cards == 0) {
            return "seaPhaseDone";
        }
        $this->giveExtraTime($active_player);
        return "nextPlayer";
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    public function getAllDatas()
    {
        $result = [];

        $current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result["players"] = self::getCollectionFromDb($sql);

        // TODO: Gather all information about current game situation (visible by player $current_player_id).

        $result["resources"] = $this->getGameResources();

        // Get pending purchases for current player ONLY
        // Pending purchases are private per-player and not visible to other players until all players commit
        $pending_purchases = self::getObjectListFromDB(
            "SELECT card_id FROM pending_purchases WHERE player_id = '$current_player_id'",
        );
        $pending_card_ids = array_column($pending_purchases, "card_id");

        // Check if current player has completed purchases (has any pending purchases)
        $result["player_has_completed_purchases"] = !empty($pending_card_ids);

        // Send pending purchases separately - frontend will handle filtering
        // This is only sent to the current player, not to other players
        $result["pending_purchases"] = $pending_card_ids;

        // Send full island slots - frontend will filter based on pending_purchases
        $result["islandslots"] = $this->getIslandSlots();
        $result["unique_tokens"] = $this->getUniqueTokens();
        $result["booty_tokens"] = $this->getBootyTokensForPlayer($current_player_id);
        $result["players_with_booty"] = $this->getPlayersWithBooty();
        // Resources each booty token provides (type_arg => resources), for "use booty" UI and choice resolution
        $result["booty_token_resources"] = [];
        foreach ($this->booty_tokens as $cfg) {
            $result["booty_token_resources"][$cfg["image_id"]] = $cfg["resources"] ?? [];
        }
        $pending_trading_post = $this->getPendingTradingPostSelection();
        $result["pending_trading_post_slot"] = null;
        if ($pending_trading_post !== null && (int) $pending_trading_post["player_id"] === (int) $current_player_id) {
            $result["pending_trading_post_slot"] = $pending_trading_post["slot_number"];
        }
        $result["playable_cards"] = $this->playable_cards;

        // Send the full, unmodified market to all players
        // Frontend will filter out pending purchases for the current player
        // Market cards are returned in a consistent order (by location_arg), so frontend can use array position as slot number
        $market_cards = $this->cards->getCardsInLocation("market");
        $result["market"] = $market_cards;

        // Send player's actual hand - frontend will add pending purchases to hand display
        $result["hand"] = $this->cards->getPlayerHand($current_player_id);
        $result["discard"] = $this->cards->getCardsInLocation("player_discard", $current_player_id);
        $result["scrap"] = $this->cards->getCardsInLocation("scrap");
        $result["playerinfo"] = $this->getPlayerInfo();
        $result["seaboard"] = $this->seaboard->getAllObjectsFlat();
        $result["non_playable_cards"] = $this->non_playable_cards;

        $result["deck_size"] = $this->cards->countCardInLocation($this->playerDeckName($current_player_id));
        $result["player_captain"] = $this->getPlayerCaptain($current_player_id);
        $result["corsair_occupied_placement_available"] = $this->canUseCorsairOccupiedPlacement($current_player_id);
        $result["corsair_occupied_slot_names"] = $this->corsairOccupiedPlacementSlotNames();
        $result["player_ship_upgrades"] = $this->getPlayerShipUpgrades($current_player_id);

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        $totalDamageCards = $this->calculateNumDamageCards($this->getPlayersNumber());
        $remainingDamageCards = $this->cards->countCardInLocation("damage_deck");

        return (int) 100 * (1 - $remainingDamageCards / $totalDamageCards);
    }

    /*
        getGameResources:
        
        Gather all relevant resources about current game situation (visible by the current player).
    */
    function getGameResources(?int $player_id = null)
    {
        $sql = "
                SELECT
                    player_id, resource_key, resource_count
                FROM resource
            ";
        if ($player_id != null) {
            $sql .= " WHERE player_id = $player_id";
        }
        $game_resources = $this->getObjectListFromDB($sql);
        return $game_resources;
    }

    function getGameResourcesHierarchical(?int $player_id = null)
    {
        $resources = $this->getGameResources($player_id);
        $hierarchical_resources = [];
        foreach ($resources as $row) {
            if (!array_key_exists($row["player_id"], $hierarchical_resources)) {
                $hierarchical_resources[$row["player_id"]] = [];
            }
            $hierarchical_resources[$row["player_id"]][$row["resource_key"]] = intval($row["resource_count"]);
        }
        return $hierarchical_resources;
    }

    function getPlayerInfo(?int $player_id = null)
    {
        static $player_info = null;
        if ($player_info === null) {
            $sql = "SELECT player_id, player_no, player_name, player_score, player_score_aux, player_ship, player_color
                FROM player";
            if ($player_id !== null) {
                $sql .= " WHERE player_id = $player_id";
            }
            $player_info = $this->getCollectionFromDB($sql);
        }
        return $player_info;
    }

    function subindexArray($arr_arr, $top_key)
    {
        print $top_key;
        $new_arr = [];
        foreach ($arr_arr as $arr) {
            unset($arr[$top_key]);
            $new_arr[$top_key] = $arr;
        }
    }

    function getUniqueTokens()
    {
        $sql = "
    		SELECT
    		    player_id, token_key
    		FROM unique_tokens
    	";
        $tokens = $this->getObjectListFromDB($sql);
        $indexed_tokens = [];
        foreach ($tokens as $token) {
            $indexed_tokens[$token["token_key"]] = $token["player_id"];
        }
        return $indexed_tokens;
    }

    function getFirstPlayerTokenOwner()
    {
        $sql = "SELECT player_id FROM unique_tokens WHERE token_key = 'first_player_token'";
        $result = $this->getObjectFromDB($sql);
        return $result ? $result["player_id"] : null;
    }

    function getTokenOwner(string $token_key)
    {
        $sql = "SELECT player_id FROM unique_tokens WHERE token_key = '$token_key'";
        $result = $this->getObjectFromDB($sql);
        return $result ? $result["player_id"] : null;
    }

    function getIslandSlots()
    {
        $sql = "
    		SELECT
    		    slot_key, number, occupying_player_id, corsair_occupying_player_id, disabled
    		FROM islandslots
    	";
        $slots = $this->getObjectListFromDB($sql);
        $indexed_slots = [];
        foreach ($slots as $slot) {
            $indexed_slots[$slot["slot_key"]][$slot["number"]] = [
                "occupying_player_id" => $slot["occupying_player_id"],
                "corsair_occupying_player_id" => $slot["corsair_occupying_player_id"],
                "disabled" => (bool) $slot["disabled"],
            ];
        }
        return $indexed_slots;
    }

    function occupyIslandSlot(string $player_id, string $slot_name, string $number)
    {
        // Preserve any existing overlay occupant when replacing the main occupant.
        $sql = "SELECT disabled, corsair_occupying_player_id FROM islandslots WHERE slot_key = '$slot_name' AND number = '$number'";
        $current_slot = $this->getObjectFromDB($sql);
        $disabled = $current_slot ? $current_slot["disabled"] : 0;
        $corsair_occupying_player_id =
            $current_slot && $current_slot["corsair_occupying_player_id"] !== null
                ? "'" . $current_slot["corsair_occupying_player_id"] . "'"
                : "null";

        self::DbQuery(
            "REPLACE INTO islandslots (slot_key, number, occupying_player_id, corsair_occupying_player_id, disabled) VALUES ('$slot_name', '$number', '$player_id', $corsair_occupying_player_id, $disabled)",
        );
        $this->bga->notify->all("skiffPlaced", clienttranslate('${player_name} placed a skiff on ${slot_name}'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
            "player_color" => $this->getPlayerColor($player_id),
            "slot_name" => $slot_name,
            "slot_number" => $number,
            "is_corsair_overlay" => false,
        ]);
    }

    function occupyIslandSlotAsCorsairOverlay(string $player_id, string $slot_name, string $number)
    {
        self::DbQuery(
            "UPDATE islandslots SET corsair_occupying_player_id = '$player_id' WHERE slot_key = '$slot_name' AND number = '$number'",
        );
        $this->bga->notify->all(
            "skiffPlaced",
            clienttranslate('${player_name} placed a skiff on occupied ${slot_name}'),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "player_color" => $this->getPlayerColor($player_id),
                "slot_name" => $slot_name,
                "slot_number" => $number,
                "is_corsair_overlay" => true,
            ],
        );
    }

    private function setPendingTradingPostSelection(?int $player_id, ?string $slot_number): void
    {
        $slot_value = 0;
        if ($slot_number !== null) {
            $slot_value = (int) ltrim($slot_number, "n");
        }

        $this->setGameStateValue("pending_trading_post_player", $player_id ?? 0);
        $this->setGameStateValue("pending_trading_post_slot", $slot_value);
    }

    private function getPendingTradingPostSelection(): ?array
    {
        $player_id = (int) $this->getGameStateValue("pending_trading_post_player");
        $slot_value = (int) $this->getGameStateValue("pending_trading_post_slot");

        if ($player_id <= 0 || $slot_value <= 0) {
            return null;
        }

        return [
            "player_id" => $player_id,
            "slot_number" => "n" . $slot_value,
        ];
    }

    private function assertPendingTradingPostSelection(int $player_id, string $slot_number): void
    {
        $pending = $this->getPendingTradingPostSelection();
        if ($pending === null) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You must place a skiff on the trading post first"));
        }

        if ((int) $pending["player_id"] !== $player_id || $pending["slot_number"] !== $slot_number) {
            throw new \Bga\GameFramework\UserException(clienttranslate("This trading post exchange is no longer valid"));
        }

        $occupancies = $this->getIslandSlots();
        $slot_state = $occupancies["trading_post"][$slot_number] ?? null;
        if ($slot_state === null || $slot_state["disabled"]) {
            throw new \Bga\GameFramework\UserException(clienttranslate("This trading post is not available"));
        }
        if ($slot_state["occupying_player_id"] !== null) {
            throw new \Bga\GameFramework\UserException(clienttranslate("There is already a skiff on trading_post"));
        }
    }

    function acquireToken(string $player_id, string $token_key)
    {
        // Check if the player already owns this token
        $current_owner = $this->getTokenOwner($token_key);
        if ($current_owner === $player_id) {
            // Player already owns this token, no need to notify
            $this->mytrace("Player $player_id already owns token $token_key, skipping notification");
            return;
        }

        $taken_from_another_player = $current_owner !== null;

        self::DbQuery("REPLACE INTO unique_tokens (player_id, token_key) VALUES ('$player_id', '$token_key')");
        $token_name = $this->token_names[$token_key];
        $this->bga->notify->all("tokenAcquired", clienttranslate('${player_name} acquired the ${token_name}'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "token_name" => $token_name,
            "player_id" => $player_id,
            "token_key" => $token_key,
        ]);

        // Admiral ability: rewards when taking tokens from other players
        $this->applyAdmiralTokenTakenAbilities($player_id, $token_key, $taken_from_another_player);
    }

    function scoreInfamy(string $player_id, int $amount, string $message = "")
    {
        $this->DbQuery("UPDATE player SET player_score=player_score+$amount WHERE player_id='$player_id'");
        $new_score = $this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$player_id'");
        if ($message === "") {
            $message = clienttranslate('${player_name} scored ${score_increment} infamy');
        }
        $this->bga->notify->all("score", $message, [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
            "player_score" => $new_score,
            "score_increment" => $amount,
        ]);
    }

    function grantExtraTurn(string $player_id, string $phase)
    {
        self::DbQuery("REPLACE INTO extra_turns (player_id, phase) VALUES ('$player_id', '$phase')");
        $this->mytrace("Granted extra turn to player $player_id for phase $phase");
    }

    function hasExtraTurn(string $player_id, string $phase)
    {
        $result = self::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM extra_turns WHERE player_id = '$player_id' AND phase = '$phase'",
        );
        return $result > 0;
    }

    function consumeExtraTurn(string $player_id, string $phase)
    {
        self::DbQuery("DELETE FROM extra_turns WHERE player_id = '$player_id' AND phase = '$phase'");
        $this->mytrace("Consumed extra turn for player $player_id in phase $phase");
    }

    function clearExtraTurns(string $phase)
    {
        self::DbQuery("DELETE FROM extra_turns WHERE phase = '$phase'");
        $this->mytrace("Cleared all extra turns for phase $phase");
    }

    function clearIslandSlots()
    {
        $player_count = $this->getPlayersNumber();

        // Define which slots should be disabled based on player count
        $disabled_slots = [];

        // Corsair occupied placement can be used once per island phase.
        $this->setGameStateValue("corsair_occupied_placement_used", 0);

        if ($player_count < 3) {
            $disabled_slots["sailmaker"]["n1"] = true;
            $disabled_slots["trading_post"]["n1"] = true;
        }

        if ($player_count < 4) {
            $disabled_slots["blacksmith"]["n2"] = true;
            $disabled_slots["workshop"]["n2"] = true;
        }

        if ($player_count < 5) {
            $disabled_slots["trading_post"]["n2"] = true;
        }

        foreach (
            [
                ["capitol", "n1"],
                ["bank", "n1"],
                ["workshop", "n1"],
                ["workshop", "n2"],
                ["trading_post", "n1"],
                ["trading_post", "n2"],
                ["shipyard", "n1"],
                ["blacksmith", "n1"],
                ["blacksmith", "n2"],
                ["sailmaker", "n1"],
                ["deep_cove", "n1"],
                ["deep_cove", "n2"],
                ["green_flag", "n1"],
                ["tan_flag", "n1"],
                ["red_flag", "n1"],
                ["blue_flag", "n1"],
                ["market", "n1"],
                ["market", "n2"],
                ["market", "n3"],
                ["market", "n4"],
                ["market", "n5"],
            ]
            as [$slot_key, $number]
        ) {
            $disabled = isset($disabled_slots[$slot_key][$number]) ? 1 : 0;
            self::DbQuery(
                "REPLACE INTO islandslots (slot_key, number, occupying_player_id, corsair_occupying_player_id, disabled) VALUES ('$slot_key', '$number', null, null, $disabled)",
            );
        }
        $player_infos = $this->getPlayerInfo();

        foreach ($player_infos as $playerid => $player) {
            $this->playerSetResourceCount($playerid, "skiff", 3);
        }
    }

    function mytrace(string $msg)
    {
        $this->trace("[SoH] " . $msg);
    }
    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    /*
        In this space, you can put any utility methods useful for your game logic
    */
    public function getStateName()
    {
        return $this->gamestate->getCurrentMainState();
    }

    function getPlayerColor(string $player_id)
    {
        // Get player color
        $sql = "SELECT
                    player_id, player_color
                FROM
                    player 
                WHERE 
                    player_id = $player_id
               ";
        $player = $this->getNonEmptyObjectFromDb($sql);
        return $player["player_color"];
    }

    function sum_array_by_key(array ...$arrays)
    {
        $out = [];

        foreach ($arrays as $a) {
            foreach ($a as $key => $value) {
                if (array_key_exists($key, $out)) {
                    $out[$key] += $value;
                } else {
                    $out[$key] = $value;
                }
            }
        }

        return $out;
    }

    function makeCostNegative($cost)
    {
        return array_map(fn($value): int => -$value, $cost);
    }
    function canPayFor($cost, $resources)
    {
        $cost = $this->makeCostNegative($cost);
        $result = $this->sum_array_by_key($resources, $cost);
        #php 8.4: return array_any($result, fn($value): bool => $value < 0);
        // Check that no resource goes negative (all values >= 0)
        return array_reduce($result, fn($carry, $value): bool => $carry && $value >= 0, true);
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

    /**
     * Resolve booty "resources" for payment.
     * If $player_choice is set, all "choice" units are assigned to that resource type.
     * Otherwise, auto-assign each "choice" unit to whichever cost resource has the
     * highest remaining need after fixed resources are applied.
     */
    private function resolveBootyResourcesForPayment(
        array $resources,
        array $cost,
        ?string $player_choice = null,
    ): array {
        // First, collect fixed (non-choice) resources
        $out = [];
        $choiceAmount = 0;
        foreach ($resources as $key => $amount) {
            if ($key === "choice") {
                $choiceAmount += $amount;
            } else {
                $out[$key] = ($out[$key] ?? 0) + $amount;
            }
        }

        if ($choiceAmount <= 0) {
            return $out;
        }

        // If player explicitly chose a valid resource type, use it
        if (
            $player_choice !== null &&
            $player_choice !== "" &&
            in_array($player_choice, $this->resource_types, true) &&
            $player_choice !== "skiff"
        ) {
            $out[$player_choice] = ($out[$player_choice] ?? 0) + $choiceAmount;
            return $out;
        }

        // Auto-assign each "choice" unit to the cost resource with the highest remaining need
        for ($i = 0; $i < $choiceAmount; $i++) {
            $bestRes = null;
            $bestNeed = 0;
            foreach ($cost as $res => $need) {
                $coveredByFixed = $out[$res] ?? 0;
                $remaining = $need - $coveredByFixed;
                if ($remaining > $bestNeed) {
                    $bestNeed = $remaining;
                    $bestRes = $res;
                }
            }
            if ($bestRes !== null) {
                $out[$bestRes] = ($out[$bestRes] ?? 0) + 1;
            }
            // If no remaining need, the choice is wasted (no refund)
        }

        return $out;
    }

    /**
     * Pay a cost, optionally using the player's booty token to cover some or all of it.
     * If use_booty_card_id is set, that booty card is applied to reduce the cost (no refund for excess), then discarded to booty_discard.
     * $booty_choice is the player's chosen resource type for "choice" (wild) resources. If null, auto-resolved.
     */
    function payWithOptionalBooty(
        int $player_id,
        array $cost,
        ?int $use_booty_card_id = null,
        ?string $booty_choice = null,
    ): void {
        if (empty($cost)) {
            return;
        }
        $player_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
        $remaining_cost = $cost;

        if ($use_booty_card_id !== null && $use_booty_card_id > 0) {
            $booty_card = $this->cards->getCard($use_booty_card_id);
            if (
                !$booty_card ||
                $booty_card["location"] !== "booty_player" ||
                (int) $booty_card["location_arg"] !== (int) $player_id
            ) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid booty token"));
            }
            $config = $this->getBootyTokenConfigByTypeArg((int) $booty_card["type_arg"]);
            if (!$config || empty($config["resources"])) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid booty token"));
            }
            $booty_resources = $this->resolveBootyResourcesForPayment($config["resources"], $cost, $booty_choice);
            // Reduce cost by booty (no refund: only subtract up to cost amount per resource)
            $remaining_cost = [];
            foreach ($cost as $res => $need) {
                $have_from_booty = $booty_resources[$res] ?? 0;
                $remaining_cost[$res] = max(0, $need - $have_from_booty);
            }
            if (!$this->canPayFor($remaining_cost, $player_resources)) {
                throw new \Bga\GameFramework\UserException(clienttranslate("You cannot afford this cost even with the booty token"));
            }
            $this->pay($player_id, $remaining_cost);
            $this->cards->moveCard($use_booty_card_id, "booty_discard", 0);
            // Build a description of what the booty token covered, capped at cost
            $booty_parts = [];
            foreach ($booty_resources as $res => $amount) {
                $used = min($amount, $cost[$res] ?? 0);
                for ($i = 0; $i < $used; $i++) {
                    $booty_parts[] = "[$res]";
                }
            }
            $booty_desc = implode(" + ", $booty_parts);
            $this->bga->notify->all(
                "bootyTokenUsed",
                clienttranslate('${player_name} uses a booty token as ${booty_usage}'),
                [
                    "player_id" => $player_id,
                    "player_name" => $this->getPlayerNameById($player_id),
                    "booty_card" => $booty_card,
                    "booty_tokens" => $this->getBootyTokensForPlayer($player_id),
                    "booty_usage" => $booty_desc,
                ],
            );
            return;
        }

        $this->pay($player_id, $remaining_cost);
    }

    function pay($player_id, $cost)
    {
        $player_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
        if (!$this->canPayFor($cost, $player_resources)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You cannot afford this action"));
        }
        $cost = $this->makeCostNegative($cost);
        $this->playerGainResources($player_id, $cost);
    }
    function playerGainResources($player_id, $resources)
    {
        $this->mytrace("playerGainResources");
        $this->dump("incoming resources", $resources);
        $current_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
        $this->dump("current resources", $current_resources);

        $summed_resources = $this->sum_array_by_key($resources, $current_resources);
        $this->dump("summed resources", $summed_resources);

        $sql = "REPLACE INTO resource (player_id, resource_key, resource_count) VALUES ";
        $values = [];
        foreach ($summed_resources as $resource_type => $resource_count) {
            $values[] = "('" . $player_id . "','$resource_type','" . $resource_count . "')";
        }
        $sql .= implode(",", $values);
        $this->mytrace("gain resources sql: $sql");

        self::DbQuery($sql);
        $msg = $this->formatResourceChangeMessage($resources);
        $log = $msg ? '${player_name} ${resource_change}' : "";
        $this->bga->notify->all("resourcesChanged", $log, [
            "player_name" => self::getPlayerNameById($player_id),
            "resources" => $this->getGameResources(),
            "resource_change" => $msg,
        ]);
    }

    function playerSetResourceCount($player_id, $resource_type, $count)
    {
        $this->mytrace("playerSetResourceCount $player_id $resource_type $count");
        $current = $this->getGameResourcesHierarchical($player_id)[$player_id];
        $old_count = $current[$resource_type] ?? 0;
        $diff = $count - $old_count;
        self::DbQuery(
            "REPLACE INTO resource (player_id, resource_key, resource_count) VALUES ('$player_id','$resource_type','$count')",
        );
        $msg = $this->formatResourceChangeMessage([$resource_type => $diff]);
        $log = $msg ? '${player_name} ${resource_change}' : "";
        $this->bga->notify->all("resourcesChanged", $log, [
            "player_name" => self::getPlayerNameById($player_id),
            "resources" => $this->getGameResources(),
            "resource_change" => $msg,
        ]);
    }

    /**
     * Build a human-readable string describing resource changes, using [resource] markers
     * that the client replaces with icons.
     * e.g. ["sail" => -2, "cannonball" => 1] => "pays 2 [sail], gains 1 [cannonball]"
     */
    private function formatResourceChangeMessage(array $resources): string
    {
        $gains = [];
        $losses = [];
        foreach ($resources as $type => $amount) {
            if ($amount > 0) {
                $gains[] = "$amount [$type]";
            } elseif ($amount < 0) {
                $losses[] = abs($amount) . " [$type]";
            }
        }
        $parts = [];
        if (!empty($losses)) {
            $parts[] = "pays " . implode(" ", $losses);
        }
        if (!empty($gains)) {
            $parts[] = "gains " . implode(" ", $gains);
        }
        if (empty($parts)) {
            return "";
        }
        return implode(", ", $parts);
    }

    function showResourceChoiceDialog(string $context, string $context_number)
    {
        $this->bga->notify->player(self::getActivePlayerId(), "showResourceChoiceDialog", "", [
            "context" => $context,
            "context_number" => $context_number,
        ]);
    }

    function notifyDeckSizeChanged(string $player_id, string $message = "")
    {
        $deck_size = $this->cards->countCardInLocation($this->playerDeckName($player_id));
        $this->bga->notify->player($player_id, "deckSizeChanged", $message, [
            "player_id" => $player_id,
            "deck_size" => $deck_size,
        ]);
    }

    function drawCards(string $player_id, int $num_cards = 1)
    {
        $this->mytrace("drawCard - drawing $num_cards cards");
        $deck_name = $this->playerDeckName($player_id);

        // Track discard pile count before drawing to detect autoreshuffle
        $discard_count_before = $this->cards->countCardInLocation("player_discard", $player_id);

        $cards_drawn = $this->cards->pickCards($num_cards, $deck_name, $player_id);
        $this->mytrace("drawCard - drew " . count($cards_drawn) . " cards");

        // Check if autoreshuffle happened (discard pile was emptied during pickCards)
        $discard_count_after = $this->cards->countCardInLocation("player_discard", $player_id);
        if ($discard_count_before > 0 && $discard_count_after == 0) {
            $this->mytrace("drawCard - autoreshuffle detected, notifying player");
            $this->bga->notify->player(
                $player_id,
                "deckReshuffled",
                clienttranslate("Your discard pile was shuffled into your deck"),
                [
                    "player_id" => $player_id,
                    "deck_size" => $this->cards->countCardInLocation($deck_name),
                ],
            );
        }

        if (count($cards_drawn) > 0) {
            $message =
                count($cards_drawn) == 1
                    ? clienttranslate("You drew a card")
                    : clienttranslate('You drew ${num_cards} cards');

            $this->bga->notify->player($player_id, "cardDrawn", $message, [
                "player_id" => $player_id,
                "cards" => $cards_drawn,
                "num_cards" => count($cards_drawn),
                "deck_size" => $this->cards->countCardInLocation($deck_name),
            ]);
        } else {
            $this->trace("no cards to draw for player $player_id");
        }

        if (count($cards_drawn) < $num_cards) {
            $this->mytrace("drawCard - drew " . count($cards_drawn) . " cards, but need " . $num_cards . " cards");
            $this->cards->moveAllCardsInLocation("player_discard", $deck_name, $player_id);
            $this->cards->shuffle($deck_name);

            // Notify that deck was reshuffled from discard
            $this->bga->notify->player(
                $player_id,
                "deckReshuffled",
                clienttranslate("Your discard pile was shuffled into your deck"),
                [
                    "player_id" => $player_id,
                    "deck_size" => $this->cards->countCardInLocation($deck_name),
                ],
            );
            $cards_drawn = $this->cards->pickCards($num_cards - count($cards_drawn), $deck_name, $player_id);
            $this->mytrace("drawCard - drew another " . count($cards_drawn) . " cards");
            if (count($cards_drawn) > 0) {
                $message =
                    count($cards_drawn) == 1
                        ? clienttranslate("You drew a card")
                        : clienttranslate('You drew ${num_cards} cards');

                $this->bga->notify->player($player_id, "cardDrawn", $message, [
                    "player_id" => $player_id,
                    "cards" => $cards_drawn,
                    "num_cards" => count($cards_drawn),
                    "deck_size" => $this->cards->countCardInLocation($deck_name),
                ]);
            }
        }
    }

    function discardCards(string $player_id, array $card_ids)
    {
        $this->mytrace("discardCards - discarding " . count($card_ids) . " cards");

        $cards_discarded = [];
        foreach ($card_ids as $card_id) {
            // Validate that the card belongs to the player and is in their hand
            $card = $this->cards->getCard($card_id);
            if ($card == null) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card"));
            }

            if ($card["location"] != "hand" || $card["location_arg"] != $player_id) {
                throw new \Bga\GameFramework\UserException(clienttranslate("You can only discard cards from your hand"));
            }

            // Move card to player's discard pile
            $this->cards->moveCard($card_id, "player_discard", $player_id);
            $cards_discarded[] = $card;
        }

        if (count($cards_discarded) > 0) {
            $message =
                count($cards_discarded) == 1
                    ? clienttranslate('${player_name} discarded a card')
                    : clienttranslate('${player_name} discarded ${num_cards} cards');

            $this->bga->notify->all("cardsDiscarded", $message, [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "cards" => $cards_discarded,
                "num_cards" => count($cards_discarded),
            ]);
        }
    }
    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    ////////////

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in seasofhavoc.action.php)
    */

    function actPlaceSkiff(string $slotname, string $number)
    {
        $player_id = self::getActivePlayerId();
        $this->mytrace("placeSkiff: $player_id slotname: $slotname number: $number");
        if ($this->getPendingTradingPostSelection() !== null) {
            throw new \Bga\GameFramework\UserException(
                clienttranslate("Finish the trading post exchange before placing another skiff"),
            );
        }
        $occupancies = $this->getIslandSlots();

        $this->dump("occupancies", $occupancies);
        $this->dump("slotnames", $occupancies[$slotname]);

        // Check if slot is disabled
        if ($occupancies[$slotname][$number]["disabled"]) {
            throw new \Bga\GameFramework\UserException(clienttranslate("This slot is not available for the current number of players"));
            return;
        }

        // Check if slot is already occupied
        if ($occupancies[$slotname][$number]["occupying_player_id"] != null) {
            if ($this->canUseCorsairOccupiedPlacement($player_id)) {
                $resolved = $this->resolveCorsairOccupiedPlacement($player_id, $slotname, $number);
                if ($resolved) {
                    return "islandTurnDone";
                }
                return null; // Dialog shown, waiting for actResourcePickedInDialog
            }
            throw new \Bga\GameFramework\UserException(clienttranslate("There is already a skiff on this slot"));
        }

        switch ($slotname) {
            case "capitol":
                $this->acquireToken($player_id, "first_player_token");
                $this->showResourceChoiceDialog($slotname, $number);
                return null; // Dialog shown, waiting for actResourcePickedInDialog
            case "bank":
                $this->showResourceChoiceDialog($slotname, $number);
                return null; // Dialog shown, waiting for actResourcePickedInDialog
            case "shipyard":
                $this->playerGainResources($player_id, [
                    "sail" => 2,
                    "cannonball" => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "blacksmith":
                $this->playerGainResources($player_id, [
                    "cannonball" => 2,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "sailmaker":
                $this->playerGainResources($player_id, [
                    "sail" => 3,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "market":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "trading_post":
                $this->setPendingTradingPostSelection((int) $player_id, $number);
                $this->bga->notify->player($player_id, "showTradingPostDialog", "", [
                    "slot_number" => $number,
                ]);
                return null; // Dialog shown, waiting for actTradingPostExchange
            case "green_flag":
                $this->showResourceChoiceDialog($slotname, $number);
                return null; // Dialog shown, waiting for actResourcePickedInDialog
            case "tan_flag":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->drawCards($player_id);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            case "red_flag":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                // Transition to scrap card state instead of completing turn
                return "scrapCard";
            case "blue_flag":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->grantExtraTurn($player_id, "island");
                $this->occupyIslandSlot($player_id, $slotname, $number);
                return "islandTurnDone";
            default:
                throw new \Bga\GameFramework\SystemException("bad skiff slot: $slotname");
        }
    }

    function actResourcePickedInDialog(string $resource, string $context, string $number): mixed
    {
        $player_id = $this->getActivePlayerId();
        switch ($context) {
            case "capitol":
                $this->playerGainResources($player_id, [
                    $resource => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $context, $number);
                return "islandTurnDone";
            case "bank":
                $this->playerGainResources($player_id, [$resource => 1]);
                $this->playerGainResources($player_id, [
                    "doubloon" => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $context, $number);
                return "islandTurnDone";
            case "green_flag":
                $this->playerGainResources($player_id, [
                    $resource => 1,
                    "skiff" => -1,
                ]);
                $this->acquireToken($player_id, $context);
                $this->occupyIslandSlot($player_id, $context, $number);
                return "islandTurnDone";
            case "corsair_occupied_capitol":
                $this->finalizeCorsairOccupiedPlacement($player_id, "capitol", $number, [$resource => 1]);
                return "islandTurnDone";
            case "corsair_occupied_bank":
                $this->finalizeCorsairOccupiedPlacement($player_id, "bank", $number, ["doubloon" => 1, $resource => 1]);
                return "islandTurnDone";
            case "corsair_occupied_green_flag":
                $this->finalizeCorsairOccupiedPlacement($player_id, "green_flag", $number, [$resource => 1]);
                return "islandTurnDone";
            case "extortion_green_flag":
                $pending = (int) $this->getGameStateValue("extortion_pending_flags");
                $this->playerGainResources($player_id, [$resource => 1]);
                $this->bga->notify->all("log", clienttranslate('${player_name}\'s Extortion: gains 1 ${resource} (Green Flag)'), [
                    "player_name" => $this->getPlayerNameById($player_id),
                    "resource" => $resource,
                ]);
                $pending &= ~self::EXTORTION_GREEN_FLAG;
                $this->setGameStateValue("extortion_pending_flags", $pending);
                if ($pending === 0) {
                    return STATE_NEXT_PLAYER_SEA_PHASE;
                }
                return STATE_EXTORTION; // Re-enter to render the remaining red-flag selection.
            default:
                throw new \Bga\GameFramework\SystemException("bad context: $context");
        }
    }

    function actTradingPostExchange(
        #[JsonParam] array $resources_spent,
        #[JsonParam] array $resources_gained,
        string $slot_number,
        ?int $use_booty_card_id = null,
    ): mixed {
        $player_id = self::getActivePlayerId();
        $valid_resources = ["sail", "cannonball", "doubloon"];

        $this->assertPendingTradingPostSelection((int) $player_id, $slot_number);

        foreach ($resources_spent as $res) {
            if (!in_array($res, $valid_resources)) {
                throw new \Bga\GameFramework\SystemException("Invalid resource type in resources_spent: $res");
            }
        }
        foreach ($resources_gained as $res) {
            if (!in_array($res, $valid_resources)) {
                throw new \Bga\GameFramework\SystemException("Invalid resource type in resources_gained: $res");
            }
        }

        if ($use_booty_card_id !== null && $use_booty_card_id > 0) {
            // Booty trade: consume the token and gain 2 resources
            if (count($resources_spent) > 0) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Cannot spend resources when using a booty token"));
            }
            if (count($resources_gained) !== 2) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Must gain exactly 2 resources when using a booty token"));
            }

            $booty_card = $this->cards->getCard($use_booty_card_id);
            if (
                !$booty_card ||
                $booty_card["location"] !== "booty_player" ||
                (int) $booty_card["location_arg"] !== (int) $player_id
            ) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid booty token"));
            }
            $this->cards->moveCard($use_booty_card_id, "booty_discard", 0);
            $this->bga->notify->all(
                "bootyTokenUsed",
                clienttranslate('${player_name} uses a booty token at the trading post'),
                [
                    "player_id" => $player_id,
                    "player_name" => self::getPlayerNameById($player_id),
                    "booty_card" => $booty_card,
                    "booty_tokens" => $this->getBootyTokensForPlayer($player_id),
                    "booty_usage" => "trading post",
                ],
            );
        } else {
            // Normal trade: spend 1-2 resources, gain the same number
            if (count($resources_spent) < 1 || count($resources_spent) > 2) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Must spend 1 or 2 resources"));
            }
            if (count($resources_gained) !== count($resources_spent)) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Must gain the same number of resources as spent"));
            }

            $spend_counts = [];
            foreach ($resources_spent as $res) {
                $spend_counts[$res] = ($spend_counts[$res] ?? 0) + 1;
            }
            $player_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
            foreach ($spend_counts as $res => $count) {
                if (($player_resources[$res] ?? 0) < $count) {
                    throw new \Bga\GameFramework\UserException(clienttranslate("You don't have enough resources"));
                }
            }

            $negative_spend = [];
            foreach ($spend_counts as $res => $count) {
                $negative_spend[$res] = -$count;
            }
            $this->playerGainResources($player_id, $negative_spend);
        }

        // Gain the chosen resources
        $gain_counts = [];
        foreach ($resources_gained as $res) {
            $gain_counts[$res] = ($gain_counts[$res] ?? 0) + 1;
        }
        $this->playerGainResources($player_id, $gain_counts);

        // Spend skiff and occupy slot
        $this->playerGainResources($player_id, ["skiff" => -1]);
        $this->occupyIslandSlot($player_id, "trading_post", $slot_number);
        $this->setPendingTradingPostSelection(null, null);
        return "islandTurnDone";
    }

    function actCompletePurchases(#[JsonParam] array $cards_purchased)
    {
        $player_id = $this->getCurrentPlayerId();
        $this->dump("cards_purchased", $cards_purchased);

        // Check if player has already completed purchases (is in pending_purchases table)
        $existing = self::getObjectFromDB(
            "SELECT player_id FROM pending_purchases WHERE player_id = '$player_id' LIMIT 1",
        );
        if ($existing) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You have already completed your purchases"));
        }

        // Validate and store purchases in pending_purchases table
        // Each item: int card_id or { card_id, use_booty_card_id?, doubloons_as_cannonballs? }
        foreach ($cards_purchased as $item) {
            $card_id = is_array($item) ? $item["card_id"] ?? null : $item;
            $use_booty_card_id = is_array($item) ? $item["use_booty_card_id"] ?? null : null;
            $doubloons_as_cannonballs = is_array($item) ? intval($item["doubloons_as_cannonballs"] ?? 0) : 0;
            $doubloons_as_sails = is_array($item) ? intval($item["doubloons_as_sails"] ?? 0) : 0;
            if ($card_id === null) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card purchase"));
            }
            $card = $this->cards->getCard($card_id);
            if (!$card || $card["location"] != "market") {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card purchase"));
            }
            // Validate Merchant substitution
            if ($doubloons_as_cannonballs > 0 || $doubloons_as_sails > 0) {
                if ($this->getPlayerCaptain($player_id) !== "merchant") {
                    throw new \Bga\GameFramework\UserException(clienttranslate("Only the Merchant can spend doubloons as other resources"));
                }
                $market_card = $this->playable_cards[$card["type"]];
                if ($doubloons_as_cannonballs > ($market_card["cost"]["cannonball"] ?? 0)) {
                    throw new \Bga\GameFramework\UserException(
                        clienttranslate("Cannot substitute more doubloons than the cannonball cost"),
                    );
                }
                if ($doubloons_as_sails > ($market_card["cost"]["sail"] ?? 0)) {
                    throw new \Bga\GameFramework\UserException(
                        clienttranslate("Cannot substitute more doubloons than the sail cost"),
                    );
                }
            }
            $use_sql = "NULL";
            if ($use_booty_card_id !== null && $use_booty_card_id > 0) {
                $use_sql = "'" . intval($use_booty_card_id) . "'";
            }
            self::DbQuery(
                "INSERT INTO pending_purchases (player_id, card_id, use_booty_card_id, doubloons_as_cannonballs, doubloons_as_sails) VALUES ('$player_id', '" .
                    intval($card_id) .
                    "', $use_sql, $doubloons_as_cannonballs, $doubloons_as_sails)",
            );
        }

        $this->trace("active player list before: " . implode(", ", $this->gamestate->getActivePlayerList()));

        // Transition this player to the "completed purchases" private state
        $this->gamestate->nextPrivateState($player_id, "completedPurchases");

        // Deactivate this player (they've completed their purchases)
        // The transition name "cardPurchasesDone" will be used when all players are done
        $this->gamestate->setPlayerNonMultiactive($player_id, "cardPurchasesDone");
    }

    function commitAllPurchases()
    {
        $this->trace("Committing all purchases");

        // Get all pending purchases (including optional booty usage and Merchant substitution)
        $pending = self::getObjectListFromDB(
            "SELECT player_id, card_id, use_booty_card_id, doubloons_as_cannonballs, doubloons_as_sails FROM pending_purchases",
        );

        // Group by player for notification
        $purchases_by_player = [];
        $purchased_card_ids = [];

        foreach ($pending as $purchase) {
            $player_id = $purchase["player_id"];
            $card_id = (int) $purchase["card_id"];
            $use_booty_card_id =
                isset($purchase["use_booty_card_id"]) &&
                $purchase["use_booty_card_id"] !== null &&
                $purchase["use_booty_card_id"] !== ""
                    ? (int) $purchase["use_booty_card_id"]
                    : null;

            $card = $this->cards->getCard($card_id);
            if (!$card || $card["location"] != "market") {
                $this->trace("Warning: Card $card_id is not in market, skipping");
                continue;
            }

            $market_card = $this->playable_cards[$card["type"]];
            $cost = $market_card["cost"];

            // Merchant ability: shift cannonball/sail cost to doubloon cost
            $doubloons_as_cannonballs = intval($purchase["doubloons_as_cannonballs"] ?? 0);
            if ($doubloons_as_cannonballs > 0) {
                $cost["cannonball"] = ($cost["cannonball"] ?? 0) - $doubloons_as_cannonballs;
                $cost["doubloon"] = ($cost["doubloon"] ?? 0) + $doubloons_as_cannonballs;
                if ($cost["cannonball"] <= 0) {
                    unset($cost["cannonball"]);
                }
            }
            $doubloons_as_sails = intval($purchase["doubloons_as_sails"] ?? 0);
            if ($doubloons_as_sails > 0) {
                $cost["sail"] = ($cost["sail"] ?? 0) - $doubloons_as_sails;
                $cost["doubloon"] = ($cost["doubloon"] ?? 0) + $doubloons_as_sails;
                if ($cost["sail"] <= 0) {
                    unset($cost["sail"]);
                }
            }

            // Pay for the card (optionally using booty token)
            $this->payWithOptionalBooty($player_id, $cost, $use_booty_card_id);

            // Move card to player's hand
            $this->cards->moveCard($card_id, "hand", $player_id);

            // Track for notification
            if (!isset($purchases_by_player[$player_id])) {
                $purchases_by_player[$player_id] = [];
            }
            $purchases_by_player[$player_id][] = $card_id;
            $purchased_card_ids[] = $card_id;
        }

        // Notify all players about purchases
        $this->bga->notify->all("cardsPurchased", clienttranslate("Card purchases completed"), [
            "purchases" => $purchases_by_player,
            "purchased_card_ids" => $purchased_card_ids,
        ]);

        // Clear pending purchases table
        self::DbQuery("DELETE FROM pending_purchases");

        // Clear island slots and refill market
        $this->clearIslandSlots();
        $num_market_cards = $this->cards->countCardInLocation("market");
        $this->trace("num market cards: $num_market_cards");
        if ($num_market_cards < 5) {
            $this->trace("picking " . (5 - $num_market_cards) . " market cards");
            $this->cards->pickCardsForLocation(5 - $num_market_cards, "market_deck", "market");
        }

        // Notify all players about the updated market
        $updated_market = $this->cards->getCardsInLocation("market");
        $this->bga->notify->all("marketUpdated", clienttranslate("Market has been refilled"), [
            "market" => $updated_market,
            "islandslots" => $this->getIslandSlots(),
        ]);
    }

    function processSimpleAction(PrimitiveCardPlayAction $action_type)
    {
        $player_id = $this->getActivePlayerId();
        $outcome = [];
        $collision_occurred = false;
        $shipwreck_event = null;
        $booty_card = null;
        switch ($action_type) {
            case PrimitiveCardPlayAction::FORWARD:
                $result = $this->seaboard->moveObjectForward("player_ship", $player_id, ["rock", "player_ship"]);
                $outcome[] = $result;
                if ($result["type"] == "collision") {
                    $collision_occurred = true;
                } else {
                    $pickup = $this->collectShipwrecksAtPlayer($player_id);
                    $shipwreck_event = $pickup["shipwreck_event"] ?? $shipwreck_event;
                    $booty_card = $pickup["booty_card"] ?? $booty_card;
                }
                break;
            case PrimitiveCardPlayAction::PIVOT_LEFT:
                $outcome[] = $this->seaboard->turnObject("player_ship", $player_id, Turn::LEFT);
                break;
            case PrimitiveCardPlayAction::PIVOT_AROUND:
                $outcome[] = $this->seaboard->turnObject("player_ship", $player_id, Turn::AROUND);
                break;
            case PrimitiveCardPlayAction::PIVOT_RIGHT:
                $outcome[] = $this->seaboard->turnObject("player_ship", $player_id, Turn::RIGHT);
                break;
            default:
                throw new \Bga\GameFramework\UserException(
                    clienttranslate("Unknown action type") . ": " . $action_type->value,
                );
        }
        $this->dump("processSimpleAction outcome", $outcome);
        return [
            "action_chain" => $outcome,
            "collision_occurred" => $collision_occurred,
            "shipwreck_event" => $shipwreck_event,
            "booty_card" => $booty_card,
        ];
    }

    function processCaptainAbility(string $ability)
    {
        $this->trace("processing captain ability: $ability");
        $player_id = $this->getActivePlayerId();
        $this->trace("player id: $player_id");
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
        $discard = $this->cards->getCardsInLocation("player_discard", $player_id);
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

    protected function beginCaptainCard(string $player_id, string $ability): array|int
    {
        $empty = ["action_chain" => [], "collision_occurred" => false, "shipwreck_event" => null, "booty_card" => null];
        if ($ability === "retaliation" || $ability === "improvisation") {
            if (empty($this->captainCardOptions($player_id, $ability))) {
                if ($ability === "improvisation") {
                    $this->drawCards($player_id);
                }
                return $empty;
            }
        } elseif ($ability === "spyglass") {
            $deck = $this->playerDeckName($player_id);
            $cards = $this->cards->pickCardsForLocation(3, $deck, "spyglass", (int) $player_id, true) ?? [];
            if (count($cards) < 3 && $this->cards->countCardInLocation("player_discard", $player_id) > 0) {
                $this->cards->moveAllCardsInLocation("player_discard", $deck, $player_id);
                $this->cards->shuffle($deck);
                $this->bga->notify->player((int) $player_id, "deckReshuffled", "", [
                    "player_id" => $player_id, "deck_size" => $this->cards->countCardInLocation($deck),
                ]);
                $this->cards->pickCardsForLocation(3 - count($cards), $deck, "spyglass", (int) $player_id, true);
            }
            if (empty($this->captainCardOptions($player_id, $ability))) {
                return $empty;
            }
        } else {
            $ship = $this->seaboard->findObject("player_ship", $player_id);
            if ($ship === null) {
                throw new \Bga\GameFramework\SystemException("Treasure Seeker ship is missing");
            }
            $rock = false;
            foreach ($this->seaboard->getSurroundingPositions($ship["x"], $ship["y"]) as $pos) {
                $rock = $rock || !empty($this->seaboard->getObjectsOfTypes($pos["x"], $pos["y"], ["rock"]));
            }
            if (!$rock) {
                return $empty;
            }
            $token = $this->drawBootyToken((int) $player_id);
            if ($token === null) {
                return $empty; // The supply and its discard can both be exhausted by held tokens.
            }
            $this->cards->moveCard($token["id"], "captain_reward", (int) $player_id);
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
        if (!$card || $card["location"] !== "player_discard" || $card["location_arg"] != $player_id) {
            throw new \Bga\GameFramework\SystemException("Pending captain card is missing from the active player's discard");
        }
        $ability = $this->playable_cards[$card["type"]]["actions"][0]["ability"];
        $options = $this->captainCardOptions($player_id, $ability);
        $private = ["available_cards" => $options];
        if ($ability === "unearth_riches") {
            $private["resources"] = $this->getBootyTokenConfigByTypeArg((int) $options[0]["type_arg"])["resources"];
        }
        return ["ability" => $ability, "_private" => [$player_id => $private]];
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
            if (!is_int($selected) || !isset($by_id[$selected])) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Choose an available card"));
            }
            $chosen = $by_id[$selected];
            if ($ability === "retaliation") {
                $fire = $choices["fire"] ?? null;
                if (!in_array($fire, ["fire left", "fire right", "skip"], true)) {
                    throw new \Bga\GameFramework\UserException(clienttranslate("Choose a firing side or skip firing"));
                }
                $this->cards->moveCard($selected, "scrap");
                $this->bga->notify->all("cardScrapped", clienttranslate('${player_name} scraps a damage card for Retaliation'), [
                    "player_name" => $this->getPlayerNameById((int) $player_id), "player_id" => (int) $player_id,
                    "card" => $chosen, "original_location" => $chosen["location"],
                ]);
                if ($fire !== "skip") {
                    $actions = [["action" => "fire", "range" => 3]];
                    $decisions = [$fire];
                }
            } else {
                $actions = $this->playable_cards[$chosen["type"]]["actions"];
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
        return $this->resolvePlayedCard((int) $card["type"], $card_id, $decisions, $use_booty_card_id, $actions);
    }

    function processGovernmentFunding(string $player_id): array
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
        return [
            "action_chain" => [],
            "collision_occurred" => false,
            "shipwreck_event" => null,
            "booty_card" => null,
        ];
    }

    function processInspire(string $player_id): array
    {
        $discard_cards = $this->cards->getCardsInLocation("player_discard", $player_id);
        $damage_cards = array_filter(
            $discard_cards,
            fn($c) => ($this->playable_cards[$c["type"]]["category"] ?? "") === "damage",
        );
        if (empty($damage_cards)) {
            $this->scoreInfamy(
                $player_id,
                1,
                clienttranslate('${player_name}\'s Inspire: gains 1 infamy'),
            );
            $this->drawCards($player_id);
        } else {
            $damage_card = reset($damage_cards);
            $this->cards->moveCard($damage_card["id"], "scrap");
            $this->bga->notify->all(
                "cardScrapped",
                clienttranslate('${player_name}\'s Inspire: scrapped a damage card'),
                [
                    "player_name" => $this->getPlayerNameById($player_id),
                    "player_id" => intval($player_id),
                    "card" => [
                        "id" => intval($damage_card["id"]),
                        "type" => intval($damage_card["type"]),
                        "location" => "player_discard",
                        "location_arg" => intval($player_id),
                    ],
                    "original_location" => "player_discard",
                ],
            );
        }
        return [
            "action_chain" => [],
            "collision_occurred" => false,
            "shipwreck_event" => null,
            "booty_card" => null,
        ];
    }

    // --- Rally the Flags ---

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

        $ship = $this->seaboard->findObject("player_ship", $player_id);
        if ($ship) {
            foreach ($this->seaboard->getSurroundingPositions($ship["x"], $ship["y"]) as $pos) {
                foreach ($this->seaboard->getObjectsOfTypes($pos["x"], $pos["y"], ["player_ship"]) as $obj) {
                    $other_id = $obj["arg"];
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

    function processRallyTheFlags(string $player_id): mixed
    {
        if (empty($this->getRallyTheFlagsOptions($player_id))) {
            return ["action_chain" => [], "collision_occurred" => false, "shipwreck_event" => null, "booty_card" => null];
        }
        return STATE_RALLY_THE_FLAGS;
    }

    function argRallyTheFlagsChooseFlag(): array
    {
        return ["available_flags" => $this->getRallyTheFlagsOptions(self::getActivePlayerId())];
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
                clienttranslate('${player_name}\'s Rally the Flags: gains 1 infamy for controlling the most flags'),
            );
        }

        return STATE_NEXT_PLAYER_SEA_PHASE;
    }

    // --- Extortion ---

    function processExtortion(string $player_id): mixed
    {
        $tokens = $this->getUniqueTokens();
        $pending = 0;

        if (isset($tokens["tan_flag"]) && $tokens["tan_flag"] == $player_id) {
            $this->drawCards($player_id, 1);
            $this->bga->notify->all("log", clienttranslate('${player_name}\'s Extortion: draws a card (Tan Flag)'), [
                "player_name" => $this->getPlayerNameById($player_id),
            ]);
        }
        if (isset($tokens["blue_flag"]) && $tokens["blue_flag"] == $player_id) {
            $this->grantExtraTurn($player_id, "island");
            $this->bga->notify->all("log", clienttranslate('${player_name}\'s Extortion: gains an extra island turn (Blue Flag)'), [
                "player_name" => $this->getPlayerNameById($player_id),
            ]);
        }
        if (isset($tokens["green_flag"]) && $tokens["green_flag"] == $player_id) {
            $pending |= self::EXTORTION_GREEN_FLAG;
        }
        if (isset($tokens["red_flag"]) && $tokens["red_flag"] == $player_id) {
            $pending |= self::EXTORTION_RED_FLAG;
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
            "pending_green" => (bool) ($pending & self::EXTORTION_GREEN_FLAG),
            "pending_red" => (bool) ($pending & self::EXTORTION_RED_FLAG),
        ];
        if ($pending & self::EXTORTION_RED_FLAG) {
            $result["available_cards"] = array_merge(
                array_values($this->cards->getPlayerHand($player_id)),
                array_values($this->cards->getCardsInLocation("player_discard", $player_id)),
            );
        }
        return $result;
    }

    function actExtortionScrapCard(int $card_id): mixed
    {
        $player_id = self::getActivePlayerId();
        $pending = (int) $this->getGameStateValue("extortion_pending_flags");
        if (!($pending & self::EXTORTION_RED_FLAG)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("No Red Flag effect is pending"));
        }
        $card = $this->cards->getCard($card_id);
        if (!$card) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card"));
        }
        if (!in_array($card["location"], ["hand", "player_discard"]) || $card["location_arg"] != $player_id) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You can only scrap cards from your hand or discard pile"));
        }
        $original_location = $card["location"];
        $this->cards->moveCard($card_id, "scrap");
        $this->bga->notify->all("cardScrapped", clienttranslate('${player_name}\'s Extortion: scrapped a card (Red Flag)'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => intval($player_id),
            "card" => ["id" => intval($card["id"]), "type" => intval($card["type"]), "location" => $card["location"], "location_arg" => intval($card["location_arg"])],
            "original_location" => $original_location,
        ]);
        $this->setGameStateValue("extortion_pending_flags", 0);
        return STATE_NEXT_PLAYER_SEA_PHASE;
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

    // --- Barter ---

    private const BARTER_RATES = ["sail" => 1, "cannonball" => 2, "doubloon" => 3];

    protected function getPlayerInfamy(string $player_id): int
    {
        return (int) $this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$player_id'");
    }

    function processBarter(string $player_id): mixed
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

    function actBarterExchange(string $resource, string $direction): mixed
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
            $this->pay((int) $player_id, [$resource => 1]);
            $this->scoreInfamy($player_id, $infamy_amount,
                clienttranslate('${player_name}\'s Barter: gains ${score_increment} infamy'),
            );
        } elseif ($direction === "infamy_to_resource") {
            $current_infamy = $this->getPlayerInfamy($player_id);
            if ($current_infamy < $infamy_amount) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Not enough infamy for this exchange"));
            }
            $this->scoreInfamy($player_id, -$infamy_amount,
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

    // --- Timely Trading ---

    function processTimelyTrading(string $player_id): mixed
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

        $this->pay((int) $player_id, $cost);
        $this->cards->moveCard($card_id, "hand", $player_id);

        $num_market_cards = $this->cards->countCardInLocation("market");
        if ($num_market_cards < 5) {
            $this->cards->pickCardsForLocation(5 - $num_market_cards, "market_deck", "market");
        }
        $updated_market = $this->cards->getCardsInLocation("market");

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

    // --- Corsair: Boarding Party ---

    function processBoardingParty(string $player_id): mixed
    {
        $targets = $this->getBoardingPartyTargets($player_id);
        if (empty($targets)) {
            return [
                "action_chain" => [],
                "collision_occurred" => false,
                "shipwreck_event" => null,
                "booty_card" => null,
            ];
        }
        return STATE_BOARDING_PARTY;
    }

    protected function getBoardingPartyTargets(string $player_id): array
    {
        $ship = $this->seaboard->findObject("player_ship", $player_id);
        if (!$ship) return [];
        $targets = [];
        foreach ($this->seaboard->getSurroundingPositions($ship["x"], $ship["y"]) as $pos) {
            foreach ($this->seaboard->getObjectsOfTypes($pos["x"], $pos["y"], ["player_ship"]) as $obj) {
                $other_id = (string) $obj["arg"];
                if ($other_id === $player_id) continue;
                $resources = $this->getGameResourcesHierarchical((int) $other_id)[$other_id] ?? [];
                $stealable = array_filter(
                    array_intersect_key($resources, array_flip(["sail", "cannonball", "doubloon"])),
                    fn($v) => $v > 0,
                );
                $booty_count = $this->cards->countCardInLocation("booty_player", $other_id);
                if (!empty($stealable) || $booty_count > 0) {
                    $targets[] = [
                        "player_id" => $other_id,
                        "resources" => $stealable,
                        "booty_token_count" => $booty_count,
                    ];
                }
            }
        }
        return $targets;
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
        $this->scoreInfamy($player_id, 1, clienttranslate('${player_name}\'s Boarding Party: gains 1 infamy'));
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

    // --- Corsair: Hunt the Bounty ---

    function processHuntTheBounty(string $player_id): mixed
    {
        return STATE_HUNT_THE_BOUNTY;
    }

    protected function getHuntTheBountyTargets(string $player_id): array
    {
        $targets = [];
        foreach ($this->loadPlayersBasicInfos() as $other_id => $_) {
            if ((string) $other_id !== $player_id) {
                $targets[] = (string) $other_id;
            }
        }
        return $targets;
    }

    function argHuntTheBounty(): array
    {
        $player_id = $this->getActivePlayerId();
        $target_ids = $this->getHuntTheBountyTargets($player_id);
        $targets = [];
        foreach ($target_ids as $tid) {
            $targets[] = [
                "player_id" => $tid,
                "player_name" => $this->getPlayerNameById((int) $tid),
            ];
        }
        return ["targets" => $targets];
    }

    function actHuntTheBountyChooseTarget(string $target_player_id): mixed
    {
        $player_id = $this->getActivePlayerId();
        if ($this->getPlayerCaptain($player_id) !== "corsair") {
            throw new \Bga\GameFramework\UserException(clienttranslate("Only the Corsair can use Hunt the Bounty"));
        }
        $valid_ids = $this->getHuntTheBountyTargets($player_id);
        if (!in_array($target_player_id, $valid_ids)) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid target for Hunt the Bounty"));
        }
        $this->setGameStateValue("hunt_the_bounty_target", (int) $target_player_id);
        $this->bga->notify->all("log",
            clienttranslate('${player_name} declares ${target_name} as their Hunt the Bounty target'),
            [
                "player_name" => $this->getPlayerNameById($player_id),
                "target_name" => $this->getPlayerNameById((int) $target_player_id),
            ],
        );
        return STATE_SEA_TURN;
    }

    function actSkipHuntTheBounty(): mixed
    {
        return STATE_SEA_TURN;
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
        $total_cost = $this->sum_array_by_key($total_cost, $cost);
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
        $this->trace("processing card actions");
        $this->dump("actions", $actions);
        $collision_occurred = false;
        $shipwreck_event = null;
        $booty_card = null;
        foreach ($actions as $i => $action) {
            $typed_action =
                gettype($action["action"]) == "string"
                    ? PrimitiveCardPlayAction::from($action["action"])
                    : $action["action"];
            $this->trace("handling " . $typed_action->value);
            $this->dump("to_send", $to_send);
            if (array_key_exists("cost", $action)) {
                $decision = $decisions[0];
                if ($decision == "skip") {
                    $this->trace("skipping action with cost due to decision == 'skip': " . $typed_action->value);
                    array_shift($decisions);
                    $this->trace("decisions after skipping: " . implode(", ", $decisions));
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
                        throw new \Bga\GameFramework\UserException("Invalid card action choice: " . $decision);
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
                    $this->trace("fire");
                    $decision = array_shift($decisions);
                    $this->trace("decision: $decision");
                    $player_id = $this->getActivePlayerId();
                    $outcome = $this->seaboard->resolveCannonFire(
                        $player_id,
                        $decision == "fire left" ? Turn::LEFT : Turn::RIGHT,
                        $action["range"],
                        ["rock", "player_ship"],
                    );
                    if ($outcome["type"] == "fire_hit") {
                        foreach ($outcome["hit_objects"] as $collider) {
                            if ($collider["type"] == "player_ship") {
                                $score_increment = 2;
                                if (
                                    $collider["heading"] == $outcome["fire_heading"] ||
                                    $collider["heading"] ==
                                        SeaBoard::turnHeading($outcome["fire_heading"], Turn::AROUND)
                                ) {
                                    # raking
                                    $score_increment = 3;
                                }
                                $this->DbQuery(
                                    "UPDATE player SET player_score=player_score+ " .
                                        $score_increment .
                                        " WHERE player_id='" .
                                        $player_id .
                                        "'",
                                );
                                $new_score = $this->getUniqueValueFromDB(
                                    "SELECT player_score from player WHERE player_id='" . $player_id . "'",
                                );
                                $this->bga->notify->all(
                                    "score",
                                    clienttranslate('${player_name} scored ${score_increment} infamy'),
                                    [
                                        "player_name" => $this->getPlayerNameById($player_id),
                                        "player_id" => $player_id,
                                        "player_score" => $new_score,
                                        "score_increment" => $score_increment,
                                    ],
                                );
                                $hit_player_id = $collider["arg"];
                                $damage_card = $this->cards->pickCardForLocation(
                                    "damage_deck",
                                    "player_discard",
                                    $hit_player_id,
                                );

                                $this->bga->notify->all(
                                    "damageReceived",
                                    clienttranslate('${player_name} receives a damage card'),
                                    [
                                        "player_name" => self::getPlayerNameById($hit_player_id),
                                        "player_id" => $hit_player_id,
                                        "damage_card" => $damage_card,
                                    ],
                                );
                                $bounty_target = (int) $this->getGameStateValue("hunt_the_bounty_target");
                                if ($bounty_target !== 0 && (int) $hit_player_id === $bounty_target &&
                                    $this->getPlayerCaptain($player_id) === "corsair") {
                                    $this->scoreInfamy($player_id, 1,
                                        clienttranslate('${player_name}\'s Hunt the Bounty: gains 1 infamy'));
                                }
                            }
                        }
                    }
                    // FIRE actions never cause movement collisions - explicitly set collision_occurred to false
                    $this->merge_results(
                        [
                            "action_chain" => [$outcome],
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
                $player_id = $this->getActivePlayerId();
                if ($i + 1 < count($actions)) {
                    $next_action = $actions[$i + 1]["action"];
                    if ($next_action instanceof \PrimitiveCardPlayAction) {
                        $next_action = $next_action->value;
                    }
                    $this->DbQuery(
                        "REPLACE INTO next_action_on_card (player_id, next_action) VALUES ($player_id, '$next_action')",
                    );
                } else {
                    $this->DBQuery("DELETE FROM next_action_on_card WHERE player_id = $player_id");
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

    function applyWhirlpoolRotation($player_id)
    {
        // Check if the ship is on a whirlpool
        if ($this->seaboard->isObjectOnWhirlpool("player_ship", $player_id)) {
            $this->trace("Ship is on whirlpool - rotating 90 degrees clockwise");
            $turn_result = $this->seaboard->turnObject("player_ship", $player_id, Turn::RIGHT);
            return ["result" => $turn_result, "occurred" => true];
        }
        return ["result" => null, "occurred" => false];
    }

    function applyGustPush($player_id)
    {
        // Check if the ship is on a gust
        $gust = $this->seaboard->getGustAtObjectLocation("player_ship", $player_id);
        if ($gust) {
            $this->trace("Ship is on gust - pushing in direction " . $gust["heading"]->toString());
            $push_result = $this->seaboard->pushObjectInDirection("player_ship", $player_id, $gust["heading"], [
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

    function actPlayCard(int $card_type, int $card_id, #[JsonParam] $decisions, ?int $use_booty_card_id = null)
    {
        $held = $this->cards->getCard($card_id);
        if (!$held || $held["location"] !== "hand" || $held["location_arg"] != $this->getActivePlayerId() ||
            (int) $held["type"] !== $card_type) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Choose a card from your hand"));
        }
        return $this->resolvePlayedCard($card_type, $card_id, $decisions, $use_booty_card_id);
    }

    protected function resolvePlayedCard(int $card_type, int $card_id, array $decisions, ?int $use_booty_card_id = null, ?array $actions = null)
    {
        $this->dump("card_type", $card_type);
        $this->dump("decisions", $decisions);
        $card = $this->playable_cards[$card_type];
        $this->dump("card played", $card);
        $player_id = $this->getActivePlayerId();

        // Check if this is a "pass" play (playing card without executing actions)
        $is_pass = !empty($decisions) && $decisions[0] === "pass";

        if ($is_pass) {
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
            $outcome = $this->processCardActions($actions ?? $card["actions"], $decisions);

            if (isset($outcome["captain_state"])) {
                if ($outcome["captain_state"] === STATE_CAPTAIN_CARD) {
                    $this->setGameStateValue("pending_captain_card", $card_id);
                }
                $this->cards->moveCard($card_id, "player_discard", $player_id);
                $this->bga->notify->all("cardPlayed", clienttranslate('${player_name} has played a card'), [
                    "player_name" => $this->getPlayerNameById($player_id),
                    "player_id" => $player_id,
                    "moveChain" => [],
                    "cost" => [],
                    "shipwreck_event" => null,
                ]);
                return $outcome["captain_state"];
            }

            $this->dump("final card play outcome", $outcome);

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
                    } elseif ($this->seaboard->isObjectOnWhirlpool("player_ship", $player_id)) {
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

        $this->cards->moveCard($card_id, "player_discard", $player_id);

        $this->bga->notify->all("cardPlayed", $notification_message, [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => $player_id,
            "moveChain" => $all_moves,
            "cost" => $outcome["cost"],
            "shipwreck_event" => $shipwreck_event,
        ]);

        if ($booty_card != null) {
            $this->dump("booty collected", $booty_card);
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

        return $outcome["collision_occurred"] || $seafeature_collision ? "collisionOccurred" : "seaTurnDone";
    }

    function stResolveCollision()
    {
        $this->mytrace("stResolveCollision");
    }

    function actResolveCollision(string $card_id, string $action_type)
    {
        $this->mytrace("actResolveCollision");
        $this->dump("card_id", $card_id);
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

        // Apply seafeature effects (whirlpool rotation and gust push) after collision resolution
        // But only if they haven't been attempted yet (seafeature effects are only applied once per card play)
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
            $typed_action = PrimitiveCardPlayAction::from($direction);
            $outcome = $this->processCardActions([["action" => $typed_action]], []);
            $this->dump("final pivot outcome", $outcome);

            // Pay the cost for pivot actions (pivots are free, but just in case)
            if (!empty($outcome["cost"])) {
                $this->payWithOptionalBooty($player_id, $outcome["cost"]);
            }

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
                } elseif ($this->seaboard->isObjectOnWhirlpool("player_ship", $player_id)) {
                    $notification_message = clienttranslate('${player_name} pivots and is rotated by the whirlpool');
                } else {
                    $notification_message = clienttranslate('${player_name} pivots and is pushed by the gust');
                }
            }

            $this->bga->notify->all("cardPlayed", $notification_message, [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "moveChain" => $all_moves,
                "cost" => $outcome["cost"],
                "shipwreck_event" => $shipwreck_event,
            ]);
            //TODO: check if player can fire due to interrupted maneuver ending in firing action
        } elseif (!empty($seafeature_effects["moves"])) {
            // No pivot, but we still need to notify about seafeature effects
            $notification_message = clienttranslate('${player_name} resolves collision');
            $shipwreck_event = $seafeature_effects["shipwreck_event"] ?? null;
            $booty_card = $seafeature_effects["booty_card"] ?? null;

            if (count($seafeature_effects["moves"]) == 2) {
                $notification_message = clienttranslate('${player_name} is affected by the whirlpool and gust');
            } elseif ($this->seaboard->isObjectOnWhirlpool("player_ship", $player_id)) {
                $notification_message = clienttranslate('${player_name} is rotated by the whirlpool');
            } else {
                $notification_message = clienttranslate('${player_name} is pushed by the gust');
            }

            $this->bga->notify->all("cardPlayed", $notification_message, [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
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
        return $seafeature_collision ? "collisionOccurred" : "collisionResolved";
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
        return "cardDiscarded";
    }

    function argScrapCard()
    {
        $this->mytrace("argScrapCard");
        $player_id = self::getActivePlayerId();

        // Get cards from hand and discard pile
        $hand_cards = $this->cards->getPlayerHand($player_id);
        $discard_cards = $this->cards->getCardsInLocation("player_discard", $player_id);

        // Combine and prepare for scrollable stock
        $available_cards = array_merge($hand_cards, $discard_cards);

        return [
            "available_cards" => $available_cards,
        ];
    }

    function actScrapCard(int $card_id)
    {
        $this->mytrace("actScrapCard: card_id=$card_id");
        $player_id = self::getActivePlayerId();

        // Validate that the card belongs to the player and is in hand or discard
        $card = $this->cards->getCard($card_id);
        if (!$card) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card"));
        }

        $valid_locations = ["hand", "player_discard"];
        if (!in_array($card["location"], $valid_locations) || $card["location_arg"] != $player_id) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You can only scrap cards from your hand or discard pile"));
        }

        // Store the original location before moving
        $original_location = $card["location"];

        // Move card to scrap pile
        $this->cards->moveCard($card_id, "scrap");

        // Ensure card ID is properly formatted
        $card_for_notification = [
            "id" => intval($card["id"]),
            "type" => intval($card["type"]),
            "location" => $card["location"],
            "location_arg" => intval($card["location_arg"]),
        ];

        // Notify players
        $this->bga->notify->all("cardScrapped", clienttranslate('${player_name} scrapped a card'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => intval($player_id),
            "card" => $card_for_notification,
            "original_location" => $original_location,
        ]);

        return "cardScrapped";
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    /*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }    
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn($state, $active_player)
    {
        $statename = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState("zombiePass");
                    break;
            }

            return;
        }

        if ($state["type"] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, "");

            return;
        }

        throw new \Bga\GameFramework\SystemException("Zombie mode not supported at this game state: " . $statename);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
        //        if( $from_version <= 1404301345 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //
    }
}
