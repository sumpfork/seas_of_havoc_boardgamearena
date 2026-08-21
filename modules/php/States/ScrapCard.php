<?php

namespace Bga\Games\SeasOfHavoc\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

class ScrapCard extends GameState
{
    public function __construct(protected \SeasOfHavoc $game)
    {
        parent::__construct(
            $game,
            id: 11,
            type: StateType::ACTIVE_PLAYER,
            name: 'scrapCard',
            description: clienttranslate('${actplayer} must scrap a card'),
            descriptionMyTurn: clienttranslate('${you} must scrap a card from your hand or discard pile'),
            transitions: ['cardScrapped' => 4],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argScrapCard();
    }

    public function zombie(int $playerId): mixed
    {
        return 'cardScrapped';
    }

    #[PossibleAction]
    public function actScrapCard(int $card_id): mixed
    {
        return $this->game->actScrapCard($card_id);
    }
}
