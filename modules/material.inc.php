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

$this->booty_tokens = [
    "booty_token_1" => ["image_id" => 0, "resources" => ["sail" => 3]],
    "booty_token_2" => ["image_id" => 1, "resources" => ["sail" => 1, "doubloon" => 1]],
    "booty_token_3" => ["image_id" => 2, "resources" => ["sail" => 1, "cannonball" => 1]],
    "booty_token_4" => ["image_id" => 3, "resources" => ["doubloon" => 1, "choice" => 1]],
    "booty_token_5" => ["image_id" => 4, "resources" => ["cannonball" => 1, "choice" => 1]],
    "booty_token_6" => ["image_id" => 5, "resources" => ["doubloon" => 2]],
    "booty_token_7" => ["image_id" => 6, "resources" => ["doubloon" => 1, "cannonball" => 1]],
    "booty_token_8" => ["image_id" => 7, "resources" => ["sail" => 1, "choice" => 1]],
    "booty_token_9" => ["image_id" => 8, "resources" => ["cannonball" => 2]],
];

$this->token_names = [
    "first_player_token" => clienttranslate("First Player Token"),
    "green_flag" => clienttranslate("Green Purser's Flag"),
    "tan_flag" => clienttranslate("Tan Flag"),
    "red_flag" => clienttranslate("Red Flag"),
    "blue_flag" => clienttranslate("Blue Flag"),
    "yellow_flag" => clienttranslate("Yellow Flag"),
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
    "sloop_of_war_chain_shot" => [
        "image_id" => 17,
        "category" => "ship_upgrade",
        "ship_name" => "Sloop of War",
        "cost" => ["cannonball" => 2, "doubloon" => 1],
        "infamy" => 3,
    ],
    "sloop_of_war_nimble_hull" => [
        "image_id" => 18,
        "category" => "ship_upgrade",
        "ship_name" => "Sloop of War",
        "cost" => ["sail" => 1, "doubloon" => 2],
        "infamy" => 3,
    ],
    "brig_extra_rations" => [
        "image_id" => 21,
        "category" => "ship_upgrade",
        "ship_name" => "Brig",
        "cost" => ["sail" => 1, "doubloon" => 1],
        "infamy" => 3,
    ],
    "brig_carronade" => [
        "image_id" => 22,
        "category" => "ship_upgrade",
        "ship_name" => "Brig",
        "cost" => ["cannonball" => 1],
        "infamy" => 3,
    ],
    "ship_of_the_line_double_gun_crews" => [
        "image_id" => 25,
        "category" => "ship_upgrade",
        "ship_name" => "Ship-of-the-Line",
        "cost" => ["cannonball" => 1, "doubloon" => 2],
        "infamy" => 3,
    ],
    "ship_of_the_line_heavy_guns" => [
        "image_id" => 26,
        "category" => "ship_upgrade",
        "ship_name" => "Ship-of-the-Line",
        "cost" => ["cannonball" => 2, "doubloon" => 1],
        "infamy" => 3,
    ],
    "galleon_bow_and_stern_chasers" => [
        "image_id" => 29,
        "category" => "ship_upgrade",
        "ship_name" => "Galleon",
        "cost" => ["cannonball" => 1, "doubloon" => 3],
        "infamy" => 3,
    ],
    "galleon_treasure_hold" => [
        "image_id" => 30,
        "category" => "ship_upgrade",
        "ship_name" => "Galleon",
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
        "ship_name" => "Brig",
        "cost" => ["sail" => 1],
        "actions" => [["action" => "forward"]],
        "image_id" => 6,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Brig",
        "cost" => ["sail" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "right"]],
            ],
        ],
        "image_id" => 7,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Brig",
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]],
            ],
        ],
        "image_id" => 8,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "War Junk",
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 9,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "War Junk",
        "cost" => ["sail" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "right"]],
            ],
        ],
        "image_id" => 10,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "War Junk",
        "cost" => ["doubloon" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"]],
            ],
        ],
        "image_id" => 11,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Sloop of War",
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 12,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Sloop of War",
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
        "ship_name" => "Sloop of War",
        "cost" => ["sail" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "right"]],
            ],
        ],
        "image_id" => 14,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Galleon",
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 15,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Galleon",
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            [
                "action" => "choice",
                "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]],
            ],
        ],
        "image_id" => 16,
        "count" => 2,
        "category" => "starting_card",
    ],
    [
        "ship_name" => "Galleon",
        "cost" => ["sail" => 1],
        "actions" => [["action" => "forward"]],
        "image_id" => 17,
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
    // Green flag cards continued (image_ids 27-30)
    [
        "cost" => ["sail" => 1, "cannonball" => 2],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
            [
                "action" => "choice",
                "choices" => [
                    [
                        "action" => "sequence",
                        "actions" => [
                            ["action" => "forward"],
                            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                        ],
                        "name" => "forward",
                    ],
                ],
                "cost" => ["sail" => 1],
            ],
        ],
        "image_id" => 27,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]]],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
        ],
        "image_id" => 28,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"]]],
        ],
        "image_id" => 29,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    [
        "cost" => ["doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"], ["action" => "pivot_180"]]],
        ],
        "image_id" => 30,
        "count" => 1,
        "flag" => "green",
        "category" => "market_card",
    ],
    // Blue flag cards (image_ids 31-43)
    [
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 31,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["cannonball" => 2],
        "actions" => [
            ["action" => "choice", "choices" => [
                ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                ["action" => "2 x fire", "range" => 2, "cost" => ["cannonball" => 2]],
            ]],
        ],
        "image_id" => 32,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["cannonball" => 3],
        "actions" => [
            ["action" => "choice", "choices" => [
                ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                ["action" => "2 x fire", "range" => 2, "cost" => ["cannonball" => 2]],
                ["action" => "3 x fire", "range" => 1, "cost" => ["cannonball" => 3]],
            ]],
        ],
        "image_id" => 33,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]]],
        ],
        "image_id" => 34,
        "count" => 1,
        "flag" => "blue",
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
        "image_id" => 35,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "forward", "cost" => ["sail" => 1]],
        ],
        "image_id" => 36,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            ["action" => "forward"],
            [
                "action" => "choice",
                "choices" => [
                    ["action" => "sequence", "actions" => [["action" => "forward"], ["action" => "left"]], "name" => "forward-left"],
                    ["action" => "sequence", "actions" => [["action" => "forward"], ["action" => "forward"]], "name" => "forward-forward"],
                    ["action" => "sequence", "actions" => [["action" => "forward"], ["action" => "right"]], "name" => "forward-right"],
                ],
                "cost" => ["sail" => 1],
            ],
        ],
        "image_id" => 37,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
        ],
        "image_id" => 38,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 2, "cannonball" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]], "cost" => ["sail" => 1]],
        ],
        "image_id" => 39,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 2],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
            [
                "action" => "choice",
                "choices" => [
                    [
                        "action" => "sequence",
                        "actions" => [
                            ["action" => "forward"],
                            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                        ],
                        "name" => "forward",
                    ],
                ],
                "cost" => ["sail" => 1],
            ],
        ],
        "image_id" => 40,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]]],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
        ],
        "image_id" => 41,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"]]],
        ],
        "image_id" => 42,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    [
        "cost" => ["doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"], ["action" => "pivot_180"]]],
        ],
        "image_id" => 43,
        "count" => 1,
        "flag" => "blue",
        "category" => "market_card",
    ],
    // Tan flag cards (image_ids 44-56)
    [
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 44,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["cannonball" => 2],
        "actions" => [
            ["action" => "choice", "choices" => [
                ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                ["action" => "2 x fire", "range" => 2, "cost" => ["cannonball" => 2]],
            ]],
        ],
        "image_id" => 45,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["cannonball" => 3],
        "actions" => [
            ["action" => "choice", "choices" => [
                ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                ["action" => "2 x fire", "range" => 2, "cost" => ["cannonball" => 2]],
                ["action" => "3 x fire", "range" => 1, "cost" => ["cannonball" => 3]],
            ]],
        ],
        "image_id" => 46,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]]],
        ],
        "image_id" => 47,
        "count" => 1,
        "flag" => "tan",
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
        "image_id" => 48,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "forward", "cost" => ["sail" => 1]],
        ],
        "image_id" => 49,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            ["action" => "forward"],
            [
                "action" => "choice",
                "choices" => [
                    ["action" => "sequence", "actions" => [["action" => "forward"], ["action" => "left"]], "name" => "forward-left"],
                    ["action" => "sequence", "actions" => [["action" => "forward"], ["action" => "forward"]], "name" => "forward-forward"],
                    ["action" => "sequence", "actions" => [["action" => "forward"], ["action" => "right"]], "name" => "forward-right"],
                ],
                "cost" => ["sail" => 1],
            ],
        ],
        "image_id" => 50,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
        ],
        "image_id" => 51,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 2, "cannonball" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]], "cost" => ["sail" => 1]],
        ],
        "image_id" => 52,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 2],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
            [
                "action" => "choice",
                "choices" => [
                    [
                        "action" => "sequence",
                        "actions" => [
                            ["action" => "forward"],
                            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                        ],
                        "name" => "forward",
                    ],
                ],
                "cost" => ["sail" => 1],
            ],
        ],
        "image_id" => 53,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]]],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
        ],
        "image_id" => 54,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"]]],
        ],
        "image_id" => 55,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    [
        "cost" => ["doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"], ["action" => "pivot_180"]]],
        ],
        "image_id" => 56,
        "count" => 1,
        "flag" => "tan",
        "category" => "market_card",
    ],
    // Red flag cards (image_ids 57-69)
    [
        "cost" => ["cannonball" => 1],
        "actions" => [["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]]],
        "image_id" => 57,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["cannonball" => 2],
        "actions" => [
            ["action" => "choice", "choices" => [
                ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                ["action" => "2 x fire", "range" => 2, "cost" => ["cannonball" => 2]],
            ]],
        ],
        "image_id" => 58,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["cannonball" => 3],
        "actions" => [
            ["action" => "choice", "choices" => [
                ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                ["action" => "2 x fire", "range" => 2, "cost" => ["cannonball" => 2]],
                ["action" => "3 x fire", "range" => 1, "cost" => ["cannonball" => 3]],
            ]],
        ],
        "image_id" => 59,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "forward"], ["action" => "right"]]],
        ],
        "image_id" => 60,
        "count" => 1,
        "flag" => "red",
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
        "image_id" => 61,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "forward", "cost" => ["sail" => 1]],
        ],
        "image_id" => 62,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "doubloon" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]], "cost" => ["sail" => 1]],
        ],
        "image_id" => 63,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
        ],
        "image_id" => 64,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 2, "cannonball" => 1],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]], "cost" => ["sail" => 1]],
        ],
        "image_id" => 65,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 2],
        "actions" => [
            ["action" => "forward"],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
            [
                "action" => "choice",
                "choices" => [
                    [
                        "action" => "sequence",
                        "actions" => [
                            ["action" => "forward"],
                            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
                        ],
                        "name" => "forward",
                    ],
                ],
                "cost" => ["sail" => 1],
            ],
        ],
        "image_id" => 66,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["sail" => 1, "cannonball" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "left"], ["action" => "right"]]],
            ["action" => "fire", "range" => 3, "cost" => ["cannonball" => 1]],
        ],
        "image_id" => 67,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"]]],
        ],
        "image_id" => 68,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => ["doubloon" => 1],
        "actions" => [
            ["action" => "choice", "choices" => [["action" => "pivot_left"], ["action" => "pivot_right"], ["action" => "pivot_180"]]],
        ],
        "image_id" => 69,
        "count" => 1,
        "flag" => "red",
        "category" => "market_card",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "rally_the_flags"]],
        "image_id" => 71,
        "count" => 1,
        "captain_key" => "pirate_queen",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "extortion"]],
        "image_id" => 72,
        "count" => 1,
        "captain_key" => "pirate_queen",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "barter"]],
        "image_id" => 73,
        "count" => 1,
        "captain_key" => "merchant",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "timely_trading"]],
        "image_id" => 74,
        "count" => 1,
        "captain_key" => "merchant",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "boarding_party"]],
        "image_id" => 75,
        "count" => 1,
        "captain_key" => "corsair",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "hunt_the_bounty"]],
        "image_id" => 76,
        "count" => 1,
        "captain_key" => "corsair",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "retaliation"]],
        "image_id" => 77,
        "count" => 1,
        "captain_key" => "rebel",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "improvisation"]],
        "image_id" => 78,
        "count" => 1,
        "captain_key" => "rebel",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "government_funding"]],
        "image_id" => 79,
        "count" => 1,
        "captain_key" => "admiral",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "inspire"]],
        "image_id" => 80,
        "count" => 1,
        "captain_key" => "admiral",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "spyglass"]],
        "image_id" => 81,
        "count" => 1,
        "captain_key" => "treasure_seeker",
        "category" => "captain",
    ],
    [
        "cost" => [],
        "actions" => [["action" => "captain ability", "ability" => "unearth_riches"]],
        "image_id" => 82,
        "count" => 1,
        "captain_key" => "treasure_seeker",
        "category" => "captain",
    ],
];
