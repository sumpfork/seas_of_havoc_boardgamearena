<?php

/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

namespace Bga\Games\SeasOfHavoc;

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
