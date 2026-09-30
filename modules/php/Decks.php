<?php
/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

namespace Bga\Games\SeasOfHavoc;

/**
 * Decks: each player's deck, hand and discard pile, drawing and discarding, scrapping, and damage cards.
 * Part of the SeasOfHavoc game class, split out by theme.
 */
trait Decks
{
    function playerDeckName($player_id)
    {
        return "player_deck_" . $player_id;
    }

    /**
     * Each player's discard is its own ordered pile: the location name carries the player, which
     * frees card_location_arg to be the position in the pile (highest = top).
     */
    function discardHand(int $player_id): void
    {
        $this->cards->moveAllCardsInLocation("hand", $this->playerDiscardName($player_id), $player_id);
    }

    function playerDiscardName($player_id)
    {
        return "player_discard_" . $player_id;
    }

    /** Put a card face up on top of a player's discard pile. */
    function discardCardToPlayer(int $card_id, $player_id): void
    {
        $this->cards->insertCardOnExtremePosition($card_id, $this->playerDiscardName($player_id), true);
    }

    /** A player's discard pile, bottom card first. */
    function getPlayerDiscard($player_id): array
    {
        return $this->cards->getCardsInLocation($this->playerDiscardName($player_id), null, "location_arg");
    }

    /**
     * The client only distinguishes "hand" from "discard", so the per-player discard location
     * names are collapsed back to "player_discard" on the way out.
     */
    function normalizeCardLocations(array $cards): array
    {
        foreach ($cards as $key => $card) {
            if (str_starts_with((string) $card["location"], "player_discard")) {
                $cards[$key]["location"] = "player_discard";
                $cards[$key]["location_arg"] = (int) $this->playerIdFromDiscardLocation($card["location"]);
            }
        }
        return $cards;
    }

    private function playerIdFromDiscardLocation(string $location): string
    {
        return substr($location, strlen("player_discard_")) ?: "0";
    }

    function calculateNumDamageCards($num_players)
    {
        if (self::DEBUG_DAMAGE_CARDS > 0) {
            return self::DEBUG_DAMAGE_CARDS;
        }
        return 10 + $num_players * 5;
    }

    /** The card type used for damage cards. */
    function damageCardType(): int
    {
        foreach ($this->playable_cards as $type => $card) {
            if (($card["category"] ?? "") === "damage") {
                return (int) $type;
            }
        }
        throw new \Bga\GameFramework\SystemException("No damage card defined");
    }

    /** Everything a player still owns at the end: their deck, their hand and their discard pile. */
    private function getPlayerOwnedCards(string $player_id): array
    {
        return array_merge(
            array_values($this->cards->getCardsInLocation($this->playerDeckName($player_id))),
            array_values($this->cards->getCardsInLocation("hand", $player_id)),
            array_values($this->cards->getCardsInLocation($this->playerDiscardName($player_id))),
        );
    }

    function notifyDeckSizeChanged(string $player_id, string $message = "")
    {
        $deck_size = $this->cards->countCardInLocation($this->playerDeckName($player_id));
        $this->bga->notify->player($player_id, "deckSizeChanged", $message, [
            "player_id" => $player_id,
            "deck_size" => $deck_size,
        ]);
    }

    function drawCards(string $player_id, int $num_cards = 1)
    {
        $this->mytrace("drawCard - drawing $num_cards cards");
        $deck_name = $this->playerDeckName($player_id);
        $discard_name = $this->playerDiscardName($player_id);

        // Each player's deck reshuffles from their own discard pile when it runs dry. The mapping
        // is per-player, so it is set for whoever is drawing right now.
        $this->cards->autoreshuffle_custom = [$deck_name => $discard_name];

        $discard_count_before = $this->cards->countCardInLocation($discard_name);
        $cards_drawn = $this->cards->pickCards($num_cards, $deck_name, $player_id);
        $this->mytrace("drawCard - drew " . count($cards_drawn) . " cards");

        // The discard emptying mid-draw is how we know the deck was reformed from it.
        if ($discard_count_before > 0 && $this->cards->countCardInLocation($discard_name) == 0) {
            $this->mytrace("drawCard - autoreshuffle detected, notifying player");
            $this->bga->notify->player(
                $player_id,
                "deckReshuffled",
                clienttranslate("Your discard pile was shuffled into your deck"),
                [
                    "player_id" => $player_id,
                    "deck_size" => $this->cards->countCardInLocation($deck_name),
                ],
            );
        }

        if (empty($cards_drawn)) {
            $this->mytrace("no cards to draw for player $player_id");
            return;
        }

        $this->bga->notify->player(
            $player_id,
            "cardDrawn",
            count($cards_drawn) == 1 ? clienttranslate("You drew a card") : clienttranslate('You drew ${num_cards} cards'),
            [
                "player_id" => $player_id,
                "cards" => $cards_drawn,
                "num_cards" => count($cards_drawn),
                "deck_size" => $this->cards->countCardInLocation($deck_name),
            ],
        );
    }

    function discardCards(string $player_id, array $card_ids)
    {
        $this->mytrace("discardCards - discarding " . count($card_ids) . " cards");

        $cards_discarded = [];
        foreach ($card_ids as $card_id) {
            // Validate that the card belongs to the player and is in their hand
            $card = $this->cards->getCard($card_id);
            if ($card == null) {
                throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card"));
            }

            if ($card["location"] != "hand" || $card["location_arg"] != $player_id) {
                throw new \Bga\GameFramework\UserException(clienttranslate("You can only discard cards from your hand"));
            }

            // Move card to player's discard pile
            $this->discardCardToPlayer($card_id, $player_id);
            $cards_discarded[] = $card;
        }

        if (count($cards_discarded) > 0) {
            $message =
                count($cards_discarded) == 1
                    ? clienttranslate('${player_name} discarded a card')
                    : clienttranslate('${player_name} discarded ${num_cards} cards');

            $this->bga->notify->all("cardsDiscarded", $message, [
                "player_name" => $this->getPlayerNameById($player_id),
                "player_id" => $player_id,
                "cards" => $cards_discarded,
                "num_cards" => count($cards_discarded),
            ]);
        }
    }

    /**
     * Give a player a damage card. War Junk Watertight Bulkheads scraps it immediately when the
     * player already had a damage card on top of their discard pile.
     */
    function dealDamageCard(string $hit_player_id): void
    {
        $this->bga->playerStats->inc("damage_received", 1, (int) $hit_player_id);
        $top = $this->cards->getCardOnTop($this->playerDiscardName($hit_player_id));
        $bulkheads =
            $top !== null &&
            (int) $top["type"] === $this->damageCardType() &&
            $this->hasShipUpgrade($hit_player_id, "war_junk_bulwark");

        $damage_card = $this->cards->pickCardForLocation(
            "damage_deck",
            $this->playerDiscardName($hit_player_id),
        );
        if (!$damage_card) {
            // "Players complete that Sea Phase using extra damage cards from the scrap pile or the
            // box as needed." The deck itself stays empty - that is what ends the game.
            $damage_card = $this->takeSpareDamageCard($hit_player_id);
        }
        // pickCardForLocation drops the card in at position 0; put it on top of the pile.
        $this->discardCardToPlayer((int) $damage_card["id"], $hit_player_id);
        $this->bga->notify->all("damageReceived", clienttranslate('${player_name} receives a damage card'), [
            "player_name" => self::getPlayerNameById($hit_player_id),
            "player_id" => $hit_player_id,
            "damage_card" => $damage_card,
            // The damage deck running out ends the game, so its size is public information.
            "damage_deck_size" => $this->cards->countCardInLocation("damage_deck"),
        ]);

        if ($bulkheads) {
            $this->cards->moveCard((int) $damage_card["id"], "scrap");
            $this->bga->notify->all(
                "cardScrapped",
                clienttranslate('${player_name}\'s Watertight Bulkheads: the damage card is scrapped immediately'),
                [
                    "player_name" => self::getPlayerNameById($hit_player_id),
                    "player_id" => (int) $hit_player_id,
                    "card" => [
                        "id" => (int) $damage_card["id"],
                        "type" => (int) $damage_card["type"],
                        "location" => "player_discard",
                        "location_arg" => (int) $hit_player_id,
                    ],
                    "original_location" => "player_discard",
                ],
            );
        }
    }

    /** A damage card from the scrap pile, or a fresh one from "the box" if the scrap has none. */
    private function takeSpareDamageCard(string $hit_player_id): array
    {
        $discard = $this->playerDiscardName($hit_player_id);
        foreach ($this->cards->getCardsInLocation("scrap") as $card) {
            if ((int) $card["type"] === $this->damageCardType()) {
                $this->cards->moveCard((int) $card["id"], $discard);
                return $this->cards->getCard((int) $card["id"]);
            }
        }
        $this->cards->createCards([["type" => $this->damageCardType(), "type_arg" => 0, "nbr" => 1]], $discard);
        $created = $this->cards->getCardsInLocation($discard);
        return end($created);
    }

    function scrapCardAndRefund(int $card_id, string $player_id): void
    {

        // Validate that the card belongs to the player and is in hand or discard
        $card = $this->cards->getCard($card_id);
        if (!$card) {
            throw new \Bga\GameFramework\UserException(clienttranslate("Invalid card"));
        }

        $from_hand = $card["location"] === "hand" && $card["location_arg"] == $player_id;
        $from_discard = $card["location"] === $this->playerDiscardName($player_id);
        if (!$from_hand && !$from_discard) {
            throw new \Bga\GameFramework\UserException(clienttranslate("You can only scrap cards from your hand or discard pile"));
        }

        // Store the original location before moving ("player_discard" is the client's name for it)
        $original_location = $from_hand ? "hand" : "player_discard";

        // Move card to scrap pile
        $this->cards->moveCard($card_id, "scrap");
        $this->bga->playerStats->inc("cards_scrapped", 1, (int) $player_id);

        // Ensure card ID is properly formatted
        $card_for_notification = [
            "id" => intval($card["id"]),
            "type" => intval($card["type"]),
            "location" => $original_location,
            "location_arg" => intval($player_id),
        ];

        // Notify players
        $this->bga->notify->all("cardScrapped", clienttranslate('${player_name} scrapped a card'), [
            "player_name" => $this->getPlayerNameById($player_id),
            "player_id" => intval($player_id),
            "card" => $card_for_notification,
            "original_location" => $original_location,
        ]);

        $cost = $this->playable_cards[$card["type"]]["cost"] ?? [];
        if ($cost) {
            $this->playerGainResources($player_id, $cost);
        }
    }
}
