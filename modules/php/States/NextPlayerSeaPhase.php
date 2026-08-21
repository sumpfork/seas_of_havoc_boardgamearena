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
            id: 8,
            type: StateType::GAME,
            name: 'nextPlayerSeaPhase',
            description: '',
            transitions: ['seaPhaseDone' => 10, 'nextPlayer' => 7],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stNextPlayerSeaPhase();
    }
}
