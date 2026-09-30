<?php

/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

class IslandPhaseSetup extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_ISLAND_PHASE_SETUP,
            type: StateType::GAME,
            name: 'islandPhaseSetup',
            description: clienttranslate('Starting Island Phase'),
            transitions: ['rebelDiscard' => STATE_REBEL_DISCARD, 'islandTurn' => STATE_ISLAND_TURN],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stIslandPhaseSetup();
    }
}
