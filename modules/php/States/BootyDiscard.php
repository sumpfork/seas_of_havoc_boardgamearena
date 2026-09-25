<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class BootyDiscard extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_BOOTY_DISCARD,
            type: StateType::ACTIVE_PLAYER,
            name: 'bootyDiscard',
            description: clienttranslate('${actplayer} must choose a booty token to discard'),
            descriptionMyTurn: clienttranslate('${you} have no room in your hold: choose a booty token to discard'),
            // The action returns the state the interrupted turn came from.
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argBootyDiscard();
    }

    public function zombie(int $playerId): mixed
    {
        $this->game->discardLeastValuableBootyToken($playerId);
        return (int) $this->game->getGameStateValue('booty_discard_return_state');
    }

    #[PossibleAction]
    public function actDiscardBootyToken(int $card_id): mixed
    {
        return $this->game->actDiscardBootyToken($card_id);
    }
}
