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
    skiffSvgHtml: function (player_color) {
      return `<svg width="100%" height="100%" viewBox="0 0 298 265" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">
      <g transform="matrix(1,0,0,1,-702.792,-710.191)">
          <g transform="matrix(4.16667,0,0,4.16667,703.292,801.562)">
              <g>
                  <path d="M0,19.624C5.469,20.959 28.423,22.913 29.458,22.081C24.755,18.445 20.071,14.823 15.468,11.265C16.694,9.088 17.977,6.986 19.089,4.798C21.739,-0.414 23.617,-5.906 24.651,-11.663C24.982,-13.508 25.143,-15.392 25.246,-17.266C25.313,-18.489 25.132,-19.725 25.067,-20.955C25.055,-21.182 25.065,-21.41 25.065,-21.809C25.635,-21.364 26.091,-21.022 26.531,-20.662C29.626,-18.128 32.719,-15.593 35.809,-13.054C38.539,-10.811 41.265,-8.563 43.992,-6.317C44.584,-5.829 45.149,-5.304 45.774,-4.864C46.15,-4.6 46.277,-4.263 46.31,-3.868C46.429,-2.491 46.521,-1.112 46.63,0.266C46.784,2.225 46.947,4.183 47.1,6.142C47.214,7.603 47.316,9.065 47.429,10.527C47.585,12.535 47.751,14.543 47.905,16.552C48.02,18.063 48.122,19.576 48.234,21.087C48.258,21.416 48.303,21.743 48.342,22.107C56.038,21.562 63.693,21.038 71.027,18.434L71.108,18.586C70.899,19.191 70.695,19.798 70.48,20.4C68.343,26.39 66.23,32.389 64.039,38.359C63.704,39.272 63.028,40.066 62.459,40.879C62.343,41.046 61.999,41.122 61.758,41.124C58.118,41.151 54.478,41.151 50.838,41.173C37.213,41.255 23.589,41.342 9.964,41.427C9.11,41.433 8.272,41.425 7.516,40.904C6.719,40.354 6.485,39.489 6.206,38.668C4.424,33.419 2.668,28.16 0.904,22.905C0.63,22.087 0.346,21.272 0.091,20.448C0.018,20.211 0.033,19.947 0,19.624" 
                  style="fill:#${player_color};fill-rule:nonzero;stroke:#${player_color};stroke-width:0.24px;"/>
              </g>
          </g>
      </g>
      </svg>`;
    },

    skiffHtml: function (id, player_color) {
      return `<div id="${id}" class="soh_skiff">${this.skiffSvgHtml(player_color)}</div>`;
    },

    // Cannon shot and explosion art: Lorc, https://game-icons.net (CC BY 3.0). They paint with
    // currentColor, so the colour of each effect lives in the stylesheet - see .cannonfire etc.
    cannonFireHtml: function (id) {
      return `<div id="${id}" class="soh_cannonfire">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="height: 36px; width: 36px;">
          <defs>
              <!-- White-hot at the muzzle ring, where the shot leaves the barrel, cooling through
                   yellow and orange to a dark red at the tip of the blast and the barrel end. -->
              <radialGradient id="${id}_heat" gradientUnits="userSpaceOnUse" cx="259" cy="300" r="260">
                  <stop offset="0" stop-color="#ffffff"/>
                  <stop offset="0.2" stop-color="#fff6b0"/>
                  <stop offset="0.45" stop-color="#ffc233"/>
                  <stop offset="0.75" stop-color="#ff6a00"/>
                  <stop offset="1" stop-color="#b3200a"/>
              </radialGradient>
              <clipPath id="${id}_shape">
                  <path d="M168.875 11.395l86.455 98.443c2.175-1.122 4.337-2.206 6.47-3.215 14.37-6.805 27.684-11.083 39.76-12.103.75-.064 1.498-.113 2.243-.15L242.69 11.396h-73.815zM18.348 64.145v27.722l.21-.25 128.432 107.3c3.262-3.764 6.643-7.526 10.127-11.276L18.347 64.147zm287.896 48.835c-.982.017-2.017.07-3.11.163-8.734.738-20.327 4.21-33.337 10.37-.564.268-1.146.567-1.715.844l30.996 35.295c1.406-.72 2.808-1.43 4.193-2.1 13.245-6.395 25.504-10.477 36.683-11.554.592-.057 1.183-.1 1.774-.14l-21.385-29.032-.344.344c-2.62-2.62-6.88-4.304-13.754-4.19zm-287.896 2.817V264.15l69.13 47.274c.036-.995.088-1.993.172-2.996 1.02-12.077 5.298-25.392 12.104-39.762 8.213-17.34 20.215-36.21 35.324-55.348l-116.73-97.52zm326.18 48.625c-.875.025-1.802.083-2.784.178-7.853.756-18.432 4.027-30.346 9.78-23.826 11.508-53.028 32.712-80.87 60.554-27.843 27.84-49.048 57.044-60.555 80.87-5.754 11.914-9.025 22.494-9.782 30.346-.755 7.853.795 12.184 3.197 14.586 2.402 2.402 6.735 3.952 14.588 3.196 7.852-.757 18.432-4.028 30.345-9.782 7.81-3.77 16.202-8.6 24.928-14.347-17.195 39.23-28.067 89.333-34.394 153.564 37.517-129.093 80.838-109.43 114.544-6.287-18.62-109.564 99.38-61.623 185.008 5.397-66.417-101.782-124.625-177.518 4.55-188.135-124.058-5.07-140.995-44.53-21.876-102.653-58.372 6.19-105.555 15.9-143.54 32.65 4.806-7.536 8.915-14.8 12.206-21.613 5.754-11.914 9.023-22.494 9.78-30.346.756-7.852-.794-12.183-3.196-14.585-1.8-1.8-4.688-3.122-9.332-3.352-.774-.038-1.596-.046-2.47-.02zm-173.442 35.65c-3.353 3.61-6.6 7.226-9.734 10.842l37.066 30.97c2.84-3.234 5.753-6.464 8.768-9.687l-36.1-32.125zm142.27 1.117c3.84.122 6.953 1.23 9.142 3.42 6.837 6.836 3.118 22.676-8.182 41.52-29.24 17.088-52.02 39.92-69.58 70.706-20.12 12.694-37.26 17.173-44.45 9.984-11.437-11.437 6.648-48.066 40.396-81.814 26.365-26.366 54.49-43.17 70.986-43.81.577-.02 1.14-.024 1.69-.007zm-163.9 24.138c-14.242 18.11-25.428 35.748-32.81 51.338-6.163 13.01-9.634 24.602-10.373 33.336-.738 8.734 1.033 13.87 4.026 16.86l-.1.1 31.152 21.304c.034-1.296.112-2.6.238-3.91 1.076-11.177 5.158-23.437 11.555-36.68 7.777-16.104 19.084-33.65 33.275-51.465l-36.965-30.882z" transform="translate(76.8, 76.8) scale(0.7, 0.7) rotate(45, 256, 256) skewX(0) skewY(0)"></path>
              </clipPath>
          </defs>
          <rect width="512" height="512" fill="url(#${id}_heat)" clip-path="url(#${id}_shape)"></rect>
      </svg></div>`;
    },

    explosionHtml: function (id) {
      return `<div id="${id}" class="soh_explosion">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="height: 64px; width: 64px;">
          <g class="" transform="translate(0,0)">
              <path d="M287.586 15.297l3.504 110.963 31.537-110.963h-35.04zm-95.78.238l-1.75 236.047-170.533-43.33L130.486 377.69l-88.77-5.174 114.432 112.357-44.466-75.867L186.896 417l-51.748-109.94 110.114 79.956-12.635-185.23.002.003 75.212 170.57 75.816-89.95-6.62 154.582 60.173-39.978-20.388 79.486 75.756-142.787-75.924 1.94L487.32 155.87l-131.402 73.08-12.264-139.69-65.41 140.336-86.435-214.06h-.003zM45.503 44.095L39.355 75.94 154.285 218h.002l-77.6-166.836-31.185-7.07zm422.27 24.776l-31.184 7.07-43.738 107.37 81.068-82.59-6.147-31.85zM279.208 403.61c-40.176 0-72.708 32.537-72.708 72.71 0 5.725.636 10.706 1.887 16.05 7.25-32.545 36.097-56.655 70.82-56.655 34.82 0 63.673 23.97 70.82 56.656 1.218-5.277 1.888-10.404 1.888-16.05 0-40.175-32.536-72.71-72.71-72.71z" fill="currentColor"></path>
          </g>
      </svg>
      </div>`;
    },

    uniqueTokenHtml: function (token_key) {
      return `<div id="token_${token_key}" class="soh_flagish" data-tokenkey="${token_key}"></div>`;
    },

    seafeatureHtml: function (id, seafeature_type) {
      return `<div class="soh_seafeature" id="${id}" data-seafeature="${seafeature_type}"></div>`;
    },

    purchaseButtonHtml: function ({ id, slotnumber, cardid }) {
      return `<a id="${id}" class="bgabutton bgabutton_blue soh_purchase_card_button" data-slotnumber="${slotnumber}" data-cardid="${cardid}">${_("Purchase Card")}</a>`;
    },

    updateLastSeaPhaseBanner: function (visible) {
      if (visible) {
        this.bga.gameArea.addLastTurnBanner(_("Last Sea Phase: the game ends after this phase."));
      } else {
        this.bga.gameArea.removeLastTurnBanner();
      }
    },

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
          this.statusBar.addActionButton(this.resourceIcon(resource) + suffix, () => onPick(resource)),
        );
    },

    /** Extra Rations: pay 1 resource of the player's choice to draw a card. One button per resource they can afford. */
    addExtraRationsButtons: function (resources, label) {
      for (const resource of resources) {
        this.statusBar.addActionButton(
          label + " " + this.resourceIcon(resource) + " " + _("to draw a card"),
          () => this.payWithOptionalBooty({ [resource]: 1 }, "actExtraRations", { resource }),
          {},
        );
      }
    },

    /**
     * A follow-up step in the action bar, to keep it to four buttons (BGA guideline A.2): replaces
     * the buttons, then addButtons() adds this step's and a Back button goes to `back`, by default
     * the state's own first step.
     */
    showBarStep: function (title, addButtons, back = () => this.restoreServerGameState()) {
      this.statusBar.removeActionButtons();
      this.statusBar.setTitle(title);
      addButtons();
      this.statusBar.addActionButton(_("Back"), back, { color: "alert" });
    },

    /**
     * Highlight `element` (a ship on the board, a flag on my player board) and make it a choice:
     * clicked or Enter/Space. Undone by clearChoosables().
     */
    makeChoosable: function (element, label, onPick, tooltipHtml) {
      if (!element) throw new Error("No element to choose for " + label);
      if (tooltipHtml) {
        this.addTooltipHtml(element.id, tooltipHtml);
        element._sohTooltip = true;
      }
      element.classList.add("soh_choosable");
      element.setAttribute("role", "button");
      element.setAttribute("aria-label", label);
      element.tabIndex = 0;
      element._sohChoose = (event) => {
        if (event.type === "keydown" && event.key !== "Enter" && event.key !== " ") return;
        event.preventDefault();
        event.stopPropagation();
        onPick();
      };
      element.addEventListener("click", element._sohChoose);
      element.addEventListener("keydown", element._sohChoose);
    },

    /**
     * Seen once (Timely Trading, 2026-11-03): every market slot showing an old card next to its new
     * one, until a reload. Not reproduced, so this says when and with which cards it happens.
     */
    assertNoStrayMarketCards: function (when) {
      const known = new Set(this.market.getCards().map((card) => this.cardsManager.getCardElementId(card)));
      const stray = [...this.market.element.querySelectorAll(".bga-cards_card")].filter((el) => !known.has(el.id));
      if (stray.length) {
        throw new Error(`Stray market cards ${when}: ${stray.map((el) => el.id).join(", ")}; market has ${[...known].join(", ")}`);
      }
    },

    clearChoosables: function () {
      for (const element of document.querySelectorAll(".soh_choosable")) {
        element.classList.remove("soh_choosable");
        element.removeAttribute("role");
        element.removeAttribute("aria-label");
        element.removeAttribute("tabindex");
        element.removeEventListener("click", element._sohChoose);
        element.removeEventListener("keydown", element._sohChoose);
        delete element._sohChoose;
        if (element._sohTooltip) {
          this.removeTooltip(element.id);
          delete element._sohTooltip;
        }
      }
      document.querySelectorAll(".soh_choosing").forEach((element) => element.classList.remove("soh_choosing"));
    },

    /** The ship elements of a player on the board (one, or two in the 2 Ship Variant). */
    playerShipElements: function (playerId) {
      return [playerId, playerId + "_2"].map((arg) => document.getElementById("player_ship_" + arg)).filter(Boolean);
    },

    /** The × in a popup's top right corner (.soh_close_x; its parent must be positioned). */
    closeButton: function (onClose) {
      const close = document.createElement("button");
      close.type = "button";
      close.className = "soh_close_x";
      close.textContent = "\u00d7";
      close.setAttribute("aria-label", _("Close"));
      close.addEventListener("click", onClose);
      return close;
    },

    /**
     * [sail], [cannonball], [doubloon], [infamy] and [skiff] in a text become their icons, as does
     * [any] for the cards' any-resource symbol.
     */
    iconifyMarkers: function (text) {
      return text.replace(/\[(sail|cannonball|doubloon|infamy|skiff|any)\]/g, (match, marker) => {
        if (marker === "skiff") {
          return `<span class="soh_resource soh_log_resource soh_log_skiff" role="img" aria-label="${_("skiff")}">` +
            this.skiffSvgHtml("555555") + "</span>";
        }
        if (marker === "any") {
          const icons = ["sail", "cannonball", "doubloon"].map((resource) => this.resourceIcon(resource)).join("/");
          return `<span class="soh_any_resource" role="img" aria-label="${_("any resource")}">${icons}</span>`;
        }
        return this.resourceIcon(marker);
      });
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

    /**
     * A shipwreck on a whirlpool or gust would hide it: shrink both and split them into opposite
     * corners of the space. Rerun whenever a shipwreck is placed or moved.
     */
    refreshShipwreckPairs: function () {
      const wrecks = this.seaboard.filter((e) => e.type === "shipwreck");
      for (const entry of this.seaboard) {
        const isWreck = entry.type === "shipwreck";
        if (!isWreck && entry.type !== "whirlpool" && entry.type !== "gust") continue;
        const paired = isWreck
          ? this.seaboard.some((f) => (f.type === "whirlpool" || f.type === "gust") && f.x == entry.x && f.y == entry.y)
          : wrecks.some((w) => w.x == entry.x && w.y == entry.y);
        $(entry.type + "_" + entry.arg).classList.toggle(
          isWreck ? "soh_seafeature_paired_shipwreck" : "soh_seafeature_hidden_partner",
          paired,
        );
      }
    },

    /** A ship's element on the sea grid; one still without a heading is drawn unturned. */
    addShipToBoard: function (entry) {
      // A second ship (2 Ship Variant) is "<player id>_2" on the board.
      const [owner, second] = String(entry.arg).split("_");
      const ownerInfo = this.gamedatas.playerinfo[owner];
      const shipid = "player_ship_" + entry.arg;
      domConstruct.place(
        `<div class="soh_player_ship" id="${shipid}" data-shipname="${second ? ownerInfo.player_ship2 : ownerInfo.player_ship}"></div>`,
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
      const shipElement = document.getElementById("player_ship_" + ship.arg);
      shipElement.style.rotate = ""; // Cancel comes back here from a previewed heading.
      if (!this.isCurrentPlayerActive()) return;
      // Heading -> step to the neighbouring cell; the grid has a cell of margin all round.
      const steps = { 1: [0, -1], 2: [1, 0], 3: [0, 1], 4: [-1, 0] };
      for (const [heading, [dx, dy]] of Object.entries(steps)) {
        const arrow = domConstruct.create("div", { class: "soh_heading_arrow" }, "seaboard");
        this.placeOnObject(arrow, "seaboardlocation_" + (ship.x + dx) + "_" + (ship.y + dy));
        // The glyph points east (heading 2).
        arrow.style.rotate = (heading - 2) * 90 + "deg";
        arrow.addEventListener("click", () => {
          shipElement.style.rotate = this.getHeadingDegrees(Number(heading)) + "deg";
          this.confirmIslandAction(_("Confirm direction"),
            () => this.bgaPerformAction("actChooseHeading", { heading: Number(heading) }), arrow);
        });
      }
    },

    cleanupChooseHeading: function () {
      query(".soh_heading_arrow").forEach(domConstruct.destroy);
    },

    /** The cards a draft choice brings: a captain's card and deck cards, or a ship's upgrades and starting deck. */
    draftChoiceCards: function (stateName, choice) {
      const cards = Object.values(this.playable_cards);
      if (stateName === "draftCaptain") {
        return [{ image_id: this.non_playable_cards[choice].image_id, nonPlayable: true, card_key: choice }].concat(
          cards.filter(c => c.category === "captain" && c.captain_key === choice));
      }
      return Object.entries(this.non_playable_cards)
        .filter(([, c]) => c.category === "ship_upgrade" && c.ship_name === choice)
        .map(([key, c]) => ({ image_id: c.image_id, nonPlayable: true, card_key: key }))
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
          if (card.nonPlayable) art.dataset.cardKey = card.card_key;
          else art.dataset.cardType = card.card_type;
          this.setupCardPreview(art);
          if (card.count > 1) {
            domConstruct.create("span", { class: "soh_card_count", innerHTML: "&times;" + card.count }, thumb);
          }
        }
        // Clicking anywhere on an option picks it (hover still zooms its cards).
        if (this.isCurrentPlayerActive()) {
          this.makeChoosable(option, this.draftChoiceName(stateName, choice),
            () => this.pickDraftChoice(stateName, choice, option));
        }
      }
    },

    /** A draft pick waits in the action bar for Confirm; picking another option switches to it. */
    pickDraftChoice: function (stateName, choice, option) {
      document.querySelector(".soh_draft_pending")?.classList.remove("soh_draft_pending");
      option.classList.add("soh_draft_pending");
      this.statusBar.removeActionButtons();
      this.statusBar.setTitle(_("${you} chose ${choice}"), { choice: this.draftChoiceName(stateName, choice) });
      this.statusBar.addActionButton(_("Confirm"), () => this.bgaPerformAction(
        stateName === "draftCaptain" ? "actDraftCaptain" : "actDraftShip",
        stateName === "draftCaptain" ? { captain: choice } : { ship: choice },
      ));
    },

    cleanupDraft: function () {
      domConstruct.destroy("soh_draft");
      this.hideCardHoverZoom();
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
          // A flag nobody has claimed yet sits on its banner on the board.
          domConstruct.place(this.uniqueTokenHtml(token_key), `${token_key}_board`);
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
        token_element = domConstruct.place(this.uniqueTokenHtml(token_key), slot_id);
      } else {
        // attachToNewParent replaces the node (keeping its on-screen spot via left/top): reset the new one.
        token_element = this.attachToNewParent(token_element, slot_id);
      }
      domStyle.set(token_element, { left: "0px", top: "0px" });
      return token_element;
    },

    /**
     * Show `element` sliding into the place it already has, from `from`. A copy does the flying, from
     * the top of the page: the board's `isolation` would keep the element itself under the player
     * panels on the way.
     */
    slideAboveAll: function (element, from) {
      const copy = element.cloneNode(true);
      [copy, ...copy.querySelectorAll("[id]")].forEach((el) => el.removeAttribute("id"));
      copy.id = "soh_flying_" + this._nextEffectId();
      copy.classList.add("soh_flying");
      domConstruct.place(copy, "overall-content");
      this.placeOnObject(copy, from);
      element.style.visibility = "hidden";
      const anim = this.slideToObject(copy, element, 800);
      anim.onEnd = () => {
        domConstruct.destroy(copy);
        element.style.visibility = "";
      };
      anim.play();
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
          green_flag: _("Take the Green Purser's flag and gain 1 resource of your choice"),
          tan_flag: _("Take the Tan Bosun's flag and draw 1 card"),
          red_flag: _("Take the Red Shipwright's flag and scrap 1 card from your hand or discard pile, gaining its cost"),
          blue_flag: _("Take the Blue Sailor's flag and place another skiff"),
        },
        // Player panel token slots, keyed by data-tokenkey.
        panel_token: {
          green_flag: _("Green Purser's flag: when you play a card showing this flag, you may gain 1 resource of your choice"),
          tan_flag: _("Tan Bosun's flag: when you play a card showing this flag, you may draw 1 card"),
          red_flag: _("Red Shipwright's flag: when you play a card showing this flag, you may scrap a card from your hand or discard pile, including that card"),
          blue_flag: _("Blue Sailor's flag: when you play a card showing this flag, you may play another card immediately"),
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
        card_text: {
          pirate_queen: _("At the start of the Island Phase, gain 2 [infamy] if you control more flags than any other player."),
          rebel: _("At the start of the Island Phase, draw 1 additional card then discard 1 card."),
          admiral: _("Whenever you take the first player token from another player, draw a card. Whenever you take a flag from another player, gain 1 [infamy]."),
          merchant: _("You may spend [doubloon] as [any] when purchasing cards from the Market."),
          corsair: _("Once per Island Phase, you may place one of your [skiff] Skiffs on an occupied space. You only gain the resources shown there."),
          treasure_seeker: _("Whenever a Shipwreck token is placed, you may adjust its location to a surrounding space."),
          war_junk_rockets: _("When firing 1 cannon, you may instead fire a rocket. Range 3. After resolving a hit, rockets explode into all surrounding spaces. Score 1 [infamy] and deal 1 damage per piece hit by this explosion. Rockets also explode after hitting rocks."),
          war_junk_bulwark: _("Immediately scrap any damage card you receive if you already have a damage card on top of your discard pile."),
          xebec_lateen_rigging: _("You may make extended maneuvers without paying the [sail] cost."),
          xebec_swift_hull: _("After resolving a sailing card (including its flag action), you may pay 1 [sail] to play another card immediately."),
          sloop_of_war_chain_shot: _("When firing 1 cannon, you may instead fire chain shot. Range 2. After resolving a hit, opponents hit by chain shot also lose a resource of their choice."),
          sloop_of_war_nimble_hull: _("Once per Sea Phase, you may resolve the maneuver on a sailing card twice. The card's flag action is only resolved once, after the maneuvers have been completed."),
          brig_extra_rations: _("Once per Island Phase, you may pay 1 [any] to draw 1 card."),
          brig_carronade: _("When firing 1 cannon, you may instead fire a carronade at no [cannonball] cost. Range 1."),
          ship_of_the_line_double_gun_crews: _("When firing any number of cannon, you may choose to fire them from both sides of your ship, but at -1 Range each. This can be combined with Heavy Guns."),
          ship_of_the_line_heavy_guns: _("When firing 1 cannon, you may instead pay 2 [cannonball] to fire heavy guns. Range 5. Heavy gun shots travel through ships, hitting any within range and earn +1 [infamy] when resolving each hit. Rocks still stop heavy gun shots."),
          galleon_bow_and_stern_chasers: _("When firing 1 cannon, you may instead fire a bow or stern chaser. Range 3. Chasers fire forwards or backwards instead of from the sides of your ship."),
          galleon_treasure_hold: _("You may have up to 2 booty tokens in your hold."),
          rally_the_flags: _("Take a flag from the Island Board or a surrounding ship. Do not use its flag action. If you control more flags than any other player, gain 1 [infamy]."),
          extortion: _("Use the action of each flag you control in any order."),
          barter: _("You may make any one of the following exchanges in either direction once: 1 [sail] : 1 [infamy]; 1 [cannonball] : 2 [infamy]; 1 [doubloon] : 3 [infamy]."),
          timely_trading: _("Choose One: Purchase a card from the Market paying its cost as usual. Add the card to your hand. Gain 2 [any]."),
          boarding_party: _("Steal a booty token or a resource of your choice from a surrounding ship. If you do, gain 1 [infamy]."),
          hunt_the_bounty: _("Declare a target ship. For the remainder of this Sea Phase, gain 1 [infamy] for each damage card you deal to it. You may play another card immediately."),
          retaliation: _("Scrap a damage card from your hand or discard pile. If you do, you may fire 1 cannon, Range 3 (without spending [cannonball])."),
          improvisation: _("Play this card as a copy of any SAILING, PIVOT, or FIRING card in your discard pile. If there are no such cards in your discard pile, draw 1 card instead."),
          government_funding: _("Gain a resource of each type that you don't currently own. If you cannot do this, draw a card instead."),
          inspire: _("If there are no damage cards in your discard pile, gain 1 [infamy] and draw 1 card. Otherwise, scrap a damage card from your discard pile."),
          spyglass: _("Look at the top 3 cards of your deck and keep 1. Return the others on top of your deck in any order."),
          unearth_riches: _("If there is a rock in a surrounding space, reveal a shipwreck token from the supply and gain its rewards immediately."),
          damage: _("Repair: When you would play this card, scrap it instead."),
        },
        ability_name: {
          rally_the_flags: _("Rally the Flags"),
          extortion: _("Extortion"),
          barter: _("Barter"),
          timely_trading: _("Timely Trading"),
          boarding_party: _("Boarding Party"),
          hunt_the_bounty: _("Hunt the Bounty"),
          retaliation: _("Retaliation"),
          improvisation: _("Improvisation"),
          government_funding: _("Government Funding"),
          inspire: _("Inspire"),
          spyglass: _("Spyglass"),
          unearth_riches: _("Unearth Riches"),
        },
        card_type: { sailing: _("Sailing"), firing: _("Firing"), pivot: _("Pivot") },
        booty_token: _("Booty token: spend it whenever you pay resources, but it is spent whole - any resources left on it are lost"),
        row_button: _("Instead of playing a card you can Row by discarding two cards. This gives you a choice of sailing forward, left, right or <i>backwards</i>."),
      };
    },

    /** The tooltip text for one key of one kind; throws if there is none, so a gap shows up. */
    tooltipText: function (kind, key) {
      const texts = this.tooltipTexts()[kind];
      const text = key === undefined ? texts : texts && texts[key];
      if (typeof text !== "string") {
        throw new Error(`No tooltip text for ${kind} ${key}`);
      }
      return this.iconifyMarkers(text);
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
      if (this.isSpectator) {
        return; // spectators have no hold
      }
      const tokens = this.booty_tokens || [];
      const mySlot = dom.byId(`booty_token_p${this.player_id}`);
      domConstruct.empty(mySlot);
      // The Galleon's Treasure Hold can carry two tokens, so show them all.
      tokens.forEach((token) => {
        const node = this.createBootyTokenNode(false, token.type_arg);
        this.setBootyTokenImageForSlot(node, token.type_arg);
        node.dataset.imageId = token.type_arg; // for the board copy's hover zoom
        domConstruct.place(node, mySlot);
        this.addBootyTokenTooltip(node, token.type_arg);
      });
      domClass.toggle(mySlot, "soh_has-token", tokens.length > 0);
    },

    /**
     * A copy of my resources and booty on my board under the hand, so they're in view while
     * choosing cards. Mirrored from the panel on every change, so the panel stays the one source.
     */
    setupMyResourcesPanel: function () {
      if (this.isSpectator) {
        dom.byId("myresources_wrap").remove();
        return;
      }
      const sources = [
        dom.byId(`player_resource_board_p${this.player_id}`),
        dom.byId(`booty_token_p${this.player_id}`),
      ];
      const content = dom.byId("soh_my_resources");
      // Copies keep their ids, prefixed, so they can carry the same tooltips as the panel.
      const copyOf = (source) => {
        const copy = source.cloneNode(true);
        [copy, ...copy.querySelectorAll("[id]")].forEach((el) => (el.id = "soh_mr_" + el.id));
        return copy;
      };
      const [board, slot] = sources;
      content.append(copyOf(board), copyOf(slot));
      for (const resource of ["sail", "cannonball", "doubloon", "skiff"]) {
        const text = this.tooltipText("resource", resource);
        this.addTooltip(`soh_mr_${resource}_p${this.player_id}`, text, "");
        this.addTooltip(`soh_mr_${resource}count_p${this.player_id}`, text, "");
      }
      new MutationObserver(() => {
        for (const count of board.querySelectorAll(".soh_resource_count")) {
          dom.byId("soh_mr_" + count.id).textContent = count.textContent;
        }
      }).observe(board, { childList: true, subtree: true, characterData: true });
      // The hold is redrawn whole (updateMyBootyToken), with fresh token ids, so copy it whole.
      const mirrorSlot = () => {
        const copy = copyOf(slot);
        dom.byId("soh_mr_" + slot.id).replaceWith(copy);
        copy.querySelectorAll(".soh_booty-token").forEach((token) => this.addBootyTokenTooltip(token, token.dataset.imageId));
      };
      new MutationObserver(mirrorSlot).observe(slot, { childList: true, attributes: true });
      mirrorSlot();
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
      domConstruct.place(this.explosionHtml(id), targetId);
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
      domConstruct.place(this.cannonFireHtml(id), board);
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
      const overlayRoot = dom.byId("overall-content");
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

    removeShipwreck: function (arg) {
      domConstruct.destroy(`shipwreck_${arg}`);
      this.seaboard = this.seaboard.filter((entry) => !(entry.type === "shipwreck" && entry.arg == arg));
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
        domConstruct.place(this.seafeatureHtml(shipwreckId, "shipwreck"), "seaboard");
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
      this.refreshShipwreckPairs();
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

    /** Panel hand counters. Players with an empty hand are absent from counts. */
    updateHandCounts: function (counts) {
      for (const el of document.querySelectorAll("[id^=handcount_p]")) {
        el.innerText = counts[el.id.slice("handcount_p".length)] ?? 0;
      }
    },

    /** Other players' decks and discards on their boards, from the server's per-location counts. */
    updateOpponentPiles: function (counts, discardTops) {
      for (const [player_id, piles] of Object.entries(this.opponentPiles)) {
        piles.deck.setCardNumber(counts[`player_deck_${player_id}`] ?? 0);
        const top = discardTops[player_id];
        // A display id, so the copy doesn't pull the real card out of another stock.
        piles.discard.setCardNumber(
          counts[`player_discard_${player_id}`] ?? 0,
          top && { id: 30000 + Number(top.id), type: top.type },
        );
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

      // Merchant ability: doubloons can cover cannonball and sail costs for market purchases
      if (includeMerchant && this.player_captain === "merchant") {
        var adjusted = Object.assign({}, resource_cost);
        for (const r of ["cannonball", "sail"]) {
          var shortfall = Math.max(0, (adjusted[r] || 0) - (playerResources[r] || 0));
          if (shortfall > 0) {
            adjusted[r] -= shortfall;
            adjusted.doubloon = (adjusted.doubloon || 0) + shortfall;
          }
        }
        return this.canPlayerAfford(adjusted, includeBooty, false);
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
