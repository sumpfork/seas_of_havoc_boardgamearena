/**
 * Seas of Havoc - Card Manager Module
 * Card setup helpers and card-related UI management
 */

define([
  "dojo/dom-style",
], function(domStyle) {
  
  return {
    /**
     * Setup helper for non-playable cards (captain, ship upgrades)
     */
    setupNonPlayableCardHelper: function(card, div) {
      let image_id = null;
      if (card.cardKey && this.non_playable_cards && this.non_playable_cards[card.cardKey]) {
        const cardData = this.non_playable_cards[card.cardKey];
        image_id = cardData.image_id;
        div.classList.add("non-playable-card-front");
      } else if (this.non_playable_cards && this.non_playable_cards.card_back) {
        image_id = this.non_playable_cards.card_back.image_id;
        div.classList.add("non-playable-card-back");
      } else {
        image_id = 0;
        div.classList.add("non-playable-card-back");
      }
      
      console.log("setup non-playable card helper for card: " + card.id + " with cardKey " + card.cardKey + " and image id " + image_id);
      
      const spriteX = (image_id % 6) * 144;
      const spriteY = Math.floor(image_id / 6) * 198;
      domStyle.set(div, "background-position", `-${spriteX}px -${spriteY}px`);
      console.log("background-position for " + card.id + " set to: " + `-${spriteX}px -${spriteY}px`);
    },

    /**
     * Add player's captain and ship upgrade cards to their board
     */
    addPlayerCardsToBoard: function(gamedatas) {
      console.log("Adding player's captain and ship upgrades to board...");
      
      // Add player's captain card
      if (gamedatas.player_captain) {
        const captainCard = {
          id: `captain-${gamedatas.player_captain}`,
          cardKey: gamedatas.player_captain,
          category: 'captain'
        };
        
        console.log("Adding captain card:", captainCard);
        try {
          this.captainStock.addCard(captainCard);
          console.log("Captain card added successfully");
        } catch (error) {
          console.error("Error adding captain card:", error);
        }
      }
      
      // Add player's ship upgrade cards
      if (gamedatas.player_ship_upgrades && gamedatas.player_ship_upgrades.length > 0) {
        gamedatas.player_ship_upgrades.forEach((upgrade, index) => {
          const upgradeCard = {
            id: `upgrade-${upgrade.upgrade_key}`,
            cardKey: upgrade.upgrade_key,
            category: 'ship_upgrade',
            isActivated: upgrade.is_activated == 1
          };
          
          console.log(`Adding upgrade card ${index + 1}:`, upgradeCard);
          try {
            this.upgradesStock.addCard(upgradeCard);
            console.log(`Upgrade card ${index + 1} added successfully`);
            
            this.updateUpgradeCardVisual(upgradeCard);
          } catch (error) {
            console.error(`Error adding upgrade card ${index + 1}:`, error);
          }
        });
      }
    },

    /**
     * Update upgrade card visual based on activation status
     */
    updateUpgradeCardVisual: function(upgradeCard) {
      if (upgradeCard.isActivated) {
        this.nonPlayableCardsManager.flipCard(upgradeCard);
      }
    }
  };
});









