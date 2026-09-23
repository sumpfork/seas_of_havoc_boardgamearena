<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class BoardingParty extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_BOARDING_PARTY,
            type: StateType::ACTIVE_PLAYER,
            name: 'boardingParty',
            description: clienttranslate('${actplayer} must resolve their Boarding Party ability'),
            descriptionMyTurn: clienttranslate('${you} must resolve your Boarding Party ability'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argBoardingParty();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipBoardingParty();
    }

    #[PossibleAction]
    public function actBoardingPartySteal(string $target_player_id, string $item): mixed
    {
        return $this->game->actBoardingPartySteal($target_player_id, $item);
    }

    #[PossibleAction]
    public function actSkipBoardingParty(): mixed
    {
        return $this->game->actSkipBoardingParty();
    }
}
