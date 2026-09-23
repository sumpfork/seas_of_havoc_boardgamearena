<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

class NextPlayerSeaPhase extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_NEXT_PLAYER_SEA_PHASE,
            type: StateType::GAME,
            name: 'nextPlayerSeaPhase',
            description: '',
            transitions: ['seaPhaseDone' => STATE_ISLAND_PHASE_SETUP, 'nextPlayer' => STATE_SEA_TURN],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stNextPlayerSeaPhase();
    }
}
