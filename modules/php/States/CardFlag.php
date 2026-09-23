<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class CardFlag extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct($game, id: STATE_CARD_FLAG, type: StateType::ACTIVE_PLAYER, name: 'cardFlag',
            description: clienttranslate('${actplayer} may use their card’s flag action'),
            descriptionMyTurn: clienttranslate('${you} may use your card’s flag action'));
    }

    public function getArgs(): array
    {
        return $this->game->argCardFlag();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipCardFlag();
    }

    #[PossibleAction]
    public function actResolveCardFlag(string $resource = '', ?int $card_id = null): mixed
    {
        return $this->game->actResolveCardFlag($resource, $card_id);
    }

    #[PossibleAction]
    public function actSkipCardFlag(): mixed
    {
        return $this->game->actSkipCardFlag();
    }
}
