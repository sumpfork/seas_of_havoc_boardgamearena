<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

class SeaPhaseSetup extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_SEA_PHASE_SETUP,
            type: StateType::GAME,
            name: 'seaPhaseSetup',
            description: clienttranslate('Starting Sea Phase'),
            transitions: ['' => STATE_SEA_TURN],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stSeaPhaseSetup();
    }
}
