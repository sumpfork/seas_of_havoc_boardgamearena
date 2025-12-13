
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- SeasOfHavoc implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.


-- Example 2: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';

ALTER TABLE `player` ADD `player_ship` varchar(64);

CREATE TABLE IF NOT EXISTS `resource` (
  `player_id` int(10) unsigned NOT NULL,
  `resource_key` varchar(32) NOT NULL,
  `resource_count` int(10) signed NOT NULL,
  PRIMARY KEY (`player_id`,`resource_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE resource ADD CONSTRAINT fk_player_id FOREIGN KEY (player_id) REFERENCES player(player_id);

CREATE TABLE IF NOT EXISTS `islandslots` (
    `slot_key` varchar(32) NOT NULL,
    `number` varchar(2) NOT NULL,
    `occupying_player_id` int(10) unsigned,
    `disabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`slot_key`, `number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE islandslots ADD CONSTRAINT fk_occupying_player_id FOREIGN KEY (occupying_player_id) REFERENCES player(player_id);

CREATE TABLE IF NOT EXISTS `player_captain` (
  `player_id` int(10) unsigned NOT NULL,
  `captain_key` varchar(32) NOT NULL,
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE player_captain ADD CONSTRAINT fk_captain_player_id FOREIGN KEY (player_id) REFERENCES player(player_id);

CREATE TABLE IF NOT EXISTS `player_ship_upgrades` (
  `player_id` int(10) unsigned NOT NULL,
  `upgrade_key` varchar(64) NOT NULL,
  `is_activated` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`, `upgrade_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE player_ship_upgrades ADD CONSTRAINT fk_upgrade_player_id FOREIGN KEY (player_id) REFERENCES player(player_id);

CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(32) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `sea` (
  `x` int(1) unsigned NOT NULL,
  `y` int(1) unsigned NOT NULL,
  `type` varchar(32) NOT NULL,
  `arg` varchar(32),
  `heading` int(1) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `next_action_on_card` (
  `player_id` int(10) unsigned NOT NULL,
  `next_action` varchar(32) NOT NULL,
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--ALTER TABLE next_action_on_card ADD CONSTRAINT fk_player_id FOREIGN KEY (player_id) REFERENCES player(player_id);

CREATE TABLE IF NOT EXISTS `unique_tokens` (
  `player_id` int(10) unsigned,
  `token_key` varchar(32) NOT NULL,
  PRIMARY KEY (`token_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--ALTER TABLE unique_tokens ADD CONSTRAINT fk_player_id FOREIGN KEY (player_id) REFERENCES player(player_id);

CREATE TABLE IF NOT EXISTS `extra_turns` (
  `player_id` int(10) unsigned NOT NULL,
  `phase` varchar(32) NOT NULL,
  PRIMARY KEY (`player_id`, `phase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE extra_turns ADD CONSTRAINT fk_extra_turns_player_id FOREIGN KEY (player_id) REFERENCES player(player_id);

CREATE TABLE IF NOT EXISTS `pending_purchases` (
  `player_id` int(10) unsigned NOT NULL,
  `card_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`player_id`, `card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE pending_purchases ADD CONSTRAINT fk_pending_purchases_player_id FOREIGN KEY (player_id) REFERENCES player(player_id);
