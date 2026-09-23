<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFramework\Actions\Types\JsonParam;

class CardPurchases extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_CARD_PURCHASES,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            name: 'cardPurchases',
            description: clienttranslate('Players may purchase cards'),
            descriptionMyTurn: clienttranslate('You may purchase cards'),
            transitions: ['cardPurchasesDone' => STATE_COMMIT_PURCHASES_PRIVATE],
            initialPrivate: 51,
        );
    }

    public function onEnteringState(int $activePlayerId): void
    {
        $this->game->stCardPurchases();
    }

    #[PossibleAction]
    public function actCompletePurchases(#[JsonParam] array $cards_purchased): void
    {
        $this->game->actCompletePurchases($cards_purchased);
    }
}
