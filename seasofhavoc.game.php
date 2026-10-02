<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SeasOfHavoc implementation : © Peter Gorniak
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

use Bga\GameFramework\Table;
use Bga\Games\SeasOfHavoc\Heading;
use Bga\Games\SeasOfHavoc\SeaBoard;
use Bga\Games\SeasOfHavoc\Resources;
use Bga\Games\SeasOfHavoc\Decks;
use Bga\Games\SeasOfHavoc\BootyAndShipwrecks;
use Bga\Games\SeasOfHavoc\ShipUpgradeRules;
use Bga\Games\SeasOfHavoc\CaptainAbilities;
use Bga\Games\SeasOfHavoc\IslandPhase;
use Bga\Games\SeasOfHavoc\CardPlay;
use Bga\Games\SeasOfHavoc\ZombieMoves;
use Bga\Games\SeasOfHavoc\Firing;
use Bga\Games\SeasOfHavoc\ScoringAndStats;

// State ids (state machine itself is defined by the classes in modules/php/States/).
if (!defined("STATE_END_GAME")) {
    // ensure this block is only invoked once, since it is included multiple times
    define("STATE_ISLAND_TURN", 3);
    define("STATE_NEXT_PLAYER_ISLAND_PHASE", 4);
    define("STATE_CARD_PURCHASES", 5);
    define("STATE_CARD_PURCHASES_PRIVATE", 51);
    define("STATE_CARD_PURCHASES_COMPLETED_PRIVATE", 52);
    define("STATE_COMMIT_PURCHASES_PRIVATE", 53);
    define("STATE_SEA_PHASE_SETUP", 6);
    define("STATE_SEA_TURN", 7);
    define("STATE_NEXT_PLAYER_SEA_PHASE", 8);
    define("STATE_RESOLVE_COLLISION", 9);
    define("STATE_ISLAND_PHASE_SETUP", 10);
    define("STATE_SCRAP_CARD", 11);
    define("STATE_REBEL_DISCARD", 12);
    define("STATE_TREASURE_SEEKER_ADJUST", 13);
    define("STATE_RALLY_THE_FLAGS", 14);
    define("STATE_EXTORTION", 15);
    define("STATE_BARTER", 16);
    define("STATE_TIMELY_TRADING", 17);
    define("STATE_BOARDING_PARTY", 18);
    define("STATE_HUNT_THE_BOUNTY", 19);
    define("STATE_CAPTAIN_CARD", 20);
    define("STATE_CARD_FLAG", 21);
    define("STATE_SWIFT_HULL", 22);
    define("STATE_BOOTY_DISCARD", 23);
    define("STATE_POST_COLLISION_FIRE", 24);
    define("STATE_COLLISION_DISCARD", 25);
    define("STATE_FINAL_SCORING", 26);
    define("STATE_HUNT_THE_BOUNTY_EXTRA_PLAY", 27);
    define("STATE_CHAIN_SHOT_LOSS", 28);
    define("STATE_CHOOSE_HEADING", 29);
    define("STATE_END_GAME", 99);
}

class SeasOfHavoc extends Table
{
    // The game logic, grouped by theme in modules/php/.
    use Resources, Decks, BootyAndShipwrecks, ShipUpgradeRules, CaptainAbilities, IslandPhase, CardPlay, Firing, ScoringAndStats, ZombieMoves;

    /** Table option (gameoptions.jsonc): 1 = off, 2 = 2 Ship Variant. */
    private const OPTION_TWO_SHIPS = 100;

    /** Captain and ship stats store 1-based indexes into these (stats.jsonc value_labels match). */
    const STAT_CAPTAINS = ["pirate_queen", "rebel", "admiral", "merchant", "corsair", "treasure_seeker"];
    const STAT_SHIPS = ["Xebec", "Ship-of-the-Line", "Galleon", "Sloop of War", "War Junk", "Brig"];

    /** Where infamy comes from, each with its own player stat (infamy_from_<source>). */
    const INFAMY_SOURCES = ["shots", "rams", "cards", "upgrades", "captain"];

    private const RESOURCE_CHOICE_CONTEXTS = [
        "capitol",
        "bank",
        "green_flag",
        "corsair_occupied_capitol",
        "corsair_occupied_bank",
        "corsair_occupied_green_flag",
    ];

    // Debug flag: give each player a booty token at game start (one with a wild resource)
    private const DEBUG_START_WITH_BOOTY = false;

    // Debug flag: fix the damage deck size so the end of the game is quick to reach. 0 = play by
    // the rules (10 + 5 per player). Set back to 0 before release.
    private const DEBUG_DAMAGE_CARDS = 0;

    private const TREASURE_SEEKER_RESUME_SEA_TURN_DONE = 2;
    private const TREASURE_SEEKER_RESUME_COLLISION = 3;
    private const TREASURE_SEEKER_RESUME_COLLISION_RESOLVED = 4;

    public const NIMBLE_HULL_CHOICE = "nimble hull: maneuver twice";

    private const EXTORTION_FLAG_BITS = ["green" => 1, "red" => 2, "tan" => 4, "blue" => 8];

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
            "island_scraps_remaining" => 23,
            "pending_card_flag_type" => 24,
            "pending_treasure_seeker_player" => 25,
            "pending_workshop_player" => 26,
            "pending_workshop_slot" => 27,
            "swift_hull_card_type" => 28,
            "booty_discard_return_state" => 29,
            "market_restocked" => 30,
            "pending_chain_shot_victims" => 31,
            "chain_shot_shooter" => 32,
            // 2 Ship Variant: which of the active player's ships the current card moves (1 or 2).
            "active_ship" => 33,
            // A skiff placement waiting on the player's resource choice (Capitol, Bank, green flag,
            // or the Corsair on one of those): RESOURCE_CHOICE_CONTEXTS index + 1, and slot number.
            "pending_resource_context" => 34,
            "pending_resource_slot" => 35,
        ]);

        $this->cards = $this->deckFactory->createDeck("card");
        $this->cards->autoreshuffle = true;

        $this->seaboard = new SeaBoard("SeasOfHavoc::DBQuery", $this);

        foreach (array_keys($this->playable_cards) as $t) {
            $this->playable_cards[$t]["card_type"] = $t;
        }
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
        $this->mydump("default_colours", $default_colors);
        $this->mydump("players", $players);
        // TEMP HACK: force the first players onto specific ships for upgrade testing.
        // Ships decide which two upgrades a player can buy, and a fixed gameinfos order meant a
        // given player count always produced the same ones. Empty = deal ships at random.
        $forced_ships_for_testing = []; //["War Junk", "Sloop of War", "Brig"];
        $ship_index = 0;

        foreach ($players as $player_id => $player) {
            $ship = $forced_ships_for_testing[$ship_index++] ?? array_rand($default_colors);
            if (!isset($default_colors[$ship])) {
                throw new \Bga\GameFramework\SystemException("Unknown or duplicate forced ship: $ship");
            }
            $color = $default_colors[$ship];
            unset($default_colors[$ship]);
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
        // 2 Ship Variant: each player also sails a second ship, of a type nobody else has. "No cards
        // or upgrades from the 2nd ship are used", so it only needs its name, for its sprite.
        if ($this->bga->tableOptions->get(self::OPTION_TWO_SHIPS) === 2) {
            foreach (array_keys($players) as $player_id) {
                $ship = array_rand($default_colors);
                unset($default_colors[$ship]);
                self::DbQuery("UPDATE player SET player_ship2 = '$ship' WHERE player_id = $player_id");
            }
        }
        //self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();

        // Infamy is tracked by the framework's player_score counter.
        $this->bga->playerScore->initDb(array_map('intval', array_keys($players)));

        $this->bga->tableStats->init(
            ["rounds", "winning_captain", "winning_ship", "winning_captain_ship", "winner_infamy", "winning_margin"],
            0,
        );
        $this->bga->tableStats->init("two_ship_variant", $this->bga->tableOptions->get(self::OPTION_TWO_SHIPS) === 2);
        $this->bga->playerStats->init(
            array_merge(
                ["captain", "ship", "upgrades_activated", "cards_bought", "cards_scrapped", "shots_fired", "shots_missed",
                    "hits_broadside", "hits_raking", "rams", "rammed", "rock_collisions", "damage_received"],
                array_map(fn($source) => "infamy_from_$source", self::INFAMY_SOURCES),
            ),
            0,
        );

        /************ Start the game initialization *****/

        $sql = "INSERT INTO resource (player_id, resource_key, resource_count) VALUES ";
        $base_resources = array_fill_keys($this->resource_types, 1);
        $base_resources["skiff"] = 3;

        $this->mydump("base resources", $base_resources);
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
                "i18n" => ["token_name"],
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

            // Each player points their ship(s) in the Choose Heading state, in player order.
            $this->seaboard->placeObject($position["x"], $position["y"], [
                "type" => "player_ship",
                "arg" => $playerid,
                "heading" => Heading::NO_HEADING,
            ]);
            if ($this->hasSecondShip($playerid)) {
                $position = $this->findEmptyBoardPosition(["player_ship", "rock", "shipwreck", "sea_monster_part"]);
                $this->seaboard->placeObject($position["x"], $position["y"], [
                    "type" => "player_ship",
                    "arg" => self::secondShipArg($playerid),
                    "heading" => Heading::NO_HEADING,
                ]);
            }

            // TEMP HACK: force first players to specific captains for ability testing.
            if (isset($forced_captains_for_testing[$player_index])) {
                $captain_key = $forced_captains_for_testing[$player_index];
                $captain_keys = array_values(array_filter($captain_keys, fn($k) => $k !== $captain_key));
            } else {
                $captain_key = array_pop($captain_keys);
            }
            $this->assignCaptainToPlayer($playerid, $captain_key);
            $this->bga->playerStats->set("captain", $this->captainStatValue($captain_key), (int) $playerid);
            $this->bga->playerStats->set("ship", $this->shipStatValue($player["player_ship"]), (int) $playerid);

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
        $this->refillMarket();

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
                    $this->mytrace("DEBUG: Gave player $pid booty token image_id=$target_image_id");
                }
            }
        }

        /************ End of the game initialization *****/
        $this->gamestate->changeActivePlayer($random_first_player);

        return STATE_CHOOSE_HEADING;
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

    /** The player's next ship still without a heading, or null once all of theirs have one. */
    function unorientedShip(int $player_id): ?array
    {
        foreach ([(string) $player_id, self::secondShipArg($player_id)] as $arg) {
            $ship = $this->seaboard->findObject("player_ship", $arg);
            if ($ship !== null && $ship["object"]["heading"] === Heading::NO_HEADING) {
                return ["arg" => $arg, "x" => $ship["x"], "y" => $ship["y"]];
            }
        }
        return null;
    }

    function argChooseHeading(): array
    {
        return ["ship" => $this->unorientedShip((int) $this->getActivePlayerId())];
    }

    function actChooseHeading(int $heading): mixed
    {
        $player_id = (int) $this->getActivePlayerId();
        $ship = $this->unorientedShip($player_id);
        $heading = Heading::from($heading);
        if ($ship === null || $heading === Heading::NO_HEADING) {
            throw new \Bga\GameFramework\SystemException("Invalid heading choice: " . $heading->toString());
        }
        $this->seaboard->removeObject($ship["x"], $ship["y"], "player_ship", $ship["arg"]);
        $this->seaboard->placeObject($ship["x"], $ship["y"], [
            "type" => "player_ship",
            "arg" => $ship["arg"],
            "heading" => $heading,
        ]);
        $this->bga->notify->all("shipOriented", clienttranslate('${player_name} chooses a heading for their ship'), [
            "player_id" => $player_id,
            "player_name" => $this->getPlayerNameById($player_id),
            "ship_arg" => $ship["arg"],
            "heading" => $heading->value,
        ]);
        if ($this->unorientedShip($player_id) !== null) {
            return STATE_CHOOSE_HEADING;
        }
        $next = (int) $this->activeNextPlayer();
        if ($this->unorientedShip($next) === null) {
            return STATE_ISLAND_PHASE_SETUP;
        }
        $this->giveExtraTime($next);
        return STATE_CHOOSE_HEADING;
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

    function stIslandPhaseSetup()
    {
        $this->mytrace("stIslandPhaseSetup");
        $this->bga->tableStats->inc("rounds", 1);
        $this->applyPirateQueenIslandPhaseStartAbilities();

        $player_infos = $this->getPlayerInfo();

        foreach ($player_infos as $playerid => $player) {
            // Draw 4 new cards
            $this->drawCards($playerid, 4);
        }

        $rebel_id = $this->applyRebelIslandPhaseStartDraw();

        // Clear any leftover extra turns from previous phases
        $this->clearExtraTurns("island");
        $this->clearUpgradeUses(["brig_extra_rations"]);

        // Set the first player token holder as the active player for the island phase
        $first_player_token_owner = $this->getFirstPlayerTokenOwner();
        if ($first_player_token_owner === null) {
            throw new \Bga\GameFramework\SystemException("No player has the first player token - this should never happen");
        }

        if ($rebel_id !== null) {
            $this->mytrace("Rebel player ($rebel_id) must discard before island phase begins");
            $this->gamestate->changeActivePlayer($rebel_id);
            $this->giveExtraTime((int) $rebel_id);
            return "rebelDiscard";
        }

        $this->mytrace(
            "Setting first player token owner ($first_player_token_owner) as active player for island phase",
        );
        $this->gamestate->changeActivePlayer($first_player_token_owner);
        $this->giveExtraTime((int) $first_player_token_owner);

        return "islandTurn";
    }

    function stNextPlayerIslandPhase()
    {
        $this->mytrace("stNextPlayerIslandPhase");

        $overflow = $this->bootyOverflowState(STATE_NEXT_PLAYER_ISLAND_PHASE);
        if ($overflow !== null) {
            return $overflow;
        }

        $resources = $this->getGameResourcesHierarchical();
        $this->mydump("fetched resources:", $resources);

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

        // Nobody else has anything to decide here, so only the claimants are made active. With no
        // claimants at all the phase passes straight through.
        $claimants = $this->getMarketClaimants();
        if (empty($claimants)) {
            $this->gamestate->setPlayersMultiactive([], "cardPurchasesDone", true);
            return;
        }
        $this->gamestate->setPlayersMultiactive($claimants, "cardPurchasesDone", true);
        foreach ($claimants as $claimant) {
            $this->giveExtraTime((int) $claimant);
        }

        // Initialize the claimants to the "making purchases" private state
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
        $this->giveExtraTime((int) $first_player_token_owner);

        $this->setGameStateValue("hunt_the_bounty_target", 0);
        $this->clearUpgradeUses(["sloop_of_war_nimble_hull"]);

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

    function stNextPlayerSeaPhase(): mixed
    {
        $overflow = $this->bootyOverflowState(STATE_NEXT_PLAYER_SEA_PHASE);
        if ($overflow !== null) {
            return $overflow;
        }
        $chain_shot = $this->nextChainShotLossState();
        if ($chain_shot !== null) {
            return $chain_shot;
        }
        $type = (int) $this->getGameStateValue("pending_card_flag_type");
        if ($type !== 0) {
            $flag = $this->playable_cards[$type]["flag"];
            if ($this->getTokenOwner($flag . "_flag") == $this->getActivePlayerId()) {
                return STATE_CARD_FLAG;
            }
            $this->setGameStateValue("pending_card_flag_type", 0);
        }
        // Xebec Swift Hull: after a sailing card (and its flag action) resolves, the player may
        // pay 1 sail to play another card immediately.
        if ($this->canUseSwiftHull($this->getActivePlayerId())) {
            return STATE_SWIFT_HULL;
        }
        $this->setGameStateValue("swift_hull_card_type", 0);
        $current_player = $this->getActivePlayerId();
        $active_player = $this->activeNextPlayer();
        $num_cards = $this->cards->countCardInLocation("hand", $active_player);
        $this->mytrace("$active_player num cards in hand: $num_cards");
        while ($num_cards == 0) {
            $active_player = $this->activeNextPlayer();
            $num_cards = $this->cards->countCardInLocation("hand", $active_player);
            $this->mytrace("$active_player num cards in hand: $num_cards");
            if ($active_player == $current_player) {
                $this->mytrace("$active_player is current player");
                break;
            }
        }
        $this->mytrace("final num cards: $num_cards");
        if ($num_cards == 0) {
            // "The game ends at the end of a Sea Phase when the Damage deck is empty."
            return $this->cards->countCardInLocation("damage_deck") == 0
                ? STATE_FINAL_SCORING
                : "seaPhaseDone";
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
        $result["endScores"] = $this->gamestate->getCurrentMainStateId() === STATE_END_GAME ? $this->getEndScores() : null;

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
        $pending_workshop = $this->getPendingWorkshopSelection();
        $result["pending_workshop_slot"] = null;
        if ($pending_workshop !== null && (int) $pending_workshop["player_id"] === (int) $current_player_id) {
            $result["pending_workshop_slot"] = $pending_workshop["slot_number"];
        }
        // Card actions are rewritten per player so the play dialog offers the shots and free
        // maneuvers their activated ship upgrades unlock.
        $result["playable_cards"] = $this->upgradedPlayableCards($current_player_id);

        // Send the full, unmodified market to all players
        // Frontend will filter out pending purchases for the current player
        // Market cards are returned in a consistent order (by location_arg), so frontend can use array position as slot number
        $result["market"] = $this->getMarketSlots();

        // Send player's actual hand - frontend will add pending purchases to hand display
        $result["hand"] = $this->cards->getPlayerHand($current_player_id);
        $result["discard"] = $this->normalizeCardLocations($this->getPlayerDiscard($current_player_id));
        $result["scrap"] = $this->cards->getCardsInLocation("scrap");
        $result["playerinfo"] = $this->getPlayerInfo();
        // Ships still to be pointed stay hidden until their owner's turn to choose a heading.
        $active_player_id = $this->getActivePlayerId();
        $result["seaboard"] = array_values(array_filter(
            $this->seaboard->getAllObjectsFlat(),
            fn($o) => $o["type"] !== "player_ship" || $o["heading"] !== Heading::NO_HEADING
                || explode("_", (string) $o["arg"])[0] == $active_player_id,
        ));
        $result["non_playable_cards"] = $this->non_playable_cards;

        $result["deck_size"] = $this->cards->countCardInLocation($this->playerDeckName($current_player_id));
        $result["damage_deck_size"] = $this->cards->countCardInLocation("damage_deck");
        $result["player_captain"] = $this->getPlayerCaptain($current_player_id);
        $result["corsair_occupied_placement_available"] = $this->canUseCorsairOccupiedPlacement($current_player_id);
        $result["corsair_occupied_slot_names"] = $this->corsairOccupiedPlacementSlotNames();
        $result["player_ship_upgrades"] = $this->getPlayerShipUpgrades($current_player_id);
        // Captains and upgrade states are public, shown in every player's panel.
        foreach (array_keys($result["players"]) as $player_id) {
            $result["players"][$player_id]["captain"] = $this->getPlayerCaptain($player_id);
            $result["players"][$player_id]["ship_upgrades"] = $this->getPlayerShipUpgrades($player_id);
        }

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see modules/php/States/)
    */
    function getGameProgression()
    {
        $totalDamageCards = $this->calculateNumDamageCards($this->getPlayersNumber());
        $remainingDamageCards = $this->cards->countCardInLocation("damage_deck");

        return (int) 100 * (1 - $remainingDamageCards / $totalDamageCards);
    }

    function getPlayerInfo(?int $player_id = null)
    {
        // Deliberately uncached: this carries player_score, and the previous static cache both
        // went stale after scoring and ignored $player_id (caching one row for every later call).
        $sql = "SELECT player_id, player_no, player_name, player_score, player_score_aux, player_ship, player_ship2, player_color
                FROM player";
        if ($player_id !== null) {
            $sql .= " WHERE player_id = $player_id";
        }
        return $this->getCollectionFromDB($sql);
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

    /**
     * Debug logging, to the server log in Studio only: production keeps its logs for real
     * problems. Use these rather than the framework's trace() and dump().
     */
    function mytrace(string $msg)
    {
        if (self::getBgaEnvironment() === "studio") {
            $this->trace("[SoH] " . $msg);
        }
    }

    function mydump(string $label, mixed $data)
    {
        if (self::getBgaEnvironment() === "studio") {
            $this->dump($label, $data);
        }
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

    /** Infamy per resource in a Barter exchange (see CaptainAbilities). */
    private const BARTER_RATES = ["sail" => 1, "cannonball" => 2, "doubloon" => 3];

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
