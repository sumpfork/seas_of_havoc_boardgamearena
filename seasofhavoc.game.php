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

use Bga\GameFramework\Actions\Types\JsonParam;

class SeaBoard
{
    const WIDTH = 5;
    const HEIGHT = 5;

    const NO_HEADING = 0;
    const NORTH = 1;
    const EAST = 2;
    const SOUTH = 3;
    const WEST = 4;
    const NORTHEAST = 6;
    const NORTHWEST = 7;

    private array|null $contents;
    private string $sqlfunc;

    function __construct($dbquery)
    {
        $this->sqlfunc = $dbquery;
        $this->contents = null;
    }

    private function init()
    {
        $row = array_fill(0, self::WIDTH, []);
        $this->contents = array_fill(0, self::HEIGHT, $row);
    }

    public function getObjects(int $x, int $y)
    {
        assert($x >= 0 && $x < self::WIDTH);
        assert($y >= 0 && $y < self::HEIGHT);
        $this->syncFromDB();
        return $this->contents[$x][$y];
    }

    public function getAllObjectsFlat()
    {
        $this->syncFromDB();
        $flat_contents = [];
        foreach ($this->contents as $x => $row) {
            foreach ($row as $y => $contents) {
                foreach ($contents as $entry) {
                    $flat_contents[] = [
                        "x" => $x,
                        "y" => $y,
                        "type" => $entry["type"],
                        "arg" => $entry["arg"],
                        "heading" => $entry["heading"],
                    ];
                }
            }
        }
        return $flat_contents;
    }

    public function getObjectsOfTypes(int $x, int $y, array $types)
    {
        assert($x >= 0 && $x < self::WIDTH);
        assert($y >= 0 && $y < self::HEIGHT);
        $this->syncFromDB();
        $contents = $this->contents[$x][$y];
        return array_filter($contents, function ($k) use ($types) {
            in_array($k["type"], $types);
        });
    }

    public function findObject($type, $arg)
    {
        for ($x = 0; $x < self::WIDTH; $x++) {
            for ($y = 0; $y < self::HEIGHT; $y++) {
                $objects = $this->getObjectsOfTypes($x, $y, [$type]);
                #return array_find($objects, fn($o) => $o["arg"] == $arg);
                foreach ($objects as $o) {
                    if ($o["arg"] == $arg) {
                        return ["x" => $x, "y" => $y, "object" => $o];
                    }
                }
            }
        }
    }

    public function moveObjectForward(
        string $object_type,
        string $arg,
        array $collision_types
    ) {
        $object_info = $this->findObject($object_type, $arg);
        $new_x = $object_info["x"];
        $new_y = $object_info["y"];
        switch ($object_info["heading"]) {
            case self::NORTH:
                $new_y++;
                if ($new_y == self::HEIGHT) {
                    $new_y = 0;
                }
                break;
            case self::EAST:
                $new_x++;
                if ($new_x == self::WIDTH) {
                    $new_x = 0;
                }
                break;
            case self::WEST:
                $new_x--;
                if ($new_x == -1) {
                    $new_x = self::WIDTH - 1;
                }
                break;
            case self::WEST:
                $new_y--;
                if ($new_y == -1) {
                    $new_y = self::HEIGHT - 1;
                }
                break;
        }
        $collided = $this->getObjectsOfTypes($new_x, $new_y, $collision_types);
        if (count($collided) == 0) {
            $this->removeObject(
                $object_info["x"],
                $object_info["y"],
                $object_info["type"],
                $object_info["arg"]
            );
            $this->placeObject($new_x, $new_y, $object_info["object"]);
            return [
                "colliders" => $collided,
                "new_x" => $new_x,
                "new_y" => $new_y,
            ];
        }
        return [
            "colliders" => $collided,
            "new_x" => $object_info["x"],
            "new_y" => object_info["y"],
        ];
    }

    public function placeObject(int $x, int $y, array $object)
    {
        assert($x >= 0 && $x < self::WIDTH);
        assert($y >= 0 && $y < self::HEIGHT);
        $this->syncFromDB();
        $this->contents[$x][$y][] = $object;
        $this->syncToDB();
    }

    public function removeObject(
        int $x,
        int $y,
        string $object_type,
        string $arg
    ) {
        $this->contents[$x][$y] = array_filter(
            $this->contents[$x][$y],
            fn($o) => $o["type"] != $object_type || $o["arg"] != $arg
        );
        $this->syncToDB();
    }

    private function syncObjectToDB(int $x, int $y, array $object)
    {
        $sql =
            "INSERT INTO sea (x, y, type, arg, heading) VALUES ('" .
            $x .
            "','" .
            $y .
            "','" .
            $object["type"] .
            "','" .
            $object["arg"] .
            "','" .
            $object["heading"] .
            "')";
        call_user_func($this->sqlfunc, $sql);
    }

    public function syncToDB()
    {
        call_user_func($this->sqlfunc, "DELETE FROM sea");

        $sql = "INSERT INTO sea (x, y, type, arg, heading) VALUES ";
        $values = [];
        foreach ($this->getAllObjectsFlat() as $entry) {
            $values[] =
                "('" .
                $entry["x"] .
                "','" .
                $entry["y"] .
                "','" .
                $entry["type"] .
                "','" .
                $entry["arg"] .
                "','" .
                $entry["heading"] .
                "')";
        }
        $sql .= implode(",", $values);
        call_user_func($this->sqlfunc, $sql);
    }

    public function syncFromDB()
    {
        if ($this->contents === null) {
            $sql = "select x, y, type, arg, heading from sea";
            $result = call_user_func($this->sqlfunc, $sql);
            $this->init();
            foreach ($result as $row) {
                $this->placeObject(intval($row["x"]), intval($row["y"]), [
                    "type" => $row["type"],
                    "arg" => $row["arg"],
                    "heading" => $row["heading"],
                ]);
            }
        }
    }

    function dump($f)
    {
        $f("seaboard", $this->contents);
    }
}

class SeasOfHavoc extends Table
{
    // for IDE (form material.inc.php)
    //private $resource_types;

    private SeaBoard $seaboard;
    //private array $starting_cards;
    private array $all_cards;
    private $cards;

    function __construct()
    {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

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
        //$this->cards->autoreshuffle = true;
        $this->cards->autoreshuffle_custom = [
            "player_deck" => "player_discard",
        ];

        $this->seaboard = new SeaBoard("SeasOfHavoc::DBQuery");

        $starting_cards = array_combine(
            array_column($this->starting_cards, "card_type"),
            array_values($this->starting_cards)
        );
        assert(count($starting_cards) == count($this->starting_cards));
        $this->starting_cards = $starting_cards;

        $market_cards = array_combine(
            array_column($this->market_cards, "card_type"),
            array_values($this->market_cards)
        );
        assert(count($market_cards) == count($this->market_cards));
        $this->market_cards = $market_cards;

        $this->playable_cards = $this->starting_cards + $this->market_cards;
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

    function stMyGameSetup()
    {
        //throw new BgaSystemException("mysetup start");

        //incredibly, it's impossible to log anything in the official game setup, so this is a second setup state
        $this->mytrace("stMyGameSetup");
        $sql =
            "INSERT INTO resource (player_id, resource_key, resource_count) VALUES ";
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
                    throw new Exception(
                        "Unknonwn player number" . $player["player_no"]
                    );
            }
            foreach ($player_resources as $resource_type => $resource_count) {
                $values[] =
                    "('" .
                    $playerid .
                    "','$resource_type','" .
                    $resource_count .
                    "')";
            }
        }
        $sql .= implode(",", $values);
        self::DbQuery($sql);

        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('capitol', 'n1', null)"
        );
        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('bank', 'n1', null)"
        );
        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('shipyard', 'n1', null)"
        );
        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('blacksmith', 'n1', null)"
        );
        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('market', 'n1', null)"
        );
        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('market', 'n2', null)"
        );
        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('market', 'n3', null)"
        );
        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('market', 'n4', null)"
        );
        self::DbQuery(
            "INSERT INTO islandslots (slot_key, number, occupying_player_id) VALUES ('market', 'n5', null)"
        );

        $player_infos = $this->getPlayerInfo();

        foreach ($player_infos as $playerid => $player) {
            $this->seaboard->placeObject(
                rand(0, SeaBoard::WIDTH - 1),
                rand(0, SeaBoard::HEIGHT - 1),
                [
                    "type" => "player_ship",
                    "arg" => $playerid,
                    "heading" => SeaBoard::NORTH,
                ]
            );
            $player_starting_cards = array_filter(
                $this->starting_cards,
                function ($v) use ($player) {
                    return $v["ship_name"] == $player["player_ship"];
                }
            );
            $start_deck = [];
            foreach ($player_starting_cards as $starting_card) {
                $start_deck[] = [
                    "type" => $starting_card["card_type"],
                    "type_arg" => 0,
                    "nbr" => $starting_card["count"],
                ];
            }
            $this->cards->createCards(
                $start_deck,
                $this->playerDeckName($playerid)
            );
            $this->cards->shuffle($this->playerDeckName($playerid));
            $this->cards->pickCards(
                4,
                $this->playerDeckName($playerid),
                $playerid
            );
        }

        $market_deck = [];
        foreach ($this->market_cards as $market_card) {
            $market_deck[] = [
                "type" => $market_card["card_type"],
                "type_arg" => 0,
                "nbr" => $market_card["count"],
            ];
        }
        $this->cards->createCards($market_deck, "market_deck");
        $this->cards->shuffle("market_deck");
        for ($i = 1; $i < 6; $i++) {
            $this->cards->pickCardForLocation("market_deck", "market", $i);
        }
        //$this->cards->shuffle("player_deck");
        // foreach ($player_infos as $playerid => $player) {
        //     //$this->notifyPlayer($playerid, 'newHand', '', array ('cards' => $handcards ));
        // }

        // Activate first player (which is in general a good idea :) )
        // $this->activeNextPlayer();
        //throw new BgaSystemException("mysetup end");

        $this->gamestate->nextState();
    }

    function stNextPlayerIslandPhase()
    {
        $this->mytrace("stNextPlayerIslandPhase");

        $resources = $this->getGameResourcesHierarchical();
        $this->dump("fetched resources:", $resources);

        #$total_resources = array_column($resources, "resource_count", "resource_key");
        #$this->dump("total resources:", $total_resources);

        $total_skiffs = array_sum(
            array_column(array_values($resources), "skiff")
        );
        $this->mytrace("total skiffs left: $total_skiffs");

        if (array_sum(array_column(array_values($resources), "skiff")) == 0) {
            $this->mytrace("next state, transition: islandPhaseDone");
            $this->gamestate->nextState("islandPhaseDone");
            return;
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

    function stCardPurchases()
    {
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stNextPlayerSeaPhase()
    {
        $active_player = $this->activeNextPlayer();
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
        //$result['starting_cards'] = $this->starting_cards;
        //$result['market_cards'] = $this->market_cards;
        $result["playable_cards"] = $this->playable_cards;
        $result["market"] = $this->cards->getCardsInLocation("market");
        $result["hand"] = $this->cards->getPlayerHand($current_player_id);
        //$result['allcards'] = $this->cards->countCardsInLocations();
        $result["playerinfo"] = $this->getPlayerInfo();
        $result["seaboard"] = $this->seaboard->getAllObjectsFlat();
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
        $game_resources = $this->getObjectListFromDB($sql);
        return $game_resources;
    }

    function getGameResourcesHierarchical(int $player_id = null)
    {
        $resources = $this->getGameResources($player_id);
        $hierarchical_resources = [];
        foreach ($resources as $row) {
            if (!array_key_exists($row["player_id"], $hierarchical_resources)) {
                $hierarchical_resources[$row["player_id"]] = [];
            }
            $hierarchical_resources[$row["player_id"]][
                $row["resource_key"]
            ] = intval($row["resource_count"]);
        }
        return $hierarchical_resources;
    }

    function getPlayerInfo(int $player_id = null)
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
            $indexed_slots[$slot["slot_key"]][$slot["number"]] =
                $slot["occupying_player_id"];
        }
        return $indexed_slots;
    }

    function occupyIslandSlot(
        string $player_id,
        string $slot_name,
        string $number
    ) {
        self::DbQuery(
            "REPLACE INTO islandslots (slot_key, number, occupying_player_id) VALUES ('$slot_name', '$number', '$player_id')"
        );
        $this->notifyAllPlayers(
            "skiffPlaced",
            clienttranslate('${player_name} placed a skiff'),
            [
                "player_name" => self::getActivePlayerName(),
                "player_id" => $player_id,
                "player_color" => $this->getPlayerColor($player_id),
                "slot_name" => $slot_name,
                "slot_number" => $number,
            ]
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
        return array_reduce(
            $result,
            fn($carry, $value): bool => !$carry ? false : $value > 0,
            true
        );
    }
    function pay($player_id, $cost)
    {
        assert($this->canPayFor($cost, $this->getGameResources($player_id)));
        $cost = $this->makeCostNegative($cost);
        $this->playerGainResources($player_id, $cost);
    }
    function playerGainResources($player_id, $resources)
    {
        $this->mytrace("playerGainResources");
        $this->dump("incoming resources", $resources);
        $current_resources = $this->getGameResourcesHierarchical($player_id)[
            $player_id
        ];
        $this->dump("current resources", $current_resources);

        $summed_resources = $this->sum_array_by_key(
            $resources,
            $current_resources
        );
        $this->dump("summed resources", $summed_resources);

        $sql =
            "REPLACE INTO resource (player_id, resource_key, resource_count) VALUES ";
        foreach ($summed_resources as $resource_type => $resource_count) {
            $values[] =
                "('" .
                $player_id .
                "','$resource_type','" .
                $resource_count .
                "')";
        }
        $sql .= implode(",", $values);
        $this->mytrace("gain resources sql: $sql");

        self::DbQuery($sql);
        $this->notifyAllPlayers(
            "resourcesChanged",
            clienttranslate("resources changed"),
            [
                "resources" => $this->getGameResources(),
            ]
        );
    }

    function showResourceChoiceDialog(string $context, string $context_number)
    {
        $this->notifyPlayer(
            self::getActivePlayerId(),
            "showResourceChoiceDialog",
            "",
            ["context" => $context, "context_number" => $context_number]
        );
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
        $this->mytrace(
            "placeSkiff: $player_id slotname: $slotname number: $number"
        );
        $occupancies = $this->getIslandSlots();

        $this->dump("occupancies", $occupancies);
        $this->dump("slotnames", $occupancies[$slotname]);

        if ($occupancies[$slotname][$number] != null) {
            throw new BgaUserException(
                $this->_("There is already a skiff on $slotname")
            );
            return;
        }
        switch ($slotname) {
            case "capitol":
                //TODO: take first player marker
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

    function actResourcePickedInDialog(
        string $resource,
        string $context,
        string $number
    ) {
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
            default:
                throw new BgaSystemException("bad context: $context");
        }
    }

    function actCompletePurchases(#[JsonParam] array $cards_purchased)
    {
        $player_id = $this->getCurrentPlayerId();
        $this->dump("cards_purchased", $cards_purchased);
        foreach ($cards_purchased as $card_id) {
            $this->dump("market cards", $this->market_cards);
            $card = $this->cards->getCard($card_id);
            $this->dump("card", $card);
            $market_card = $this->market_cards[$card["type"]];
            $this->pay($player_id, $market_card["cost"]);
            $this->cards->moveCard($card_id, "hand", $player_id);
        }
        $this->gamestate->setPlayerNonMultiactive(
            $player_id,
            "cardPurchasesDone"
        );
    }

    function actPlayCard(int $card_type, #[JsonParam] $decisions)
    {
        $this->dump("card_type", $card_type);
        $this->dump("decisions", $decisions);
        $card_spec = $this->cards->getCard($card_type);
        $this->dump("card spec played", $card_spec);
        $card = $this->playable_cards[$card_spec["type"]];
        $this->dump("card played", $card);
        $choice_count = 0;
        $player_id = $this->getActivePlayerId();

        foreach ($card["actions"] as $action) {
            switch ($action["action"]) {
                case "forward":
                    $outcome = $this->seaboard->moveObjectForward(
                        "player_ship",
                        $player_id,
                        ["rock", "player_ship"]
                    );
                    if (count($outcome["colliders"]) == 0) {
                        $this->notifyAllPlayers(
                            "shipMove",
                            clienttranslate('${player_name} moves forward'),
                            [
                                "player_id" => $player_id,
                                "new_x" => $outcome["new_x"],
                                "new_y" => $outcome["new_y"],
                            ]
                        );
                        break;
                    }
            }
        }
        $this->gamestate->nextState();
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

        throw new feException(
            "Zombie mode not supported at this game state: " . $statename
        );
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
