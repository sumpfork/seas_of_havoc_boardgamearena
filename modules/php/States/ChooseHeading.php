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

/** Game start: in player order, each player points their ship(s) in an orthogonal direction. */
class ChooseHeading extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_CHOOSE_HEADING,
            type: StateType::ACTIVE_PLAYER,
            name: 'chooseHeading',
            description: clienttranslate('${actplayer} must choose a heading for their ship'),
            descriptionMyTurn: clienttranslate('${you} must choose a heading for your ship'),
            transitions: [],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argChooseHeading();
    }

    public function zombie(int $playerId): mixed
    {
        $ship = $this->game->unorientedShip($playerId);
        return $this->game->actChooseHeading(
            $this->game->findSafeHeadingAtPosition($ship["x"], $ship["y"], ["rock", "shipwreck"])->value,
        );
    }

    #[PossibleAction]
    public function actChooseHeading(int $heading): mixed
    {
        return $this->game->actChooseHeading($heading);
    }
}
