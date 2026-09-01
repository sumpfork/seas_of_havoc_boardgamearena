<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class Barter extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 16,
            type: StateType::ACTIVE_PLAYER,
            name: 'barter',
            description: clienttranslate('${actplayer} must resolve their Barter ability'),
            descriptionMyTurn: clienttranslate('${you} must resolve your Barter ability'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argBarter();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipBarter();
    }

    #[PossibleAction]
    public function actBarterExchange(string $resource, string $direction): mixed
    {
        return $this->game->actBarterExchange($resource, $direction);
    }

    #[PossibleAction]
    public function actSkipBarter(): mixed
    {
        return $this->game->actSkipBarter();
    }
}
