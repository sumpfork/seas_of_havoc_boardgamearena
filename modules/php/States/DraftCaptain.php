<?php

/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

/** Snake draft option: in player order from the first player, each player picks a captain. */
class DraftCaptain extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_DRAFT_CAPTAIN,
            type: StateType::ACTIVE_PLAYER,
            name: 'draftCaptain',
            description: clienttranslate('${actplayer} must pick a captain'),
            descriptionMyTurn: clienttranslate('${you} must pick a captain'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argDraftCaptain();
    }

    public function zombie(int $playerId): mixed
    {
        $captains = $this->game->argDraftCaptain()["captains"];
        return $this->game->actDraftCaptain($captains[array_rand($captains)]);
    }

    #[PossibleAction]
    public function actDraftCaptain(string $captain): mixed
    {
        return $this->game->actDraftCaptain($captain);
    }
}
