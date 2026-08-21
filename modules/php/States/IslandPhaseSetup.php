<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

class IslandPhaseSetup extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 10,
            type: StateType::GAME,
            name: 'islandPhaseSetup',
            description: clienttranslate('Starting Island Phase'),
            transitions: ['rebelDiscard' => 12, 'islandTurn' => 3, 'treasureSeekerSetup' => 13],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stIslandPhaseSetup();
    }
}
