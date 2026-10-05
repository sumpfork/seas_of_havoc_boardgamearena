/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/**
 * Seas of Havoc - Utility Methods Module
 * Helper functions for resource management and game state utilities
 */

define([
  "dojo/dom",
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/dom-style",
  "dojo/query",
  "dojo/_base/fx",
  "dojo/fx",
  g_gamethemeurl + "modules/js/constants.js",
], function (dom, domClass, domConstruct, domStyle, query, baseFX, fx, Constants) {
  // Debug logging only in Studio (see constants.js).
  const console = Constants.console;

  return {
    /**
     * The one way to ask for a resource: a grey, icon-only status bar button per resource, always
     * in sail / cannonball / doubloon order. The title says what the choice is for.
     * @param {function(string)} onPick called with the chosen resource
     * @param {string[]} [offered] the resources to offer, if not all three
     * @param {string} [suffix] html after each icon, e.g. whose resource it is
     */
    addResourceButtons: function (onPick, offered = ["sail", "cannonball", "doubloon"], suffix = "") {
      ["sail", "cannonball", "doubloon"]
        .filter((resource) => offered.includes(resource))
        .forEach((resource) =>
          this.statusBar.addActionButton(this.resourceIcon(resource) + suffix, () => onPick(resource), {
            color: "secondary",
          }),
        );
    },

    resourceIcon: function (resource) {
      const names = { sail: _("sail"), cannonball: _("cannonball"), doubloon: _("doubloon"), infamy: _("infamy") };
      if (!Object.hasOwn(names, resource)) throw new Error("Unknown resource icon: " + resource);
      const label = names[resource].replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;");
      return (
        '<span class="soh_resource soh_log_resource soh_' +
        resource +
        '" data-resource="' +
        resource +
        '" role="img" aria-label="' +
        label +
        '" title="' +
        label +
        '"></span>'
      );
    },

    /**
     * Find an object on the seaboard by type and arg
     */
    /** Glow the active player's ship(s) in their colour, so it is clear whose turn it is on the board. */
    highlightActivePlayerShips: function (playerId) {
      for (const ship of document.querySelectorAll("#seaboard .soh_player_ship")) {
        const owner = ship.id.replace("player_ship_", "").split("_")[0];
        const active = playerId != null && owner == playerId;
        ship.classList.toggle("soh_active_turn_ship", active);
        ship.style.setProperty("--owner-color", active ? "#" + this.gamedatas.playerinfo[owner].player_color : "");
      }
    },

    /**
     * A ship on a whirlpool or gust hides it, though it acts on the ship after every card. Show a
     * small copy of the feature in the corner of the square, above the ship.
     */
    refreshSeaFeatureBadges: function () {
      document.querySelectorAll(".soh_seafeature_badge").forEach((node) => node.remove());
      const ships = this.seaboard.filter((e) => e.type === "player_ship");
      for (const feature of this.seaboard) {
        if (feature.type !== "whirlpool" && feature.type !== "gust") continue;
        if (!ships.some((ship) => ship.x == feature.x && ship.y == feature.y)) continue;
        const badge = domConstruct.place(
          `<div class="soh_seafeature soh_seafeature_badge" data-seafeature="${feature.type}"></div>`,
          "seaboardlocation_" + feature.x + "_" + feature.y,
        );
        if (feature.type === "gust") {
          badge.style.rotate = this.getHeadingDegrees(feature.heading) - 90 + "deg";
        }
      }
    },

    /** A ship's element on the sea grid; one still without a heading is drawn unturned. */
    addShipToBoard: function (entry) {
      // A second ship (2 Ship Variant) is "<player id>_2" on the board.
      const [owner, second] = String(entry.arg).split("_");
      const ownerInfo = this.gamedatas.playerinfo[owner];
      const shipid = "player_ship_" + entry.arg;
      domConstruct.place(
        this.format_block("jstpl_player_ship", {
          id: shipid,
          shipname: second ? ownerInfo.player_ship2 : ownerInfo.player_ship,
        }),
        "seaboard",
      );
      this.placeOnObject(shipid, "seaboardlocation_" + entry.x + "_" + entry.y);
      if (Number(entry.heading) !== 0) {
        domStyle.set(shipid, "rotate", this.getHeadingDegrees(entry.heading) + "deg");
      }
    },

    /** Choose Heading: reveal the active player's ship and, for them, an arrow on each side of it. */
    setupChooseHeading: function (args) {
      const ship = args.ship;
      if (!this.getObjectOnSeaboard("player_ship", ship.arg)) {
        const entry = { type: "player_ship", arg: ship.arg, x: ship.x, y: ship.y, heading: 0 };
        this.seaboard.push(entry);
        this.addShipToBoard(entry);
      }
      if (!this.isCurrentPlayerActive()) return;
      // Heading -> step to the neighbouring cell; the grid has a cell of margin all round.
      const steps = { 1: [0, -1], 2: [1, 0], 3: [0, 1], 4: [-1, 0] };
      for (const [heading, [dx, dy]] of Object.entries(steps)) {
        const arrow = domConstruct.create("div", { class: "soh_heading_arrow" }, "seaboard");
        this.placeOnObject(arrow, "seaboardlocation_" + (ship.x + dx) + "_" + (ship.y + dy));
        // The glyph points east (heading 2).
        arrow.style.rotate = (heading - 2) * 90 + "deg";
        arrow.addEventListener("click", () => this.bgaPerformAction("actChooseHeading", { heading: Number(heading) }));
      }
    },

    cleanupChooseHeading: function () {
      query(".soh_heading_arrow").forEach(domConstruct.destroy);
    },

    /** The cards a draft choice brings: a captain's card and deck cards, or a ship's upgrades and starting deck. */
    draftChoiceCards: function (stateName, choice) {
      const cards = Object.values(this.playable_cards);
      if (stateName === "draftCaptain") {
        return [{ image_id: this.non_playable_cards[choice].image_id, nonPlayable: true }].concat(
          cards.filter(c => c.category === "captain" && c.captain_key === choice));
      }
      return Object.values(this.non_playable_cards)
        .filter(c => c.category === "ship_upgrade" && c.ship_name === choice)
        .map(c => ({ image_id: c.image_id, nonPlayable: true }))
        .concat(cards.filter(c => c.category === "starting_card" && c.ship_name === choice));
    },

    draftChoiceName: function (stateName, choice) {
      return stateName === "draftCaptain" ? _(this.non_playable_cards[choice].name) : _(choice);
    },

    /** Snake draft: every player sees what each captain or ship still on offer brings. */
    setupDraft: function (stateName, args) {
      this.cleanupDraft();
      const panel = domConstruct.create("div", { id: "soh_draft", class: "whiteblock" }, this.bga.gameArea.getElement(), "first");
      for (const choice of stateName === "draftCaptain" ? args.captains : args.ships) {
        const option = domConstruct.create("div", { class: "soh_draft_option" }, panel);
        const title = domConstruct.create("h3", {}, option);
        if (stateName === "draftShip") {
          domConstruct.create("div", { class: "soh_player_ship soh_panel_ship", "data-shipname": choice }, title);
        }
        title.append(this.draftChoiceName(stateName, choice));
        const row = domConstruct.create("div", { class: "soh_draft_cards" }, option);
        for (const card of this.draftChoiceCards(stateName, choice)) {
          const thumb = domConstruct.create("div", { class: "soh_draft_card" }, row);
          const art = domConstruct.create("div", {
            class: (card.nonPlayable ? "soh_non-playable-card-front" : "soh_playable-card-front") + " soh_panel_card_art",
            style: `width: 144px; height: 198px; background-position: -${(card.image_id % 6) * 144}px -${Math.floor(card.image_id / 6) * 198}px`,
          }, thumb);
          this.setupCardPreview(art);
          if (card.count > 1) {
            domConstruct.create("span", { class: "soh_draft_count", innerHTML: "&times;" + card.count }, thumb);
          }
        }
      }
    },

    cleanupDraft: function () {
      domConstruct.destroy("soh_draft");
    },

    getObjectOnSeaboard: function (object_type, arg) {
      for (const entry of this.seaboard) {
        if (entry.type == object_type && entry.arg == arg) {
          return entry;
        }
      }
    },

    /**
     * Update unique token displays (first player, etc.)
     */
    updateUniqueTokens: function (unique_tokens) {
      console.log("updating unique tokens");
      console.log(unique_tokens);
      for (const [token_key, player_id] of Object.entries(unique_tokens)) {
        console.log("token id: " + token_key + " player id: " + player_id);
        if (player_id === null) {
          continue;
        }
        var token_element = this.placeUniqueToken(token_key, player_id);
        domStyle.set(token_element, "zIndex", 1);
      }
    },

    /**
     * Park the one element that represents a unique token on a player's panel slot, creating it
     * the first time. There is only ever one of each token, so it is reparented rather than
     * redrawn - that is what lets a transfer animate from the previous owner's panel.
     */
    placeUniqueToken: function (token_key, player_id) {
      var slot_id = `${token_key}_p${player_id}`;
      var token_element = dom.byId("token_" + token_key);
      if (!token_element) {
        token_element = domConstruct.place(this.format_block("jstpl_unique_token", { token_key: token_key }), slot_id);
      } else {
        this.attachToNewParent(token_element, slot_id);
      }
      domStyle.set(token_element, { left: "0px", top: "0px" });
      return token_element;
    },

    // Booty sprite: 4 cols. Col 0 = seafeatures. Cols 1,2 = 4 tokens each (rows 0–3). Col 3 = 1 token (row 0). cellSize 63 = full, 50 = slot.
    setBootyTokenPosition: function (node, imageId, cellSize) {
      const index = parseInt(imageId, 10);
      if (Number.isNaN(index)) {
        console.warn("[booty] setBootyTokenPosition invalid imageId", imageId);
        return;
      }
      let col, row;
      if (index <= 3) {
        col = 1;
        row = index;
      } else if (index <= 7) {
        col = 2;
        row = index - 4;
      } else {
        col = 3;
        row = 0;
      }
      const x = col * cellSize;
      const y = row * cellSize;
      domStyle.set(node, "backgroundPosition", `-${x}px -${y}px`);
    },

    setBootyTokenImage: function (node, imageId) {
      console.log("[booty] setBootyTokenImage", { imageId });
      this.setBootyTokenPosition(node, imageId, 63);
    },

    setBootyTokenImageForSlot: function (node, imageId) {
      console.log("[booty] setBootyTokenImageForSlot", { imageId });
      this.setBootyTokenPosition(node, imageId, 50);
    },

    createBootyTokenNode: function (isBack, imageId) {
      if (isBack) {
        console.log("[booty] createBootyTokenNode facedown (back art)");
      } else {
        console.log("[booty] createBootyTokenNode faceup", { imageId });
      }
      const classes = isBack ? "soh_booty-token soh_booty-token-back" : "soh_booty-token";
      const node = domConstruct.create("div", { className: classes });
      if (!isBack) {
        this.setBootyTokenImage(node, imageId);
      }
      return node;
    },

    /**
     * All tooltip text, by kind and then by key. Built on each call so _() runs after the
     * translations have loaded.
     */
    tooltipTexts: function () {
      return {
        // What placing a skiff does, keyed by the slot's data-slotname.
        skiff_slot: {
          capitol: _("Take the first player token and gain 1 resource of your choice"),
          bank: _("Gain 1 doubloon and 1 resource of your choice"),
          shipyard: _("Gain 2 sails and 1 cannonball"),
          sailmaker: _("Gain 3 sails"),
          blacksmith: _("Gain 2 cannonballs"),
          workshop: _("Pay its cost to activate one of your ship upgrades"),
          trading_post: _("Exchange up to 2 resources"),
          deep_cove: _("Scrap up to 2 cards from your hand or discard pile and gain their cost in resources"),
          market: _("Claim this card. You pay for it at the end of the Island Phase and add it to your hand"),
          green_flag: _("Take the Purser's flag and gain 1 resource of your choice"),
          tan_flag: _("Take the Bosun's flag and draw 1 card"),
          red_flag: _("Take the Shipwright's flag and scrap 1 card from your hand or discard pile, gaining its cost"),
          blue_flag: _("Take the Sailor's flag and place another skiff"),
        },
        // Player panel token slots, keyed by data-tokenkey.
        panel_token: {
          green_flag: _("Purser's flag: when you play a card showing this flag, you may gain 1 resource of your choice"),
          tan_flag: _("Bosun's flag: when you play a card showing this flag, you may draw 1 card"),
          red_flag: _("Shipwright's flag: when you play a card showing this flag, you may scrap a card from your hand or discard pile, including that card"),
          blue_flag: _("Sailor's flag: when you play a card showing this flag, you may play another card immediately"),
          first_player_token: _("First player: places the first skiff in the Island Phase and plays the first card in the Sea Phase"),
        },
        resource: {
          sail: _("Sails"),
          cannonball: _("Cannonballs"),
          doubloon: _("Doubloons"),
          skiff: _("Skiffs left to place this Island Phase"),
        },
        sea_feature: {
          rock: _("Rock: a ship that runs into it stops, discards a card and takes a damage card"),
          whirlpool: _("Whirlpool: a ship on here after a card is resolved pivots 90° counter-clockwise"),
          gust: _("Gust: a ship on here after a card is resolved is pushed 1 space the way the gust blows, without changing heading"),
          shipwreck: _("Shipwreck: sail onto it to take its booty token"),
        },
        booty_token: _("Booty token: spend it whenever you pay resources, but it is spent whole - any resources left on it are lost"),
      };
    },

    /** The tooltip text for one key of one kind; throws if there is none, so a gap shows up. */
    tooltipText: function (kind, key) {
      const texts = this.tooltipTexts()[kind];
      const text = key === undefined ? texts : texts && texts[key];
      if (typeof text !== "string") {
        throw new Error(`No tooltip text for ${kind} ${key}`);
      }
      return text;
    },

    /** Player panel flags, first player token and resources. */
    addPlayerPanelTooltips: function (player_id) {
      for (const slot of document.querySelectorAll(`#player_token_board_p${player_id} > [data-tokenkey]`)) {
        this.addTooltip(slot.id, this.tooltipText("panel_token", slot.dataset.tokenkey), "");
      }
      for (const resource of ["sail", "cannonball", "doubloon", "skiff"]) {
        const text = this.tooltipText("resource", resource);
        this.addTooltip(`${resource}_p${player_id}`, text, "");
        this.addTooltip(`${resource}count_p${player_id}`, text, "");
      }
    },

    addSeaFeatureTooltip: function (node_id, seafeature_type) {
      this.addTooltip(node_id, this.tooltipText("sea_feature", seafeature_type), "");
    },

    /** A face-down booty token (another player's): what booty is, without what is on it. */
    addFacedownBootyTokenTooltip: function (node) {
      node.id = node.id || "booty_token_view_" + this._nextEffectId();
      this.addTooltip(node.id, this.tooltipText("booty_token"), "");
    },

    /** A large copy of a face-up booty token on hover, so the resources on it can be read. */
    addBootyTokenTooltip: function (node, imageId) {
      node.id = node.id || "booty_token_view_" + this._nextEffectId();
      const zoom = this.createBootyTokenNode(false, imageId);
      zoom.classList.add("soh_booty-token-zoom");
      this.setBootyTokenPosition(zoom, imageId, 126);
      this.addTooltipHtml(node.id, `<p>${this.tooltipText("booty_token")}</p>` + zoom.outerHTML);
    },

    /**
     * Draw the player's own hold from the tokens they actually hold. (lastBootyTokenTypeArg is only
     * for showing a just-collected token face up as it flies in: drawing the hold from it showed a
     * token the player had just chosen to discard.)
     */
    updateMyBootyToken: function () {
      const tokens = this.booty_tokens || [];
      const mySlot = dom.byId(`booty_token_p${this.player_id}`);
      domConstruct.empty(mySlot);
      // The Galleon's Treasure Hold can carry two tokens, so show them all.
      tokens.forEach((token) => {
        const node = this.createBootyTokenNode(false, token.type_arg);
        this.setBootyTokenImageForSlot(node, token.type_arg);
        domConstruct.place(node, mySlot);
        this.addBootyTokenTooltip(node, token.type_arg);
      });
      domClass.toggle(mySlot, "soh_has-token", tokens.length > 0);
    },

    renderFacedownTokenForPlayer: function (playerId) {
      console.log("[booty] renderFacedownTokenForPlayer", playerId);
      const slot = dom.byId(`booty_token_p${playerId}`);
      domConstruct.empty(slot);
      const node = this.createBootyTokenNode(true, null);
      domConstruct.place(node, slot);
      this.addFacedownBootyTokenTooltip(node);
      domClass.add(slot, "soh_has-token");
    },

    _nextEffectId: function () {
      this._effectCounter = (this._effectCounter || 0) + 1;
      return this._effectCounter;
    },

    /** Flash an explosion on a board square (cannon hits, rocket blasts). */
    /**
     * The burst as an unplayed animation, so a card's effects can be sequenced with its moves
     * instead of all going off at once. `extraClass` recolours it for a ram or a splash. Returns
     * null when the target square is not on screen.
     */
    explosionAnimation: function (x, y, extraClass) {
      const targetId = "seaboardlocation_" + x + "_" + y;
      if (!dom.byId(targetId)) {
        return null;
      }
      const id = "explosion_" + this._nextEffectId();
      domConstruct.place(this.format_block("jstpl_explosion", { id: id }), targetId);
      if (extraClass) {
        domClass.add(id, extraClass);
      }
      domStyle.set(id, "opacity", "0");
      return fx.chain([
        // 0.8s in total: 50 delay + 150 in + 400 hold + 200 out.
        baseFX.fadeIn({
          node: id,
          delay: 50,
          duration: 150,
          beforeBegin: () => {
            this.bga.sounds.play("crash");
          },
        }),
        baseFX.fadeOut({
          node: id,
          delay: 400,
          duration: 200,
          onEnd: function () {
            domConstruct.destroy(id);
          },
        }),
      ]);
    },

    /**
     * The muzzle flash as an unplayed animation, sitting on the firing ship and pointing the way
     * the shot went. Used for hits and misses alike - a miss is still a shot.
     */
    muzzleFlashAnimation: function (shipId, fireHeading) {
      // The art is a barrel at the top with the blast spiking out of the bottom, so it fires south
      // unturned: north needs a half turn, and each heading after it a further quarter turn.
      if (![1, 2, 3, 4].includes(Number(fireHeading))) {
        throw new Error("Cannot fire towards heading " + fireHeading);
      }
      // On the board, not inside the ship: the ship is rotated to its heading, which would rotate
      // the flash and its offset a second time.
      const board = dom.byId("seaboard");
      const id = "cannonfire_" + this._nextEffectId();
      domConstruct.place(this.format_block("jstpl_cannon_fire", { id: id }), board);
      domStyle.set(id, "rotate", (((fireHeading - 1) * 90 + 180) % 360) + "deg");
      domStyle.set(id, "opacity", 0);
      // Measured when the flash plays: moves queued ahead of it have not happened yet.
      const placeOnShip = () => {
        const b = board.getBoundingClientRect();
        const r = dom.byId(shipId).getBoundingClientRect();
        domStyle.set(id, "left", r.left - b.left + r.width / 2 + "px");
        domStyle.set(id, "top", r.top - b.top + r.height / 2 + "px");
      };
      return fx.chain([
        baseFX.fadeIn({
          node: id,
          duration: 80,
          beforeBegin: () => {
            placeOnShip();
            this.bga.sounds.play("cannon_fire"); // sounds/cannon_fire.mp3/.ogg, preloaded by BGA
          },
        }),
        baseFX.fadeOut({
          node: id,
          duration: 200,
          delay: 100,
          onEnd: function () {
            domConstruct.destroy(id);
          },
        }),
      ]);
    },

    /**
     * A bright ball flying from the firing ship to the square the shot lands on: without it a
     * flash on the hull alone does not read as "that ship shot over there". A shot that wraps
     * around the board edge flies off that edge and comes back in from the opposite one.
     * Returns null when either end is off screen.
     */
    tracerAnimation: function (shipId, fireHeading, x, y) {
      const NORTH = 1,
        EAST = 2,
        SOUTH = 3,
        WEST = 4;
      const ship = dom.byId(shipId);
      const target = dom.byId("seaboardlocation_" + x + "_" + y);
      const board = dom.byId("seaboard");
      if (!ship || !target || !board) {
        return null;
      }
      const centre = (node) => {
        const b = board.getBoundingClientRect();
        const r = node.getBoundingClientRect();
        return { left: r.left - b.left + r.width / 2, top: r.top - b.top + r.height / 2 };
      };
      const cell = (cx, cy) => centre(dom.byId("seaboardlocation_" + cx + "_" + cy));
      // Off-board cells (-1 and 6) the shot leaves by and re-enters from, on the target's line.
      const edges = {
        [NORTH]: () => [cell(x, -1), cell(x, 6)],
        [SOUTH]: () => [cell(x, 6), cell(x, -1)],
        [EAST]: () => [cell(6, y), cell(-1, y)],
        [WEST]: () => [cell(-1, y), cell(6, y)],
      }[fireHeading];
      if (!edges) {
        throw new Error("Cannot fire towards heading " + fireHeading);
      }
      // Measured when the shot plays, not now: moves queued ahead of it have not happened yet.
      let path = null;
      const plan = () => {
        if (!path) {
          const from = centre(ship);
          const to = centre(target);
          const [exit, entry] = edges();
          // The target sits behind the ship relative to the fire heading exactly when the shot wrapped.
          const ahead =
            (exit.left - from.left) * (to.left - from.left) + (exit.top - from.top) * (to.top - from.top) > 0;
          path = ahead ? [from, to, to, to] : [from, exit, entry, to];
          if (ahead) {
            // Straight shot: all the flight time goes to the one leg.
            first.duration = 350;
            second.duration = 0;
          }
        }
        return path;
      };

      const id = "tracer_" + this._nextEffectId();
      domConstruct.place('<div id="' + id + '" class="soh_tracer" style="display: none"></div>', board);
      const leg = (i) =>
        baseFX.animateProperty({
          node: id,
          duration: 175,
          beforeBegin: () => domStyle.set(id, "display", ""),
          properties: {
            left: () => ({ start: plan()[i].left, end: plan()[i + 1].left }),
            top: () => ({ start: plan()[i].top, end: plan()[i + 1].top }),
          },
        });
      const first = leg(0);
      const second = leg(2);
      const anim = fx.chain([first, second]);
      anim.onEnd = function () {
        domConstruct.destroy(id);
      };
      return anim;
    },

    /** Muzzle flash, then the tracer flying out to where the shot lands. */
    shotAnimation: function (shipId, fireHeading, x, y) {
      const flash = this.muzzleFlashAnimation(shipId, fireHeading);
      const tracer = this.tracerAnimation(shipId, fireHeading, x, y);
      return tracer ? fx.chain([flash, tracer]) : flash;
    },

    animateExplosionAt: function (x, y) {
      const animation = this.explosionAnimation(x, y);
      if (animation) {
        animation.play();
      }
    },

    animateBootyTokenPickup: function (event, playerId) {
      if (!event) {
        return;
      }
      console.groupCollapsed("[booty] animateBootyTokenPickup");
      console.log("event", event, "playerId", playerId);
      const targetSlotId = `booty_token_p${playerId}`;
      const seaboardNode = dom.byId("seaboard");
      const isOwner = playerId == this.player_id;
      const faceupTypeArg = isOwner ? this.lastBootyTokenTypeArg : null;
      console.log("[booty] pickup animation", { isOwner, faceupTypeArg });
      const tokenNode =
        isOwner && faceupTypeArg != null
          ? this.createBootyTokenNode(false, faceupTypeArg)
          : this.createBootyTokenNode(true, null);
      const tokenId = `booty_pickup_${playerId}_${event.shipwreck_arg}_${Date.now()}`;
      tokenNode.id = tokenId;
      domClass.add(tokenNode, "soh_booty-token-pickup");
      const overlayRoot = dom.byId("overall_game") || seaboardNode;
      domConstruct.place(tokenNode, overlayRoot);
      const startId = `seaboardlocation_${event.old_x}_${event.old_y}`;
      this.placeOnObject(tokenId, startId);
      const anim = this.slideToObject(tokenId, targetSlotId, 800);
      const self = this;
      anim.onEnd = function () {
        console.log("[booty] animation onEnd", {
          tokenId,
          playerId,
          myPlayerId: self.player_id,
          lastTypeArg: self.lastBootyTokenTypeArg,
        });
        if (!self.debugBootyPickup) {
          domConstruct.destroy(tokenId);
        }
        if (String(playerId) === String(self.player_id)) {
          self.updateMyBootyToken();
        } else {
          self.renderFacedownTokenForPlayer(playerId);
        }
      };
      anim.play();
      console.groupEnd();
    },

    applyShipwreckEvents: function (events) {
      if (!events || !events.length) {
        return;
      }
      console.groupCollapsed("[booty] applyShipwreckEvents");
      console.log(events);
      for (const event of events) {
        console.log("[booty] applyShipwreckEvent", event);
        const shipwreckId = `shipwreck_${event.shipwreck_arg}`;
        if (dom.byId(shipwreckId)) {
          domConstruct.destroy(shipwreckId);
        }
        const targetId = `seaboardlocation_${event.new_x}_${event.new_y}`;
        const seafeature = this.format_block("jstpl_seafeature", {
          id: shipwreckId,
          seafeature_type: "shipwreck",
        });
        domConstruct.place(seafeature, "seaboard");
        this.placeOnObject(shipwreckId, targetId);
        this.addSeaFeatureTooltip(shipwreckId, "shipwreck");
        let updated = false;
        for (const entry of this.seaboard) {
          if (entry.type === "shipwreck" && entry.arg == event.shipwreck_arg) {
            entry.x = event.new_x;
            entry.y = event.new_y;
            updated = true;
            break;
          }
        }
        if (!updated) {
          this.seaboard.push({
            type: "shipwreck",
            arg: event.shipwreck_arg,
            x: event.new_x,
            y: event.new_y,
            heading: 0,
          });
        }
      }
      console.groupEnd();
    },

    /**
     * Update resource count displays for all players
     */
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

    /**
     * Update deck card count display
     */
    updateDeckCount: function (deckSize) {
      console.log("updating deck count");
      console.log("deck size: " + deckSize);
      const deckSizeNum = parseInt(deckSize, 10);
      this.playerDeck.setCardNumber(deckSizeNum);
    },

    /**
     * Damage deck: shared, and the game ends when it runs out, so everyone sees the count.
     */
    updateDamageDeckCount: function (deckSize) {
      this.damageDeck.setCardNumber(parseInt(deckSize, 10));
    },

    /**
     * Get current player's resources as an object
     */
    getPlayerResources: function () {
      var playerResources = {};
      console.log("active player id: " + this.getActivePlayerId());
      console.log("this.player_id: " + this.player_id);
      for (const resource of this.resources) {
        if (resource.player_id == this.player_id) {
          playerResources[resource.resource_key] = resource.resource_count;
        }
      }
      return playerResources;
    },

    /**
     * Add two resource objects together
     */
    addResources: function (r1, r2) {
      var sum = {};
      for (const [resource_key, num] of Object.entries(r1)) {
        sum[resource_key] = num;
      }
      for (const [resource_key, num] of Object.entries(r2)) {
        if (Object.hasOwn(sum, resource_key)) {
          sum[resource_key] += num;
        } else {
          sum[resource_key] = num;
        }
      }
      return sum;
    },

    /**
     * Spend resources for current player (local update)
     */
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

    /**
     * Check if current player can afford a resource cost.
     * @param {boolean} includeBooty - If true (default), count booty token resources.
     */
    canPlayerAfford: function (resource_cost, includeBooty, includeMerchant) {
      if (typeof includeBooty === "undefined") includeBooty = true;
      if (typeof includeMerchant === "undefined") includeMerchant = true;
      if (typeof resource_cost === "undefined") {
        return true;
      }
      var playerResources = this.getPlayerResources();

      // Merchant ability: doubloons can cover cannonball costs for market purchases
      if (includeMerchant && this.player_captain === "merchant" && resource_cost.cannonball) {
        var cannonball_need = resource_cost.cannonball;
        var cannonball_have = playerResources.cannonball || 0;
        var doubloon_have = playerResources.doubloon || 0;
        var doubloon_need = resource_cost.doubloon || 0;
        var cannonball_shortfall = Math.max(0, cannonball_need - cannonball_have);
        var doubloon_surplus = Math.max(0, doubloon_have - doubloon_need);
        if (cannonball_shortfall > 0 && doubloon_surplus >= cannonball_shortfall) {
          // Can afford by substituting doubloons — check with adjusted cost
          var adjusted = Object.assign({}, resource_cost);
          adjusted.cannonball = cannonball_need - cannonball_shortfall;
          adjusted.doubloon = doubloon_need + cannonball_shortfall;
          if (adjusted.cannonball <= 0) delete adjusted.cannonball;
          return this.canPlayerAfford(adjusted, includeBooty, false);
        }
      }

      var affordable = true;
      for (const [resource_key, num] of Object.entries(resource_cost)) {
        var player_has = playerResources[resource_key] || 0;
        if (player_has - num < 0) {
          affordable = false;
          break;
        }
      }
      if (affordable) return true;
      if (!includeBooty) return false;
      // Check if booty token could cover the gap
      var tokenRes = this.getMyBootyTokenRes(resource_cost);
      if (tokenRes && this.bootyOverlapsCost(tokenRes, resource_cost)) {
        var bootyResolved = this.resolveBootyResources(tokenRes, resource_cost);
        var reducedCost = this.computeEffectiveCost(resource_cost, bootyResolved);
        return this.canPlayerAfford(reducedCost, false);
      }
      return false;
    },

    /**
     * Client-side mirror of PHP resolveBootyResourcesForPayment.
     * Resolves fixed resources and auto-assigns "choice" to the cost resource the player is
     * shortest of (counting what they hold), else the one with the most left to pay.
     */
    resolveBootyResources: function (tokenRes, cost) {
      var held = this.getPlayerResources();
      var out = {};
      var choiceAmount = 0;
      for (var key in tokenRes) {
        if (key === "choice") {
          choiceAmount += tokenRes[key];
        } else {
          out[key] = (out[key] || 0) + tokenRes[key];
        }
      }
      for (var i = 0; i < choiceAmount; i++) {
        var bestRes = null;
        var bestShort = 0;
        var bestNeed = 0;
        for (var res in cost) {
          var remaining = cost[res] - (out[res] || 0);
          if (remaining <= 0) continue;
          var short = remaining - (held[res] || 0);
          if (!bestRes || short > bestShort || (short === bestShort && remaining > bestNeed)) {
            bestShort = short;
            bestNeed = remaining;
            bestRes = res;
          }
        }
        if (bestRes) {
          out[bestRes] = (out[bestRes] || 0) + 1;
        }
      }
      return out;
    },

    /**
     * Compute effective cost after applying booty token resources.
     * Returns a cost object with only positive remaining amounts.
     */
    computeEffectiveCost: function (fullCost, bootyResolved) {
      var effective = {};
      for (var res in fullCost) {
        var remaining = fullCost[res] - (bootyResolved[res] || 0);
        if (remaining > 0) {
          effective[res] = remaining;
        }
      }
      return effective;
    },

    /**
     * Get the current player's booty token resources, or null if none.
     */
    getMyBootyTokenRes: function (cost) {
      var token = this.pickBootyTokenForCost(cost);
      if (!token) return null;
      return (this.gamedatas.booty_token_resources || {})[token.type_arg] || null;
    },

    /**
     * With the Galleon's Treasure Hold a player can hold two tokens; spend the one that covers
     * the most of the cost (ties go to the cheaper token, so the better one stays in the hold).
     */
    pickBootyTokenForCost: function (cost) {
      var tokens = this.booty_tokens || [];
      if (tokens.length === 0) return null;
      if (tokens.length === 1 || !cost) return tokens[0];
      var self = this;
      var score = function (token) {
        var res = (self.gamedatas.booty_token_resources || {})[token.type_arg] || {};
        var resolved = self.resolveBootyResources(res, cost);
        var covered = 0;
        for (var key in cost) {
          covered += Math.min(resolved[key] || 0, cost[key]);
        }
        // Prefer more coverage, then the token with less total value left unused.
        var total = 0;
        for (var r in res) total += res[r];
        return covered * 100 - total;
      };
      return tokens.slice().sort(function (a, b) {
        return score(b) - score(a);
      })[0];
    },

    /** Drop one spent token from the local hold (the hold may carry two). */
    consumeBootyToken: function (tokenId) {
      this.booty_tokens = (this.booty_tokens || []).filter(function (t) {
        return String(t.id) !== String(tokenId);
      });
      this.lastBootyTokenTypeArg = this.booty_tokens.length === 1 ? this.booty_tokens[0].type_arg : undefined;
      this.updateMyBootyToken();
    },

    getMyBootyTokenId: function (cost) {
      var token = this.pickBootyTokenForCost(cost);
      return token ? token.id : null;
    },

    /**
     * Check if a booty token's resources overlap with a cost (i.e. could save any resources).
     */
    bootyOverlapsCost: function (tokenRes, cost) {
      if (!tokenRes || !cost) return false;
      var costKeys = Object.keys(cost).filter(function (k) {
        return cost[k] > 0;
      });
      if (costKeys.length === 0) return false;
      for (var res in tokenRes) {
        if (tokenRes[res] > 0 && (res === "choice" || costKeys.indexOf(res) !== -1)) {
          return true;
        }
      }
      return false;
    },

    /**
     * Build an HTML string describing how a booty token's resolved resources are being used.
     * e.g. "Using booty token as <sail icon> + <cannonball icon>"
     * Only includes resources that actually offset the cost (capped at what's needed).
     */
    formatBootyUsageMessage: function (bootyResolved, cost) {
      var parts = [];
      for (var res in bootyResolved) {
        var used = Math.min(bootyResolved[res] || 0, cost[res] || 0);
        for (var i = 0; i < used; i++) {
          parts.push("<span class='soh_resource soh_log_resource soh_" + res + "'></span>");
        }
      }
      if (parts.length === 0) return null;
      return _("Using booty token as ${resources}").replace("${resources}", parts.join(" + "));
    },
  };
});
