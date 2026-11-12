/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SeasOfHavoc implementation : © Peter Gorniak
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
  "dojo/dom-construct",
  "dojo/dom-style",
  "dojo/dom-attr",
  "dojo/_base/lang",
  "dojo/query",
  getLibUrl("bga-animations", "1.x"),
  getLibUrl("bga-cards", "1.x"),
  getLibUrl("bga-score-sheet", "1.x"),
  // Custom modules - use g_gamethemeurl to load from game folder
  g_gamethemeurl + "modules/js/constants.js",
  g_gamethemeurl + "modules/js/utils.js",
  g_gamethemeurl + "modules/js/islandSlots.js",
  g_gamethemeurl + "modules/js/cardManager.js",
  g_gamethemeurl + "modules/js/dialogs.js",
  g_gamethemeurl + "modules/js/purchases.js",
  g_gamethemeurl + "modules/js/tradingPost.js",
  g_gamethemeurl + "modules/js/treasureSeeker.js",
  g_gamethemeurl + "modules/js/stateHandlers.js",
  g_gamethemeurl + "modules/js/notifications.js",
  g_gamethemeurl + "modules/js/cardPreview.js",
  // Dojo extras
  "dojo/NodeList-traverse",
  "dojo/NodeList-data",
  "ebg/core/gamegui",
  "ebg/counter",
], function (
  dojo,
  declare,
  domConstruct,
  domStyle,
  attr,
  lang,
  query,
  BgaAnimations,
  BgaCards,
  BgaScoreSheet,
  // Custom modules
  Constants,
  Utils,
  IslandSlots,
  CardManager,
  Dialogs,
  Purchases,
  TradingPost,
  TreasureSeeker,
  StateHandlers,
  Notifications,
  CardPreview,
) {
  // Debug logging only in Studio (see constants.js).
  const console = Constants.console;

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

    /**
     * BGA framework hook: replace [resource] markers in log messages with inline icons.
     */
    bgaFormatText: function (log, args) {
      try {
        // Infamy markers are in the message itself, not its args.
        if (log) log = this.iconifyMarkers(log);
        if (log && args && !args.processed) {
          args.processed = true;
          // [resource] markers become icons, including inside nested logs (resource_change is one).
          const keys = ["resource_change", "booty_usage", "resource_list", "resource"];
          const walk = (values) => {
            for (const key of Object.keys(values)) {
              if (typeof values[key] === "string" && keys.includes(key)) {
                values[key] = this.iconifyMarkers(values[key]);
              } else if (values[key] && typeof values[key] === "object") {
                walk(values[key].args ?? values[key]);
              }
            }
          };
          walk(args);
          // The played card's type becomes a thumbnail that zooms on click (see setup).
          if (args.card_image !== undefined) {
            const art = document.createElement("div");
            this.setupHelper({ type: args.card_image }, art);
            art.classList.add("soh_panel_card_art");
            Object.assign(art.style, { width: "144px", height: "198px" });
            // Most cards have no printed name, so the alt text is the kind of card (and a market
            // card's printed type: sailing, firing, pivot).
            const card = this.playable_cards[args.card_image];
            const label =
              card.category === "captain"
                ? _("${captain} card").replace("${captain}", _(this.gamedatas.non_playable_cards[card.captain_key].name))
                : card.category === "market_card"
                  ? _("${types} market card").replace("${types}", card.type.map((t) => this.tooltipText("card_type", t)).join(" / "))
                  : { starting_card: _("Starting card"), damage: _("Damage card"), rowing: _("Rowing card") }[card.category];
            if (label === undefined) throw new Error(`No log label for card category ${card.category}`);
            args.card_image = `<div class="soh_panel_card soh_log_card" role="img" aria-label="${label}">${art.outerHTML}</div>`;
          }
          // A spent booty token's image id becomes an inline token that enlarges on hover (see setup).
          if (args.booty_image !== undefined) {
            const token = this.createBootyTokenNode(false, args.booty_image);
            token.classList.add("soh_booty-token-button-icon", "soh_log_booty");
            this.setBootyTokenPosition(token, args.booty_image, 36);
            token.dataset.imageId = args.booty_image;
            token.setAttribute("role", "img");
            token.setAttribute("aria-label", _("Booty token"));
            args.booty_image = token.outerHTML;
          }
        }
      } catch (e) {
        console.error(log, args, "bgaFormatText exception", e.stack);
      }
      return { log: log, args: args };
    },

    updateHandSelectionMode: function () {
      // A scrap/discard pick owns the hand until cleanupCardPick; onEnteringState can run after the
      // onUpdateActionButtons that set it up.
      if (!this.playerHand || this._cardPick) {
        return;
      }
      // Don't use checkAction() here — it returns false during state transitions
      // because the BGA framework locks the interface. Instead check possibleactions directly.
      this._rowing = false;
      this._rowDiscards = [];
      this._labelRowDiscards();
      const possibleActions = this.gamedatas.gamestate.possibleactions || [];
      const canSelect = this.isCurrentPlayerActive() && possibleActions.indexOf("actPlayCard") !== -1;
      const selectionMode = canSelect ? "single" : "none";
      console.log(
        "[hand] updateHandSelectionMode canSelect=" +
          canSelect +
          " mode=" +
          selectionMode +
          " active=" +
          this.isCurrentPlayerActive() +
          " possibleactions=" +
          JSON.stringify(possibleActions),
      );
      this.playerHand.setSelectionMode(selectionMode);
      if (!canSelect) {
        this.cleanupCardPlayDialog();
      }
    },

    /**
     * setupGameArea:
     * Initializes the main game area with all HTML structure.
     * This replaces the legacy seasofhavoc.view.php and seasofhavoc_seasofhavoc.tpl files.
     */
    setupGameArea: function () {
      const html = `
        <div id="board" class="soh_board shadow">
            <div id="skiff_slot_capitol_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="capitol" data-number="n1"></div>
            <div id="skiff_slot_bank_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="bank" data-number="n1"></div>
            <div id="skiff_slot_workshop_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="workshop" data-number="n1"></div>
            <div id="skiff_slot_workshop_n2" class="soh_skiff_slot soh_unoccupied" data-slotname="workshop" data-number="n2"></div>
            <div id="skiff_slot_trading_post_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="trading_post" data-number="n1"></div>
            <div id="skiff_slot_trading_post_n2" class="soh_skiff_slot soh_unoccupied" data-slotname="trading_post" data-number="n2"></div>
            <div id="skiff_slot_shipyard_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="shipyard" data-number="n1"></div>   
            <div id="skiff_slot_blacksmith_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="blacksmith" data-number="n1"></div>
            <div id="skiff_slot_blacksmith_n2" class="soh_skiff_slot soh_unoccupied" data-slotname="blacksmith" data-number="n2"></div>
            <div id="skiff_slot_sailmaker_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="sailmaker" data-number="n1"></div>
            <div id="skiff_slot_deep_cove_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="deep_cove" data-number="n1"></div>
            <div id="skiff_slot_deep_cove_n2" class="soh_skiff_slot soh_unoccupied" data-slotname="deep_cove" data-number="n2"></div>
            <div id="skiff_slot_green_flag_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="green_flag" data-number="n1"></div>
            <div id="skiff_slot_tan_flag_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="tan_flag" data-number="n1"></div>
            <div id="skiff_slot_blue_flag_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="blue_flag" data-number="n1"></div>
            <div id="skiff_slot_red_flag_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="red_flag" data-number="n1"></div>
            <div id="red_flag_board" class="soh_flagish soh_board_flag" data-tokenkey="red_flag"></div>
            <div id="tan_flag_board" class="soh_flagish soh_board_flag" data-tokenkey="tan_flag"></div>
            <div id="green_flag_board" class="soh_flagish soh_board_flag" data-tokenkey="green_flag"></div>
            <div id="blue_flag_board" class="soh_flagish soh_board_flag" data-tokenkey="blue_flag"></div>
            <div id="seaboard"></div>
        </div>
        <div class="whiteblock soh_market-section">
            <div class="soh_market-container">
                <div class="soh_market-area">
                    <h3>${_("Market")}</h3>
                    <div id="market">
                        <div id="skiff_slot_market_n1" class="soh_skiff_slot soh_unoccupied" data-slotname="market" data-number="n1"></div>
                        <div id="skiff_slot_market_n2" class="soh_skiff_slot soh_unoccupied" data-slotname="market" data-number="n2"></div>
                        <div id="skiff_slot_market_n3" class="soh_skiff_slot soh_unoccupied" data-slotname="market" data-number="n3"></div>
                        <div id="skiff_slot_market_n4" class="soh_skiff_slot soh_unoccupied" data-slotname="market" data-number="n4"></div>
                        <div id="skiff_slot_market_n5" class="soh_skiff_slot soh_unoccupied" data-slotname="market" data-number="n5"></div>
                    </div>
                </div>
                <div class="soh_scrap-area">
                    <h3>${_("Scrap Pile")}</h3>
                    <div id="scrap"></div>
                </div>
                <div class="soh_damage-area">
                    <h3>${_("Damage Deck")}</h3>
                    <div id="damage_deck"></div>
                </div>
            </div>
        </div>

        <div id="mycards">
        <div id="mydeck_wrap" class="whiteblock">
            <h3>${_("My Deck")}</h3>
            <div id="mydeck">
            </div>
        </div>
        <div id="myhand_wrap" class="whiteblock">
            <h3>${_("My Hand")}</h3>
            <div id="myhand">
            </div>
        </div>
        <div id="mydiscard_wrap" class="whiteblock">
            <h3>${_("My Discard")}</h3>
            <div id="mydiscard">
            </div>
        </div>
        </div>

        <div id="myship" class="whiteblock soh_player_board">
        <div id="myresources_wrap">
            <div id="soh_my_resources" class="soh_my_resources"></div>
        </div>
        <div id="captain_wrap">
            <h3>${_("Captain")}</h3>
            <div id="captain_stock">
            </div>
        </div>
        <div id="upgrades_wrap">
            <h3>${_("Ship Upgrades")}</h3>
            <div id="upgrades_stock">
            </div>
        </div>
        </div>

        <div id="soh_other_boards"></div>

      `;

      this.bga.gameArea.getElement().insertAdjacentHTML("beforeend", html);
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

    /** End-of-game breakdown of each player's infamy, above the board. */
    showScoreSheet: function (scores, animate) {
      // A floating popup: the end-of-game results panel redraws the page top, which swallowed an inline sheet.
      document.getElementById("soh_score_sheet_popup")?.remove();
      const popup = document.createElement("div");
      popup.id = "soh_score_sheet_popup";
      document.body.appendChild(popup);
      // Closes on a click outside the sheet or on Escape.
      let closed;
      const closedPromise = new Promise(resolve => { closed = resolve; });
      const onKey = (event) => { if (event.key === "Escape") dismiss(); };
      const dismiss = () => { popup.remove(); document.removeEventListener("keydown", onKey); closed(); };
      popup.addEventListener("click", (event) => { if (event.target === popup) dismiss(); });
      document.addEventListener("keydown", onKey);
      const node = document.createElement("div");
      node.id = "soh_score_sheet";
      popup.append(node);
      const entries = [
        { property: "shots", label: _("Hits") },
        { property: "rams", label: _("Rams") },
        { property: "captain", label: _("Captain") },
        { property: "purchased", label: _("Purchased cards") },
        { property: "damage", label: _("Damage") },
        { property: "upgrades", label: _("Ship upgrades") },
        { property: "total", label: _("Total"), labelClasses: "soh_score_sheet_label soh_score_sheet_total", scoresClasses: "soh_score_sheet_score soh_score_sheet_total", height: 34 },
      ].map(e => ({ labelClasses: "soh_score_sheet_label", scoresClasses: "soh_score_sheet_score", ...e }));
      const sheet = new BgaScoreSheet.ScoreSheet(node, {
        animationsActive: () => animate && this.bgaAnimationsActive(),
        players: this.gamedatas.players,
        entries: entries,
        classes: "soh_score_sheet_parchment",
        playerNameWidth: 100,
        playerNameHeight: 36,
        entryLabelWidth: 150,
        entryLabelHeight: 28,
        scores: animate ? undefined : scores,
      });
      node.append(this.closeButton(dismiss));
      if (!animate) return Promise.resolve();
      // Live game: hold the notification queue until the sheet is closed, so BGA's end-of-game
      // screen only takes over afterwards. Replays (animations off) don't wait.
      const shown = sheet.setScores(scores, { startBy: this.player_id });
      return this.bgaAnimationsActive() ? Promise.all([shown, closedPromise]) : shown;
    },

    setup: function (gamedatas) {
      console.groupCollapsed("Game Setup");
      console.log("dojo version: " + dojo.version);
      console.log(gamedatas);

      // Initialize the game area HTML structure (replaces legacy view.php/tpl files)
      this.setupGameArea();
      this.updateLastSeaPhaseBanner(gamedatas.last_sea_phase);

      this.playable_cards = gamedatas.playable_cards;
      this.non_playable_cards = gamedatas.non_playable_cards;
      this.player_captain = gamedatas.player_captain;
      this.player_ship_upgrades = gamedatas.player_ship_upgrades || [];
      this.corsairOccupiedPlacementAvailable = !!gamedatas.corsair_occupied_placement_available;
      this.corsairOccupiedSlotNames = gamedatas.corsair_occupied_slot_names || [];

      // Log thumbnails are HTML strings, so they get their zoom handlers on first hover or click.
      const setupLogCardPreview = (event, replay) => {
        const art = event.target.closest(".soh_log_card .soh_panel_card_art");
        if (art && !art.dataset.previewReady) {
          this.setupCardPreview(art);
          replay(art);
        }
      };
      document.addEventListener("click", (event) => setupLogCardPreview(event, (art) => art.click()));
      document.addEventListener("mouseover", (event) =>
        setupLogCardPreview(event, (art) => art.dispatchEvent(new MouseEvent("mouseenter"))));

      // Log booty tokens are HTML strings too, so their enlarged copy is placed on hover. Fixed
      // positioning keeps it from being clipped by the log panel.
      document.addEventListener("mouseover", (event) => {
        const token = event.target.closest(".soh_log_booty");
        if (!token || token.contains(event.relatedTarget)) return;
        const zoom = this.createBootyTokenNode(false, token.dataset.imageId);
        zoom.classList.add("soh_booty-token-zoom", "soh_log_booty_zoom");
        this.setBootyTokenPosition(zoom, token.dataset.imageId, 126);
        const rect = token.getBoundingClientRect();
        Object.assign(zoom.style, {
          left: Math.max(0, rect.left - 134) + "px",
          top: Math.max(0, rect.top + rect.height / 2 - 63) + "px",
        });
        document.body.appendChild(zoom);
        token.addEventListener("mouseleave", () => zoom.remove(), { once: true });
      });

      // Card setup helper for playable cards
      this.setupHelper = (card, div) => {
        let image_id = null;
        if (typeof card.type !== "undefined") {
          div.classList.add("soh_playable-card-front");
          div.dataset.cardType = card.type;
          const cardData = this.playable_cards[card.type];
          image_id = cardData.image_id;
          if (image_id === null) {
            // Rowing: its own image, not a sprite slot (see make_rowing_card.py).
            div.classList.add("soh_rowing-card");
            return;
          }
        } else {
          image_id = gamedatas.non_playable_cards.card_back.image_id;
          div.classList.add("soh_playable-card-back");
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
        cardBorderRadius: "8px",

        setupDiv: (card, div) => {
          div.classList.add("soh_seasofhavoc-card");
        },
        setupFrontDiv: (card, div) => {
          this.setupHelper(card, div);
          this.setupCardPreview(div);
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
        cardBorderRadius: "8px",
        setupDiv: (card, div) => {
          div.classList.add("soh_seasofhavoc-card", "non-playable-card");
        },
        setupFrontDiv: (card, div) => {
          this.setupNonPlayableCardHelper(card, div, "front");
          this.setupCardPreview(div);
        },
        setupBackDiv: (card, div) => {
          this.setupNonPlayableCardHelper(card, div, "back");
          this.setupCardPreview(div);
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
          div.classList.add("soh_booty-token");
        },
      });

      // Create HandStock for player hand
      this.playerHand = new BgaCards.HandStock(this.cardsManager, $("myhand"), {
        cardOverlap: 30,
        cardShift: 8,
        inclination: 8,
      });

      // Convert deck_size to number
      const deckSize = parseInt(gamedatas.deck_size, 10);

      // Create the deck
      // All four piles use the library's default counter (a round chip below the pile) so they
      // read the same and never sit on top of a face-up card.
      this.playerDeck = new BgaCards.Deck(this.cardsManager, $("mydeck"), {
        cardNumber: deckSize,
        counter: {
          hideWhenEmpty: true,
        },
      });

      // Set selection mode to single
      this.playerHand.setSelectionMode("single");

      // Shared pile: the game ends when it empties, so the count is the table's clock. Every damage
      // card is the same card, so the pile shows its face - a back would only hide what it is.
      const damageCardType = Number(
        Object.keys(gamedatas.playable_cards).find(
          (type) => gamedatas.playable_cards[type].category === "damage",
        ),
      );
      this.damageDeck = new BgaCards.Deck(this.cardsManager, $("damage_deck"), {
        cardNumber: parseInt(gamedatas.damage_deck_size, 10),
        fakeCardGenerator: (deckId) => ({ id: deckId, type: damageCardType }),
        counter: {
          hideWhenEmpty: true,
        },
      });

      // Top card only, like the damage deck: a fanned pile outgrows its box late in the game.
      // Earlier cards stay in the stock so the pile viewer can still list them.
      this.playerDiscard = new BgaCards.Deck(this.cardsManager, $("mydiscard"), {
        autoRemovePreviousCards: false,
        counter: {
          hideWhenEmpty: true,
        },
      });

      // Cards still sliding in, for the reshuffle to wait on: emptying the pile mid-slide leaves the
      // card's element behind, outside any stock, and drawing that card again then throws.
      this.discardArrivals = new Set();
      const addToDiscard = this.playerDiscard.addCard.bind(this.playerDiscard);
      this.playerDiscard.addCard = (...args) => {
        const arrival = addToDiscard(...args);
        this.discardArrivals.add(arrival);
        arrival.finally(() => this.discardArrivals.delete(arrival));
        return arrival;
      };

      // Same as the discard.
      this.scrapPile = new BgaCards.Deck(this.cardsManager, $("scrap"), {
        autoRemovePreviousCards: false,
        counter: {
          hideWhenEmpty: true,
        },
      });

      this.bindPileViewer($("mydiscard"), _("My Discard"), () =>
        this.showPileDialog(_("My Discard"), this.playerDiscard.getCards()),
      );
      this.bindPileViewer($("scrap"), _("Scrap Pile"), () => this.showPileDialog(_("Scrap Pile"), this.scrapPile.getCards()));
      if (!this.isSpectator) {
        // The client only has card backs for the deck; the server answers with notif_deckContents.
        this.bindPileViewer($("mydeck"), _("My Deck"), () =>
          this.bgaPerformAction("actViewDeck", {}, { checkAction: false }),
        );
      }

      // Create SlotStock for market
      this.market = new BgaCards.SlotStock(this.cardsManager, $("market"), {
        slotsIds: ["market_slot_n1", "market_slot_n2", "market_slot_n3", "market_slot_n4", "market_slot_n5"],
        mapCardToSlot: (card) => {
          if (this.marketSlotMap && card.id) {
            return this.marketSlotMap[card.id] || null;
          }
          return null;
        },
      });

      // Create SlotStock for captain
      this.captainStock = new BgaCards.SlotStock(this.nonPlayableCardsManager, $("captain_stock"), {
        slotsIds: ["captain"],
        mapCardToSlot: (card) => {
          if (card.category === "captain") {
            return "captain";
          }
          return null;
        },
      });

      // Create SlotStock for ship upgrades
      this.upgradesStock = new BgaCards.SlotStock(this.nonPlayableCardsManager, $("upgrades_stock"), {
        slotsIds: ["upgrade_1", "upgrade_2"],
        mapCardToSlot: (card) => {
          if (card.category === "ship_upgrade") {
            const upgrade1Slot = this.upgradesStock.slots["upgrade_1"];
            const upgrade2Slot = this.upgradesStock.slots["upgrade_2"];

            if (upgrade1Slot && upgrade1Slot.children.length === 0) {
              return "upgrade_1";
            } else if (upgrade2Slot && upgrade2Slot.children.length === 0) {
              return "upgrade_2";
            }
            return "upgrade_1";
          }
          return null;
        },
        wrap: "nowrap",
        direction: "row",
        gap: "10px",
      });

      this.cards_purchased = [];
      this._bootyUsedForPurchase = false;

      // Set up selection change callback for HandStock
      this.playerHand.onSelectionChange = (selection, lastChange) => {
        this.onCardSelectedPlayerHand();
      };

      // Debug: log clicks on hand to diagnose selection issues
      var self = this;
      $("myhand").addEventListener("click", function (e) {
        var cardDiv = e.target.closest(".card");
        if (cardDiv) {
          console.warn(
            "[hand-debug] click cardId=" +
              cardDiv.id +
              " hasSelectableClass=" +
              cardDiv.classList.contains("bga-cards_selectable-card") +
              " hasDisabledClass=" +
              cardDiv.classList.contains("bga-cards_disabled-card") +
              " cardsInHand=" +
              self.playerHand.getCards().length +
              " allClasses=" +
              cardDiv.className,
          );
        }
      });

      // Build a set of pending purchase card IDs
      var pending_card_ids = new Set();
      if (gamedatas.pending_purchases) {
        var pending_array = Array.isArray(gamedatas.pending_purchases)
          ? gamedatas.pending_purchases
          : Object.values(gamedatas.pending_purchases);
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

      this.updateHandSelectionMode();

      for (var i in gamedatas.discard) {
        var card = this.gamedatas.discard[i];
        console.log("adding card type: " + card.type + " id: " + card.id + " to player discard");
        this.playerDiscard.addCard({ id: card.id, type: card.type, location: card.location || "discard" });
      }

      // Keyed by id, so restore the scrap order (location_arg) or the wrong card ends up on top.
      const scrapCards = Object.values(gamedatas.scrap).sort((a, b) => a.location_arg - b.location_arg);
      for (var card of scrapCards) {
        console.log("adding card type: " + card.type + " id: " + card.id + " to scrap pile");
        this.scrapPile.addCard({ id: card.id, type: card.type, location: card.location || "scrap" });
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
          location: "market",
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
      this.opponentPiles = {};
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        console.log(`player color: ${player.color}`);

        var skiff = this.skiffHtml("skiff_p" + player_id, player.color);

        var player_board = this.bga.playerPanels.getElement(player_id);
        if (player_board) {
          player_board.insertAdjacentHTML(
            "beforeend",
            `<div class="soh_cp_board soh_resource_row" id="player_resource_board_p${player.id}">
                <div id="sail_p${player.id}" class="soh_sail soh_resource"></div><span id="sailcount_p${player.id}" class="soh_resource_count">0</span>
                <div id="cannonball_p${player.id}" class="soh_cannonball soh_resource"></div><span id="cannonballcount_p${player.id}" class="soh_resource_count">0</span>
                <div id="doubloon_p${player.id}" class="soh_doubloon soh_resource"></div><span id="doublooncount_p${player.id}" class="soh_resource_count">0</span>
                ${skiff} <span id="skiffcount_p${player.id}" class="soh_resource_count">0</span>
            </div>
            <div class="soh_cp_board soh_token_row" id="player_token_board_p${player.id}">
                <div id="green_flag_p${player.id}" class="soh_flagish soh_no_own_flag" data-tokenkey="green_flag"></div>
                <div id="tan_flag_p${player.id}" class="soh_flagish soh_no_own_flag" data-tokenkey="tan_flag"></div>
                <div id="blue_flag_p${player.id}" class="soh_flagish soh_no_own_flag" data-tokenkey="blue_flag"></div>
                <div id="red_flag_p${player.id}" class="soh_flagish soh_no_own_flag" data-tokenkey="red_flag"></div>
                <div id="first_player_token_p${player.id}" class="soh_flagish soh_no_own_flag" data-tokenkey="first_player_token"></div>
                <div id="booty_token_p${player.id}" class="soh_booty-token-slot"></div>
                <div class="soh_hand_count"><span></span><span></span><span></span><span id="handcount_p${player_id}" class="soh_hand_count_number">0</span></div>
            </div>`,
          );
          this.addPlayerPanelCards(player);
          this.addPlayerPanelTooltips(player_id);
        }
      }

      this.islandSlots = gamedatas.islandslots;
      this.players = gamedatas.players;
      this.resources = gamedatas.resources;
      this.unique_tokens = gamedatas.unique_tokens;
      // Ensure booty_tokens is always a JS array (PHP assoc arrays encode as objects)
      var rawBooty = gamedatas.booty_tokens;
      this.booty_tokens = Array.isArray(rawBooty) ? rawBooty : Object.values(rawBooty || {});
      this.players_with_booty = gamedatas.players_with_booty || [];

      this.updateResources(gamedatas.resources);
      this.updateHandCounts(gamedatas.hand_counts);
      this.updateOpponentPiles(gamedatas.location_counts, gamedatas.discard_tops);
      this.updateIslandSlots(gamedatas.islandslots, gamedatas.players);
      this.updateUniqueTokens(gamedatas.unique_tokens);
      this.updateMyBootyToken();
      this.setupMyResourcesPanel();
      // Render facedown tokens for other players who have booty
      for (var i = 0; i < this.players_with_booty.length; i++) {
        var pid = this.players_with_booty[i];
        if (pid != this.player_id) {
          this.renderFacedownTokenForPlayer(pid);
        }
      }

      // Set up seaboard grid: 8x8 cells centred on #seaboard, which is itself placed over the
      // grid printed on the board art (see #seaboard in the stylesheet).
      var CELL = 61.75;
      for (var x = -1; x <= 6; x++) {
        for (var y = -1; y <= 6; y++) {
          var id = "seaboardlocation_" + x + "_" + y;
          domConstruct.place(`<div class="soh_seaboardlocation" id="${id}"></div>`, "seaboard");
          this.placeOnObjectPos($(id), "seaboard", CELL * (x - 2.5), CELL * (y - 2.5));
        }
      }

      this.seaboard = gamedatas.seaboard;
      for (const entry of gamedatas.seaboard) {
        var target_id = "seaboardlocation_" + entry.x + "_" + entry.y;
        switch (entry.type) {
          case "player_ship":
            this.addShipToBoard(entry);
            break;
          case "rock":
          case "gust":
          case "whirlpool":
          case "shipwreck":
            var seafeatureid = entry.type + "_" + entry.arg;
            domConstruct.place(this.seafeatureHtml(seafeatureid, entry.type), "seaboard");
            this.placeOnObject(seafeatureid, target_id);
            this.addSeaFeatureTooltip(seafeatureid, entry.type);
            if (entry.type === "gust") {
              domStyle.set(seafeatureid, "rotate", this.getHeadingDegrees(entry.heading) - 90 + "deg");
            }
            break;
        }
      }

      this.refreshSeaFeatureBadges();
      this.refreshShipwreckPairs();

      // Infamy track: one anchor per space, 0 in the top left corner, running clockwise.
      for (var s = 0; s < 60; s++) {
        var spot = this.getScoreSpot(s);
        domConstruct.place(`<div class="soh_score_location" id="score_location_${s}"></div>`, "board");
        var loc = $("score_location_" + s);
        loc.dataset.fan = spot.fan;
        domStyle.set(loc, { left: spot.x + "px", top: spot.y + "px" });
      }
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        domConstruct.place(
          `<div id="score_marker_${player_id}" class="soh_score_marker" data-color="${player.color}" data-score="${player.score | 0}"></div>`,
          this.scoreLocationId(player.score | 0),
        );
      }
      document.querySelectorAll(".soh_score_location").forEach((loc) => this.fanScoreMarkers(loc));

      if (gamedatas.endScores) {
        this.showScoreSheet(gamedatas.endScores, false);
      }

      // Setup game notifications
      this.setupNotifications();

      var skiffslot_class = query(".soh_skiff_slot");
      var handlers = skiffslot_class.on("click", lang.hitch(this, "onClickSkiffSlot"));

      console.log("Ending game setup");
      console.groupEnd();
    },

    ///////////////////////////////////////////////////
    //// Event Handlers

    /**
     * Centre of infamy track space s (0-59) in board.jpg pixels. Corner spaces (0/15/30/45) are
     * ~48px squares, the 14 spaces along each side are ~30px wide. "fan" is the axis along which
     * markers sharing the space get spread: across the track, or diagonally in corners.
     */
    getScoreSpot: function (s) {
      const L = 264, R = 736, T = 60, B = 532.5; // corner centres
      const X0 = 288, X1 = 711.5, Y0 = 85, Y1 = 508; // inner edges of the corners
      const PX = (X1 - X0) / 14, PY = (Y1 - Y0) / 14;
      if (s % 15 == 0) {
        const [x, y] = [[L, T], [R, T], [R, B], [L, B]][s / 15];
        return { x, y, fan: "diag" };
      }
      const i = (s % 15) - 0.5;
      if (s < 15) return { x: X0 + PX * i, y: T, fan: "y" };
      if (s < 30) return { x: R, y: Y0 + PY * i, fan: "x" };
      if (s < 45) return { x: X1 - PX * i, y: B, fan: "y" };
      return { x: L, y: Y1 - PY * i, fan: "x" };
    },

    /** Track space for a score; the track loops every 60 (rulebook: add 60 per lap). */
    scoreLocationId: function (score) {
      return "score_location_" + (((score % 60) + 60) % 60);
    },

    /**
     * Move a player's infamy marker along the track. It slides through every corner it passes
     * instead of cutting across, and markers re-fanned around it glide aside; resolves when done.
     */
    moveScoreMarker: function (player_id, score) {
      const marker = $("score_marker_" + player_id);
      const oldScore = Number(marker.dataset.score);
      marker.dataset.score = score;
      const oldLoc = marker.parentNode;
      const newLoc = $(this.scoreLocationId(score));
      const step = Math.sign(score - oldScore);
      const corners = [];
      for (let s = oldScore + step; s !== score; s += step) {
        if ((((s % 60) + 60) % 60) % 15 == 0) corners.push($(this.scoreLocationId(s)).getBoundingClientRect());
      }
      const affected = [...new Set([...oldLoc.children, ...newLoc.children])];
      const before = new Map(affected.map((m) => [m, m.getBoundingClientRect()]));
      newLoc.appendChild(marker);
      this.fanScoreMarkers(oldLoc);
      this.fanScoreMarkers(newLoc);
      if (!this.bgaAnimationsActive()) return Promise.resolve();
      // Moving to the top: keep the travelling skull above the ones it passes or lands on.
      marker.style.zIndex = 1;
      return Promise.all(
        affected.map((m) => {
          const from = before.get(m), to = m.getBoundingClientRect();
          // Waypoints as offsets from where the marker ends up; corner anchors are 0x0 points.
          const points = [{ x: from.left - to.left, y: from.top - to.top }];
          if (m === marker) {
            const cx = to.left + to.width / 2, cy = to.top + to.height / 2;
            corners.forEach((c) => points.push({ x: c.left - cx, y: c.top - cy }));
          }
          points.push({ x: 0, y: 0 });
          const legs = points.slice(1).map((p, i) => Math.hypot(p.x - points[i].x, p.y - points[i].y));
          const length = legs.reduce((a, b) => a + b, 0);
          if (!length) return null;
          let travelled = 0;
          const keyframes = points.map((p, i) => {
            if (i > 0) travelled += legs[i - 1];
            return { translate: `${p.x}px ${p.y}px`, offset: travelled / length };
          });
          return m.animate(keyframes, {
            duration: m === marker ? Math.max(600, length * 2) : 300,
            easing: "ease-in-out",
          }).finished;
        }),
      ).then(() => (marker.style.zIndex = ""));
    },

    /** Spread the markers sharing a space along its fan axis, centred on the space. */
    fanScoreMarkers: function (loc) {
      const markers = loc.querySelectorAll(".soh_score_marker");
      const step = loc.dataset.fan == "diag" ? 6 : Math.min(8, 24 / Math.max(1, markers.length - 1));
      markers.forEach((m, k) => {
        const d = (k - (markers.length - 1) / 2) * step;
        const dx = loc.dataset.fan == "y" ? 0 : d;
        const dy = loc.dataset.fan == "x" ? 0 : d;
        m.style.transform = `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px))`;
      });
    },

    onClickSkiffSlot: function (event) {
      console.log("$$$$ Event : onClickSkiffSlot");
      console.log(event);
      event.preventDefault();
      const source = event.target || event.srcElement;
      const sourceElement = source && source.nodeType === 1 ? source : null;
      const slotElement = sourceElement ? sourceElement.closest(".soh_skiff_slot") : null;
      if (!this.checkAction("actPlaceSkiff")) {
        console.log("nope");
        return;
      }

      if (!slotElement) {
        console.log("not a skiff slot");
        return;
      }

      if (slotElement.classList.contains("disabled")) {
        console.log("skiff slot is disabled for this player count");
        return;
      }

      if (slotElement.classList.contains("soh_occupied_blocked")) {
        console.log("skiff slot is occupied and not available");
        return;
      }

      if (slotElement.classList.contains("soh_slot_unavailable")) {
        console.log("skiff slot is not available");
        return;
      }

      console.log(slotElement.dataset.slotname, slotElement.dataset.number);

      if (!this.isCurrentPlayerActive()) {
        return;
      }
      this.confirmIslandAction(_("Confirm placement"), () => {
        this.bgaPerformAction("actPlaceSkiff", {
          slotname: slotElement.dataset.slotname,
          number: slotElement.dataset.number,
        });
      }, slotElement);
    },

    /**
     * Swaps the status bar for Confirm / Cancel before an island action or a ship's starting
     * direction is sent, unless the player
     * turned that off (preference 100: 1 = confirm, 2 = don't). Confirm counts down and fires on
     * its own (BGA's autoclick); Cancel puts the normal buttons back. slotElement, if given, is
     * highlighted while waiting.
     */
    confirmIslandAction: function (label, action, slotElement) {
      this.clearPendingSkiffSlot();
      const confirmed = () => {
        this.clearPendingSkiffSlot();
        action();
      };
      if (this.getGameUserPreference(100) != 1) {
        confirmed();
        return;
      }
      if (slotElement) {
        slotElement.classList.add("soh_pending_skiff");
      }
      this.statusBar.removeActionButtons();
      this.statusBar.addActionButton(label, confirmed, { autoclick: true });
      this.statusBar.addActionButton(_("Cancel"), () => {
        this.clearPendingSkiffSlot();
        this.restoreServerGameState();
      }, { color: "alert" });
    },

    clearPendingSkiffSlot: function () {
      query(".soh_pending_skiff").removeClass("soh_pending_skiff");
    },

    onCardSelectedPlayerHand: function () {
      if (this._cardPick) {
        this._onCardPicked(this.playerHand);
        return;
      }
      if (this._rowing) {
        // The hand is picking rowing's two discards; the maneuver dialog is up while there are two.
        this._syncRowDiscards();
        if (this.playerHand.getSelection().length === 2) {
          this.showRowingDialog();
        } else {
          this.cleanupCardPlayDialog();
        }
        return;
      }
      console.groupCollapsed("player card selected");
      var selection = this.playerHand.getSelection();
      console.log("player hand selection:", selection);

      if (selection.length == 1) {
        // Only allow card selection when it's the player's turn and they can play cards
        if (!this.isCurrentPlayerActive() || !this.checkAction("actPlayCard", true)) {
          console.log("Not active player or cannot play cards - clearing selection");
          this.cleanupCardPlayDialog();
          console.groupEnd();
          return;
        }

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
  var modulesToMixin = [Utils, IslandSlots, CardManager, Dialogs, Purchases, TradingPost, TreasureSeeker, StateHandlers, Notifications, CardPreview];

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
