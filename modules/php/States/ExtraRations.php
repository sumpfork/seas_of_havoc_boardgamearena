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

/** Brig Extra Rations, offered after each of its owner's Island Phase turns and at the end of the phase. */
class ExtraRations extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_EXTRA_RATIONS,
            type: StateType::ACTIVE_PLAYER,
            name: 'extraRations',
            description: clienttranslate('Island Phase: ${actplayer} may use Extra Rations to draw a card'),
            descriptionMyTurn: clienttranslate('Island Phase: ${you} may pay 1 resource to draw a card'),
        );
    }

    public function getArgs(): array
    {
        return ['extra_rations_resources' => $this->game->extraRationsPaymentOptions($this->game->getActivePlayerId())];
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->zombieExtraRations($playerId);
    }

    #[PossibleAction]
    public function actExtraRations(string $resource, ?int $use_booty_card_id = null): int
    {
        $this->game->actExtraRations($resource, $use_booty_card_id);
        return STATE_NEXT_PLAYER_ISLAND_PHASE;
    }

    #[PossibleAction]
    public function actSkipExtraRations(): int
    {
        return STATE_NEXT_PLAYER_ISLAND_PHASE;
    }
}
