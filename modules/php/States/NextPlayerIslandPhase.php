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
            id: STATE_NEXT_PLAYER_ISLAND_PHASE,
            type: StateType::GAME,
            name: 'nextPlayerIslandPhase',
            description: '',
            transitions: ['islandPhaseDone' => STATE_CARD_PURCHASES, 'nextPlayer' => STATE_ISLAND_TURN],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stNextPlayerIslandPhase();
    }
}
