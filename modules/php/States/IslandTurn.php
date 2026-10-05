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

class IslandTurn extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: STATE_ISLAND_TURN,
            type: StateType::ACTIVE_PLAYER,
            name: 'islandTurn',
            description: clienttranslate('${actplayer} must place a skiff'),
            descriptionMyTurn: clienttranslate('${you} must place a skiff'),
            transitions: ['islandTurnDone' => STATE_NEXT_PLAYER_ISLAND_PHASE, 'scrapCard' => STATE_SCRAP_CARD],
        );
    }

    public function getArgs(): array
    {
        return [
            'can_use_extra_rations' => $this->game->canUseExtraRations($this->game->getActivePlayerId()),
            'can_restock_market' => $this->game->canRestockMarket(),
            'market_restocked' => (bool) $this->game->getGameStateValue("market_restocked"),
            // A skiff placement still waiting on a choice. In the args, not a notification, so a
            // page refresh shows the choice again.
            'pending_resource_choice' => $this->game->getPendingResourceChoice(),
            'pending_workshop' => $this->game->getPendingWorkshopChoice(),
        ];
    }

    #[PossibleAction]
    public function actPlaceSkiff(string $slotname, string $number): mixed
    {
        return $this->game->actPlaceSkiff($slotname, $number);
    }

    #[PossibleAction]
    public function actResourcePickedInDialog(string $resource): mixed
    {
        return $this->game->actResourcePickedInDialog($resource);
    }

    public function zombie(int $playerId): mixed
    {
        return $this->game->zombieIslandTurn($playerId);
    }

    #[PossibleAction]
    public function actTradingPostExchange(
        #[JsonParam] array $resources_spent,
        #[JsonParam] array $resources_gained,
        string $slot_number,
        ?int $use_booty_card_id = null,
    ): mixed {
        return $this->game->actTradingPostExchange($resources_spent, $resources_gained, $slot_number, $use_booty_card_id);
    }

    #[PossibleAction]
    public function actActivateShipUpgrade(string $upgrade_key, ?int $use_booty_card_id = null): mixed
    {
        return $this->game->actActivateShipUpgrade($upgrade_key, $use_booty_card_id);
    }

    #[PossibleAction]
    public function actRestockMarket(): mixed
    {
        return $this->game->actRestockMarket();
    }

    #[PossibleAction]
    public function actExtraRations(?int $use_booty_card_id = null): mixed
    {
        return $this->game->actExtraRations($use_booty_card_id);
    }
}
