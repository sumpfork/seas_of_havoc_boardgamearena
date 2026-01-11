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
  // Custom modules - use g_gamethemeurl to load from game folder
  g_gamethemeurl + "modules/js/constants.js",
  g_gamethemeurl + "modules/js/utils.js",
  g_gamethemeurl + "modules/js/islandSlots.js",
  g_gamethemeurl + "modules/js/cardManager.js",
  g_gamethemeurl + "modules/js/dialogs.js",
  g_gamethemeurl + "modules/js/purchases.js",
  g_gamethemeurl + "modules/js/stateHandlers.js",
  g_gamethemeurl + "modules/js/notifications.js",
  // Dojo extras
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
  // Custom modules
  Constants,
  Utils,
  IslandSlots,
  CardManager,
  Dialogs,
  Purchases,
  StateHandlers,
  Notifications
) {
  // Direction constants - available globally for this module
  const NORTH = Constants.NORTH;
  const EAST = Constants.EAST;
  const SOUTH = Constants.SOUTH;
  const WEST = Constants.WEST;
  
  // Build the game class with all module methods mixed in
  var gameClass = declare("bgagame.seasofhavoc", ebg.core.gamegui, {
    constructor: function () {
      console.log("seasofhavoc constructor");
      this.clientStateVars = {};
    },

    // Direction helper - delegate to constants module
    getHeadingDegrees: function (direction) {
      return Constants.getHeadingDegrees(direction);
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

      // Card setup helper for playable cards
      this.setupHelper = (card, div) => {
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
      };

        // Create the animation manager for BGA Cards
      this.animationManager = new BgaAnimations.Manager(this);
      
        // Create CardManager for BGA Cards library
      this.cardsManager = new BgaCards.Manager({
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
      });

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
          return true;
        },
      });

      this.bootyTokenManager = new BgaCards.Manager({
        animationManager: this.animationManager,
        getId: (card) => `booty-token-${card.id}`,
        cardWidth: 63,
        cardHeight: 63,
        setupDiv: (card, div) => {
          div.classList.add('booty-token');
        },
      });
      
      // Create HandStock for player hand
      this.playerHand = new BgaCards.HandStock(this.cardsManager, $("myhand"), {
        cardOverlap: "30px",
        cardShift: "8px",
        inclination: 8,
      });

      // Convert deck_size to number
      const deckSize = parseInt(gamedatas.deck_size, 10);
      
      // Create the deck
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

      // Create SlotStock for market
      this.market = new BgaCards.SlotStock(this.cardsManager, $("market"), {
        slotsIds: ['market_slot_n1', 'market_slot_n2', 'market_slot_n3', 'market_slot_n4', 'market_slot_n5'],
        mapCardToSlot: (card) => {
          if (this.marketSlotMap && card.id) {
            return this.marketSlotMap[card.id] || null;
          }
          return null;
        }
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
            const upgrade1Slot = this.upgradesStock.slots['upgrade_1'];
            const upgrade2Slot = this.upgradesStock.slots['upgrade_2'];
            
            if (upgrade1Slot && upgrade1Slot.children.length === 0) {
              return 'upgrade_1';
            } else if (upgrade2Slot && upgrade2Slot.children.length === 0) {
              return 'upgrade_2';
            }
            return 'upgrade_1';
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
      
      // Build a set of pending purchase card IDs
      var pending_card_ids = new Set();
      if (gamedatas.pending_purchases) {
        var pending_array = Array.isArray(gamedatas.pending_purchases) ? gamedatas.pending_purchases : Object.values(gamedatas.pending_purchases);
        for (var i = 0; i < pending_array.length; i++) {
          pending_card_ids.add(pending_array[i]);
        }
      }
      
      // Add cards from actual hand
      for (var i in gamedatas.hand) {
        var card = this.gamedatas.hand[i];
        console.log("adding card type: " + card.type + " id: " + card.id + " to player hand");
        this.playerHand.addCard({
          id: card.id,
          type: card.type,
          location: card.location || "hand",
        });
      }
      
      // Add cards from pending purchases to hand
      if (gamedatas.pending_purchases && gamedatas.market) {
        var market_array = Array.isArray(gamedatas.market) ? gamedatas.market : Object.values(gamedatas.market);
        for (var i = 0; i < market_array.length; i++) {
          var card = market_array[i];
          if (card && card.id && pending_card_ids.has(card.id)) {
            console.log("adding pending purchase card type: " + card.type + " id: " + card.id + " to player hand");
            this.playerHand.addCard({
              id: card.id,
              type: card.type,
              location: "hand",
            });
          }
        }
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

      // Store gamedatas for later use
      this.gamedatas = gamedatas;
      
      // Build map from card ID to slot ID
      this.marketSlotMap = {};
      
      var market_array = Array.isArray(gamedatas.market) ? gamedatas.market : Object.values(gamedatas.market);
      
      for (var i = 0; i < market_array.length; i++) {
        var card = market_array[i];
        if (!card || !card.id) {
          continue;
        }
        
        var slot_id = "market_slot_n" + (i + 1);
        var skiff_slot_id = "skiff_slot_market_n" + (i + 1);
        
        this.marketSlotMap[card.id] = slot_id;
        
        if (pending_card_ids.has(card.id)) {
          console.log("Card " + card.id + " is pending purchase, leaving slot " + slot_id + " empty");
          continue;
        }
        
        console.log("adding card type: " + card.type + " id: " + card.id + " to market slot " + slot_id);
        
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
      this.positionMarketSkiffSlots();

      // Add player's captain and upgrade cards
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

      // Set up seaboard grid
      for (var x = -1; x <= 6; x++) {
        for (var y = -1; y <= 6; y++) {
          var id = "seaboardlocation_" + x + "_" + y;
          var location = this.format_block("jstpl_seaboard_location", {
            id: id,
          });
          domConstruct.place(location, "seaboard");
          var seaboard = $("seaboard");
          var target_x = -seaboard.offsetWidth / 2 + 30 + 64 * x;
          var target_y = -seaboard.offsetWidth / 2 + 30 + 64 * y;
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
              shipname: gamedatas.playerinfo[entry.arg].player_ship,
            };
            var ship = this.format_block("jstpl_player_ship", subs);
            domConstruct.place(ship, "seaboard");
            console.log(target_id);
            this.placeOnObject(shipid, target_id);
            domStyle.set(shipid, "rotate", this.getHeadingDegrees(entry.heading) + "deg");
            break;
          case "rock":
          case "gust":
          case "whirlpool":
          case "shipwreck":
            var seafeatureid = entry.type + "_" + entry.arg;
            var subs = {
              id: seafeatureid,
              seafeature_type: entry.type,
            };
            var seafeature = this.format_block("jstpl_seafeature", subs);
            domConstruct.place(seafeature, "seaboard");
            this.placeOnObject(seafeatureid, target_id);
            if (entry.type === "gust") {
              domStyle.set(seafeatureid, "rotate", (this.getHeadingDegrees(entry.heading) - 90) + "deg");
            }
            break;
        }
      }
      
      // Setup game notifications
      this.setupNotifications();

      var skiffslot_class = query(".skiff_slot");
      var handlers = skiffslot_class.on("click", lang.hitch(this, "onClickSkiffSlot"));

      console.log("Ending game setup");
      console.groupEnd();
    },

    ///////////////////////////////////////////////////
    //// Event Handlers
    
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
      
      if (source.classList.contains("disabled")) {
        console.log("skiff slot is disabled for this player count");
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
      } else {
        this.cleanupCardPlayDialog();
      }
      console.log(selection);
      console.groupEnd();
    },
  });
  
  // Mix in methods from all modules
  var modulesToMixin = [Utils, IslandSlots, CardManager, Dialogs, Purchases, StateHandlers, Notifications];
  
  for (var i = 0; i < modulesToMixin.length; i++) {
    var module = modulesToMixin[i];
    for (var methodName in module) {
      if (module.hasOwnProperty(methodName)) {
        gameClass.prototype[methodName] = module[methodName];
      }
    }
  }
  
  return gameClass;
});
