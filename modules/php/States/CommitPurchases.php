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

class CommitPurchases extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_COMMIT_PURCHASES_PRIVATE,
            type: StateType::GAME,
            name: 'commitPurchases',
            description: clienttranslate('Processing card purchases'),
            transitions: ['' => STATE_SEA_PHASE_SETUP],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stCommitPurchases();
    }
}
