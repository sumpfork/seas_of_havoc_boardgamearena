<?php

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
            description: clienttranslate('${actplayer} must declare a Hunt the Bounty target'),
            descriptionMyTurn: clienttranslate('${you} must declare a Hunt the Bounty target'),
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
    public function actHuntTheBountyChooseTarget(string $target_player_id): mixed
    {
        return $this->game->actHuntTheBountyChooseTarget($target_player_id);
    }

    #[PossibleAction]
    public function actSkipHuntTheBounty(): mixed
    {
        return $this->game->actSkipHuntTheBounty();
    }
}
