<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFramework\Actions\Types\JsonParam;

class CaptainCard extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct($game, id: 20, type: StateType::ACTIVE_PLAYER, name: 'captainCard',
            description: clienttranslate('${actplayer} must resolve their captain card'),
            descriptionMyTurn: clienttranslate('${you} must resolve your captain card'),
            transitions: ['seaTurnDone' => 8, 'collisionOccurred' => 9]);
    }

    public function getArgs(): array
    {
        return $this->game->argCaptainCard();
    }

    public function zombie(int $playerId): mixed
    {
        $args = $this->getArgs();
        $cards = $args['_private'][$playerId]['available_cards'];
        return $this->game->actResolveCaptainCard([
            'card_id' => (int) $cards[0]['id'], 'fire' => 'skip',
            'order' => array_map('intval', array_column($cards, 'id')), 'resource' => 'sail',
        ], ['pass']);
    }

    #[PossibleAction]
    public function actResolveCaptainCard(#[JsonParam] array $choices, #[JsonParam] array $decisions = [], ?int $use_booty_card_id = null): mixed
    {
        return $this->game->actResolveCaptainCard($choices, $decisions, $use_booty_card_id);
    }
}
