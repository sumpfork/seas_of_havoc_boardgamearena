<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFramework\Actions\Types\JsonParam;

class SeaTurn extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 7,
            type: StateType::ACTIVE_PLAYER,
            name: 'seaTurn',
            description: clienttranslate('${actplayer} must play a card'),
            descriptionMyTurn: clienttranslate('${you} must play a card'),
            transitions: ['seaTurnDone' => 8, 'collisionOccurred' => 9],
        );
    }

    public function zombie(int $playerId): mixed
    {
        return 'seaTurnDone';
    }

    #[PossibleAction]
    public function actPlayCard(int $card_type, int $card_id, #[JsonParam] $decisions, ?int $use_booty_card_id = null): mixed
    {
        return $this->game->actPlayCard($card_type, $card_id, $decisions, $use_booty_card_id);
    }
}
