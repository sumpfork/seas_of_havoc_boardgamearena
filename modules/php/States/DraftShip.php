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

/** Snake draft option, once every captain is picked: in reverse player order, each player picks a ship. */
class DraftShip extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_DRAFT_SHIP,
            type: StateType::ACTIVE_PLAYER,
            name: 'draftShip',
            description: clienttranslate('${actplayer} must pick a ship'),
            descriptionMyTurn: clienttranslate('${you} must pick a ship'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argDraftShip();
    }

    public function zombie(int $playerId): mixed
    {
        $ships = $this->game->argDraftShip()["ships"];
        return $this->game->actDraftShip($ships[array_rand($ships)]);
    }

    #[PossibleAction]
    public function actDraftShip(string $ship): mixed
    {
        return $this->game->actDraftShip($ship);
    }
}
