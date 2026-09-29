<?php

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
