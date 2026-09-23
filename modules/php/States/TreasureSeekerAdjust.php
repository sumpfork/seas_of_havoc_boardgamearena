<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class TreasureSeekerAdjust extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_TREASURE_SEEKER_ADJUST,
            type: StateType::ACTIVE_PLAYER,
            name: 'treasureSeekerAdjust',
            description: clienttranslate('${actplayer} may adjust the shipwreck location'),
            descriptionMyTurn: clienttranslate('${you} may move the shipwreck to a surrounding space (Treasure Seeker ability)'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argTreasureSeekerAdjust();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipTreasureSeekerAdjust();
    }

    #[PossibleAction]
    public function actAdjustShipwreck(int $x, int $y): mixed
    {
        return $this->game->actAdjustShipwreck($x, $y);
    }

    #[PossibleAction]
    public function actSkipTreasureSeekerAdjust(): mixed
    {
        return $this->game->actSkipTreasureSeekerAdjust();
    }
}
