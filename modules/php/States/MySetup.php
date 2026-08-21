<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

class MySetup extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 2,
            type: StateType::GAME,
            name: 'mySetup',
            description: '',
            transitions: [],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stMyGameSetup();
    }
}
