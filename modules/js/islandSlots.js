/**
 * Seas of Havoc - Island Slots Module
 * Handles island slot display and skiff slot positioning
 */

define([
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/dom-style",
  "dojo/query",
], function(domClass, domConstruct, domStyle, query) {
  
  return {
    /**
     * Update island slots display (skiffs on islands, market, etc.)
     */
    updateIslandSlots: function(islandslots, players) {
      console.log("updating island slots");
      console.log(islandslots);
      console.log(players);
      
      // First, clear all existing skiffs from island slots
      query(".skiff_placed").forEach(domConstruct.destroy);
      
      for (const [slot, numbers] of Object.entries(islandslots)) {
        for (const [number, slotData] of Object.entries(numbers)) {
          console.log("slot: " + slot + " number: " + number + " slot data: ", slotData);
          const skiff_slot_id = `skiff_slot_${slot}_${number}`;
          const skiff_slot = $(skiff_slot_id);
          console.log("skiff slot: " + skiff_slot_id);
          
          if (!skiff_slot) {
            console.warn("Skiff slot not found: " + skiff_slot_id);
            continue;
          }
          
          // Clear any existing skiffs from this slot
          query(".skiff", skiff_slot).forEach(domConstruct.destroy);
          
          // Handle disabled slots
          if (slotData.disabled) {
            domClass.add(skiff_slot_id, "disabled");
            console.log("Disabled slot: " + skiff_slot_id);
          } else {
            domClass.remove(skiff_slot_id, "disabled");
          }
          
          // Handle occupied slots
          if (slotData.occupying_player_id != null) {
            // For market slots, check if the card in this slot is in pending purchases
            if (slot == "market") {
              var slot_index = parseInt(number.substring(1)) - 1;
              
              var card_id_for_slot = null;
              if (this.gamedatas && this.gamedatas.market) {
                var market_array = Array.isArray(this.gamedatas.market) ? 
                                   this.gamedatas.market : 
                                   Object.values(this.gamedatas.market);
                if (market_array.length > slot_index && market_array[slot_index]) {
                  card_id_for_slot = market_array[slot_index].id;
                }
              }
              
              // Check if this card is in pending purchases for the current player
              if (card_id_for_slot && this.gamedatas && this.gamedatas.pending_purchases) {
                var pending_array = Array.isArray(this.gamedatas.pending_purchases) ? 
                                   this.gamedatas.pending_purchases : 
                                   Object.values(this.gamedatas.pending_purchases);
                var is_pending = pending_array.indexOf(card_id_for_slot) !== -1;
                
                if (is_pending && slotData.occupying_player_id == this.player_id) {
                  console.log("Skipping skiff for slot " + number + " - card " + card_id_for_slot + " is pending purchase");
                  domClass.add(skiff_slot_id, "unoccupied");
                  continue;
                }
              }
            }
            
            var skiff_id = "skiff_p" + slotData.occupying_player_id + "_" + slot + "_" + number;
            console.log("skiff_id: " + skiff_id);

            var skiff = this.format_block("jstpl_skiff", {
              player_color: players[slotData.occupying_player_id].color,
              id: skiff_id,
            });
            
            domConstruct.place(skiff, skiff_slot);
            domClass.remove(skiff_slot_id, "unoccupied");
            domClass.add(skiff_id, "skiff_placed");
          } else {
            // Slot is unoccupied
            domClass.add(skiff_slot_id, "unoccupied");
            
            // For market slots, position and show/hide based on whether there's a card
            if (slot == "market") {
              var market_slot_id = "market_slot_n" + number.substring(1);
              var slot_index = parseInt(number.substring(1)) - 1;
              
              var has_card = false;
              if (this.gamedatas && this.gamedatas.market) {
                var market_array = Array.isArray(this.gamedatas.market) ? 
                                   this.gamedatas.market : 
                                   Object.values(this.gamedatas.market);
                if (market_array.length > slot_index && market_array[slot_index]) {
                  var card_id = market_array[slot_index].id;
                  if (this.gamedatas.pending_purchases) {
                    var pending_array = Array.isArray(this.gamedatas.pending_purchases) ? 
                                       this.gamedatas.pending_purchases : 
                                       Object.values(this.gamedatas.pending_purchases);
                    if (pending_array.indexOf(card_id) === -1) {
                      has_card = true;
                    }
                  } else {
                    has_card = true;
                  }
                }
              }
              
              if (!has_card) {
                domStyle.set(skiff_slot_id, "display", "none");
              } else {
                var market_slot = $(market_slot_id);
                if (market_slot) {
                  this.positionMarketSkiffSlot(skiff_slot_id, market_slot_id);
                  domStyle.set(skiff_slot_id, "display", "block");
                }
              }
            }
          }
        }
      }
    },

    /**
     * Position a single market skiff slot inside its corresponding market slot container
     * so it naturally moves with the card
     */
    positionMarketSkiffSlot: function(skiff_slot_id, market_slot_id) {
      var skiff_slot = $(skiff_slot_id);
      if (!skiff_slot) {
        return;
      }
      
      var slot_container = null;
      if (this.market && this.market.slots && this.market.slots[market_slot_id]) {
        slot_container = this.market.slots[market_slot_id];
      } else {
        slot_container = $(market_slot_id);
      }
      
      if (!slot_container) {
        return;
      }
      
      // Ensure the slot container has position relative for absolute child positioning
      domStyle.set(slot_container, "position", "relative");
      
      // Move skiff slot inside the card slot container if not already there
      if (skiff_slot.parentNode !== slot_container) {
        slot_container.appendChild(skiff_slot);
      }
    },

    /**
     * Position all market skiff slots
     */
    positionMarketSkiffSlots: function() {
      for (var i = 1; i <= 5; i++) {
        var market_slot_id = "market_slot_n" + i;
        var skiff_slot_id = "skiff_slot_market_n" + i;
        var skiff_slot = $(skiff_slot_id);
        
        if (!skiff_slot) {
          continue;
        }
        
        var slot_container = null;
        if (this.market && this.market.slots && this.market.slots[market_slot_id]) {
          slot_container = this.market.slots[market_slot_id];
        } else {
          slot_container = $(market_slot_id);
        }
        
        if (slot_container) {
          var slot_index = i - 1;
          var has_card = false;
          if (this.gamedatas && this.gamedatas.market) {
            var market_array = Array.isArray(this.gamedatas.market) ? 
                               this.gamedatas.market : 
                               Object.values(this.gamedatas.market);
            if (market_array.length > slot_index && market_array[slot_index]) {
              var card_id = market_array[slot_index].id;
              if (this.gamedatas.pending_purchases) {
                var pending_array = Array.isArray(this.gamedatas.pending_purchases) ? 
                                   this.gamedatas.pending_purchases : 
                                   Object.values(this.gamedatas.pending_purchases);
                if (pending_array.indexOf(card_id) === -1) {
                  has_card = true;
                }
              } else {
                has_card = true;
              }
            }
          }
          
          if (has_card) {
            this.positionMarketSkiffSlot(skiff_slot_id, market_slot_id);
            domStyle.set(skiff_slot, "display", "block");
          } else {
            domStyle.set(skiff_slot, "display", "none");
          }
        }
      }
    }
  };
});









