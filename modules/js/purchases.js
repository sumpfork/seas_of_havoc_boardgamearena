/**
 * Seas of Havoc - Card Purchases Module
 * Card purchase logic and UI
 */

define([
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/dom-style",
  "dojo/dom-attr",
  "dojo/html",
  "dojo/_base/lang",
  "dojo/query",
], function(domClass, domConstruct, domStyle, attr, html, lang, query) {
  
  return {
    /**
     * Update card purchase buttons on market cards
     */
    updateCardPurchaseButtons: function(create) {
      console.groupCollapsed("update card purchase buttons");
      
      // Check if player has completed purchases
      if (this.gamedatas && this.gamedatas.player_has_completed_purchases) {
        console.log("Player has completed purchases, skipping purchase button creation");
        return;
      }
      
      var pending_card_ids = this.cards_purchased || [];
      
      for (const [slot, numbers] of Object.entries(this.islandSlots)) {
        if (slot == "market") {
          for (const [number, info] of Object.entries(numbers)) {
            const occupant = info.occupying_player_id;
            if (occupant == this.player_id) {
              var button_id = "purchase_button_" + number;
              console.log("number " + number + " is ours");
              var slot_card = null;
              var slotnum = number;
              
              var slot_id = "market_slot_n" + number.substring(1);
              var skiff_slot_id = "skiff_slot_market_" + number;
              var slot_element = $(slot_id);
              if (!slot_element) {
                throw new Error("Market slot element not found: " + slot_id);
              }
              
              if (!this.market.slots || !this.market.slots[slot_id]) {
                throw new Error("SlotStock slot not found: " + slot_id);
              }
              
              var slot_index = parseInt(number.substring(1)) - 1;
              var market_array = Array.isArray(this.gamedatas.market) ? 
                                 this.gamedatas.market : 
                                 Object.values(this.gamedatas.market);
              
              if (market_array.length <= slot_index || !market_array[slot_index]) {
                console.log("No card at slot position " + number + " (index " + slot_index + "), skipping purchase button");
                continue;
              }
              
              var card_data = market_array[slot_index];
              
              for (const cardspec of this.market.getCards()) {
                if (String(cardspec.id) == String(card_data.id)) {
                  slot_card = cardspec;
                  console.log("Found card " + slot_card.id + " in slot " + number);
                  break;
                }
              }
              
              if (!slot_card) {
                console.log("Card spec not found for card ID " + card_data.id + " in slot " + number + ", skipping purchase button");
                continue;
              }
              
              var card_element = this.cardsManager.getCardElement(slot_card);
              if (!card_element) {
                console.log("Card element not found for card " + slot_card.id + " in slot " + number + ", skipping purchase button");
                continue;
              }
              
              if (pending_card_ids.indexOf(slot_card.id) !== -1) {
                console.log("Card " + slot_card.id + " is already purchased, skipping purchase button");
                continue;
              }
              
              console.log(slot_card);
              var card = this.playable_cards[slot_card.type];
              console.log(card);
              
              if (create) {
                // Remove any existing button for this slot first
                var existing_button = $(button_id);
                if (existing_button) {
                  domConstruct.destroy(existing_button);
                }
                
                var cardIdValue = String(slot_card.id);
                var purchase_button = this.format_block("jstpl_card_purchase_button", {
                  id: button_id,
                  slotnumber: slotnum,
                  cardid: cardIdValue,
                });
                
                // Get the SlotStock's slot element
                var slot_container = this.market.slots[slot_id];
                if (!slot_container) {
                  console.error("Slot container not found for: " + slot_id);
                  continue;
                }
                
                // Ensure slot container has position:relative for absolute positioning
                domStyle.set(slot_container, "position", "relative");
                
                // Place button at end of slot container
                domConstruct.place(purchase_button, slot_container);
              }
              
              var buttonNodes = query("#" + button_id);

              buttonNodes.forEach(function(node) {
                if (node._purchaseHandler) {
                  node._purchaseHandler.remove();
                }
              });

              var handler = buttonNodes.on("click", lang.hitch(this, "onClickPurchaseButton"));

              buttonNodes.forEach(function(node) {
                node._purchaseHandler = handler;
              });

              if (!this.canPlayerAfford(card.cost)) {
                console.log("disabling");
                buttonNodes.forEach(function(node) {
                  domClass.remove(node, "bgabutton_green");
                  domClass.add(node, "disabled");
                  html.set(node, "Cannot Afford");
                });
              } else {
                console.log("enabling purchase button");
                buttonNodes.forEach(function(node) {
                  domClass.add(node, "bgabutton_green");
                  domClass.remove(node, "disabled");
                  html.set(node, "Purchase Card");
                });
              }
            }
          }
        }
      }
      console.groupEnd();
    },

    /**
     * Handle purchase button click
     */
    onClickPurchaseButton: function(event) {
      console.groupCollapsed("card purchase button clicked");
      console.log("=== PURCHASE BUTTON CLICKED ===");
      console.log("onClickPurchaseButton function called!");
      console.log("Event object:", event);
      console.log("Event type:", event.type);
      console.log("Event target:", event.target);
      console.log("Event currentTarget:", event.currentTarget);
      event.preventDefault();
      
      const source = event.target || event.srcElement;
      const slotnumber = source.dataset.slotnumber;
      const card_id = source.dataset.cardid;
      console.log("slotnumber: " + slotnumber);
      console.log("card_id: " + card_id);
      
      if (!card_id) {
        console.error("Card ID not found on button for slotnumber: " + slotnumber);
        console.groupEnd();
        return;
      }
      
      var slot_card = this.market.getCards().find(card => card.id == card_id);
      console.log(slot_card);
      if (!slot_card) {
        console.error("Card not found in market for card_id: " + card_id);
        console.groupEnd();
        return;
      }
      
      var card = this.playable_cards[slot_card.type];
      if (!card) {
        console.error("Playable card not found for type: " + slot_card.type);
        console.groupEnd();
        return;
      }
      
      this.playerSpendResources(card.cost);
      console.log(card);
      console.log(slot_card);
      
      // Remove purchase button before moving card to hand
      var purchase_button = query(`.purchase_card_button[data-slotnumber="${slotnumber}"]`)[0];
      if (purchase_button) {
        domConstruct.destroy(purchase_button);
      }
      
      // Clean up skiff slot and skiff for this market slot
      var skiff_slot_id = "skiff_slot_market_" + slotnumber;
      var skiff_slot = $(skiff_slot_id);
      if (skiff_slot) {
        query(".skiff", skiff_slot).forEach(domConstruct.destroy);
        domClass.add(skiff_slot, "unoccupied");
        domStyle.set(skiff_slot, "display", "none");
        if (this.islandSlots && this.islandSlots.market && this.islandSlots.market[slotnumber]) {
          this.islandSlots.market[slotnumber].occupying_player_id = null;
        }
      }
      
      // Add card to hand (local preview)
      this.playerHand.addCard({
        id: slot_card.id,
        type: slot_card.type,
        location: "hand",
      });
      
      // Remove card from market SlotStock
      this.market.removeCard(slot_card);
      
      this.updateCardPurchaseButtons(false);
      this.cards_purchased.push(slot_card.id);
      console.groupEnd();
    },

    /**
     * Handle complete purchases button click
     */
    onCompletePurchasesClicked: function(event) {
      console.log("onCompletePurchasesClicked");
      console.log(this.cards_purchased);
      
      // Remove all purchase buttons
      query(".purchase_card_button").forEach(domConstruct.destroy);
      
      this.bgaPerformAction("actCompletePurchases", {
        cards_purchased: JSON.stringify(this.cards_purchased),
      });
    }
  };
});









