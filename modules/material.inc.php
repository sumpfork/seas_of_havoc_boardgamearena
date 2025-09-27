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

$this->card_types = [
    1 => [ "card_name" => ...,
                ...
              ]
];

*/
$this->resource_types = ["sail", "cannonball", "doubloon", "skiff"];

$this->token_names = [
    "first_player_token" => _("First Player Token"),
    "green_flag" => _("Green Purser's Flag"),
    "tan_flag" => _("Tan Flag"),
    "red_flag" => _("Red Flag"),
    "blue_flag" => _("Blue Flag"),
    "yellow_flag" => _("Yellow Flag"),
];

$this->non_playable_cards = [
    "card_back" => [
        "image_id" => 0,
    ],
    "pirate_queen" => ["image_id" => 1, "category" => "captain"],
    "rebel" => ["image_id" => 2, "category" => "captain"],
    "admiral" => ["image_id" => 3, "category" => "captain"],
    "merchant" => ["image_id" => 4, "category" => "captain"],
    "corsair" => ["image_id" => 5, "category" => "captain"],
    "treasure_seeker" => ["image_id" => 6, "category" => "captain"],
    "player_aid_front" => ["image_id" => 7, "category" => "player_aid"],
    "player_aid_back" => ["image_id" => 8, "category" => "player_aid"],
    "war_junk_rockets" => [
        "image_id" => 9,
        "category" => "ship_upgrade",
        "ship_name" => "War Junk",
        "cost" => ["cannonball" => 2, "doubloon" => 1],
    ],
    "war_junk_bulwark" => [
        "image_id" => 10,
        "category" => "ship_upgrade",
        "ship_name" => "War Junk",
        "cost" => ["sail" => 1, "doubloon" => 1],
    ],
    "xebec_lateen_rigging" => [
        "image_id" => 13,
        "category" => "ship_upgrade",
        "ship_name" => "Xebec",
        "cost" => ["sail" => 2],
        "infamy" => 3,
    ],
    "xebec_swift_hull" => [
        "image_id" => 14,
        "category" => "ship_upgrade",
        "ship_name" => "Xebec",
        "cost" => ["sail" => 1, "doubloon" => 1],
        "infamy" => 3,
    ],
];

$this->playable_cards = [
    [
        "cost" => [],
        "actions" => [["action" => "scrap_self"]],
        "image_id" => 70,
        "category" => "damage",
    ],
    [
        "ship_name" => "Xebec",
        "cost" => ["sail" => 1],
        "actions" => [["action" => "forward"], ["action" => "forward", "cost" => ["sail" => 1]]],
        "image_id" => 0,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Xebec",
        "cost" => ["sail" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "right"]],
            ],
        ],
        "image_id" => 1,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Xebec",
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 2,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Ship-of-the-Line",
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 3,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Ship-of-the-Line",
        "cost" => ["sail" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "right"]],
            ],
        ],
        "image_id" => 4,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Ship-of-the-Line",
        "cost" => ["sail" => 1],
        "actions" => [["action" => "forward"]],
        "image_id" => 5,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Sloop-of-War",
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]],
            ],
        ],
        "image_id" => 13,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 18,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["cannonball" => 2],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [
                    [
                        "action" => "fire",
                        "range" => 3,
                        "cost" => ["cannonball" => 1],
                    ],
                    [
                        "action" => "2 x fire",
                        "range" => 2,
                        "cost" => ["cannonball" => 2],
                    ],
                ],
            ],
        ],
        "image_id" => 19,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["cannonball" => 3],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [
                    [
                        "action" => "fire",
                        "range" => 3,
                        "cost" => ["cannonball" => 1],
                    ],
                    [
                        "action" => "2 x fire",
                        "range" => 2,
                        "cost" => ["cannonball" => 2],
                    ],
                    [
                        "action" => "3 x fire",
                        "range" => 1,
                        "cost" => ["cannonball" => 3],
                    ],
                ],
            ],
        ],
        "image_id" => 20,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]],
            ],
        ],
        "image_id" => 21,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 2, "doubloon" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [
                    ["action" => "left"],
                    [
                        "action" => "sequence",
                        "actions" => [
                            ["action" => "forward"],
                            [
                                "action" => "choice",
                                "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]],
                                "cost" => ["sail" => 1],
                            ],
                        ],
                        "name" => "forward",
                    ],
                    ["action" => "right"],
                ],
            ],
        ],
        "image_id" => 22,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1],
        "actions" => [["action" => "forward"], ["action" => "forward", "cost" => ["sail" => 1]]],
        "image_id" => 23,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            [
                "actions" => [
                    ["action" => "forward"],
                    [
                        "action" => "choice",
                        "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]],
                        "cost" => ["sail" => 1],
                    ],
                ],
                "name" => "forward",
            ],
        ],
        "image_id" => 24,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 1],
        "actions" => [["action" => "forward"], ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 25,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 2, "cannonball" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]], "cost" => ["sail" => 1]],
        ],
        "image_id" => 26,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
];
