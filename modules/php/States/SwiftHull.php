<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class SwiftHull extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_SWIFT_HULL,
            type: StateType::ACTIVE_PLAYER,
            name: 'swiftHull',
            description: clienttranslate('${actplayer} may use Swift Hull to play another card'),
            descriptionMyTurn: clienttranslate('${you} may pay 1 sail to play another card immediately'),
        );
    }

    public function getArgs(): array
    {
        return $this->game->argSwiftHull();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipSwiftHull();
    }

    #[PossibleAction]
    public function actUseSwiftHull(): mixed
    {
        return $this->game->actUseSwiftHull();
    }

    #[PossibleAction]
    public function actSkipSwiftHull(): mixed
    {
        return $this->game->actSkipSwiftHull();
    }
}
