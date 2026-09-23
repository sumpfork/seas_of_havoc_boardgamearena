<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class RebelDiscard extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_REBEL_DISCARD,
            type: StateType::ACTIVE_PLAYER,
            name: 'rebelDiscard',
            description: clienttranslate('${actplayer} must discard a card'),
            descriptionMyTurn: clienttranslate('${you} must discard a card (Rebel ability)'),
            transitions: ['cardDiscarded' => STATE_ISLAND_TURN],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argRebelDiscard();
    }

    public function zombie(int $playerId): mixed
    {
        return 'cardDiscarded';
    }

    #[PossibleAction]
    public function actRebelDiscardCard(int $card_id): mixed
    {
        return $this->game->actRebelDiscardCard($card_id);
    }
}
