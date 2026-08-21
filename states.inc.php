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
    define("STATE_END_GAME", 99);
}

// State machine is defined via State classes in modules/php/States/.
// State 1 is overridden here to go directly to islandPhaseSetup (state 10) instead of the default state 2.
$machinestates = [
    STATE_GAME_SETUP => \Bga\GameFramework\GameStateBuilder::gameSetup(STATE_ISLAND_PHASE_SETUP)->build(),
];
