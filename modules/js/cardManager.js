/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/**
 * Seas of Havoc - Card Manager Module
 * Card setup helpers and card-related UI management
 */

define(["dojo/dom-style", getLibUrl("bga-cards", "1.x"), g_gamethemeurl + "modules/js/constants.js"], function (domStyle, BgaCards, Constants) {
  // Debug logging only in Studio (see constants.js).
  const console = Constants.console;

  return {
    setupCardPreview: function (face) {
      if (face.dataset.previewReady) return;
      face.dataset.previewReady = 'true';
      face.tabIndex = 0;
      face.setAttribute('role', 'button');
      face.setAttribute('aria-label', _('View card'));
      face.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          face.click();
        }
      });
      // Faces that show a card front: bga-cards fronts, panel/log/draft thumbnails, activated upgrades.
      const showsFront = () =>
        !(face.classList.contains('soh_non-playable-card-back') && !face.classList.contains('upgrade-activated-face')) &&
        !!(face.closest('.soh_seasofhavoc-card[data-side="front"]') || face.classList.contains('soh_panel_card_art') ||
           face.classList.contains('upgrade-activated-face'));
      // Mouse users get the tooltip (enlarged copy and text) on hover; touch users on a tap.
      const hoverZoom = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
      if (hoverZoom) {
        face.addEventListener('mouseenter', () => {
          if (showsFront()) this.showCardHoverZoom(face);
        });
        face.addEventListener('mouseleave', () => this.hideCardHoverZoom());
      }
      face.addEventListener('click', event => {
        this.hideCardHoverZoom();
        // Scrap and discard picks open no dialog with the card, so touch users get its text as they select.
        const touchPick = !hoverZoom && face.closest('.soh_card_pick .bga-cards_selectable-stock');
        // Selectable stocks own clicks for playing, scrapping, and discarding.
        if ((face.closest('.bga-cards_selectable-stock') && !touchPick) || !showsFront()) return;
        // A draft card's click also chooses its option (when it is a choice).
        const inDraft = face.closest('#soh_draft');
        if (!inDraft && !touchPick) event.stopPropagation();
        // Touch users get the tooltip until their next tap; tapping the same card just closes it.
        if (!hoverZoom) {
          if (this._tapZoomFace === face) {
            this._tapZoomFace = null;
            return;
          }
          this.showCardHoverZoom(face);
          this._tapZoomFace = face;
          setTimeout(() => document.addEventListener('pointerdown', (event) => {
            this.hideCardHoverZoom();
            if (!face.contains(event.target)) this._tapZoomFace = null;
          }, { once: true }));
          return;
        }
        if (inDraft) return;
        // The keyboard's way to the card (Enter on a focused one).
        const previousFocus = document.activeElement;
        const dialog = document.createElement('dialog');
        dialog.className = 'soh_card-zoom-dialog';
        dialog.setAttribute('aria-label', _('Card preview'));
        const close = this.closeButton(() => dialog.close());
        const scale = Math.min(3, (window.innerWidth - 64) / 144, (window.innerHeight - 88) / 198);
        dialog.append(this.cardArtCopy(face, scale), this.cardTextPanel(face), close);
        dialog.addEventListener('click', event => {
          if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => {
          dialog.remove();
          previousFocus.focus();
        });
        document.body.append(dialog);
        dialog.showModal();
      });
    },

    /** The face's card art, scaled up, in a frame sized to match. */
    cardArtCopy: function (face, scale) {
      const frame = document.createElement('div');
      const art = document.createElement('div');
      const style = getComputedStyle(face);
      frame.style.width = `${144 * scale}px`;
      frame.style.height = `${198 * scale}px`;
      Object.assign(art.style, {
        width: '144px', height: '198px',
        backgroundImage: style.backgroundImage,
        backgroundPosition: style.backgroundPosition,
        backgroundSize: style.backgroundSize,
        transform: `scale(${scale})`, transformOrigin: 'top left',
      });
      frame.append(art);
      return frame;
    },

    /** The card's wording as text, so it can be translated: see cardTextHtml. */
    cardTextPanel: function (face) {
      const { cardKey, cardType } = face.dataset;
      if (cardKey === undefined && cardType === undefined) throw new Error("Card face without a card");
      const panel = document.createElement('div');
      panel.className = 'soh_card_text';
      panel.innerHTML = cardKey !== undefined
        ? this.nonPlayableCardTextHtml(this.non_playable_cards[cardKey], cardKey)
        : this.playableCardTextHtml(this.playable_cards[cardType]);
      return panel;
    },

    /** Heading, what kind of card it is, then its text lines. Cost and infamy are icons on the card. */
    cardTextHtml: function (title, kind, lines) {
      return (title ? `<h3>${title}</h3>` : '') + `<div class="soh_card_kind">${kind}</div>` +
        lines.map((line) => `<p>${line}</p>`).join('');
    },

    costIcons: function (cost) {
      return Object.entries(cost).map(([resource, n]) => `${n}${this.resourceIcon(resource)}`).join(' ');
    },

    /** A captain or a ship upgrade. */
    nonPlayableCardTextHtml: function (card, cardKey) {
      const kind = card.category === 'captain' ? _('Captain') : _('${ship} Upgrade').replace('${ship}', _(card.ship_name));
      return this.cardTextHtml(_(card.name), kind, [this.tooltipText('card_text', cardKey)]);
    },

    playableCardTextHtml: function (card) {
      if (card.category === 'rowing') return this.cardTextHtml(_('Rowing card'), _('Sailing'), [this.tooltipText('row_button')]);
      if (card.category === 'damage') return this.cardTextHtml('', _('Damage card'), [this.tooltipText('card_text', 'damage')]);
      if (card.category === 'captain') {
        const ability = card.printed_actions[0].ability;
        const captain = _(this.non_playable_cards[card.captain_key].name);
        return this.cardTextHtml(this.tooltipText('ability_name', ability), _('${captain} ability').replace('${captain}', captain),
          [this.tooltipText('card_text', ability)]);
      }
      const { types, lines } = this.printedCardLines(card.printed_actions);
      const kind = (card.type ?? types).map((type) => this.tooltipText('card_type', type)).join(' / ');
      if (card.flag) {
        const flag = `${card.flag}_flag`;
        lines.push(`<span class="soh_tooltip_flag"><span class="soh_flagish" data-tokenkey="${flag}"></span>` +
          `<span>${this.tooltipText('panel_token', flag)}</span></span>`);
      }
      if (card.category === 'starting_card') lines.push(_('${ship} Starting Deck').replace('${ship}', _(card.ship_name)));
      return this.cardTextHtml('', kind, lines);
    },

    /**
     * The text a sailing, firing or pivot card prints, from what its actions do. Goes by the printed
     * actions: upgrades rewrite the live ones (Lateen Rigging drops the extended maneuver's cost).
     */
    printedCardLines: function (actions) {
      const flat = [];
      const walk = (nodes) => nodes.forEach((node) => {
        flat.push(node);
        walk(node.choices ?? []);
        walk(node.actions ?? []);
      });
      walk(actions);
      const cannons = { fire: 1, '2 x fire': 2, '3 x fire': 3 };
      const fires = flat.filter((node) => cannons[node.action]);
      const lines = [];
      for (const action of new Set(fires.map((node) => node.action))) {
        const same = fires.filter((node) => node.action === action);
        const cost = this.costIcons(same[0].cost) + (same.length > 1 ? ` ${_('each')}` : '');
        lines.push(`${cost}: ` + _('Fire ${n} cannon. Range ${range}')
          .replace('${n}', cannons[action]).replace('${range}', same[0].range));
      }
      const extended = flat.find((node) => node.cost && !cannons[node.action]);
      if (extended) {
        lines.push(`${this.costIcons(extended.cost)}: ` + (extended.action === 'forward'
          ? _('Perform the extended maneuver as well.') : _('Perform the extended maneuver instead.')));
      }
      const around = flat.find((node) => node.action === 'pivot 180');
      if (around) lines.push(`${this.costIcons(around.option_cost)}: ${_('Pivot 180°')}`);
      const moves = ['forward', 'backward', 'left', 'right'];
      const types = [];
      if (flat.some((node) => moves.includes(node.action))) types.push('sailing');
      if (fires.length) types.push('firing');
      if (flat.some((node) => node.action.startsWith('pivot'))) types.push('pivot');
      return { types, lines };
    },

    /**
     * The card's tooltip: enlarged, with its text, beside the hovered face (below the card where the
     * screen is too narrow), after a short delay so sweeping across the hand doesn't flash every
     * card. Fixed positioning keeps it clear of panels that clip. The hovered card is outlined
     * meanwhile, to show which one the zoom is of.
     */
    showCardHoverZoom: function (face) {
      this.hideCardHoverZoom();
      this._cardHoverTimer = setTimeout(() => {
        if (!face.isConnected) return;
        const stacked = window.innerWidth < 2 * 144 + 240 + 16;
        const scale = stacked
          ? Math.min(2, (window.innerWidth - 16) / 144, window.innerHeight / 2 / 198)
          : Math.min(2, (window.innerHeight - 16) / 198);
        const zoom = document.createElement('div');
        zoom.className = 'soh_card-hover-zoom' + (stacked ? ' soh_card-hover-zoom_stacked' : '');
        zoom.append(this.cardArtCopy(face, scale), this.cardTextPanel(face));
        document.body.append(zoom);
        const { width, height } = zoom.getBoundingClientRect();
        const rect = face.getBoundingClientRect();
        const right = rect.right + 8;
        const left = right + width <= window.innerWidth ? right : Math.max(0, rect.left - 8 - width);
        const top = Math.min(Math.max(8, rect.top + rect.height / 2 - height / 2), window.innerHeight - height - 8);
        Object.assign(zoom.style, { left: `${left}px`, top: `${Math.max(0, top)}px` });
        this._cardHoverZoom = zoom;
        // Thumbnails are scaled art in a clipping frame, so outline the frame.
        this._cardHoverSource = face.closest('.soh_panel_card, .soh_draft_card') || face;
        this._cardHoverSource.classList.add('soh_card-hover-source');
      }, 250);
    },

    hideCardHoverZoom: function () {
      clearTimeout(this._cardHoverTimer);
      this._cardHoverZoom?.remove();
      this._cardHoverZoom = null;
      this._cardHoverSource?.classList.remove('soh_card-hover-source');
      this._cardHoverSource = null;
    },

    /**
     * Setup helper for non-playable cards (captain, ship upgrades)
     */
    setupNonPlayableCardHelper: function (card, div, side) {
      let image_id = null;
      const cardData = card.cardKey && this.non_playable_cards ? this.non_playable_cards[card.cardKey] : null;
      if (cardData) div.dataset.cardKey = card.cardKey;

      if (side === "back" && cardData && cardData.category === "ship_upgrade") {
        // Ship upgrade cards flip to their activated (upgraded) side, stored 2 sprites after the front.
        image_id = cardData.image_id + 2;
        // Its upgraded side: real card art, so it can be enlarged like a front.
        div.classList.add("soh_non-playable-card-back", "upgrade-activated-face");
      } else if (cardData) {
        image_id = cardData.image_id;
        div.classList.add("soh_non-playable-card-front");
      } else if (this.non_playable_cards && this.non_playable_cards.card_back) {
        image_id = this.non_playable_cards.card_back.image_id;
        div.classList.add("soh_non-playable-card-back");
      } else {
        image_id = 0;
        div.classList.add("soh_non-playable-card-back");
      }

      console.log(
        "setup non-playable card helper for card: " +
          card.id +
          " with cardKey " +
          card.cardKey +
          " side " +
          side +
          " and image id " +
          image_id,
      );

      const spriteX = (image_id % 6) * 144;
      const spriteY = Math.floor(image_id / 6) * 198;
      domStyle.set(div, "background-position", `-${spriteX}px -${spriteY}px`);
      console.log("background-position for " + card.id + " set to: " + `-${spriteX}px -${spriteY}px`);
    },

    /**
     * Add player's captain and ship upgrade cards to their board
     */
    addPlayerCardsToBoard: function (gamedatas) {
      console.log("Adding player's captain and ship upgrades to board...");

      // Add player's captain card
      if (gamedatas.player_captain) {
        const captainCard = {
          id: `captain-${gamedatas.player_captain}`,
          cardKey: gamedatas.player_captain,
          category: "captain",
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
      const upgradesPlaced = [];
      if (gamedatas.player_ship_upgrades && gamedatas.player_ship_upgrades.length > 0) {
        gamedatas.player_ship_upgrades.forEach((upgrade, index) => {
          const upgradeCard = {
            id: `upgrade-${upgrade.upgrade_key}`,
            cardKey: upgrade.upgrade_key,
            category: "ship_upgrade",
            isActivated: upgrade.is_activated == 1,
          };

          console.log(`Adding upgrade card ${index + 1}:`, upgradeCard);
          try {
            upgradesPlaced.push(this.upgradesStock.addCard(upgradeCard));
            console.log(`Upgrade card ${index + 1} added successfully`);

            this.updateUpgradeCardVisual(upgradeCard);
          } catch (error) {
            console.error(`Error adding upgrade card ${index + 1}:`, error);
          }
        });
      }
      // Resolves once the cards have animated into their slots (see addUpgradeActivationButtons).
      this.upgradeCardsPlaced = Promise.all(upgradesPlaced);
    },

    /**
     * The player's ships, as drawn on the board, at the end of their panel's flag row: in the
     * 2 Ship Variant this is how you tell whose second ship is whose, since each ship type has its
     * own colour. Other players also get a board under mine with their captain, upgrades, deck and
     * discard (mine are the full cards in #myship and the piles in #mycards).
     */
    addPlayerPanelCards: function (player) {
      const info = this.gamedatas.playerinfo[player.id];
      const ships = [info.player_ship, info.player_ship2].filter(Boolean)
        .map((shipname) => `<div class="soh_player_ship soh_panel_ship" data-shipname="${shipname}" title="${_(shipname)}"></div>`)
        .join("");
      $(`player_token_board_p${player.id}`).insertAdjacentHTML("beforeend", `<div class="soh_panel_ships">${ships}</div>`);
      if (player.id == this.player_id) {
        return;
      }
      $("soh_other_boards").insertAdjacentHTML(
        "beforeend",
        `<div class="whiteblock soh_player_board">
           <h3 style="color: #${player.color}">${player.name}</h3>
           <div id="player_panel_cards_p${player.id}" class="soh_player_panel_cards"></div>
         </div>`,
      );
      const row = $(`player_panel_cards_p${player.id}`);
      // Each part under a heading, as on my board.
      // bga-cards names a pile's face-down top card after the pile's element id, so piles need one.
      const group = (label, classes = "", id = "") => {
        row.insertAdjacentHTML("beforeend", `<div class="soh_board_group"><h3>${label}</h3><div ${id && `id="${id}"`} class="${classes}"></div></div>`);
        return row.lastElementChild.lastElementChild;
      };
      group(_("Ship"), "soh_board_ships").innerHTML = ships;
      // No captain yet while the snake draft runs.
      if (player.captain) {
        this.addPanelCard(group(_("Captain")), `panel_captain_p${player.id}`, player.captain, false);
      }
      const upgrades = group(_("Ship Upgrades"), "soh_board_upgrades");
      for (const upgrade of player.ship_upgrades) {
        this.addPanelCard(upgrades, `panel_upgrade_p${player.id}_${upgrade.upgrade_key}`, upgrade.upgrade_key, upgrade.is_activated == 1);
      }
      // Their deck and discard as mine are shown, but not clickable: only the top discard is known here.
      this.opponentPiles[player.id] = {
        deck: new BgaCards.Deck(this.cardsManager, group(_("Deck"), "soh_board_pile", `deck_p${player.id}`), { cardNumber: 0, counter: { hideWhenEmpty: true } }),
        discard: new BgaCards.Deck(this.cardsManager, group(_("Discard"), "soh_board_pile", `discard_p${player.id}`), { cardNumber: 0, counter: { hideWhenEmpty: true } }),
      };
    },

    addPanelCard: function (row, id, cardKey, active) {
      const thumb = document.createElement("div");
      thumb.id = id;
      thumb.className = "soh_panel_card";
      thumb.dataset.cardkey = cardKey;
      row.append(thumb);
      this.setPanelCardActive(id, active);
    },

    /** Upgrades show their activated side (2 sprites after the front) once active. */
    setPanelCardActive: function (id, active) {
      const thumb = $(id);
      const cardData = this.non_playable_cards[thumb.dataset.cardkey];
      const image_id = cardData.image_id + (active ? 2 : 0);
      const position = `-${(image_id % 6) * 144}px -${Math.floor(image_id / 6) * 198}px`;
      thumb.innerHTML = `<div class="soh_non-playable-card-front soh_panel_card_art" data-card-key="${thumb.dataset.cardkey}" style="background-position: ${position}"></div>`;
      this.setupCardPreview(thumb.firstChild);
    },

    /**
     * Update upgrade card visual based on activation status
     */
    updateUpgradeCardVisual: function (upgradeCard) {
      if (upgradeCard.isActivated) {
        this.nonPlayableCardsManager.flipCard(upgradeCard);
      }
    },
  };
});
