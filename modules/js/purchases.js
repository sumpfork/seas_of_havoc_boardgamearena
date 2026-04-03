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
      
      var purchasedEntries = this.cards_purchased || [];
      var pending_card_ids = purchasedEntries.map(function(e) {
        return (typeof e === "object") ? e.card_id : e;
      });
      
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
      event.preventDefault();
      
      const source = event.target || event.srcElement;
      const slotnumber = source.dataset.slotnumber;
      const card_id = source.dataset.cardid;
      
      if (!card_id) {
        console.error("Card ID not found on button for slotnumber: " + slotnumber);
        console.groupEnd();
        return;
      }
      
      var slot_card = this.market.getCards().find(card => card.id == card_id);
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

      // Merchant ability: ask how many doubloons to substitute for cannonballs
      var cannonballCost = card.cost.cannonball || 0;
      if (this.player_captain === "merchant" && cannonballCost > 0) {
        var playerRes = this.getPlayerResources();
        var doubloonAvail = (playerRes.doubloon || 0) - (card.cost.doubloon || 0);
        if (doubloonAvail > 0) {
          var cannonballAvail = playerRes.cannonball || 0;
          // Max substitution: limited by cannonball cost and available surplus doubloons
          var maxSub = Math.min(cannonballCost, doubloonAvail);
          // Min substitution: if not enough cannonballs, must substitute some
          var minSub = Math.max(0, cannonballCost - cannonballAvail);

          if (minSub < maxSub) {
            // Player has a real choice — show dialog
            this._pendingMerchantPurchase = {
              slot_card: slot_card, card: card, slotnumber: slotnumber,
              minSub: minSub, maxSub: maxSub,
            };
            this.setClientState("client_merchantSubstitute", {
              descriptionmyturn: _("How many doubloons to spend as cannonballs? (${min}-${max})").replace("${min}", minSub).replace("${max}", maxSub),
            });
            console.groupEnd();
            return;
          } else {
            // No choice: must substitute exactly minSub (== maxSub)
            this._pendingDoubloonsAsCannonballs = minSub;
          }
        }
      }

      // Check if player has an unused booty token with resources that overlap this card's cost
      var tokenRes = (!this._bootyUsedForPurchase) ? this.getMyBootyTokenRes() : null;
      var bootyOverlap = this.bootyOverlapsCost(tokenRes, card.cost);

      if (bootyOverlap) {
        var canAffordWithout = this.canPlayerAfford(card.cost, false);

        if (!canAffordWithout) {
          this._finalizePurchase(slot_card, card, slotnumber, true);
          console.groupEnd();
          return;
        }

        // Can afford either way — ask the player via client state in the status bar
        this._pendingPurchase = { slot_card: slot_card, card: card, slotnumber: slotnumber };
        this.setClientState("client_bootyPurchaseConfirm", {
          descriptionmyturn: _("Use your booty token to help pay for this card?"),
        });
        console.groupEnd();
        return;
      }

      // No booty applicable — purchase immediately
      this._finalizePurchase(slot_card, card, slotnumber, false);
      console.groupEnd();
    },

    /**
     * Finalize a card purchase: move card, spend resources, record in cards_purchased.
     */
    _finalizePurchase: function(slot_card, card, slotnumber, useBooty) {
      var effectiveCost = Object.assign({}, card.cost);

      // Apply Merchant doubloon-as-cannonball substitution
      var doubloonsAsCannonballs = this._pendingDoubloonsAsCannonballs || 0;
      this._pendingDoubloonsAsCannonballs = 0;
      if (doubloonsAsCannonballs > 0) {
        effectiveCost.cannonball = (effectiveCost.cannonball || 0) - doubloonsAsCannonballs;
        effectiveCost.doubloon = (effectiveCost.doubloon || 0) + doubloonsAsCannonballs;
        if (effectiveCost.cannonball <= 0) delete effectiveCost.cannonball;
      }

      if (useBooty) {
        var tokenRes = this.getMyBootyTokenRes();
        if (tokenRes) {
          var bootyResolved = this.resolveBootyResources(tokenRes, effectiveCost);
          effectiveCost = this.computeEffectiveCost(effectiveCost, bootyResolved);
          var msg = this.formatBootyUsageMessage(bootyResolved, effectiveCost);
          if (msg) this.showMessage(msg, "info");
        }
      }

      this.playerSpendResources(effectiveCost);

      var purchase_button = query(`.purchase_card_button[data-slotnumber="${slotnumber}"]`)[0];
      if (purchase_button) {
        domConstruct.destroy(purchase_button);
      }

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

      this.playerHand.addCard({
        id: slot_card.id,
        type: slot_card.type,
        location: "hand",
      });

      this.market.removeCard(slot_card);

      var entry = {
        card_id: slot_card.id,
        slot_card: { id: slot_card.id, type: slot_card.type },
        slotnumber: slotnumber,
        doubloons_as_cannonballs: doubloonsAsCannonballs,
      };
      if (useBooty) {
        entry.use_booty_card_id = this.booty_tokens[0].id;
        this._bootyUsedForPurchase = true;
        this.booty_tokens = [];
        this.updateMyBootyToken();
      }
      this.cards_purchased.push(entry);

      // Re-evaluate buttons after resources and booty state are fully updated
      this.updateCardPurchaseButtons(false);
    },

    /**
     * Called from onUpdateActionButtons for client_bootyPurchaseConfirm state.
     */
    onBootyPurchaseYes: function() {
      var ctx = this._pendingPurchase;
      this._pendingPurchase = null;
      this._restoringFromBootyConfirm = true;
      this.restoreServerGameState();
      this._restoringFromBootyConfirm = false;
      if (ctx) {
        this._finalizePurchase(ctx.slot_card, ctx.card, ctx.slotnumber, true);
      }
    },

    onBootyPurchaseNo: function() {
      var ctx = this._pendingPurchase;
      this._pendingPurchase = null;
      this._restoringFromBootyConfirm = true;
      this.restoreServerGameState();
      this._restoringFromBootyConfirm = false;
      if (ctx) {
        this._finalizePurchase(ctx.slot_card, ctx.card, ctx.slotnumber, false);
      }
    },

    onMerchantSubstituteChosen: function(amount) {
      var ctx = this._pendingMerchantPurchase;
      this._pendingMerchantPurchase = null;
      this._pendingDoubloonsAsCannonballs = amount;
      this._restoringFromBootyConfirm = true;
      this.restoreServerGameState();
      this._restoringFromBootyConfirm = false;
      if (ctx) {
        // Continue to booty check / finalize
        this.onClickPurchaseButton_afterMerchant(ctx.slot_card, ctx.card, ctx.slotnumber);
      }
    },

    onMerchantSubstituteCancel: function() {
      this._pendingMerchantPurchase = null;
      this._pendingDoubloonsAsCannonballs = 0;
      this._restoringFromBootyConfirm = true;
      this.restoreServerGameState();
      this._restoringFromBootyConfirm = false;
    },

    /**
     * Continue purchase flow after Merchant substitution has been chosen.
     * This runs the booty-token check and finalization.
     */
    onClickPurchaseButton_afterMerchant: function(slot_card, card, slotnumber) {
      // Check if player has an unused booty token with resources that overlap this card's cost
      var tokenRes = (!this._bootyUsedForPurchase) ? this.getMyBootyTokenRes() : null;
      var bootyOverlap = this.bootyOverlapsCost(tokenRes, card.cost);

      if (bootyOverlap) {
        var canAffordWithout = this.canPlayerAfford(card.cost, false);

        if (!canAffordWithout) {
          this._finalizePurchase(slot_card, card, slotnumber, true);
          return;
        }

        this._pendingPurchase = { slot_card: slot_card, card: card, slotnumber: slotnumber };
        this.setClientState("client_bootyPurchaseConfirm", {
          descriptionmyturn: _("Use your booty token to help pay for this card?"),
        });
        return;
      }

      this._finalizePurchase(slot_card, card, slotnumber, false);
    },

    onBootyPurchaseCancel: function() {
      this._pendingPurchase = null;
      this._restoringFromBootyConfirm = true;
      this.restoreServerGameState();
      this._restoringFromBootyConfirm = false;
    },

    /**
     * Handle complete purchases button click
     */
    onCompletePurchasesClicked: function(event) {
      console.log("onCompletePurchasesClicked");
      console.log(this.cards_purchased);
      this._submitCompletePurchases(this.cards_purchased);
    },

    _submitCompletePurchases: function(cardsPayload) {
      if (!cardsPayload || cardsPayload.length === 0) {
        cardsPayload = [];
      }
      query(".purchase_card_button").forEach(domConstruct.destroy);
      this.bgaPerformAction("actCompletePurchases", {
        cards_purchased: JSON.stringify(cardsPayload),
      });
    },

    _savePurchaseSnapshot: function() {
      this._purchaseSnapshot = {
        resources: JSON.parse(JSON.stringify(this.resources)),
        booty_tokens: JSON.parse(JSON.stringify(this.booty_tokens || [])),
        marketOccupancy: {},
      };
      if (this.islandSlots && this.islandSlots.market) {
        for (var num in this.islandSlots.market) {
          this._purchaseSnapshot.marketOccupancy[num] = this.islandSlots.market[num].occupying_player_id;
        }
      }
    },

    onRestartPurchasesClicked: function() {
      var snap = this._purchaseSnapshot;
      if (!snap) {
        window.location.reload();
        return;
      }

      for (var i = this.cards_purchased.length - 1; i >= 0; i--) {
        var entry = this.cards_purchased[i];
        this.playerHand.removeCard({ id: entry.slot_card.id, type: entry.slot_card.type });
        this.market.addCard({ id: entry.slot_card.id, type: entry.slot_card.type, location: "market" });
      }

      this.resources = JSON.parse(JSON.stringify(snap.resources));
      this.updateResources(this.resources);

      this.booty_tokens = JSON.parse(JSON.stringify(snap.booty_tokens));
      this.updateMyBootyToken();

      if (this.islandSlots && this.islandSlots.market) {
        for (var num in snap.marketOccupancy) {
          this.islandSlots.market[num].occupying_player_id = snap.marketOccupancy[num];
        }
      }

      this.cards_purchased = [];
      this._bootyUsedForPurchase = false;
      this._pendingDoubloonsAsCannonballs = 0;

      query(".purchase_card_button").forEach(domConstruct.destroy);
      this.updateCardPurchaseButtons(true);

      this.updateIslandSlots(this.islandSlots, this.gamedatas.players);
      this.positionMarketSkiffSlots();
    }
  };
});









