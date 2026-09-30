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
use Bga\GameFramework\Actions\Types\JsonParam;

class SeaTurn extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_SEA_TURN,
            type: StateType::ACTIVE_PLAYER,
            name: 'seaTurn',
            description: clienttranslate('${actplayer} must play a card'),
            descriptionMyTurn: clienttranslate('${you} must play a card'),
            transitions: ['seaTurnDone' => STATE_NEXT_PLAYER_SEA_PHASE, 'collisionOccurred' => STATE_RESOLVE_COLLISION],
        );
    }

    /**
     * Being asked to play a card with an empty hand means something routed back here instead of
     * ending the turn - fail loudly rather than leaving the table stuck on a player who cannot act.
     */
    public function onEnteringState(int $activePlayerId): void
    {
        $this->game->assertActivePlayerHasCards($activePlayerId);
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->zombieSeaTurn($playerId);
    }

    #[PossibleAction]
    public function actPlayCard(int $card_type, int $card_id, #[JsonParam] $decisions, ?int $use_booty_card_id = null, int $ship = 1): mixed
    {
        return $this->game->actPlayCard($card_type, $card_id, $decisions, $use_booty_card_id, $ship);
    }
}
