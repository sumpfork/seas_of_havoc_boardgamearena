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

class HuntTheBountyExtraPlay extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_HUNT_THE_BOUNTY_EXTRA_PLAY,
            type: StateType::ACTIVE_PLAYER,
            name: 'huntTheBountyExtraPlay',
            description: clienttranslate('${actplayer} may play another card'),
            descriptionMyTurn: clienttranslate('${you} may play another card immediately'),
        );
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipHuntTheBountyExtraPlay();
    }

    #[PossibleAction]
    public function actHuntTheBountyPlayAnother(): mixed
    {
        return $this->game->actHuntTheBountyPlayAnother();
    }

    #[PossibleAction]
    public function actSkipHuntTheBountyExtraPlay(): mixed
    {
        return $this->game->actSkipHuntTheBountyExtraPlay();
    }
}
