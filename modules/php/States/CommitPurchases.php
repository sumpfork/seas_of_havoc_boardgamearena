<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

class CommitPurchases extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 53,
            type: StateType::GAME,
            name: 'commitPurchases',
            description: clienttranslate('Processing card purchases'),
            transitions: ['' => 6],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stCommitPurchases();
    }
}
