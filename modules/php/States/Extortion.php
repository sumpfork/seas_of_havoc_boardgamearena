<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class Extortion extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_EXTORTION,
            type: StateType::ACTIVE_PLAYER,
            name: 'extortion',
            description: clienttranslate('${actplayer} must resolve their Extortion ability'),
            descriptionMyTurn: clienttranslate('${you} must resolve your Extortion ability'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argExtortion();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipExtortion();
    }

    #[PossibleAction]
    public function actResourcePickedInDialog(string $resource, string $context, string $number): mixed
    {
        return $this->game->actResourcePickedInDialog($resource, $context, $number);
    }

    #[PossibleAction]
    public function actExtortionScrapCard(int $card_id): mixed
    {
        return $this->game->actExtortionScrapCard($card_id);
    }

    #[PossibleAction]
    public function actSkipExtortion(): mixed
    {
        return $this->game->actSkipExtortion();
    }
}
