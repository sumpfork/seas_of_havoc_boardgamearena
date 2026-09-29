<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

/** A player hit by Chain Shot chooses which resource to lose, out of turn. */
class ChainShotLoss extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_CHAIN_SHOT_LOSS,
            type: StateType::ACTIVE_PLAYER,
            name: 'chainShotLoss',
            description: clienttranslate('${actplayer} was hit by chain shot and must choose a resource to lose'),
            descriptionMyTurn: clienttranslate('${you} were hit by chain shot: choose a resource to lose'),
            // The action returns to the next-player step of the sea phase.
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return ['options' => $this->game->chainShotLossOptions((int) $this->game->getActivePlayerId())];
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actChainShotLoseLargest();
    }

    #[PossibleAction]
    public function actChainShotLose(string $resource): mixed
    {
        return $this->game->actChainShotLose($resource);
    }
}
