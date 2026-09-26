<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

/**
 * The collision penalty: whoever caused it discards a card before the collision is resolved.
 * Skipped entirely when their hand is empty, so this state is only entered with cards to choose.
 */
class CollisionDiscard extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_COLLISION_DISCARD,
            type: StateType::ACTIVE_PLAYER,
            name: 'collisionDiscard',
            description: clienttranslate('${actplayer} must discard a card for the collision'),
            descriptionMyTurn: clienttranslate('${you} must discard a card for the collision'),
            transitions: ['cardDiscarded' => STATE_RESOLVE_COLLISION],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argCollisionDiscard();
    }

    public function zombie(int $playerId): mixed
    {
        $hand = $this->getArgs()['available_cards'];
        return $this->game->actCollisionDiscardCard((int) reset($hand)['id']);
    }

    #[PossibleAction]
    public function actCollisionDiscardCard(int $card_id): mixed
    {
        return $this->game->actCollisionDiscardCard($card_id);
    }
}
