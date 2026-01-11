/**
 * Seas of Havoc - Notifications Module
 * Game notification handlers
 */

define([
  "dojo",
  "dojo/dom",
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/dom-style",
  "dojo/dom-attr",
  "dojo/_base/fx",
  "dojo/fx",
  "dojo/query",
], function(dojo, dom, domClass, domConstruct, domStyle, attr, baseFX, fx, query) {
  
  return {
    /**
     * Set up all notification subscriptions
     */
    setupNotifications: function() {
      console.log("notifications subscriptions setup");
      this.bgaSetupPromiseNotifications();
      
      dojo.subscribe('cardScrapped', this, 'notif_cardScrapped');
      dojo.subscribe('cardsDiscarded', this, 'notif_cardsDiscarded');
      dojo.subscribe('deckReshuffled', this, 'notif_deckReshuffled');
      dojo.subscribe('cardsPurchased', this, 'notif_cardsPurchased');
      dojo.subscribe('marketUpdated', this, 'notif_marketUpdated');
    },

    /**
     * Deck size changed notification
     */
    notif_deckSizeChanged: function(args) {
      console.log("notify: deck size changed");
      console.log(args);
      if (args.player_id == this.player_id) {
        this.updateDeckCount(args.deck_size);
      }
    },

    /**
     * Show resource choice dialog notification
     */
    notif_showResourceChoiceDialog: function(args) {
      console.groupCollapsed("show resource choice dialog");

      this.clientStateVars.slot_context = args.context;
      this.clientStateVars.slot_number = args.context_number;
      console.log("context: " + this.clientStateVars.slot_context);
      console.log("number: " + this.clientStateVars.slot_number);

      this.setClientState("client_resourceDialog", {
        descriptionmyturn: _("${you} must select a resource"),
      });

      console.groupEnd();
    },

    /**
     * Dummy start notification
     */
    notif_dummyStart: function(args) {
      console.log("dummystart");
      this.bgaPerformAction("actExitDummyStart", {});
    },

    /**
     * Resources changed notification
     */
    notif_resourcesChanged: function(args) {
      console.groupCollapsed("notify: resources changed");
      console.log("Notification: resourcesChanged");
      console.log(args.resources);
      console.log("current resources:");
      console.log(this.resources);

      this.resources = args.resources;
      this.updateResources(args.resources);
      console.groupEnd();
    },

    /**
     * Skiff placed notification
     */
    notif_skiffPlaced: function(args) {
      console.groupCollapsed("notify: skiff placed");
      console.log(args);

      var slot_name = args.slot_name;
      var slot_number = args.slot_number;
      var postfix = "_" + slot_name + "_" + slot_number;
      var skiff_id = "skiff_p" + args.player_id + postfix;

      this.islandSlots[slot_name][slot_number] = {
        occupying_player_id: args.player_id,
        disabled: this.islandSlots[slot_name][slot_number]?.disabled || false
      };

      console.log("skiff_id: " + skiff_id);
      var player_board_id = "overall_player_board_" + args.player_id;
      console.log("player board id: " + player_board_id);

      var skiff = this.format_block("jstpl_skiff", {
        player_color: args.player_color,
        id: skiff_id,
      });
      var skiff_slot = query(`.skiff_slot[data-slotname="${slot_name}"][data-number="${slot_number}"]`)[0];
      console.log("skiff slot:");
      console.log(skiff_slot);

      domConstruct.place(skiff, skiff_slot);
      this.placeOnObject(skiff_id, player_board_id);
      domStyle.set(skiff_id, "zIndex", 1);
      this.slideToObject(skiff_id, skiff_slot, 1000).play();
      domClass.remove(skiff_slot, "unoccupied");
      domClass.add(skiff_id, "skiff_placed");
      console.groupEnd();
    },

    /**
     * Cards purchased notification
     */
    notif_cardsPurchased: function(args) {
      console.groupCollapsed("notify: cards purchased");
      console.log(args);
      
      var purchases = args.purchases || {};
      
      for (var player_id in purchases) {
        var card_ids = purchases[player_id];
        for (var i = 0; i < card_ids.length; i++) {
          var card_id = card_ids[i];
          
          var market_cards = this.market.getCards();
          var purchased_card = market_cards.find(card => card.id == card_id);
          
          if (purchased_card) {
            this.market.removeCard(purchased_card);
            
            var card_div = this.cardsManager.getCardElement(purchased_card);
            if (card_div) {
              domClass.remove(card_div, "purchased");
              var skiff_slot = query(`.skiff_slot`, card_div)[0];
              if (skiff_slot) {
                query(".skiff", skiff_slot).forEach(domConstruct.destroy);
                query(".purchase_card_button", card_div).forEach(domConstruct.destroy);
                domClass.add(skiff_slot, "unoccupied");
                domStyle.set(skiff_slot, "display", "none");
              }
            }
          }
        }
      }
      
      query(".skiff_slot[data-slotname='market']").forEach(function(slot) {
        query(".skiff", slot).forEach(domConstruct.destroy);
        query(".purchase_card_button", slot.parentElement).forEach(domConstruct.destroy);
        domClass.add(slot, "unoccupied");
        var slot_number = attr.get(slot, "data-number");
        var market_slot_id = "market_slot_n" + slot_number.substring(1);
        var market_slot = $(market_slot_id);
        if (market_slot) {
          var has_card = query(".card", market_slot).length > 0;
          if (!has_card) {
            domStyle.set(slot, "display", "none");
          }
        }
      });
      
      this.cards_purchased = [];
      
      console.groupEnd();
    },

    /**
     * Market updated notification
     */
    notif_marketUpdated: function(args) {
      console.groupCollapsed("notify: market updated");
      console.log(args);
      
      var existing_cards = this.market.getCards();
      for (var i = 0; i < existing_cards.length; i++) {
        this.market.removeCard(existing_cards[i]);
      }
      
      this.marketSlotMap = {};
      
      var market_array = Array.isArray(args.market) ? args.market : Object.values(args.market);
      for (var i = 0; i < market_array.length; i++) {
        var card = market_array[i];
        if (!card || !card.id) {
          continue;
        }
        
        var slot_id = "market_slot_n" + (i + 1);
        
        this.marketSlotMap[card.id] = slot_id;
        
        var cardObj = {
          id: card.id,
          type: card.type,
          location: "market"
        };
        
        this.market.addCard(cardObj);
        
        var card_div = this.cardsManager.getCardElement(cardObj);
        if (card_div) {
          attr.set(card_div, "data-slotnumber", "n" + (i + 1));
          attr.set(card_div, "data-cardid", card.id);
        }
      }
      
      if (this.gamedatas) {
        this.gamedatas.market = args.market;
      }
      
      this.positionMarketSkiffSlots();
      
      if (this.islandSlots && this.players) {
        this.updateIslandSlots(this.islandSlots, this.players);
      }
      
      console.groupEnd();
    },

    /**
     * Token acquired notification
     */
    notif_tokenAcquired: function(args) {
      console.groupCollapsed("notify: token acquired");
      console.log(args);

      var token_key = args.token_key;
      var player_id = args.player_id;

      var tokens = query("#" + token_key);
      var token_element = null;
      if (token_element != null) {
        token_element = tokens[0];
      } else {
        console.log("creating token");
        var token_html = this.format_block("jstpl_unique_token", { token_key: args.token_key });
        if (token_key == "first_player_token") {
          token_element = domConstruct.place(token_html, `skiff_slot_capitol_n1`);
        } else {
          token_element = domConstruct.place(token_html, `skiff_slot_${token_key}_n1`);
        }
      }

      this.unique_tokens[token_key] = args.player_id;

      if (player_id != null) {
        var target = `${token_key}_p${player_id}`;
        domStyle.set(token_element, "zIndex", 1);
        this.slideToObject(token_element, target, 1000).play();
      }
      console.groupEnd();
    },

    /**
     * Card played notification (handles ship movement animations)
     */
    notif_cardPlayed: function(args) {
      console.groupCollapsed("notify: card played");
      console.log(args);
      var shipid = "player_ship_" + args.player_id;

      var anims = [];
      for (var move of args.moveChain) {
        console.log("processing move");
        console.log(move);
        switch (move.type) {
          case "move": {
            if (move.teleport_at != null) {
              let target_id = "seaboardlocation_" + move.teleport_at.x + "_" + move.teleport_at.y;
              let forward = this.slideToObject(shipid, target_id, 1000);
              anims.push(forward);
              target_id = "seaboardlocation_" + move.teleport_to.x + "_" + move.teleport_to.y;
              anims.push(this.slideToObject(shipid, target_id, 0));
            }
            var target_id = "seaboardlocation_" + move.new_x + "_" + move.new_y;
            anims.push(this.slideToObject(shipid, target_id, 1000));
            break;
          }
          case "turn": {
            let player_ship = this.getObjectOnSeaboard("player_ship", args.player_id);
            console.log(
              args.player_id +
                " my old heading " +
                player_ship.heading +
                " event old heading " +
                move.old_heading +
                " new heading " +
                move.new_heading,
            );
            let current_deg = this.getHeadingDegrees(player_ship.heading);
            let target_deg = this.getHeadingDegrees(move.new_heading);
            let diff = Math.abs(target_deg - current_deg);
            let curve = [current_deg, target_deg];
            console.log("target: " + target_deg + " current: " + current_deg + " diff: " + diff);
            if (diff > 180) {
              if (target_deg > current_deg) {
                curve = [current_deg, -(360 - target_deg)];
                console.log("adjusted target to: " + -(360 - target_deg));
              } else {
                curve = [-(360 - current_deg), target_deg];
                console.log("adjusted current to: " + -(360 - current_deg));
              }
            }
            let anim = new baseFX.Animation({
              curve: curve,
              onAnimate: function(v) {
                domStyle.set(shipid, "rotate", v + "deg");
              },
            });
            console.log(anim);
            anims.push(anim);
            player_ship.heading = move.new_heading;
            console.log(this.seaboard);
            break;
          }
          case "fire_hit": {
            let cannon_fire = this.format_block("jstpl_cannon_fire", {});
            let rotation = this.getHeadingDegrees(move.fire_heading);
            console.log("fire rotation " + rotation);
            domConstruct.place(cannon_fire, shipid);
            domStyle.set("cannonfire", "rotate", rotation + "deg");
            let offset = null;
            var NORTH = 1, SOUTH = 3, EAST = 2, WEST = 4;
            switch (move.fire_heading) {
              case NORTH:
                offset = ["top", "20px"];
                break;
              case SOUTH:
                offset = ["top", "-20px"];
                break;
              case EAST:
                offset = ["left", "20px"];
                break;
              case WEST:
                offset = ["left", "-20px"];
                break;
            }
            console.log(offset);
            domStyle.set("cannonfire", "rotate", rotation + "deg");
            domStyle.set("cannonfire", offset[0], offset[1], rotation + "deg");
            let explosion = this.format_block("jstpl_explosion", {});
            let target_id = "seaboardlocation_" + move.hit_x + "_" + move.hit_y;
            domConstruct.place(explosion, target_id);
            domStyle.set("explosion", "opacity", "0");
            domStyle.set("cannonfire", "opacity", 0);
            fx.chain([
              baseFX.fadeIn({ node: "cannonfire", duration: 100 }),
              baseFX.fadeOut({
                node: "cannonfire",
                duration: 100,
                delay: 1000,
                onEnd: function() {
                  domConstruct.destroy("cannonfire");
                },
              }),
            ]).play();
            fx.chain([
              baseFX.fadeIn({ node: "explosion", delay: 100 }),
              baseFX.fadeOut({
                node: "explosion",
                delay: 1000,
                onEnd: function() {
                  domConstruct.destroy("explosion");
                },
              }),
            ]).play();
            break;
          }
        }
      }
      console.log(anims);
      if (anims.length) {
        fx.chain(anims).play();
      }
      console.groupEnd();
    },

    /**
     * Score notification
     */
    notif_score: function(args) {
      console.log("score for " + args.player_id + " " + args.player_score);
      this.scoreCtrl[args.player_id].setValue(args.player_score);
    },

    /**
     * Damage received notification
     */
    notif_damageReceived: function(args) {
      console.log("notify damage received");
      let damage_card = args.damage_card;
      let player_id = args.player_id;
      var shipid = "player_ship_" + args.player_id;
      if (player_id == this.player_id) {
        this.playerDiscard.addCard(damage_card.type, damage_card.id);
      }
    },

    /**
     * Card drawn notification
     */
    notif_cardDrawn: function(args) {
      console.log("notify card drawn");
      let cards = args.cards;
      let player_id = args.player_id;
      console.log("Cards drawn:", cards);
      console.log("Player ID:", player_id);
      if (player_id == this.player_id) {
        this.updateDeckCount(args.deck_size);
        let fromElement = dom.byId("mydeck");
        console.log("fromElement: " + fromElement);
        
        cards.forEach(card => {
          this.playerHand.addCard(
            {
              id: card.id,
              type: card.type,
              location: "hand",
            },
            {
              fromElement: fromElement,
              originalSide: "back",
            },
          );
        });
      }
    },

    /**
     * Card scrapped notification
     */
    notif_cardScrapped: function(args) {
      console.log("notify card scrapped");
      
      let card = args.card;
      let player_id = args.player_id;
      let original_location = args.original_location;
      
      if (!card || !card.id) {
        console.error("Invalid card data in cardScrapped notification:", card);
        return;
      }
      
      if (original_location === "hand" && player_id == this.player_id) {
        this.playerHand.removeCard({id: card.id});
      } else if (original_location === "player_discard" && player_id == this.player_id) {
        this.playerDiscard.removeCard({id: card.id});
      }
      
      this.scrapPile.addCard({
        id: card.id,
        type: card.type,
        location: "scrap"
      });
      
      this.cleanupScrapCardSelection();
    },

    /**
     * Cards discarded notification
     */
    notif_cardsDiscarded: function(args) {
      console.log("notify cards discarded");
      let cards = args.cards;
      let player_id = args.player_id;
      console.log("Cards discarded:", cards);
      console.log("Player ID:", player_id);
      
      if (player_id == this.player_id) {
        cards.forEach(card => {
          this.playerHand.removeCard({id: card.id});
          this.playerDiscard.addCard({
            id: card.id,
            type: card.type,
            location: "discard"
          });
        });
      } else {
        cards.forEach(card => {
          this.playerDiscard.addCard({
            id: card.id,
            type: card.type,
            location: "discard"
          });
        });
      }
    },

    /**
     * Deck reshuffled notification
     */
    notif_deckReshuffled: function(args) {
      console.log("notify deck reshuffled");
      let player_id = args.player_id;
      let deck_size = args.deck_size;
      console.log(`Player ${player_id} deck reshuffled, new deck size: ${deck_size}`);
      
      if (player_id == this.player_id) {
        this.playerDiscard.removeAll();
        this.updateDeckCount(deck_size);
      }
    }
  };
});









