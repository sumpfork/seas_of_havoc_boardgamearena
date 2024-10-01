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
 * material.inc.php
 *
 * SeasOfHavoc game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */


/*

Example:

$this->card_types = array(
    1 => array( "card_name" => ...,
                ...
              )
);

*/
$this->token_types = array(
  'skiff_slot_capitol' => array(
    'type' => 'skiff_slot',
    'name' => clienttranslate("Capitol"),
    'tooltip' => clienttranslate("Take the first player token and gain a resource of your choice.")
  )
);

$this->resource_types = array(
  'sail', 'cannonball', 'doubloon', 'skiff'
);
