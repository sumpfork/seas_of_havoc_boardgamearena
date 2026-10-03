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

define(["dojo/dom-style", g_gamethemeurl + "modules/js/constants.js"], function (domStyle, Constants) {
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
      face.addEventListener('click', event => {
        // Selectable stocks own clicks for playing, scrapping, and discarding.
        if (face.closest('.bga-cards_selectable-stock') ||
            (face.classList.contains('soh_non-playable-card-back') && !face.classList.contains('upgrade-activated-face')) ||
            !(face.closest('.soh_seasofhavoc-card[data-side="front"]') || face.classList.contains('soh_panel_card_art') ||
              face.classList.contains('upgrade-activated-face'))) return;
        event.stopPropagation();
        const previousFocus = document.activeElement;
        const dialog = document.createElement('dialog');
        dialog.className = 'soh_card-zoom-dialog';
        dialog.setAttribute('aria-label', _('Card preview'));
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'bgabutton bgabutton_gray';
        close.textContent = _('Close');
        close.addEventListener('click', () => dialog.close());
        const frame = document.createElement('div');
        const art = document.createElement('div');
        const style = getComputedStyle(face);
        const scale = Math.min(3, (window.innerWidth - 64) / 144, (window.innerHeight - 120) / 198);
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
        dialog.append(frame, close);
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

    /**
     * Setup helper for non-playable cards (captain, ship upgrades)
     */
    setupNonPlayableCardHelper: function (card, div, side) {
      let image_id = null;
      const cardData = card.cardKey && this.non_playable_cards ? this.non_playable_cards[card.cardKey] : null;

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
            this.upgradesStock.addCard(upgradeCard);
            console.log(`Upgrade card ${index + 1} added successfully`);

            this.updateUpgradeCardVisual(upgradeCard);
          } catch (error) {
            console.error(`Error adding upgrade card ${index + 1}:`, error);
          }
        });
      }
    },

    /** Captain and ship upgrade thumbnails in a player's panel; click shows the full card. */
    addPlayerPanelCards: function (player) {
      const row = document.createElement("div");
      row.className = "soh_cp_board soh_player_panel_cards";
      row.id = `player_panel_cards_p${player.id}`;
      this.bga.playerPanels.getElement(player.id).append(row);
      // The player's ships as drawn on the board: in the 2 Ship Variant this is how you tell whose
      // second ship is whose, since each ship type has its own colour.
      const info = this.gamedatas.playerinfo[player.id];
      for (const shipname of [info.player_ship, info.player_ship2].filter(Boolean)) {
        row.insertAdjacentHTML(
          "beforeend",
          `<div class="soh_player_ship soh_panel_ship" data-shipname="${shipname}" title="${_(shipname)}"></div>`,
        );
      }
      // No captain yet while the snake draft runs.
      if (player.captain) {
        this.addPanelCard(row, `panel_captain_p${player.id}`, player.captain, false);
      }
      for (const upgrade of player.ship_upgrades) {
        this.addPanelCard(row, `panel_upgrade_p${player.id}_${upgrade.upgrade_key}`, upgrade.upgrade_key, upgrade.is_activated == 1);
      }
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
      thumb.classList.toggle("soh_active", active);
      thumb.classList.toggle("soh_inactive", cardData.category === "ship_upgrade" && !active);
      thumb.innerHTML = `<div class="soh_non-playable-card-front soh_panel_card_art" style="background-position: ${position}"></div>`;
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
