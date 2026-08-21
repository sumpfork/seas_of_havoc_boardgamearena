<?php

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
            id: 9,
            type: StateType::ACTIVE_PLAYER,
            name: 'resolveCollision',
            description: clienttranslate('${actplayer} must resolve a collision'),
            descriptionMyTurn: clienttranslate('${you} must resolve a collision'),
            transitions: [
                'collisionResolved' => 8,
                'collisionOccurred' => 9,
            ],
        );
    }

    public function onEnteringState(int $activePlayerId): void
    {
        $this->game->stResolveCollision();
    }

    public function getArgs(): array
    {
        return $this->game->argResolveCollision() ?? [];
    }

    #[PossibleAction]
    public function actResolveCollision(string $card_id, string $action_type): mixed
    {
        return $this->game->actResolveCollision($card_id, $action_type);
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
