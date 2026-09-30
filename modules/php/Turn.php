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
