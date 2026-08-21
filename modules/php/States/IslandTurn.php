<?php

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
            id: 3,
            type: StateType::ACTIVE_PLAYER,
            name: 'islandTurn',
            description: clienttranslate('${actplayer} must place a skiff'),
            descriptionMyTurn: clienttranslate('${you} must place a skiff'),
            transitions: ['islandTurnDone' => 4, 'scrapCard' => 11],
        );
    }

    #[PossibleAction]
    public function actPlaceSkiff(string $slotname, string $number): mixed
    {
        return $this->game->actPlaceSkiff($slotname, $number);
    }

    #[PossibleAction]
    public function actResourcePickedInDialog(string $resource, string $context, string $number): mixed
    {
        return $this->game->actResourcePickedInDialog($resource, $context, $number);
    }

    public function zombie(int $playerId): mixed
    {
        return 'islandTurnDone';
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
}
