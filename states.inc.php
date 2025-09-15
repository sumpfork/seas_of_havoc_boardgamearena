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
 * states.inc.php
 *
 * SeasOfHavoc game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!
// define contants for state ids
if (!defined("STATE_END_GAME")) {
    // ensure this block is only invoked once, since it is included multiple times
    define("STATE_GAME_SETUP", 1);
    define("STATE_MY_SETUP", 2);
    define("STATE_ISLAND_TURN", 3);
    define("STATE_NEXT_PLAYER_ISLAND_PHASE", 4);
    define("STATE_CARD_PURCHASES", 5);
    define("STATE_SEA_PHASE_SETUP", 6);
    define("STATE_SEA_TURN", 7);
    define("STATE_NEXT_PLAYER_SEA_PHASE", 8);
    define("STATE_RESOLVE_COLLISION", 9);
    define("STATE_ISLAND_PHASE_SETUP", 10);
    define("STATE_SCRAP_CARD", 11);
    define("STATE_END_GAME", 99);
}

$machinestates = [
    // The initial state. Please do not modify.
    STATE_GAME_SETUP => [
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => STATE_MY_SETUP],
    ],
    // exists only for debugging initial php setup that doesn't produce log messages
    // unless some player states have been active
    // 2 => array(
    //      "name" => "DummyStart",
    //      "description" => "only exists because debugging doesn't work in start states",
    //      "type" => "activeplayer",
    //      "possibleactions" => array("actExitDummyStart"),
    //      "transitions" => array("" => 3)
    // ),
    STATE_MY_SETUP => [
        "name" => "mySetup",
        "description" => "f",
        "type" => "game",
        "action" => "stMyGameSetup",
        "transitions" => ["" => STATE_ISLAND_PHASE_SETUP],
    ],
    STATE_ISLAND_PHASE_SETUP => [
        "name" => "islandPhaseSetup",
        "description" => clienttranslate("Starting Island Phase"),
        "type" => "game",
        "action" => "stIslandPhaseSetup",
        "transitions" => ["" => STATE_ISLAND_TURN],
    ],
    STATE_ISLAND_TURN => [
        "name" => "islandTurn",
        "description" => clienttranslate('${actplayer} must place a skiff'),
        "descriptionmyturn" => clienttranslate('${you} must place a skiff'),
        "type" => "activeplayer",
        "possibleactions" => ["actPlaceSkiff", "actResourcePickedInDialog"],
        "transitions" => ["islandTurnDone" => STATE_NEXT_PLAYER_ISLAND_PHASE, "scrapCard" => STATE_SCRAP_CARD],
    ],

    STATE_NEXT_PLAYER_ISLAND_PHASE => [
        "name" => "nextPlayerIslandPhase",
        "description" => "",
        "type" => "game",
        "action" => "stNextPlayerIslandPhase",
        "transitions" => ["islandPhaseDone" => STATE_CARD_PURCHASES, "nextPlayer" => STATE_ISLAND_TURN],
    ],

    STATE_CARD_PURCHASES => [
        "name" => "cardPurchases",
        "description" => clienttranslate("Players may purchase cards"),
        "descriptionmyturn" => clienttranslate("You may purchase cards"),
        "type" => "multipleactiveplayer",
        "action" => "stCardPurchases",
        "possibleactions" => ["actCompletePurchases"],
        "transitions" => ["cardPurchasesDone" => STATE_SEA_PHASE_SETUP],
    ],
    STATE_SEA_PHASE_SETUP => [
        "name" => "seaPhaseSetup",
        "description" => clienttranslate("Starting Sea Phase"),
        "type" => "game",
        "action" => "stSeaPhaseSetup",
        "transitions" => ["" => STATE_SEA_TURN],
    ],
    STATE_SEA_TURN => [
        "name" => "seaTurn",
        "description" => clienttranslate('${actplayer} must play a card'),
        "descriptionmyturn" => clienttranslate('${you} must play a card'),
        "type" => "activeplayer",
        "possibleactions" => ["actPlayCard"],
        "transitions" => ["seaTurnDone" => STATE_NEXT_PLAYER_SEA_PHASE, "collisionOccurred" => STATE_RESOLVE_COLLISION],
    ],

    STATE_NEXT_PLAYER_SEA_PHASE => [
        "name" => "nextPlayerSeaPhase",
        "description" => "",
        "type" => "game",
        "action" => "stNextPlayerSeaPhase",
        "transitions" => ["seaPhaseDone" => STATE_ISLAND_PHASE_SETUP, "nextPlayer" => STATE_SEA_TURN],
    ],

    STATE_RESOLVE_COLLISION => [
        "name" => "resolveCollision",
        "description" => clienttranslate('${actplayer} must resolve a collision'),
        "descriptionmyturn" => clienttranslate('${you} must resolve a collision'),
        "type" => "activeplayer",
        "action" => "stResolveCollision",
        "args" => "argResolveCollision",
        "possibleactions" => ["actResolveCollision", "actPivotPickedInDialog"],
        "transitions" => ["collisionResolved" => STATE_NEXT_PLAYER_SEA_PHASE],
    ],
    STATE_SCRAP_CARD => [
        "name" => "scrapCard",
        "description" => clienttranslate('${actplayer} must scrap a card'),
        "descriptionmyturn" => clienttranslate('${you} must scrap a card from your hand or discard pile'),
        "type" => "activeplayer",
        "args" => "argScrapCard",
        "possibleactions" => ["actScrapCard"],
        "transitions" => ["cardScrapped" => STATE_NEXT_PLAYER_ISLAND_PHASE],
    ],
    // Final state.
    // Please do not modify (and do not overload action/args methods).
    STATE_END_GAME => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd",
    ],
];
