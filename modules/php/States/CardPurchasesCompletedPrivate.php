<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;

class CardPurchasesCompletedPrivate extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_CARD_PURCHASES_COMPLETED_PRIVATE,
            type: StateType::PRIVATE,
            name: 'cardPurchasesCompleted',
            description: clienttranslate('Waiting for other players to complete their purchases'),
            descriptionMyTurn: clienttranslate('Waiting for other players to complete their purchases'),
            transitions: [],
        );
    }

    public function zombie(int $playerId): mixed
    {
        return null;
    }
}
