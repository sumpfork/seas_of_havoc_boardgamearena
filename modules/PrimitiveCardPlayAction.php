<?php

// Canonical action strings shared by material definitions and the game engine.
enum PrimitiveCardPlayAction: string
{
    case FORWARD = "forward";
    case PIVOT_LEFT = "pivot left";
    case PIVOT_RIGHT = "pivot right";
    case PIVOT_AROUND = "pivot 180";
    case SCRAP_SELF = "scrap self";
    case LEFT = "left";
    case RIGHT = "right";
    case FIRE = "fire";
    case FIRE2 = "2 x fire";
    case FIRE3 = "3 x fire";
    case SEQUENCE = "sequence";
    case CHOICE = "choice";
    case CAPTAIN_ABILITY = "captain ability";
}
