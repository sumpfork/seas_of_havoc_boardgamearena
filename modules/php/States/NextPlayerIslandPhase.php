<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

class NextPlayerIslandPhase extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 4,
            type: StateType::GAME,
            name: 'nextPlayerIslandPhase',
            description: '',
            transitions: ['islandPhaseDone' => 5, 'nextPlayer' => 3],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stNextPlayerIslandPhase();
    }
}
