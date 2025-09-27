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

require_once APP_GAMEMODULE_PATH . "module/table/table.game.php";
require_once "modules/SeaBoard.php";

use Bga\GameFramework\Actions\Types\JsonParam;


enum PrimitiveCardPlayAction: string
{
    case FORWARD = "forward";
    case PIVOT_LEFT = "pivot left";
    case PIVOT_RIGHT = "pivot right";
    case LEFT = "left";
    case RIGHT = "right";
    case FIRE = "fire";
    case FIRE2 = "2 x fire";
    case FIRE3 = "3 x fire3";
    case SEQUENCE = "sequence";
    case CHOICE = "choice";
}
class SeasOfHavoc extends Table
{
    private SeaBoard $seaboard;
    private $cards;
    private array $playable_cards;
    private array $resource_types;
    private array $token_names;
    private array $non_playable_cards;

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
            //    "my_first_global_variable" => 10,
            //    "my_second_global_variable" => 11,
            //      ...
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...
        ]);

        $this->cards = $this->getNew("module.common.deck");
        $this->cards->init("card");
        $this->cards->autoreshuffle = true;
        $this->cards->autoreshuffle_custom = [
            "player_deck" => "player_discard",
        ];

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

        // Init global values with their initial values
        //self::setGameStateInitialValue( 'my_first_global_variable', 0 );

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // TODO: setup the initial game situation here

        /************ End of the game initialization *****/
        $this->activeNextPlayer();
        //$this->gamestate->nextState();
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
        $ship_upgrades = array_filter($this->non_playable_cards, function($card) use ($ship_name) {
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


    function stMyGameSetup()
    {
        //throw new BgaSystemException("mysetup start");

        //incredibly, it's impossible to log anything in the official game setup, so this is a second setup state
        $this->mytrace("stMyGameSetup");
        $sql = "INSERT INTO resource (player_id, resource_key, resource_count) VALUES ";
        $base_resources = array_fill_keys($this->resource_types, 1);
        $base_resources["skiff"] = 3;

        $this->dump("base resources", $base_resources);
        $player_infos = $this->loadPlayersBasicInfos();

        $values = [];
        foreach ($player_infos as $playerid => $player) {
            $player_resources = $base_resources;
            $this->mytrace("player is " . $playerid);

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
        self::DbQuery("INSERT INTO unique_tokens (player_id, token_key) VALUES ('$random_first_player', 'first_player_token')");
        
        // Notify players who got the first player token
        $this->notifyAllPlayers("tokenAcquired", clienttranslate('${player_name} starts the game with the ${token_name}'), [
            "player_name" => $this->getPlayerNameById($random_first_player),
            "token_name" => $this->token_names["first_player_token"],
            "player_id" => $random_first_player,
            "token_key" => "first_player_token",
        ]);
        
        // Create other tokens without owners
        foreach (["green_flag", "tan_flag", "blue_flag", "red_flag"] as $token) {
            self::DbQuery("INSERT INTO unique_tokens (player_id, token_key) VALUES (NULL, '$token')");
        }

        $this->clearIslandSlots();

        $player_infos = $this->getPlayerInfo();

        // Get all captain cards for random assignment
        $captain_cards = array_filter($this->non_playable_cards, fn($card) => isset($card["category"]) && $card["category"] == "captain");
        $captain_keys = array_keys($captain_cards);
        shuffle($captain_keys);

        foreach ($player_infos as $playerid => $player) {
            $this->seaboard->placeObject(rand(0, SeaBoard::WIDTH - 1), rand(0, SeaBoard::HEIGHT - 1), [
                "type" => "player_ship",
                "arg" => $playerid,
                "heading" => Heading::NORTH,
            ]);
            
            // Assign random captain to player
            $captain_key = array_pop($captain_keys);
            $this->assignCaptainToPlayer($playerid, $captain_key);
            
            // Assign ship upgrade cards matching player's ship
            $this->assignShipUpgradesToPlayer($playerid, $player["player_ship"]);
            
            $player_starting_cards = array_filter(
                array_filter($this->playable_cards, fn($x) => $x["category"] == "starting_card"),
                function ($v) use ($player) {
                    return $v["ship_name"] == $player["player_ship"];
                },
            );
            $start_deck = [];
            foreach ($player_starting_cards as $starting_card) {
                $start_deck[] = [
                    "type" => $starting_card["card_type"],
                    "type_arg" => 0,
                    "nbr" => $starting_card["count"],
                ];
            }
            $this->cards->createCards($start_deck, $this->playerDeckName($playerid));
            $this->cards->shuffle($this->playerDeckName($playerid));
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
        #for ($i = 1; $i < 6; $i++) {
        $this->cards->pickCardsForLocation(6, "market_deck", "market");
        #}

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

        $this->gamestate->nextState();
    }

    function stIslandPhaseSetup()
    {
        $this->mytrace("stIslandPhaseSetup");
        $player_infos = $this->getPlayerInfo();

        foreach ($player_infos as $playerid => $player) {            
            // Draw 4 new cards
            $this->drawCards($playerid, 4);
        }
        
        // Clear any leftover extra turns from previous phases
        $this->clearExtraTurns("island");
        
        // Set the first player token holder as the active player for the island phase
        $first_player_token_owner = $this->getFirstPlayerTokenOwner();
        if ($first_player_token_owner === null) {
            throw new BgaSystemException("No player has the first player token - this should never happen");
        }
        
        $this->mytrace("Setting first player token owner ($first_player_token_owner) as active player for island phase");
        $this->gamestate->changeActivePlayer($first_player_token_owner);
        
        $this->gamestate->nextState();
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
                $this->gamestate->nextState("nextPlayer");
                return;
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
                $this->gamestate->nextState("islandPhaseDone");
                return;
            }
            
            $this->mytrace("Player $active_player has no skiffs, skipping");
            $active_player = $this->activeNextPlayer();
            
        }

        $this->mytrace("Next player with skiffs: $active_player");
        $this->giveExtraTime($active_player);
        $this->gamestate->nextState("nextPlayer");
    }

    function stCardPurchases()
    {
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stSeaPhaseSetup()
    {
        $this->mytrace("stSeaPhaseSetup");
        
        // Set the first player token holder as the active player for the sea phase
        $first_player_token_owner = $this->getFirstPlayerTokenOwner();
        if ($first_player_token_owner === null) {
            throw new BgaSystemException("No player has the first player token - this should never happen");
        }
        
        $this->mytrace("Setting first player token owner ($first_player_token_owner) as active player for sea phase");
        $this->gamestate->changeActivePlayer($first_player_token_owner);
        
        $this->gamestate->nextState();
    }

    function stNextPlayerSeaPhase()
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
            $this->gamestate->nextState("seaPhaseDone");
            return;
        }
        $this->giveExtraTime($active_player);
        $this->gamestate->nextState("nextPlayer");
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = [];

        $current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result["players"] = self::getCollectionFromDb($sql);

        // TODO: Gather all information about current game situation (visible by player $current_player_id).

        $result["resources"] = $this->getGameResources();
        $result["islandslots"] = $this->getIslandSlots();
        $result["unique_tokens"] = $this->getUniqueTokens();
        $result["playable_cards"] = $this->playable_cards;
        $result["market"] = $this->cards->getCardsInLocation("market");
        $result["hand"] = $this->cards->getPlayerHand($current_player_id);
        $result["discard"] = $this->cards->getCardsInLocation("player_discard", $current_player_id);
        $result["scrap"] = $this->cards->getCardsInLocation("scrap");
        $result["playerinfo"] = $this->getPlayerInfo();
        $result["seaboard"] = $this->seaboard->getAllObjectsFlat();
        $result["non_playable_cards"] = $this->non_playable_cards;
        $result["deck_size"] = $this->cards->countCardInLocation($this->playerDeckName($current_player_id));
        $result["player_captain"] = $this->getPlayerCaptain($current_player_id);
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
    		    slot_key, number, occupying_player_id
    		FROM islandslots
    	";
        $slots = $this->getObjectListFromDB($sql);
        $indexed_slots = [];
        foreach ($slots as $slot) {
            $indexed_slots[$slot["slot_key"]][$slot["number"]] = $slot["occupying_player_id"];
        }
        return $indexed_slots;
    }

    function occupyIslandSlot(string $player_id, string $slot_name, string $number)
    {
        self::DbQuery(
            "REPLACE INTO islandslots (slot_key, number, occupying_player_id) VALUES ('$slot_name', '$number', '$player_id')",
        );
        $this->notifyAllPlayers("skiffPlaced", clienttranslate('${player_name} placed a skiff on ${slot_name}'), [
            "player_name" => self::getActivePlayerName(),
            "player_id" => $player_id,
            "player_color" => $this->getPlayerColor($player_id),
            "slot_name" => $slot_name,
            "slot_number" => $number,
        ]);
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
        
        self::DbQuery(
            "REPLACE INTO unique_tokens (player_id, token_key) VALUES ('$player_id', '$token_key')",
        );
        $token_name = $this->token_names[$token_key];
        $this->notifyAllPlayers("tokenAcquired", clienttranslate('${player_name} acquired the ${token_name}'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "token_name" => $token_name,
            "player_id" => $player_id,
            "token_key" => $token_key,
        ]);
    }

    function grantExtraTurn(string $player_id, string $phase)
    {
        self::DbQuery("REPLACE INTO extra_turns (player_id, phase) VALUES ('$player_id', '$phase')");
        $this->mytrace("Granted extra turn to player $player_id for phase $phase");
    }

    function hasExtraTurn(string $player_id, string $phase)
    {
        $result = self::getUniqueValueFromDB("SELECT COUNT(*) FROM extra_turns WHERE player_id = '$player_id' AND phase = '$phase'");
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
        foreach ([
            ["capitol", "n1"],
            ["bank", "n1"],
            ["shipyard", "n1"],
            ["blacksmith", "n1"],
            ["green_flag", "n1"],
            ["tan_flag", "n1"],
            ["red_flag", "n1"],
            ["blue_flag", "n1"],
            ["market", "n1"],
            ["market", "n2"],
            ["market", "n3"],
            ["market", "n4"],
            ["market", "n5"],
        ] as [$slot_key, $number]) {
            self::DbQuery(
                "REPLACE INTO islandslots (slot_key, number, occupying_player_id) VALUES ('$slot_key', '$number', null)",
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
        $state = $this->gamestate->state();
        return $state["name"];
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
    function pay($player_id, $cost)
    {
        $player_resources = $this->getGameResourcesHierarchical($player_id)[$player_id];
        if (!$this->canPayFor($cost, $player_resources)) {
            throw new BgaUserException($this->_("You cannot afford this action"));
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
        foreach ($summed_resources as $resource_type => $resource_count) {
            $values[] = "('" . $player_id . "','$resource_type','" . $resource_count . "')";
        }
        $sql .= implode(",", $values);
        $this->mytrace("gain resources sql: $sql");

        self::DbQuery($sql);
        $this->notifyAllPlayers("resourcesChanged", clienttranslate('resources changed for ${player_name}'), [
            "player_name" => self::getPlayerNameById($player_id),
            "resources" => $this->getGameResources(),
        ]);
    }

    function playerSetResourceCount($player_id, $resource_type, $count)
    {
        $this->mytrace("playerSetResourceCount $player_id $resource_type $count");
        self::DbQuery(
            "REPLACE INTO resource (player_id, resource_key, resource_count) VALUES ('$player_id','$resource_type','$count')",
        );
        $this->notifyAllPlayers("resourcesChanged", clienttranslate('resources changed for ${player_name}'), [
            "player_name" => self::getPlayerNameById($player_id),
            "resources" => $this->getGameResources(),
        ]);
    }

    function showResourceChoiceDialog(string $context, string $context_number)
    {
        $this->notifyPlayer(self::getActivePlayerId(), "showResourceChoiceDialog", "", [
            "context" => $context,
            "context_number" => $context_number,
        ]);
    }

    function notifyDeckSizeChanged(string $player_id, string $message = ""){
        $deck_size = $this->cards->countCardInLocation($this->playerDeckName($player_id));
        $this->notifyPlayer($player_id, "deckSizeChanged", $message, [
            "player_id" => $player_id,
            "deck_size" => $deck_size,
        ]);
    }

    function drawCards(string $player_id, int $num_cards = 1){
        $this->mytrace("drawCard - drawing $num_cards cards");
        $deck_name = $this->playerDeckName($player_id);
        
        $cards_drawn = $this->cards->pickCards($num_cards, $deck_name, $player_id);
        $this->mytrace("drawCard - drew " . count($cards_drawn) . " cards");
        if (count($cards_drawn) > 0) {
            $message = count($cards_drawn) == 1 
                ? clienttranslate('You drew a card') 
                : clienttranslate('You drew ${num_cards} cards');
            
            $this->notifyPlayer($player_id, "cardDrawn", $message, [
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
            $this->notifyPlayer($player_id, "deckReshuffled", clienttranslate('Your discard pile was shuffled into your deck'), [
                "player_id" => $player_id,
                "deck_size" => $this->cards->countCardInLocation($deck_name),
            ]);
            $cards_drawn = $this->cards->pickCards($num_cards - count($cards_drawn), $deck_name, $player_id);
            $this->mytrace("drawCard - drew another " . count($cards_drawn) . " cards");
            if (count($cards_drawn) > 0) {
                $message = count($cards_drawn) == 1 
                    ? clienttranslate('You drew a card') 
                    : clienttranslate('You drew ${num_cards} cards');
                
                $this->notifyPlayer($player_id, "cardDrawn", $message, [
                    "player_id" => $player_id,
                    "cards" => $cards_drawn,
                    "num_cards" => count($cards_drawn),
                    "deck_size" => $this->cards->countCardInLocation($deck_name),
                ]);
            }
        }

    }

    function discardCards(string $player_id, array $card_ids){
        $this->mytrace("discardCards - discarding " . count($card_ids) . " cards");
        
        $cards_discarded = [];
        foreach ($card_ids as $card_id) {
            // Validate that the card belongs to the player and is in their hand
            $card = $this->cards->getCard($card_id);
            if ($card == null) {
                throw new BgaUserException($this->_("Invalid card"));
            }
            
            if ($card['location'] != 'hand' || $card['location_arg'] != $player_id) {
                throw new BgaUserException($this->_("You can only discard cards from your hand"));
            }
            
            // Move card to player's discard pile
            $this->cards->moveCard($card_id, "player_discard", $player_id);
            $cards_discarded[] = $card;
        }
        
        if (count($cards_discarded) > 0) {
            $message = count($cards_discarded) == 1 
                ? clienttranslate('${player_name} discarded a card') 
                : clienttranslate('${player_name} discarded ${num_cards} cards');
            
            $this->notifyAllPlayers("cardsDiscarded", $message, [
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
        $occupancies = $this->getIslandSlots();

        $this->dump("occupancies", $occupancies);
        $this->dump("slotnames", $occupancies[$slotname]);

        if ($occupancies[$slotname][$number] != null) {
            throw new BgaUserException($this->_("There is already a skiff on $slotname"));
            return;
        }
        // $this->DbQuery(
        //     "REPLACE INTO current_skiff_slot (player_id, slot_name, slot_number) VALUES ($player_id, '$slotname', '$number')",
        // );

        switch ($slotname) {
            case "capitol":
                //TODO: take first player marker
                $this->acquireToken($player_id, "first_player_token");
                $this->showResourceChoiceDialog($slotname, $number);
                break;
            case "bank":
                $this->showResourceChoiceDialog($slotname, $number);
                break;
            case "shipyard":
                $this->playerGainResources($player_id, [
                    "sail" => 2,
                    "cannonball" => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                $this->gamestate->nextState("islandTurnDone");
                break;
            case "blacksmith":
                $this->playerGainResources($player_id, [
                    "cannonball" => 2,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                $this->gamestate->nextState("islandTurnDone");
                break;
            case "market":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                $this->gamestate->nextState("islandTurnDone");
                break;
            case "green_flag":
                $this->showResourceChoiceDialog($slotname, $number);
                break;
            case "tan_flag":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->drawCards($player_id);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                $this->gamestate->nextState("islandTurnDone");
                break;
            case "red_flag":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->occupyIslandSlot($player_id, $slotname, $number);
                // Transition to scrap card state instead of completing turn
                $this->gamestate->nextState("scrapCard");
                break;
            case "blue_flag":
                $this->playerGainResources($player_id, ["skiff" => -1]);
                $this->acquireToken($player_id, $slotname);
                $this->grantExtraTurn($player_id, "island");
                $this->occupyIslandSlot($player_id, $slotname, $number);
                $this->gamestate->nextState("islandTurnDone");
                break;
            default:
                throw new BgaSystemException("bad skiff slot: $slotname");
                break;
        }
    }

    function actExitDummyStart()
    {
        //throw new BgaSystemException("act dummy");
        //only exists to make BGA debugging in setup code work
        $this->gamestate->nextState();
    }

    function actResourcePickedInDialog(string $resource, string $context, string $number)
    {
        $player_id = $this->getActivePlayerId();
        switch ($context) {
            case "capitol":
                $this->playerGainResources($player_id, [
                    $resource => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $context, $number);
                $this->gamestate->nextState("islandTurnDone");
                break;
            case "bank":
                $this->playerGainResources($player_id, [$resource => 1]);
                $this->playerGainResources($player_id, [
                    "doubloon" => 1,
                    "skiff" => -1,
                ]);
                $this->occupyIslandSlot($player_id, $context, $number);
                $this->gamestate->nextState("islandTurnDone");
                break;
            case "green_flag":
                $this->playerGainResources($player_id, [
                    $resource => 1,
                    "skiff" => -1,
                ]);
                $this->acquireToken($player_id, $context);
                $this->occupyIslandSlot($player_id, $context, $number);
                $this->gamestate->nextState("islandTurnDone");
                break;
            default:
                throw new BgaSystemException("bad context: $context");
        }
    }

    function actCompletePurchases(#[JsonParam] array $cards_purchased)
    {
        $player_id = $this->getCurrentPlayerId();
        $this->dump("cards_purchased", $cards_purchased);
        foreach ($cards_purchased as $card_id) {
            //$this->dump("market cards", $this->market_cards);
            $card = $this->cards->getCard($card_id);
            $this->dump("card", $card);
            $market_card = $this->playable_cards[$card["type"]];
            $this->pay($player_id, $market_card["cost"]);
            $this->cards->moveCard($card_id, "hand", $player_id);
        }
        $this->trace("active player list: " . implode(", ", $this->gamestate->getActivePlayerList()));
        if (count($this->gamestate->getActivePlayerList()) == 1) {
            $this->trace("only one active player, clearing island slots");
            $this->clearIslandSlots();
            $num_market_cards = $this->cards->countCardInLocation("market");
            $this->trace("num market cards: $num_market_cards");
            if ($num_market_cards < 6) {
                $this->trace("picking " . (6 - $num_market_cards) . " market cards");
                $this->cards->pickCardsForLocation(6 - $num_market_cards, "market_deck", "market");
            }
        }

        $this->gamestate->setPlayerNonMultiactive($player_id, "cardPurchasesDone");
    }

    function processSimpleAction(PrimitiveCardPlayAction $action_type)
    {
        $player_id = $this->getActivePlayerId();
        $outcome = [];
        $collision_occurred = false;
        switch ($action_type) {
            case PrimitiveCardPlayAction::FORWARD:
                $result = $this->seaboard->moveObjectForward("player_ship", $player_id, ["rock", "player_ship"]);
                $outcome[] = $result;
                if ($result["type"] == "collision") {
                    $collision_occurred = true;
                }
                break;
            case PrimitiveCardPlayAction::PIVOT_LEFT:
                $outcome[] = $this->seaboard->turnObject("player_ship", $player_id, Turn::LEFT);
                break;
            case PrimitiveCardPlayAction::PIVOT_RIGHT:
                $outcome[] = $this->seaboard->turnObject("player_ship", $player_id, Turn::RIGHT);
                break;
            default:
                throw new BgaUserException($this->_("Unknown action type: $action_type->value"));
        }
        $this->dump("processSimpleAction outcome", $outcome);
        return ["action_chain" => $outcome, "collision_occurred" => $collision_occurred];
    }

    function merge_results(array $result, $cost, array &$to_send, array &$total_cost, bool &$collision_occurred)
    {
        $total_cost = $this->sum_array_by_key($total_cost, $cost);
        if (array_key_exists("collision_occurred", $result)) {
            $collision_occurred = $collision_occurred || $result["collision_occurred"];
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
        foreach ($actions as $i => $action) {
            $typed_action =
                gettype($action["action"]) == "string"
                    ? PrimitiveCardPlayAction::from($action["action"])
                    : $action["action"];
            $this->trace("handling " . $typed_action->value);
            $this->dump("to_send", $to_send);
            if (array_key_exists("cost", $action)) {
                # an action with a cost is always optional, so check the next decision where 0 is taking the action
                # and 1 is 'Skip'
                $decision = array_shift($decisions);
                if ($decision == "skip") {
                    $this->trace("skipping action with cost due to decision == 'skip': " . $typed_action->value);
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
                    );
                    break;
                case PrimitiveCardPlayAction::CHOICE:
                    $decision = array_shift($decisions);
                    $choices = $action["choices"];
                    $choice_names = array_map(fn($x) => key_exists("name", $x) ? $x["name"] : $x["action"], $choices);
                    $decision_index = array_search($decision, $choice_names);
                    $result = $this->processCardActions([$choices[array_keys($choices)[$decision_index]]], $decisions);
                    $this->merge_results($result, $cost, $to_send, $total_cost, $collision_occurred);
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
                    $this->merge_results($result, $cost, $to_send, $total_cost, $collision_occurred);
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
                    $this->merge_results($result, $cost, $to_send, $total_cost, $collision_occurred);
                    break;
                case PrimitiveCardPlayAction::FIRE:
                case PrimitiveCardPlayAction::FIRE2:
                case PrimitiveCardPlayAction::FIRE3:
                    $this->trace("fire");
                    $decision = array_shift($decisions);
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
                                $this->notifyAllPlayers(
                                    "score",
                                    clienttranslate('${player_name} scored ${score_increment} infamy'),
                                    [
                                        "player_name" => self::getActivePlayerName(),
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

                                $this->notifyAllPlayers(
                                    "damageReceived",
                                    clienttranslate('${player_name} receives a damage card'),
                                    [
                                        "player_name" => self::getPlayerNameById($hit_player_id),
                                        "player_id" => $hit_player_id,
                                        "damage_card" => $damage_card,
                                    ],
                                );
                            }
                        }
                    }
                    $this->merge_results(["action_chain" => [$outcome]], $cost, $to_send, $total_cost, $collision_occurred);
                    break;
                default:
                    $result = $this->processSimpleAction($typed_action);
                    $this->merge_results($result, $cost, $to_send, $total_cost, $collision_occurred);
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
        return ["cost" => $total_cost, "action_chain" => $to_send, "collision_occurred" => $collision_occurred];
    }

    function actPlayCard(int $card_type, int $card_id, #[JsonParam] $decisions)
    {
        $this->dump("card_type", $card_type);
        $this->dump("decisions", $decisions);
        $card = $this->playable_cards[$card_type];
        $this->dump("card played", $card);
        $player_id = $this->getActivePlayerId();

        $outcome = $this->processCardActions($card["actions"], $decisions);
        $this->dump("final card play outcome", $outcome);

        // Pay the total cost from all actions (pay() includes validation)
        if (!empty($outcome["cost"])) {
            $this->pay($player_id, $outcome["cost"]);
        }

        $this->cards->moveCard($card_id, "player_discard", $player_id);

        $this->notifyAllPlayers("cardPlayed", clienttranslate('${player_name} has played a card'), [
            "player_name" => self::getActivePlayerName(),
            "player_id" => $player_id,
            "moveChain" => $outcome["action_chain"],
            "cost" => $outcome["cost"],
        ]);

        $this->gamestate->nextState($outcome["collision_occurred"] ? "collisionOccurred" : "seaTurnDone");
    }

    function stResolveCollision()
    {
        $this->mytrace("stResolveCollision");
        #$this->gamestate->nextState("seaTurnDone");
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
        if ($direction != "no pivot") {
            $typed_action = PrimitiveCardPlayAction::from($direction);
            $outcome = $this->processCardActions([["action" => $typed_action]], []);
            $this->dump("final pivot outcome", $outcome);

            $player_id = $this->getActivePlayerId();
            
            // Pay the cost for pivot actions (pay() includes validation)
            if (!empty($outcome["cost"])) {
                $this->pay($player_id, $outcome["cost"]);
            }

            $this->notifyAllPlayers("cardPlayed", clienttranslate('${player_name} pivots'), [
                "player_name" => self::getActivePlayerName(),
                "player_id" => $player_id,
                "moveChain" => $outcome["action_chain"],
                "cost" => $outcome["cost"],
            ]);
            //TODO: check if player can fire due to interrupted maneuver ending in firing action
        }
        $this->gamestate->nextState("collisionResolved");
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
            throw new BgaUserException($this->_("Invalid card"));
        }
        
        $valid_locations = ["hand", "player_discard"];
        if (!in_array($card["location"], $valid_locations) || $card["location_arg"] != $player_id) {
            throw new BgaUserException($this->_("You can only scrap cards from your hand or discard pile"));
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
            "location_arg" => intval($card["location_arg"])
        ];
        
        // Notify players
        $this->notifyAllPlayers("cardScrapped", clienttranslate('${player_name} scrapped a card'), [
            "player_name" => self::getActivePlayerName(),
            "player_id" => intval($player_id),
            "card" => $card_for_notification,
            "original_location" => $original_location,
        ]);
        
        $this->gamestate->nextState("cardScrapped");
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

        throw new feException("Zombie mode not supported at this game state: " . $statename);
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
