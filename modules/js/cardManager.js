/**
 * Seas of Havoc - Card Manager Module
 * Card setup helpers and card-related UI management
 */

define(["dojo/dom-style"], function (domStyle) {
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
            face.classList.contains('non-playable-card-back') ||
            !face.closest('.seasofhavoc-card[data-side="front"]')) return;
        event.stopPropagation();
        const previousFocus = document.activeElement;
        const dialog = document.createElement('dialog');
        dialog.className = 'card-zoom-dialog';
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
        div.classList.add("non-playable-card-back");
      } else if (cardData) {
        image_id = cardData.image_id;
        div.classList.add("non-playable-card-front");
      } else if (this.non_playable_cards && this.non_playable_cards.card_back) {
        image_id = this.non_playable_cards.card_back.image_id;
        div.classList.add("non-playable-card-back");
      } else {
        image_id = 0;
        div.classList.add("non-playable-card-back");
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
