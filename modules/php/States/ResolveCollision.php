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
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class ResolveCollision extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_RESOLVE_COLLISION,
            type: StateType::ACTIVE_PLAYER,
            name: 'resolveCollision',
            description: clienttranslate('${actplayer} must resolve a collision'),
            descriptionMyTurn: clienttranslate('${you} must resolve a collision'),
            transitions: [
                'collisionResolved' => STATE_NEXT_PLAYER_SEA_PHASE,
                'collisionOccurred' => STATE_RESOLVE_COLLISION,
            ],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argResolveCollision();
    }

    #[PossibleAction]
    public function actPivotPickedInDialog(string $direction): mixed
    {
        return $this->game->actPivotPickedInDialog($direction);
    }

    public function zombie(int $playerId): mixed
    {
        return 'collisionResolved';
    }
}
