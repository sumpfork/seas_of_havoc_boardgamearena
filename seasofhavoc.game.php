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


require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');

use \Bga\GameFramework\Actions\Types\StringParam;

class SeasOfHavoc extends Table
{
    // for IDE (form material.inc.php)
    //private $resource_types;

    function __construct()
    {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels(array(
            //    "my_first_global_variable" => 10,
            //    "my_second_global_variable" => 11,
            //      ...
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...
        ));
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
    protected function setupNewGame($players, $options = array())
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode(',', $values);
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
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
        $this->gamestate->nextState();
    }

    function stMyGameSetup()
    {
        //incredibly, it's impossible to log anything in the official game setup, so this is a second setup state
        $this->mytrace('stMyGameSetup');
        $sql = "INSERT INTO resource (player_id, resource_key, resource_count) VALUES ";
        $base_resources = array_fill_keys($this->resource_types, 1);
        $base_resources['skiff'] = 3;

        $this->dump("base resources", $base_resources);
        $player_infos = $this->loadPlayersBasicInfos();

        $values = array();
        foreach ($player_infos as $playerid => $player) {
            $player_resources = $base_resources;
            $this->mytrace("player is " . $playerid);

            switch ($player['player_no']) {
                case 1:
                    break;
                case 2:
                    $player_resources['sail'] += 1;
                    break;
                case 3:
                    $player_resources['cannonball'] += 1;
                    break;
                case 4:
                    $player_resources['sail'] += 1;
                    $player_resources['cannonball'] += 1;
                    break;
                case 5:
                    $player_resources['cannonball'] += 1;
                    $player_resources['doubloon'] += 1;
                    break;
                default:
                    throw new Exception('Unknonwn player number' . $player['player_no']);
            }
            foreach ($player_resources as $resource_type => $resource_count) {
                $values[] = "('" . $playerid . "','$resource_type','" . $resource_count . "')";
            }
        }
        $sql .= implode(',', $values);
        self::DbQuery($sql);

        self::DbQuery("INSERT INTO islandslots (slot_key, occupying_player_id) VALUES ('capitol', null)");
        self::DbQuery("INSERT INTO islandslots (slot_key, occupying_player_id) VALUES ('bank', null)");
        self::DbQuery("INSERT INTO islandslots (slot_key, occupying_player_id) VALUES ('shipyard', null)");

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        $this->gamestate->nextState();
    }

    function stNextPlayerIslandPhase()
    {
        $this->mytrace("stNextPlayerIslandPhase");

        $resources = $this->getGameResourcesHierarchical();
        $this->dump("fetched resources:", $resources);

        #$total_resources = array_column($resources, "resource_count", "resource_key");
        #$this->dump("total resources:", $total_resources);

        $total_skiffs = array_sum(array_column(array_values($resources), "skiff"));
        $this->mytrace("total skiffs left: $total_skiffs");

        if (array_sum(array_column(array_values($resources), "skiff")) == 0) {
            $this->mytrace("next state, transition: islandPhaseDone");
            # $this->gamestate->nextState("islandPhaseDone");
        }

        // Go to next player
        $active_player = $this->activeNextPlayer();
        #$resources_by_player = $this->subindexArray($resources, "player_id");
        #$this->dump("resources_by_player: ", $resources_by_player);
        // while ($resources_by_player[$active_player]["skiff"] == 0) {
        //     $active_player = $this->activeNextPlayer();
        //     $resources_by_player = $this->subindexArray($resources, "player_id");
        // }

        $this->giveExtraTime($active_player);
        $this->gamestate->nextState("nextPlayer");
    }

    function stCardPurchases() {}

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();

        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        // TODO: Gather all information about current game situation (visible by player $current_player_id).

        $result['resources'] = $this->getGameResources();
        $result['islandslots'] = $this->getIslandSlots();

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
        // TODO: compute and return the game progression

        return 0;
    }

    /*
        getGameResources:
        
        Gather all relevant resources about current game situation (visible by the current player).
    */
    function getGameResources(int $player_id = null)
    {
        $sql = "
    		SELECT
    		    player_id, resource_key, resource_count
    		FROM resource
    	";
        if ($player_id != null) {
            $sql .= " WHERE player_id = $player_id";
        }
        return $this->getObjectListFromDB($sql);
    }

    function getGameResourcesHierarchical(int $player_id = null)
    {
        $resources = $this->getGameResources($player_id);
        $hierarchical_resources = array();
        foreach ($resources as $row) {
            if (!array_key_exists($row["player_id"], $hierarchical_resources)) {
                $hierarchical_resources[$row["player_id"]] = array();
            }
            $hierarchical_resources[$row["player_id"]][$row["resource_key"]] = intval($row["resource_count"]);
        }
        return $hierarchical_resources;
    }

    function subindexArray($arr_arr, $top_key)
    {
        print($top_key);
        $new_arr = array();
        foreach ($arr_arr as $arr) {
            unset($arr[$top_key]);
            $new_arr[$top_key] = $arr;
        }
    }

    function getIslandSlots()
    {
        $sql = "
    		SELECT
    		    slot_key, occupying_player_id
    		FROM islandslots
    	";

        return $this->getCollectionFromDB($sql);
    }

    function occupyIslandSlot(string $player_id, string $slot_name)
    {
        self::DbQuery("REPLACE INTO islandslots (slot_key, occupying_player_id) VALUES ('$slot_name', '$player_id')");
        $this->notifyAllPlayers(
            "skiffPlaced",
            clienttranslate('${player_name} placed a skiff'),
            array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => $player_id,
                'player_color' => $this->getPlayerColor($player_id),
                'slot_name' => $slot_name
            )
        );
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
        return $state['name'];
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
        return $player['player_color'];
    }

    function sum_array_by_key(array ...$arrays)
    {
        $out = array();

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
        $sql .= implode(',', $values);
        $this->mytrace("gain resources sql: $sql");

        self::DbQuery($sql);
        $this->notifyAllPlayers(
            "resourcesChanged",
            clienttranslate('${player_name} resources changed'),
            array(
                'player_name' => self::getActivePlayerName(),
                'resources' => $this->getGameResources($player_id)
            )
        );
    }

    function showResourceChoiceDialog(string $context)
    {
        $this->notifyPlayer(self::getActivePlayerId(), "showResourceChoiceDialog", "", ['context' => $context]);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in seasofhavoc.action.php)
    */

    function actPlaceSkiff(string $slotname)
    {
        $player_id = self::getActivePlayerId();
        $this->mytrace("placeSkiff: $player_id slotname: $slotname");
        $occupancies = $this->getIslandSlots();

        $this->dump("occupancies", $occupancies);

        if ($occupancies[$slotname]["occupying_player_id"] != null) {
            throw new BgaUserException($this->_("There is already a skiff on $slotname"));
        }
        switch ($slotname) {
            case 'capitol':
                //TODO: take first player marker
                $this->showResourceChoiceDialog($slotname);
                break;
            case 'bank':
                $this->showResourceChoiceDialog($slotname);
                break;
            case 'shipyard':
                $this->playerGainResources($player_id, ["sail" => 2, "cannonball" => 1, "skiff" => -1]);
                $this->occupyIslandSlot($player_id, $slotname);
                $this->gamestate->nextState("islandTurnDone");
                break;
            default:
                throw new BgaSystemException("bad skiff slot: $slotname");
                break;
        }
    }

    function actResourcePickedInDialog(string $resource, string $context)
    {
        $player_id = $this->getActivePlayerId();
        switch ($context) {
            case 'capitol':
                $this->playerGainResources($player_id, [$resource => 1, "skiff" => -1]);
                $this->occupyIslandSlot($player_id, $context);
                $this->gamestate->nextState("islandTurnDone");
                break;
            case 'bank':
                $this->playerGainResources($player_id, [$resource => 1]);
                $this->playerGainResources($player_id, ["doubloon" => 1, "skiff" => -1]);
                $this->occupyIslandSlot($player_id, $context);
                $this->gamestate->nextState("islandTurnDone");
                break;
            default:
                throw new BgaSystemException("bad context: $context");
        }
    }
    /*
    
    Example:

    function playCard( $card_id )
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'playCard' ); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card there 
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays ${card_name}' ), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ) );
          
    }
    
    */


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
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState("zombiePass");
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, '');

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
