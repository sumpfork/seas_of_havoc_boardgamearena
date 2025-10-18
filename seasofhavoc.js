/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SeasOfHavoc implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * seasofhavoc.js
 *
 * SeasOfHavoc user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

//could maybe share these via bga mechanisms
//but it's awkward, these have to match the php versions though
const NORTH = 1;
const EAST = 2;
const SOUTH = 3;
const WEST = 4;

define([
  "dojo",
  "dojo/_base/declare",
  "dojo/on",
  "dojo/dom",
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/html",
  "dojo/dom-style",
  "dojo/dom-attr",
  "dojo/_base/lang",
  "dojo/query",
  "dojo/_base/fx",
  "dojo/fx",
  "dojo/aspect",
  getLibUrl('bga-animations', '1.x'),
  getLibUrl('bga-cards', '1.x'),
  "dojo/NodeList-traverse",
  "dojo/NodeList-data",
  "ebg/core/gamegui",
  "ebg/counter",
], function (
  dojo,
  declare,
  on,
  dom,
  domClass,
  domConstruct,
  html,
  domStyle,
  attr,
  lang,
  query,
  baseFX,
  fx,
  aspect,
  BgaAnimations,
  BgaCards,
) {
  return declare("bgagame.seasofhavoc", ebg.core.gamegui, {
    constructor: function () {
      console.log("seasofhavoc constructor");

      // Here, you can init the global variables of your user interface
      // Example:
      // this.myGlobalValue = 0;
      this.clientStateVars = {};
    },
    getHeadingDegrees: function (direction) {
      switch (Number(direction)) {
        case NORTH:
          deg = 90;
          break;
        case EAST:
          deg = 180;
          break;
        case SOUTH:
          deg = 270;
          break;
        case WEST:
          deg = 0;
          break;
        default:
          console.error("couldn't convert " + direction + " to degrees");
      }
      console.log(deg + " degrees");
      return deg;
    },
    getObjectOnSeaboard: function (object_type, arg) {
      for (const entry of this.seaboard) {
        if (entry.type == object_type && entry.arg == arg) {
          return entry;
        }
      }
    },
    updateUniqueTokens: function (unique_tokens) {
      console.log("updating unique tokens");
      console.log(unique_tokens);
      for (const [token_key, player_id] of Object.entries(unique_tokens)) {
        console.log("token id: " + token_key + " player id: " + player_id);
        if (player_id === null) {
          continue;
        }
        var token_html = this.format_block("jstpl_unique_token", { token_key: token_key });
        var token_element = domConstruct.place(token_html, `player_token_board_p${player_id}`);
        this.placeOnObject(token_element, `${token_key}_p${player_id}`);
        domStyle.set(token_element, "zIndex", 1);
      }
    },
    updateResources: function (resources) {
      console.log("updating resources");
      console.log(resources);
      for (const resource of resources) {
        console.log(resource);
        console.log(`${resource.resource_key}count_p${resource.player_id}`);
        document.getElementById(`${resource["resource_key"]}count_p${resource["player_id"]}`).innerText =
          resource.resource_count;
      }
    },

    updateIslandSlots: function (islandslots, players) {
      console.log("updating island slots");
      console.log(islandslots);
      console.log(players);
      for (const [slot, numbers] of Object.entries(islandslots)) {
        for (const [number, occupant] of Object.entries(numbers)) {
          console.log("occupant: " + occupant);
          if (occupant != null) {
            var skiff_id = "skiff_p" + occupant + "_" + slot;
            console.log("skiff_id: " + skiff_id);

            var skiff = this.format_block("jstpl_skiff", {
              player_color: players[occupant].color,
              id: skiff_id,
            });
            const skiff_slot = `skiff_slot_${slot}_${number}`;
            domConstruct.place(skiff, skiff_slot);
            domClass.remove(skiff_slot, "unoccupied");
          }
        }
      }
    },
    updateDeckCount: function (deckSize) {
      console.log("updating deck count");
      console.log("deck size: " + deckSize);
      // Convert to number in case it's a string
      const deckSizeNum = parseInt(deckSize, 10);
      this.playerDeck.setCardNumber(deckSizeNum);
    },
    getPlayerResources: function () {
      var playerResources = {};
      console.log("adtive player id: " + this.getActivePlayerId());
      console.log("this.player_id: " + this.player_id);
      for (const resource of this.resources) {
        if (resource.player_id == this.player_id) {
          playerResources[resource.resource_key] = resource.resource_count;
        }
      }
      return playerResources;
    },
    addResources: function (r1, r2) {
      var sum = {};
      for (const [resource_key, num] of Object.entries(r1)) {
        sum[resource_key] = num;
      }
      for (const [resource_key, num] of Object.entries(r2)) {
        if (Object.hasOwn(resource_key)) sum[resource_key] += num;
        else sum[resource_key] = num;
      }
      return sum;
    },
    playerSpendResources: function (resource_cost) {
      console.log("player " + this.player_id + " spending resources:");
      console.log(resource_cost);
      for (var resource of this.resources) {
        if (resource.player_id == this.player_id) {
          if (!Object.hasOwn(resource_cost, resource.resource_key)) {
            continue;
          }
          var diff = resource.resource_count - resource_cost[resource.resource_key];
          console.log("diff for " + resource.resource_key + ": " + diff);
          if (diff < 0) {
            this.showMessage(_("Player tried to spend more than they have"), "error");
          }
          resource.resource_count = diff;
        }
      }
      this.updateResources(this.resources);
    },
    canPlayerAfford: function (resource_cost) {
      var playerResources = this.getPlayerResources();
      console.log("checking cost, player resources are");
      console.log(playerResources);
      console.log("cost is");
      console.log(resource_cost);
      if (typeof resource_cost === "undefined") {
        //can always afford something without cost
        return true;
      }
      for (const [resource_key, num] of Object.entries(resource_cost)) {
        var player_has = playerResources[resource_key] || 0;
        if (player_has - num < 0) {
          console.log("not enough " + resource_key + " (" + player_has + " vs " + num + ")");
          return false;
        }
      }
      return true;
    },
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
        image_id = 0; // fallback
        div.classList.add("non-playable-card-back");
      }
      
      console.log("setup non-playable card helper for card: " + card.id + " with cardKey " + card.cardKey + " and image id " + image_id);
      
      const spriteX = (image_id % 6) * 144;
      const spriteY = Math.floor(image_id / 6) * 198;
      domStyle.set(div, "background-position", `-${spriteX}px -${spriteY}px`);
      console.log("background-position for " + card.id + " set to: " + `-${spriteX}px -${spriteY}px`);
    },

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
            
            // Apply visual styling based on activation status
            this.updateUpgradeCardVisual(upgradeCard);
          } catch (error) {
            console.error(`Error adding upgrade card ${index + 1}:`, error);
          }
        });
      }
    },

    updateUpgradeCardVisual: function(upgradeCard) {
      if (upgradeCard.isActivated) {
        // Flip card to back side using BGA Cards library
        this.nonPlayableCardsManager.flipCard(upgradeCard);
      }
    },

    /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */

    setup: function (gamedatas) {
      console.groupCollapsed("Game Setup");
      console.log("dojo version: " + dojo.version);
      console.log(gamedatas);

      this.playable_cards = gamedatas.playable_cards;
      this.non_playable_cards = gamedatas.non_playable_cards;

      (this.setupHelper = (card, div) => {
        let image_id = null;
        if (typeof card.type !== "undefined") {
          div.classList.add("playable-card-front");
          const cardData = this.playable_cards[card.type];
          image_id = cardData.image_id;
        } else {
          image_id = gamedatas.non_playable_cards.card_back.image_id;
          div.classList.add("playable-card-back");
        }
        console.log(
          "setup helper for card: " +
            card.id +
            " with type " +
            card.type +
            " and image id " +
            image_id +
            " and location " +
            card.location,
        );

        const spriteX = (image_id % 6) * 144;
        const spriteY = Math.floor(image_id / 6) * 198;
        domStyle.set(div, "background-position", `-${spriteX}px -${spriteY}px`);
      }),
        // Create the animation manager for BGA Cards
        (this.animationManager = new BgaAnimations.Manager(this)),
        // Create CardManager for BGA Cards library
        (this.cardsManager = new BgaCards.Manager({
          animationManager: this.animationManager,
          getId: (card) => `card-${card.id}`,
          cardWidth: 144,
          cardHeight: 198,

          setupDiv: (card, div) => {
            div.classList.add('seasofhavoc-card');
          },
          setupFrontDiv: (card, div) => {
            this.setupHelper(card, div);
          },
          setupBackDiv: (card, div) => {
            this.setupHelper(card, div);
          },
          isCardVisible: (card) => {
            return typeof card.type !== "undefined";
          },
        }));

      // Create CardManager for non-playable cards (captain and ship upgrades)
      this.nonPlayableCardsManager = new BgaCards.Manager({
        animationManager: this.animationManager,
        getId: (card) => `np-card-${card.id}`,
        cardWidth: 144,
        cardHeight: 198,
        setupDiv: (card, div) => {
          div.classList.add('seasofhavoc-card', 'non-playable-card');
        },
        setupFrontDiv: (card, div) => {
          this.setupNonPlayableCardHelper(card, div);
        },
        setupBackDiv: (card, div) => {
          this.setupNonPlayableCardHelper(card, div);
        },
        isCardVisible: (card) => {
          return true; // Non-playable cards are always visible
        },
      });

      // Create HandStock for player hand
      this.playerHand = new BgaCards.HandStock(this.cardsManager, $("myhand"), {
        cardOverlap: "30px",
        cardShift: "8px",
        inclination: 8,
      });

      // Convert deck_size to number (BGA returns it as a string)
      const deckSize = parseInt(gamedatas.deck_size, 10);
      
      // Create the deck without initial cardNumber, then set it explicitly
      // (passing cardNumber in constructor options caused incorrect count)
      this.playerDeck = new BgaCards.Deck(this.cardsManager, $("mydeck"), {
        counter: {
          position: "center",
          extraClasses: "text-shadow",
        },
      });
      
      this.playerDeck.setCardNumber(deckSize);

      // Set selection mode to single
      this.playerHand.setSelectionMode("single");

      this.playerDiscard = new BgaCards.AllVisibleDeck(this.cardsManager, $('mydiscard'), {
        shift: '8px',
        //verticalShift: '0px',
        //horizontalShift: '10px',
        //direction: 'horizontal',
        counter: {
            hideWhenEmpty: true,
        },
      });

      this.scrapPile = new BgaCards.AllVisibleDeck(this.cardsManager, $('scrap'), {
        shift: '8px',
        counter: {
            hideWhenEmpty: true,
        },
      });

      this.market = new BgaCards.LineStock(this.cardsManager, $("market"), {
        direction: 'horizontal',
        center: true
      });

      // Create SlotStock for captain
      this.captainStock = new BgaCards.SlotStock(this.nonPlayableCardsManager, $("captain_stock"), {
        slotsIds: ['captain'],
        mapCardToSlot: (card) => {
          if (card.category === 'captain') {
            return 'captain';
          }
          return null;
        }
      });

      // Create SlotStock for ship upgrades
      this.upgradesStock = new BgaCards.SlotStock(this.nonPlayableCardsManager, $("upgrades_stock"), {
        slotsIds: ['upgrade_1', 'upgrade_2'],
        mapCardToSlot: (card) => {
          if (card.category === 'ship_upgrade') {
            // Find first empty upgrade slot
            const upgrade1Slot = this.upgradesStock.slots['upgrade_1'];
            const upgrade2Slot = this.upgradesStock.slots['upgrade_2'];
            
            if (upgrade1Slot && upgrade1Slot.children.length === 0) {
              return 'upgrade_1';
            } else if (upgrade2Slot && upgrade2Slot.children.length === 0) {
              return 'upgrade_2';
            }
            return 'upgrade_1'; // fallback to first slot
          }
          return null;
        },
        wrap: 'nowrap',
        direction: 'row',
        gap: '10px'
      });

      this.cards_purchased = [];

      // Set up selection change callback for HandStock
      this.playerHand.onSelectionChange = (selection, lastChange) => {
        this.onCardSelectedPlayerHand();
      };
      // LineStock uses the CardManager to handle card types automatically
      for (var i in gamedatas.hand) {
        var card = this.gamedatas.hand[i];
        console.log("adding card type: " + card.type + " id: " + card.id + " to player hand");
        this.playerHand.addCard({
          id: card.id,
          type: card.type,
          location: card.location || "hand",
        });
      }
      for (var i in gamedatas.discard) {
        var card = this.gamedatas.discard[i];
        console.log("adding card type: " + card.type + " id: " + card.id + " to player discard");
        this.playerDiscard.addCard({id: card.id, type: card.type, location: card.location || "discard"});
      }
      
      for (var i in gamedatas.scrap) {
        var card = this.gamedatas.scrap[i];
        console.log("adding card type: " + card.type + " id: " + card.id + " to scrap pile");
        this.scrapPile.addCard({id: card.id, type: card.type, location: card.location || "scrap"});
      }

      var slotno = 1;
      for (var card_id in gamedatas.market) {
        var card = this.gamedatas.market[card_id];
        console.log("adding card type: " + card.type + " id: " + card.id + " to market");
        var cardObj = {
          id: card.id,
          type: card.type,
          location: "market"
        };
        this.market.addCard(cardObj);
        var card_div = this.cardsManager.getCardElement(cardObj);
        attr.set(card_div, "data-slotnumber", "n" + slotno);
        attr.set(card_div, "data-cardid", card.id);

        // stick a buy skiff slot on it - use the ID directly since it's predefined in the template
        var skiff_slot_id = "skiff_slot_market_n" + slotno;
        var skiff_slot = $(skiff_slot_id);
        if (skiff_slot) {
          console.log("Found skiff slot " + skiff_slot_id + ", placing on card");
          domConstruct.place(skiff_slot, card_div);
          // Make sure the skiff slot is visible (in case it was hidden during a previous purchase)
          domStyle.set(skiff_slot, "display", "");
        } else {
          console.error("Could not find skiff_slot with id: " + skiff_slot_id);
        }
        slotno++;
      }

      // Add player's actual captain and upgrade cards to player board
      console.log("Adding player cards to board...");
      this.addPlayerCardsToBoard(gamedatas);
      console.log("Player board setup complete!");
      // Setting up player boards
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        console.log(`player color: ${player.color}`);

        var skiff = this.format_block("jstpl_skiff", {
          player_color: player.color,
          id: "skiff_p" + player_id,
        });

        document.getElementById("player_board_" + player_id).insertAdjacentHTML(
          "beforeend",
          this.format_block("jstpl_resources_playerboard", {
            player_id: player.id,
            skiff: skiff,
          }),
        );
      }

      this.islandSlots = gamedatas.islandslots;
      this.players = gamedatas.players;
      this.resources = gamedatas.resources;
      this.unique_tokens = gamedatas.unique_tokens;

      this.updateResources(gamedatas.resources);
      this.updateIslandSlots(gamedatas.islandslots, gamedatas.players);
      this.updateUniqueTokens(gamedatas.unique_tokens);

      for (var x = -1; x <= 6; x++) {
        for (var y = -1; y <= 6; y++) {
          var id = "seaboardlocation_" + x + "_" + y;
          var location = this.format_block("jstpl_seaboard_location", {
            id: id,
          });
          domConstruct.place(location, "seaboard");
          var seaboard = $("seaboard");
          var target_x = -seaboard.offsetWidth / 2 + 32 + 64 * x;
          var target_y = -seaboard.offsetWidth / 2 + 32 + 64 * y;
          this.placeOnObjectPos($(id), "seaboard", target_x, target_y);
        }
      }

      this.seaboard = gamedatas.seaboard;
      for (const entry of gamedatas.seaboard) {
        var target_id = "seaboardlocation_" + entry.x + "_" + entry.y;
        switch (entry.type) {
          case "player_ship":
            var shipid = "player_ship_" + entry.arg;
            var subs = {
              id: shipid,
              //player id is in 'arg'
              shipname: gamedatas.playerinfo[entry.arg].player_ship,
            };
            var ship = this.format_block("jstpl_player_ship", subs);
            domConstruct.place(ship, "seaboard");
            console.log(target_id);
            this.placeOnObject(shipid, target_id);
            domStyle.set(shipid, "rotate", this.getHeadingDegrees(entry.heading) + "deg");
          //this.slideToObject(shipid, target_id, 10 ).play();
        }
      }
      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      var skiffslot_class = query(".skiff_slot");
      var handlers = skiffslot_class.on("click", lang.hitch(this, "onClickSkiffSlot"));

      //this.showDummyDialog();
      console.log("Ending game setup");
      console.groupEnd();
    },
    showDummyDialog: function () {
      this.myDlg = new ebg.popindialog();
      this.myDlg.create("dummyDialog");
      this.myDlg.setTitle(_("Yep, this is dumb"));
      this.myDlg.setMaxWidth(500); // Optional

      var html = this.format_block("jstpl_dummy_dialog");

      this.myDlg.setContent(html);
      this.myDlg.hideCloseIcon();
      this.myDlg.show();

      this.setClientState("client_dummyDialog", {
        descriptionmyturn: _("${you} must abide by BGA's dumb logging"),
      });

      on(
        query(".dummy_button"),
        "click",
        lang.hitch(this, (event) => {
          console.log("dummy button clicked");

          event.preventDefault();
          this.bgaPerformAction("actExitDummyStart", {});
          this.myDlg.destroy();
        }),
      );
    },
    showCardPlayDialog: function (card, card_id) {
      domConstruct.destroy("card_display_dialog");
      var dlg = this.format_block("jstpl_card_play_dialog");
      domConstruct.place(dlg, "myhand_wrap", "first");
      var bga = this;
      var makeDecisionSummary = function (tree, decisionSummary) {
        if (typeof decisionSummary === "undefined") {
          decisionSummary = [];
        }
        console.log("making decision summary " + decisionSummary);
        tree.forEach((options) => {
          for (let i = 0; i < options.length; i++) {
            let option = options[i];
            console.log(option);
            var checkbox = dom.byId(option.id);
            console.log("checked: " + checkbox.checked);
            if (checkbox.checked) {
              decisionSummary.push(option.name);
              makeDecisionSummary(option.children, decisionSummary);
            }
          }
        });
        return decisionSummary;
      };

      on(
        query(".play_card_button"),
        "click",
        lang.hitch(this, (event) => {
          console.groupCollapsed("card play button clicked");
          console.log("play card button clicked");
          event.preventDefault();
          var decisionSummary = makeDecisionSummary(this.dep_tree);
          console.log("decisions");
          console.log(decisionSummary);
          console.log("card ");
          console.log(card);
          this.bgaPerformAction("actPlayCard", {
            card_type: card.card_type,
            card_id: card_id,
            decisions: JSON.stringify(decisionSummary),
          });
          this.dep_tree = null;
          domConstruct.destroy("card_display_dialog");
          console.log("moving card with type: " + card.card_type + " id :" + card_id);
          this.playerDiscard.addCard({id: card_id, type: card.card_type, location: "discard", fromStock: this.playerHand});
          this.playerHand.removeCard({ id: card_id, type: card.card_type });
          console.groupEnd();
        }),
      );
      var display_dom = query("#card_display");
      console.log("display dom:");
      console.log(display_dom);
      var tmpStock = new BgaCards.LineStock(this.cardsManager, display_dom[0], { center: false });
      tmpStock.addCard({ id: 10000, type: card.card_type });

      console.log(card);
      var bga = this;
      var make_card_dependency_tree = function (actions, choice_count) {
        var tree = new Map();
        if (typeof choice_count === "undefined") {
          choice_count = 0;
        }
        for (const action of actions) {
          console.log("tree considering action:");
          console.log(action);
          var option_count = 0;
          var num_descendant_choices = 0;
          switch (action.action) {
            case "choice":
              var tree_choices = [];
              for (const option of action.choices) {
                var choice_name = option.name || option.action;
                var id = "card_choice_" + choice_count + "_option_" + option_count;
                children = make_card_dependency_tree([option], choice_count + num_descendant_choices + 1);
                entry = {
                  name: choice_name,
                  id: id,
                  children: children,
                };
                if (typeof option.cost !== "undefined") {
                  entry.cost = option.cost;
                }
                if (typeof action.cost !== "undefined") {
                  //propagate parent cost down
                  if (typeof entry.cost !== "undefined") {
                    console.warn("overwriting cost for " + choice_name);
                  }
                  entry.cost = action.cost;
                }

                tree_choices.push(entry);
                console.log("choice added: " + choice_name + " " + id);
                num_descendant_choices += children.size;
                option_count++;
              }
              if (typeof action.cost !== "undefined") {
                tree_choices.push({
                  name: "skip",
                  id: "card_choice_" + choice_count + "_option_" + option_count,
                  children: new Map(),
                });
                option_count++;
              }
              tree.set("choice_" + choice_count, tree_choices);
              choice_count++;
              choice_count += num_descendant_choices;
              break;
            case "sequence":
              children = make_card_dependency_tree(action.actions, choice_count);
              children.forEach((value, key) => {
                tree.set(key, value);
              });
              break;
            default: {
              let choice_names = [];
              let choice_name = action.name || action.action;
              if (choice_name == "fire" || choice_name == "2 x fire" || choice_name == "3 x fire") {
                choice_names.push(choice_name + " left");
                choice_names.push(choice_name + " right");
              } else {
                choice_names.push(choice_name);
              }
              if (typeof action.cost !== "undefined") {
                choice_names.push("skip");
              }
              if (choice_names.length > 1) {
                var tree_choices = [];
                let parent_cost = action.cost;
                delete action.cost;
                for (let i = 0; i < choice_names.length; i++) {
                  to_push = {
                    name: choice_names[i],
                    id: "card_choice_" + choice_count + "_option_" + i,
                    children: new Map(),
                  };
                  if (choice_names[i] != "skip") {
                    to_push["cost"] = parent_cost;
                  }
                  tree_choices.push(to_push);
                }
                tree.set("choice_" + choice_count, tree_choices);
                choice_count++;
                num_descendant_choices += 1;
              }
            }
          }
        }
        console.log("returning tree");
        console.log(tree);
        return tree;
      };
      console.groupCollapsed("make dependency tree");
      this.dep_tree = make_card_dependency_tree(card.actions);
      console.log(this.dep_tree);
      console.groupEnd();

      var render_rows = function (tree, row_number) {
        var rendered_choices = [];
        var row_number = row_number || 1;
        tree.forEach((options, choice_id) => {
          var rendered_options = [];
          console.log(options);
          for (var option of options) {
            console.log("rendering option " + option.name);
            rendered_options.push(
              bga.format_block("jstpl_card_choice_radio", {
                id: option.id,
                name: choice_id,
                value: option.name,
                label: option.name,
              }),
            );
            rendered_choices = rendered_choices.concat(render_rows(option.children, row_number + 1));
          }
          var choice_html = bga.format_block("jstpl_card_choices_row", {
            row_number: row_number + ".",
            card_choices: rendered_options.join("\n"),
          });
          rendered_choices.unshift(choice_html);
          row_number++;
        });
        return rendered_choices;
      };
      console.groupCollapsed("render play rows");
      var result = render_rows(this.dep_tree);
      console.log(result);
      console.groupEnd();

      var bga = this;
      var computeTotalPlayCost = function (tree, costAcc) {
        if (typeof costAcc === "undefined") {
          costAcc = {};
        }
        console.log("computing total play cost " + costAcc);
        tree.forEach((options) => {
          for (var option of options) {
            console.log(option);
            var checkbox = dom.byId(option.id);
            console.log("checked: " + checkbox.checked);
            console.log(option.cost);
            console.log(costAcc);
            if (checkbox.checked && typeof option.cost !== "undefined") {
              costAcc = bga.addResources(option.cost, costAcc);
            }
            costAcc = computeTotalPlayCost(option.children, costAcc);
          }
        });
        return costAcc;
      };

      var showHideControls = function (tree, hide, totalCost) {
        console.log("showing/hiding controls " + hide);
        if (typeof totalCost === "undefined") {
          console.groupCollapsed("compute total play cose");
          totalCost = computeTotalPlayCost(tree);
          console.groupEnd();
          console.log("total play cost is:");
          console.log(totalCost);
        }
        tree.forEach((options) => {
          for (var option of options) {
            var checkbox = dom.byId(option.id);
            console.log(option);
            console.log(checkbox);
            console.log(checkbox.checked);
            if (hide) {
              checkbox.checked = false;
              domStyle.set(checkbox.parentNode.parentNode, "display", "none");
              showHideControls(option.children, true, totalCost);
            } else {
              domStyle.set(checkbox.parentNode.parentNode, "display", "inline-block");
              if (typeof option.cost !== "undefined" && option.cost) {
                console.log("option cost:");
                console.log(option.cost);
                console.log("totalCost:");
                console.log(totalCost);
                var adjustedCost = bga.addResources(option.cost, totalCost);
                console.log("adjusted cost:");
                console.log(adjustedCost);
                if (bga.canPlayerAfford(adjustedCost)) {
                  attr.remove(checkbox, "disabled");
                } else {
                  attr.set(checkbox, "disabled", "true");
                }
              }
              showHideControls(option.children, !checkbox.checked, totalCost);
            }
          }
        });
      };

      var checkIsCardReadyToBePlayed = function (tree) {
        var isReady = true;
        if (tree.length == 0) {
          return;
        }
        console.log("starting ready to play check");
        tree.forEach((options) => {
          if (!isReady) {
            return;
          }
          var anythingChecked = false;
          for (var option of options) {
            var checkbox = dom.byId(option.id);
            console.log("starting to check option:");
            console.log(option);
            console.log("parent display: " + domStyle.get(checkbox.parentNode.parentNode, "display"));
            if (domStyle.get(checkbox.parentNode.parentNode, "display") != "none") {
              console.log("checking children:");
              console.log(option.children);
              if (!checkIsCardReadyToBePlayed(option.children)) {
                console.log("nothing checked in children");
                isReady = false;
                return;
              }
              anythingChecked |= checkbox.checked;
              console.log("checkbox is checked: " + checkbox.checked);
              console.log("anything checked now: " + anythingChecked);
            } else {
              console.log("skipping because option is hidden:");
              console.log(option);
              return;
            }
            console.log("anything checked at end of loop " + anythingChecked);
          }
          console.log("after options anything checked: " + anythingChecked);
          isReady &= anythingChecked;
          console.log("updated isReady to " + isReady);
        });
        console.log("final ready to play: " + isReady);
        return isReady;
      };

      var updatePlayCardButton = function () {
        const button_id = "play_card_button";
        console.groupCollapsed("check whether card is ready to be played");
        let ready = checkIsCardReadyToBePlayed(bga.dep_tree);
        console.groupEnd();
        if (ready) {
          domClass.add(button_id, "bgabutton_green");
          domClass.remove(button_id, "disabled");
        } else {
          domClass.remove(button_id, "bgabutton_green");
          domClass.add(button_id, "disabled");
        }
      };
      if (result.length > 0) {
        var choices_html = result.join("\n");
        domConstruct.place(choices_html, "card_choices");
        query(".card_choice_radio").connect("onchange", this, (event) => {
          console.groupCollapsed("show/hide play controls");
          showHideControls(this.dep_tree);
          console.groupEnd();
          updatePlayCardButton();
        });
      }
      console.groupCollapsed("show/hide play controls");
      showHideControls(this.dep_tree);
      console.groupEnd();
      updatePlayCardButton();
      this.cardPlayDialogShown = true;
    },
    updateCardPurchaseButtons: function (create) {
      console.groupCollapsed("update card purchase buttons");
      console.log("showing card purchase buttons");
      console.log(this.islandSlots);
      for (const [slot, numbers] of Object.entries(this.islandSlots)) {
        if (slot == "market") {
          for (const [number, occupant] of Object.entries(numbers)) {
            console.log("market occupant: " + occupant);
            if (occupant == this.player_id) {
              var button_id = "purchase_button_" + number;
              console.log("number " + number + " is ours");
              var slot_card = null;
              for (const cardspec of this.market.getCards()) {
                console.log(cardspec);
                var div = this.cardsManager.getCardElement(cardspec);
                console.log(div);
                //console.log("children");
                //console.log(query(div));
                var skiff_slot_element = query(div).children(".skiff_slot")[0];
                if (!skiff_slot_element) {
                  console.error("ERROR: No skiff slot found for market card", cardspec, "- every market card must have a skiff slot!");
                  throw new Error("Market card missing skiff slot: card id " + cardspec.id);
                }
                var slotnum = attr.get(skiff_slot_element, "data-number");
                console.log("slotnum " + slotnum);
                if (slotnum == number) {
                  slot_card = cardspec;
                  console.log("found it");
                  break;
                }
              }
              if (slot_card === null) {
                continue;
              }
              console.log(slot_card);
              var card = this.playable_cards[slot_card.type];
              console.log(card);
              if (create) {
                var purchase_button = this.format_block("jstpl_card_purchase_button", {
                  id: button_id,
                  slotnumber: slotnum,
                });
                var card_div = this.cardsManager.getCardElement(slot_card);
                //dojo.place(purchase_button, "skiff_slot_" + slot + "_" + number);
                console.log(card_div);
                console.log("placing on " + card_div + " card purchase button: " + purchase_button);
                domConstruct.place(purchase_button, card_div);
              }
              // Always ensure event handler is connected
              console.log("=== PURCHASE BUTTON EVENT HANDLER DEBUG ===");
              console.log("Looking for button with ID: " + button_id);
              var buttonNodes = query("#" + button_id);
              console.log("Found button nodes:", buttonNodes);
              console.log("Number of nodes found:", buttonNodes.length);

              // Remove any existing handler first to avoid duplicates
              buttonNodes.forEach(function (node, index) {
                console.log("Processing node " + index + ":", node);
                if (node._purchaseHandler) {
                  console.log("Removing existing handler from node " + index);
                  node._purchaseHandler.remove();
                } else {
                  console.log("No existing handler found on node " + index);
                }
              });

              // Connect the event handler and store reference on the DOM node
              console.log("Attempting to connect click handler...");
              var handler = buttonNodes.on("click", lang.hitch(this, "onClickPurchaseButton"));
              console.log("Handler created:", handler);

              buttonNodes.forEach(function (node, index) {
                console.log("Storing handler reference on node " + index);
                node._purchaseHandler = handler;
              });
              console.log("=== END PURCHASE BUTTON EVENT HANDLER DEBUG ===");

              if (!this.canPlayerAfford(card.cost)) {
                console.log("disabling");
                buttonNodes.forEach(function (node) {
                  domClass.remove(node, "bgabutton_green");
                  domClass.add(node, "disabled");
                  html.set(node, "Cannot Afford");
                });
              } else {
                console.log("enabling purchase button");
                buttonNodes.forEach(function (node) {
                  domClass.add(node, "bgabutton_green");
                  domClass.remove(node, "disabled");
                  html.set(node, "Purchase");
                });
              }
            }
          }
        }
      }
      console.groupEnd();
    },
    onClickPurchaseButton: function (event) {
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
      console.log("slotnumber: " + slotnumber);

      var card_dom = query(`[data-slotnumber=${slotnumber}`)[0];
      console.log(card_dom);
      var card_id = attr.get(card_dom, "data-cardid");
      console.log("card id " + card_id);
      var slot_card = this.market.getCards().find(card => card.id == card_id);
      console.log(slot_card);
      var card = this.playable_cards[slot_card.type];
      this.playerSpendResources(card.cost);
      console.log(card);
      console.log(slot_card);
      
      // Remove purchase button and hide skiff slot from card div before moving to hand
      var purchase_button = query(`.purchase_card_button[data-slotnumber="${slotnumber}"]`, card_dom)[0];
      if (purchase_button) {
        domConstruct.destroy(purchase_button);
      }
      
      var skiff_slot = query(`.skiff_slot`, card_dom)[0];
      if (skiff_slot) {
        // Don't destroy the skiff slot, just hide it so it can be restored on reload
        domStyle.set(skiff_slot, "display", "none");
      }
      
      this.playerHand.addCard({
        id: slot_card.id,
        type: slot_card.type,
        location: "hand",
      });
      this.market.removeCard(slot_card);
      this.updateCardPurchaseButtons(false);
      this.cards_purchased.push(slot_card.id);
      console.groupEnd();
    },
    onCompletePurchasesClicked: function (event) {
      console.log("onCompletePurchasesClicked");
      console.log(this.cards_purchased);
      this.bgaPerformAction("actCompletePurchases", {
        cards_purchased: JSON.stringify(this.cards_purchased),
      });
    },
    onClickSkiffSlot: function (event) {
      console.log("$$$$ Event : onClickSkiffSlot");
      console.log(event);
      event.preventDefault();
      const source = event.target || event.srcElement;
      if (!this.checkAction("actPlaceSkiff")) {
        console.log("nope");
        return;
      }

      if (!source.classList.contains("skiff_slot")) {
        console.log("not a skiff slot");
        return;
      }
      console.log(source.dataset.slotname, source.dataset.number);

      if (this.isCurrentPlayerActive()) {
        console.log("calling actPlaceSkiff");
        this.bgaPerformAction("actPlaceSkiff", {
          slotname: source.dataset.slotname,
          number: source.dataset.number,
        });
      }
    },
    onCardSelectedPlayerHand: function () {
      console.groupCollapsed("player card selected");
      var selection = this.playerHand.getSelection();
      console.log("player hand selection:", selection);
      if (selection.length == 1) {
        var selectedCard = selection[0];
        var card_type = selectedCard.type;
        console.log("type: " + card_type);
        var card = this.playable_cards[card_type];
        console.log(card);
        this.showCardPlayDialog(card, selectedCard.id);
      }
      console.log(selection);
      console.groupEnd();
    },
    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
        case "dummyStart":
        //this.bgaPerformAction("actExitDummyStart", {});
        case "cardPurchases": {
          this.cards_purchased = [];
          // Restore visibility of skiff slots on market cards that haven't been purchased
          this.market.getCards().forEach(card => {
            var card_div = this.cardsManager.getCardElement(card);
            var skiff_slot = query(`.skiff_slot`, card_div)[0];
            if (skiff_slot) {
              domStyle.set(skiff_slot, "display", "");
            }
          });
          this.updateCardPurchaseButtons(true);
          break;
        }
        case "seaTurn": {
          query(".skiff_placed").forEach(domConstruct.destroy);
          query(".purchase_card_button").forEach(domConstruct.destroy);
          break;
        }
        case "scrapCard": {
          this.setupScrapCardSelection(args.args);
          break;
        }
        case "dummmy":
          break;
      }
    },

    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */
        case "scrapCard":
          this.cleanupScrapCardSelection();
          break;

        case "dummmy":
          break;
      }
    },

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: " + stateName);

      if (this.isCurrentPlayerActive()) {
        switch (stateName) {
          case "cardPurchases":
            this.statusBar.addActionButton(_("Complete Purchases"), this.onCompletePurchasesClicked.bind(this));
            break;
          case "client_resourceDialog":
            this.statusBar.addActionButton(
              _("<div class='resource sail' data-resource='sail'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              _("<div class='resource cannonball' data-resource='cannonball'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              _("<div class='resource doubloon' data-resource='doubloon'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            break;
          case "resolveCollision":
            this.statusBar.addActionButton(
              "<div class='resource pivot_left' data-pivot='pivot left'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              "<div class='resource nope' data-pivot='no pivot'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              "<div class='resource pivot_right' data-pivot='pivot right'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );

            break;
        }
      }
    },
    onResourceButtonClicked: function (event) {
      console.log("resource button clicked");
      const source = event.target || event.srcElement;
      console.log(
        "resource picked " +
          source.dataset.resource +
          " context: " +
          this.clientStateVars.slot_context +
          " number: " +
          this.clientStateVars.slot_number,
      );

      event.preventDefault();
      if (source.dataset.resource != null) {
        this.bgaPerformAction("actResourcePickedInDialog", {
          resource: source.dataset.resource,
          context: this.clientStateVars.slot_context,
          number: this.clientStateVars.slot_number,
        });
        this.statusBar.removeActionButtons();
      }
    },
    onPivotButtonClicked: function (event) {
      const source = event.target || event.srcElement;
      console.log("pivot button clicked");
      console.log(source);
      console.log("pivot picked " + source.dataset.pivot);
      this.bgaPerformAction("actPivotPickedInDialog", {
        direction: source.dataset.pivot,
      });
      this.statusBar.removeActionButtons();
      event.preventDefault();
    },
    ///////////////////////////////////////////////////
    //// Utility methods

    /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

    setupScrapCardSelection: function(args) {
      console.log("Setting up scrap card selection");
      console.log(args);
      
      // Create a dialog using the template
      var scrapDialog = this.format_block('jstpl_scrap_card_dialog', {});
      document.body.insertAdjacentHTML('beforeend', scrapDialog);
      
      // Create scrollable stock for card selection
      this.scrapCardSelection = new BgaCards.ScrollableStock(
        this.cardsManager, 
        $("scrap_card_selection_wrapper"), 
        {
          gap: '8px',
          center: true,
          scrollStep: 150,
          leftButton: { html: '◀' },
          rightButton: { html: '▶' }
        }
      );
      
      // Set selection mode to single
      this.scrapCardSelection.setSelectionMode("single");
      
      // Add available cards to the selection
      if (args.available_cards) {
        for (var i in args.available_cards) {
          var card = args.available_cards[i];
          this.scrapCardSelection.addCard({
            id: card.id,
            type: card.type,
            location: card.location
          });
        }
      }
      
      // Set up selection callback
      this.scrapCardSelection.onSelectionChange = (selection, lastChange) => {
        if (selection.length > 0) {
          var selectedCard = selection[0];
          console.log("Card selected for scrapping:", selectedCard);
          
          // Add confirm button when a card is selected
          if (!$("confirm_scrap_button")) {
            domConstruct.create("button", {
              id: "confirm_scrap_button",
              class: "bgabutton bgabutton_red",
              innerHTML: "Scrap Card"
            }, $("cancel_scrap_button"), "before");
            
            on($("confirm_scrap_button"), "click", () => {
              this.confirmScrapCard(selectedCard.id);
            });
          }
        } else {
          // Remove confirm button if no card selected
          if ($("confirm_scrap_button")) {
            domConstruct.destroy("confirm_scrap_button");
          }
        }
      };
      
      // Set up cancel button
      on($("cancel_scrap_button"), "click", () => {
        this.cleanupScrapCardSelection();
      });
    },

    cleanupScrapCardSelection: function() {
      console.log("Cleaning up scrap card selection");
      
      if (this.scrapCardSelection) {
        this.scrapCardSelection = null;
      }
      
      if ($("scrap_card_dialog")) {
        domConstruct.destroy("scrap_card_dialog");
      }
    },

    confirmScrapCard: function(cardId) {
      console.log("Confirming scrap of card:", cardId);
      
      if (this.checkAction("actScrapCard")) {
        this.bgaPerformAction("actScrapCard", {
          card_id: cardId
        });
      }
    },

    ///////////////////////////////////////////////////
    //// Player's action

    /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */

    /* Example:
        
        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );
            
            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/seasofhavoc/seasofhavoc/myAction.html", { 
                                                                    lock: true, 
                                                                    myArgument1: arg1, 
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 }, 
                         this, function( result ) {
                            
                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)
                            
                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );        
        },        
        
        */

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your seasofhavoc.game.php file.
        
        */
    setupNotifications: function () {
      console.log("notifications subscriptions setup");
      this.bgaSetupPromiseNotifications();
      
      // Subscribe to card scrapped notification
      dojo.subscribe('cardScrapped', this, 'notif_cardScrapped');
      
      // Subscribe to card discard notifications
      dojo.subscribe('cardsDiscarded', this, 'notif_cardsDiscarded');
      
      // Subscribe to deck reshuffle notification
      dojo.subscribe('deckReshuffled', this, 'notif_deckReshuffled');
    },

    // TODO: from this point and below, you can write your game notifications handling methods

    // TODO: from this point and below, you can write your game notifications handling methods
    notif_deckSizeChanged: function (args) {
      console.log("notify: deck size changed");
      console.log(args);
      if (args.player_id == this.player_id) {
        this.updateDeckCount(args.deck_size);
      }
    },
    notif_showResourceChoiceDialog: function (args) {
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
    notif_dummyStart: function (args) {
      console.log("dummystart");
      this.bgaPerformAction("actExitDummyStart", {});
    },
    notif_resourcesChanged: function (args) {
      console.groupCollapsed("notify: resources changed");
      console.log("Notification: resourcesChanged");
      console.log(args.resources);
      console.log("current resources:");
      console.log(this.resources);

      this.resources = args.resources;
      this.updateResources(args.resources);
      console.groupEnd();
    },
    notif_skiffPlaced: function (args) {
      console.groupCollapsed("notify: skiff placed");
      console.log(args);

      var slot_name = args.slot_name;
      var slot_number = args.slot_number;
      var postfix = "_" + slot_name + "_" + slot_number;
      var skiff_id = "skiff_p" + args.player_id + postfix;

      this.islandSlots[slot_name][slot_number] = args.player_id;

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

      domConstruct.place(
        skiff,
        //"skiff_slot" + postfix
        skiff_slot,
      );
      this.placeOnObject(skiff_id, player_board_id);
      domStyle.set(skiff_id, "zIndex", 1);
      this.slideToObject(
        skiff_id,
        //"skiff_slot" + postfix,
        skiff_slot,
        1000,
      ).play();
      domClass.remove(skiff_slot, "unoccupied");
      domClass.add(skiff_id, "skiff_placed");
      console.groupEnd();
    },
    notif_tokenAcquired: function (args) {
      console.groupCollapsed("notify: token acquired");
      console.log(args);

      var token_key = args.token_key;
      var player_id = args.player_id;

      var tokens = query("#" + token_key);
      var token_element = null;
      if (token_element != null) {
        var token_element = tokens[0];
      } else {
        console.log("creating token");
        var token_html = this.format_block("jstpl_unique_token", { token_key: args.token_key });
        if (token_key == "first_player_token") {
          var token_element = domConstruct.place(token_html, `skiff_slot_capitol_n1`);
        } else {
          var token_element = domConstruct.place(token_html, `skiff_slot_${token_key}_n1`);
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
    notif_cardPlayed: function (args) {
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
              //let fadeout = baseFX.fadeOut({node: shipid});
              //anims.push(fx.combine(forward, fadeout));
              anims.push(forward);
              target_id = "seaboardlocation_" + move.teleport_to.x + "_" + move.teleport_to.y;
              anims.push(this.slideToObject(shipid, target_id, 0));
              //anims.push(baseFX.fadeIn({node: shipid}));
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
              onAnimate: function (v) {
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
                onEnd: function () {
                  domConstruct.destroy("cannonfire");
                },
              }),
            ]).play();
            fx.chain([
              baseFX.fadeIn({ node: "explosion", delay: 100 }),
              baseFX.fadeOut({
                node: "explosion",
                delay: 1000,
                onEnd: function () {
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
    notif_score: function (args) {
      console.log("score for " + args.player_id + " " + args.player_score);
      this.scoreCtrl[args.player_id].setValue(args.player_score);
    },
    notif_damageReceived: function (args) {
      console.log("notify damage received");
      let damage_card = args.damage_card;
      let player_id = args.player_id;
      var shipid = "player_ship_" + args.player_id;
      if (player_id == this.player_id) {
        this.playerDiscard.addCard(damage_card.type, damage_card.id);
      }
    },
    notif_cardDrawn: function (args) {
      console.log("notify card drawn");
      let cards = args.cards;
      let player_id = args.player_id;
      console.log("Cards drawn:", cards);
      console.log("Player ID:", player_id);
      if (player_id == this.player_id) {
        this.updateDeckCount(args.deck_size);
        let fromElement = dom.byId("mydeck");
        console.log("fromElement: " + fromElement);
        
        // Add each card to the player's hand
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
    notif_cardScrapped: function (args) {
      console.log("notify card scrapped");
      
      let card = args.card;
      let player_id = args.player_id;
      let original_location = args.original_location;
      
      // Defensive check for card object
      if (!card || !card.id) {
        console.error("Invalid card data in cardScrapped notification:", card);
        return;
      }
      
      // Move the card to scrap pile visually
      if (original_location === "hand" && player_id == this.player_id) {
        // Remove from hand
        this.playerHand.removeCard({id: card.id});
      } else if (original_location === "player_discard" && player_id == this.player_id) {
        // Remove from discard
        this.playerDiscard.removeCard({id: card.id});
      }
      
      // Add to scrap pile
      this.scrapPile.addCard({
        id: card.id,
        type: card.type,
        location: "scrap"
      });
      
      // Close the scrap selection dialog
      this.cleanupScrapCardSelection();
    },
    notif_cardsDiscarded: function (args) {
      console.log("notify cards discarded");
      let cards = args.cards;
      let player_id = args.player_id;
      console.log("Cards discarded:", cards);
      console.log("Player ID:", player_id);
      
      if (player_id == this.player_id) {
        // For the current player: remove from hand and add to discard
        cards.forEach(card => {
          this.playerHand.removeCard({id: card.id});
          this.playerDiscard.addCard({
            id: card.id,
            type: card.type,
            location: "discard"
          });
        });
      } else {
        // For other players: just add the cards to the discard pile (they're public)
        cards.forEach(card => {
          this.playerDiscard.addCard({
            id: card.id,
            type: card.type,
            location: "discard"
          });
        });
      }
    },
    notif_deckReshuffled: function (args) {
      console.log("notify deck reshuffled");
      let player_id = args.player_id;
      let deck_size = args.deck_size;
      console.log(`Player ${player_id} deck reshuffled, new deck size: ${deck_size}`);
      
      if (player_id == this.player_id) {
        // Clear the discard pile visually (cards moved back to deck)
        this.playerDiscard.removeAll();
        
        // Update deck count
        this.updateDeckCount(deck_size);
      }
    },
    /*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */
  });
});
