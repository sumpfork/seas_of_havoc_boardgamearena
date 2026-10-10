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

class HuntTheBounty extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_HUNT_THE_BOUNTY,
            type: StateType::ACTIVE_PLAYER,
            name: 'huntTheBounty',
            description: clienttranslate('${actplayer} must declare a target ship for Hunt the Bounty'),
            descriptionMyTurn: clienttranslate('${you} must declare a target ship for Hunt the Bounty'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argHuntTheBounty();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipHuntTheBounty();
    }

    #[PossibleAction]
    public function actHuntTheBountyChooseTarget(string $target_ship): mixed
    {
        return $this->game->actHuntTheBountyChooseTarget($target_ship);
    }

    #[PossibleAction]
    public function actSkipHuntTheBounty(): mixed
    {
        return $this->game->actSkipHuntTheBounty();
    }
}
