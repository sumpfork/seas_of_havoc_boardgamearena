<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFramework\Actions\Types\JsonParam;

class CardPurchasesPrivate extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 51,
            type: StateType::PRIVATE,
            name: 'cardPurchasesPrivate',
            description: clienttranslate('Players may purchase cards'),
            descriptionMyTurn: clienttranslate('You may purchase cards'),
            transitions: ['completedPurchases' => 52],
        );
    }

    public function zombie(int $playerId): mixed
    {
        $this->game->actCompletePurchases([]);
        return null;
    }

    #[PossibleAction]
    public function actCompletePurchases(#[JsonParam] array $cards_purchased): void
    {
        $this->game->actCompletePurchases($cards_purchased);
    }
}
