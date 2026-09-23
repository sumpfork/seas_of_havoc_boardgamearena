<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class TimelyTrading extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_TIMELY_TRADING,
            type: StateType::ACTIVE_PLAYER,
            name: 'timelyTrading',
            description: clienttranslate('${actplayer} must resolve their Timely Trading ability'),
            descriptionMyTurn: clienttranslate('${you} must resolve your Timely Trading ability'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argTimelyTrading();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipTimelyTrading();
    }

    #[PossibleAction]
    public function actTimelyTradingGainDoubloons(): mixed
    {
        return $this->game->actTimelyTradingGainDoubloons();
    }

    #[PossibleAction]
    public function actTimelyTradingPurchaseCard(int $card_id, int $doubloons_as_cannonballs = 0, int $doubloons_as_sails = 0): mixed
    {
        return $this->game->actTimelyTradingPurchaseCard($card_id, $doubloons_as_cannonballs, $doubloons_as_sails);
    }

    #[PossibleAction]
    public function actSkipTimelyTrading(): mixed
    {
        return $this->game->actSkipTimelyTrading();
    }
}
