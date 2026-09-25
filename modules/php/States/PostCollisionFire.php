<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

/**
 * "After resolving the collision, Cannon fire depicted at the next ship outline may be resolved."
 * The pivot can turn the ship, so the side is chosen again here rather than reusing the side the
 * player picked when they played the card.
 */
class PostCollisionFire extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_POST_COLLISION_FIRE,
            type: StateType::ACTIVE_PLAYER,
            name: 'postCollisionFire',
            description: clienttranslate('${actplayer} may fire after the collision'),
            descriptionMyTurn: clienttranslate('${you} may fire after the collision'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argPostCollisionFire();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actPostCollisionFire('skip');
    }

    #[PossibleAction]
    public function actPostCollisionFire(string $decision, ?int $use_booty_card_id = null): mixed
    {
        return $this->game->actPostCollisionFire($decision, $use_booty_card_id);
    }
}
