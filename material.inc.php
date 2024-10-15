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
$this->resource_types = array(
  'sail',
  'cannonball',
  'doubloon',
  'skiff'
);

$this->starting_cards = array(
  array(
    'ship_name' => 'Xebec',
    'cost' => array('sail' => 1),
    'actions' => array(
      array('action' => 'forward'),
      array('action' => 'forward', 'cost' => array('sail' => 1))
    ),
    "image_id" => 0,
    "card_id" => 0,
    "count" => 2
  ),
  array(
    'ship_name' => 'Xebec',
    'cost' => array('sail' => 1),
    'actions' => array(
      array('action' => 'choice', array('action' => "left", 'action' => "right"))
    ),
    "image_id" => 1,
    "card_id" => 1,
    "count" => 2,
  ),
  array(
    'ship_name' => 'Xebec',
    'cost' => array('cannonball' => 1),
    'actions' => array(
      array('action' => 'fire', 'range' => 3, 'cost' => array('cannonball' => 1))
    ),
    "image_id" => 2,
    "card_id" => 2,
    "count" => 2,
  ),
  array(
    'ship_name' => 'Ship-of-the-Line',
    'cost' => array('cannonball' => 1),
    'actions' => array(
      array('action' => 'fire', 'range' => 3, 'cost' => array('cannonball' => 1))
    ),
    "image_id" => 3,
    "card_id" => 3,
    "count" => 2,
  ),
  array(
    'ship_name' => 'Ship-of-the-Line',
    'cost' => array('sail' => 1),
    'actions' => array(
      array('action' => 'choice', array('action' => "left", 'action' => "right"))
    ),
    "image_id" => 4,
    "card_id" => 4,
    "count" => 2,
  ),
  array(
    'ship_name' => 'Ship-of-the-Line',
    'cost' => array('sail' => 1, 'doubloon' => 1),
    'actions' => array(
      array('action' => 'choice', array('action' => "left", 'action' => 'forward', 'action' => "right"))
    ),
    "image_id" => 5,
    "card_id" => 5,
    "count" => 2,
  ),
);
