<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

/**
 * Reached at the end of the Sea Phase that empties the damage deck. Adds up the infamy on each
 * player's cards and active upgrades, then hands over to the framework's end-of-game state, which
 * shows the score panel.
 */
class FinalScoring extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_FINAL_SCORING,
            type: StateType::GAME,
            name: 'finalScoring',
            description: clienttranslate('Final scoring'),
            transitions: ['gameEnd' => STATE_END_GAME],
        );
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        return $this->game->stFinalScoring();
    }
}
