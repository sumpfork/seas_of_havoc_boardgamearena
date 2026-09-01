<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class RallyTheFlags extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 14,
            type: StateType::ACTIVE_PLAYER,
            name: 'rallyTheFlagsChooseFlag',
            description: clienttranslate('${actplayer} must choose a flag to take'),
            descriptionMyTurn: clienttranslate('${you} must take a flag (Rally the Flags)'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argRallyTheFlagsChooseFlag();
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->actSkipRallyTheFlags();
    }

    #[PossibleAction]
    public function actRallyTheFlagsChooseFlag(string $flag_key): mixed
    {
        return $this->game->actRallyTheFlagsChooseFlag($flag_key);
    }

    #[PossibleAction]
    public function actSkipRallyTheFlags(): mixed
    {
        return $this->game->actSkipRallyTheFlags();
    }
}
