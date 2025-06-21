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

enum Heading: int
{
    case NO_HEADING = 0;
    case NORTH = 1;
    case EAST = 2;
    case SOUTH = 3;
    case WEST = 4;

    public function toString(): string
    {
        return match ($this) {
            Heading::NO_HEADING => "NO_HEADING",
            Heading::NORTH => "NORTH",
            Heading::EAST => "EAST",
            Heading::SOUTH => "SOUTH",
            Heading::WEST => "WEST",
        };
    }
}

enum Turn: int
{
    case LEFT = 0;
    case RIGHT = 1;
    case AROUND = 2;
    case NOTURN = 4;

    public function toString(): string
    {
        return match ($this) {
            Turn::LEFT => "LEFT",
            Turn::RIGHT => "RIGHT",
            Turn::AROUND => "AROUND",
            Turn::NOTURN => "NOTURN",
        };
    }
}

class SeaBoard
{
    const WIDTH = 6;
    const HEIGHT = 6;

    private array|null $contents;
    private string $sqlfunc;
    private $bga;

    function __construct($dbquery, $bga)
    {
        $this->sqlfunc = $dbquery;
        $this->contents = null;
        $this->bga = $bga;
    }

    private function init()
    {
        $row = array_fill(0, self::WIDTH, []);
        $this->contents = array_fill(0, self::HEIGHT, $row);
    }

    // public static string headingAsString(Heading $heading) {

    // }
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
        return array_filter($contents, fn($k) => in_array($k["type"], $types));
    }

    public function findObject($type, $arg)
    {
        $this->syncFromDB();
        for ($x = 0; $x < self::WIDTH; $x++) {
            for ($y = 0; $y < self::HEIGHT; $y++) {
                $objects = $this->getObjectsOfTypes($x, $y, [$type]);
                foreach ($objects as $o) {
                    if ($o["arg"] == $arg) {
                        return ["x" => $x, "y" => $y, "object" => $o];
                    }
                }
            }
        }
    }

    private function computeForwardMovement(int $x, int $y, Heading $heading)
    {
        $new_x = $x;
        $new_y = $y;
        $teleport_at = null;
        $teleport_to = null;

        switch ($heading) {
            case Heading::NORTH:
                $new_y--;
                if ($new_y == -1) {
                    $teleport_at = ["x" => $new_x, "y" => $new_y];
                    $teleport_to = ["x" => $new_x, "y" => self::HEIGHT];
                    $new_y = self::HEIGHT - 1;
                }
                break;
            case Heading::EAST:
                $new_x++;
                if ($new_x == self::WIDTH) {
                    $teleport_at = ["x" => $new_x, "y" => $new_y];
                    $teleport_to = ["x" => -1, "y" => $new_y];
                    $new_x = 0;
                }
                break;
            case Heading::WEST:
                $new_x--;
                if ($new_x == -1) {
                    $teleport_at = ["x" => $new_x, "y" => $new_y];
                    $teleport_to = ["x" => self::WIDTH, "y" => $new_y];
                    $new_x = self::WIDTH - 1;
                }
                break;
            case Heading::SOUTH:
                $new_y++;
                if ($new_y == self::HEIGHT) {
                    $teleport_at = ["x" => $new_x, "y" => $new_y];
                    $teleport_to = ["x" => $new_x, "y" => -1];
                    $new_y = 0;
                }
                break;
        }
        return ["new_x" => $new_x, "new_y" => $new_y, "teleport_at" => $teleport_at, "teleport_to" => $teleport_to];
    }

    public function moveObjectForward(string $object_type, string $arg, array $collision_types)
    {
        $this->syncFromDB();
        $object_info = $this->findObject($object_type, $arg);
        $object = $object_info["object"];
        $this->bga->dump("object being moved forward", $object_info);
        $movement_result = $this->computeForwardMovement($object_info["x"], $object_info["y"], $object["heading"]);

        $collided = $this->getObjectsOfTypes($movement_result["new_x"], $movement_result["new_y"], $collision_types);
        if (count($collided) > 0) {
            return [
                "type" => "collision",
                "colliders" => $collided,
                "collision_x" => $movement_result["new_x"],
                "collision_y" => $movement_result["new_y"],
            ];
        }
        $old_x = $object_info["x"];
        $old_y = $object_info["y"];

        $this->removeObject($object_info["x"], $object_info["y"], $object["type"], $object["arg"]);
        $this->placeObject($movement_result["new_x"], $movement_result["new_y"], $object);

        return [
            "type" => "move",
            "colliders" => $collided,
            "old_x" => $old_x,
            "old_y" => $old_y,
            "new_x" => $movement_result["new_x"],
            "new_y" => $movement_result["new_y"],
            "teleport_at" => $movement_result["teleport_at"],
            "teleport_to" => $movement_result["teleport_to"],
        ];
    }

    public static function turnHeading(Heading $heading, Turn $direction)
    {
        switch ($direction) {
            case Turn::LEFT:
                $heading = Heading::from($heading->value - 1);
                if ($heading == Heading::NO_HEADING) {
                    $heading = Heading::WEST;
                }
                break;
            case Turn::RIGHT:
            # fallthrough
            case Turn::AROUND:
                $new_value = $heading->value + ($direction == Turn::RIGHT ? 1 : 2);
                if ($new_value > Heading::WEST->value) {
                    $new_value -= 4;
                }
                $heading = Heading::from($new_value);
                if ($heading > Heading::WEST) {
                    $heading = Heading::from($heading->value - 4);
                }
                break;
        }
        return $heading;
    }

    public function turnObject(string $object_type, string $arg, Turn $direction)
    {
        $this->syncFromDB();
        $object_info = $this->findObject($object_type, $arg);
        $object = $object_info["object"];
        $new_heading = $this->turnHeading($object["heading"], $direction);
        $old_heading = $object["heading"];

        $this->removeObject($object_info["x"], $object_info["y"], $object["type"], $object["arg"]);
        $this->bga->trace(
            "changing heading from " .
                $object["heading"]->toString() .
                " to " .
                $new_heading->toString() .
                " due to " .
                $direction->toString() .
                " turn",
        );
        $object["heading"] = $new_heading;
        $this->placeObject($object_info["x"], $object_info["y"], $object);

        return ["type" => "turn", "old_heading" => $old_heading, "new_heading" => $new_heading];
    }

    public function resolveCannonFire(string $player_id, Turn $direction, int $distance, array $collision_types)
    {
        $this->syncFromDB();
        $object_info = $this->findObject("player_ship", $player_id);
        $ship_heading = $object_info["object"]["heading"];
        $fire_heading = $this->turnHeading($ship_heading, $direction);
        $x = $object_info["x"];
        $y = $object_info["y"];
        $this->bga->trace(
            "resolving cannon fire from " .
                $x .
                " " .
                $y .
                "ship heading" .
                $ship_heading->toString() .
                " fire direction " .
                $direction->toString() .
                " fire heading " .
                $fire_heading->toString(),
        );
        for ($d = 1; $d <= $distance; $d++) {
            $movement_result = $this->computeForwardMovement($x, $y, $fire_heading);
            $x = $movement_result["new_x"];
            $y = $movement_result["new_y"];
            $this->bga->trace("checking " . $x . " " . $y . " while firing");
            $collided = $this->getObjectsOfTypes($x, $y, $collision_types);
            $this->bga->dump("collided", $collided);
            if (count($collided)) {
                return [
                    "type" => "fire_hit",
                    "hit_x" => $x,
                    "hit_y" => $y,
                    "hit_objects" => $collided,
                    "fire_heading" => $fire_heading,
                ];
            }
        }
        return ["type" => "fire_miss"];
    }

    public function placeObject(int $x, int $y, array $object)
    {
        assert($x >= 0 && $x < self::WIDTH);
        assert($y >= 0 && $y < self::HEIGHT);
        $this->syncFromDB();
        $this->contents[$x][$y][] = $object;
        $this->syncToDB();
    }

    public function removeObject(int $x, int $y, string $object_type, string $arg)
    {
        $this->contents[$x][$y] = array_filter(
            $this->contents[$x][$y],
            fn($o) => $o["type"] != $object_type || $o["arg"] != $arg,
        );
        $this->syncToDB();
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
                $entry["heading"]->value .
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
                    "heading" => Heading::from($row["heading"]),
                ]);
            }
        }
    }

    function dump()
    {
        $this->bga->dump("seaboard", $this->contents);
    }
}

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

        foreach (["first_player_token", "green_flag", "tan_flag", "blue_flag", "red_flag"] as $token) {
            self::DbQuery("INSERT INTO unique_tokens (player_id, token_key) VALUES (NULL, '$token')");
        }

        $this->clearIslandSlots();

        $player_infos = $this->getPlayerInfo();

        foreach ($player_infos as $playerid => $player) {
            $this->seaboard->placeObject(rand(0, SeaBoard::WIDTH - 1), rand(0, SeaBoard::HEIGHT - 1), [
                "type" => "player_ship",
                "arg" => $playerid,
                "heading" => Heading::NORTH,
            ]);
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
            $this->cards->pickCards(4, $this->playerDeckName($playerid), $playerid);
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
        for ($i = 1; $i < 6; $i++) {
            $this->cards->pickCardForLocation("market_deck", "market", $i);
        }

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

        // Activate first player (which is in general a good idea :) )
        // $this->activeNextPlayer();
        //throw new BgaSystemException("mysetup end");

        $this->gamestate->nextState();
    }

    function stIslandPhaseSetup()
    {
        $this->mytrace("stIslandPhaseSetup");
        $player_infos = $this->getPlayerInfo();

        foreach ($player_infos as $playerid => $player) {
            $this->cards->pickCards(4, $this->playerDeckName($playerid), $playerid);
        }
        $this->gamestate->nextState();
    }

    function stNextPlayerIslandPhase()
    {
        $this->mytrace("stNextPlayerIslandPhase");

        $resources = $this->getGameResourcesHierarchical();
        $this->dump("fetched resources:", $resources);

        $total_skiffs = array_sum(array_column(array_values($resources), "skiff"));
        $this->mytrace("total skiffs left: $total_skiffs");

        if (array_sum(array_column(array_values($resources), "skiff")) == 0) {
            $this->mytrace("next state, transition: islandPhaseDone");
            $this->gamestate->nextState("islandPhaseDone");
            return;
        }

        // Go to next player
        $active_player = $this->activeNextPlayer();

        $this->giveExtraTime($active_player);
        $this->gamestate->nextState("nextPlayer");
    }

    function stCardPurchases()
    {
        $this->gamestate->setAllPlayersMultiactive();
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
        return array_reduce($result, fn($carry, $value): bool => !$carry ? false : $value > 0, true);
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

    function drawCard(string $player_id){
        $this->mytrace("drawCard");
        $card = $this->cards->pickCard($this->playerDeckName($player_id), $player_id);
        $this->notifyPlayer($player_id, "cardDrawn", clienttranslate('You drew a card'), [
            "player_id" => $player_id,
            "card" => $card,
        ]);
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
                $this->acquireToken($player_id, $slotname);
                $this->drawCard($player_id);
                break;
            case "red_flag":
                $this->acquireToken($player_id, $slotname);
                break;
            case "blue_flag":
                $this->acquireToken($player_id, $slotname);
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
        $this->gamestate->setPlayerNonMultiactive($player_id, "cardPurchasesDone");
        if (count($this->gamestate->getActivePlayerList()) == 0) {
            $this->clearIslandSlots();
        }
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

            $this->notifyAllPlayers("cardPlayed", clienttranslate('${player_name} pivots'), [
                "player_name" => self::getActivePlayerName(),
                "player_id" => $this->getActivePlayerId(),
                "moveChain" => $outcome["action_chain"],
                "cost" => $outcome["cost"],
            ]);
            //TODO: check if player can fire due to interrupted maneuver ending in firing action
        }
        $this->gamestate->nextState("collisionResolved");
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
