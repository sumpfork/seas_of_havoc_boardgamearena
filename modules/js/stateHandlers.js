/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/**
 * Seas of Havoc - State Handlers Module
 * Game state enter/leave/update action button handlers
 */

define(["dojo/dom-class", "dojo/dom-construct", "dojo/query", g_gamethemeurl + "modules/js/constants.js"], function (domClass, domConstruct, query, Constants) {
  // Debug logging only in Studio (see constants.js).
  const console = Constants.console;

  return {
    /**
     * Called when entering a new game state
     */
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);
      this.updateHandSelectionMode();
      this.highlightActivePlayerShips(args.type === "activeplayer" ? args.active_player : null);

      switch (stateName) {
        case "cardPurchases": {
          if (!this._restoringFromBootyConfirm) {
            this.cards_purchased = [];
            this._bootyUsedForPurchase = false;
            this._savePurchaseSnapshot();
          }
          this.updateCardPurchaseButtons(true);
          break;
        }

        case "cardPurchasesPrivate": {
          if (!this._restoringFromBootyConfirm) {
            this.cards_purchased = [];
            this._bootyUsedForPurchase = false;
            this._savePurchaseSnapshot();
          }
          this.updateCardPurchaseButtons(true);
          break;
        }

        case "cardPurchasesCompleted": {
          this.updateCardPurchaseButtons(false);
          break;
        }

        case "islandPhaseSetup": {
          if (this.player_captain === "corsair") {
            this.corsairOccupiedPlacementAvailable = true;
          }
          break;
        }

        case "islandTurn": {
          // In the args rather than a notification, so a page refresh reopens the exchange.
          if (this.isCurrentPlayerActive() && args.args.pending_trading_post) {
            this.initTradingPost(args.args.pending_trading_post.slot_number);
          }
          break;
        }

        case "seaPhaseSetup": {
          // Clear all skiffs when entering sea phase
          query(".skiff_placed").forEach(domConstruct.destroy);
          query(".soh_purchase_card_button").forEach(domConstruct.destroy);
          query(".soh_skiff_slot").forEach(function (slot) {
            domClass.add(slot, "soh_unoccupied");
            query(".soh_skiff", slot).forEach(domConstruct.destroy);
          });
          break;
        }

        case "seaTurn": {
          query(".skiff_placed").forEach(domConstruct.destroy);
          query(".soh_purchase_card_button").forEach(domConstruct.destroy);
          break;
        }

        case "treasureSeekerAdjust": {
          this.setupTreasureSeekerAdjust(args.args);
          break;
        }

        case "chooseHeading":
          this.setupChooseHeading(args.args);
          break;

        case "draftCaptain":
        case "draftShip":
          this.setupDraft(stateName, args.args);
          break;

        case "extortion": {
          this._extortionArgs = args.args || {};
          break;
        }
      }
      // Skiff slots stay highlighted (e.g. for the Corsair) only while this player is placing one.
      this.updateSkiffSlotPlaceability();
    },

    /**
     * Extortion: green and red need a second choice, tan and blue resolve straight away.
     */
    onExtortionFlagChosen: function (flag, args) {
      if (flag === "green") {
        this.setClientState("client_extortionGreenResource", {
          descriptionmyturn: _("Sea Phase: ${you} must choose a resource (Green Purser's Flag)"),
        });
        return;
      }
      if (flag === "red") {
        this.setClientState("client_extortionScrap", {
          descriptionmyturn: _("Sea Phase: ${you} must choose a card to scrap (Red Shipwright's Flag)"),
        });
        return;
      }
      this.bgaPerformAction("actExtortionUseFlag", { flag });
    },

    /** What Boarding Party can take from `target`; addExit() adds the way out (skip or back). */
    addBoardingPartyChoices: function (target, addExit) {
      const title = _("Sea Phase: ${you} may steal 1 resource or a booty token from ${player}");
      const player = this.gamedatas.players[target.player_id].name;
      this.statusBar.setTitle(title, { player });
      const held = target.resources || {};
      const resources = Object.keys(held).filter((r) => held[r] > 0);
      const steal = (item) => this.bgaPerformAction("actBoardingPartySteal", { target_player_id: target.player_id, item });
      const hasBooty = (target.booty_token_count || 0) > 0;
      if (resources.length + hasBooty <= 3) {
        this.addResourceButtons(steal, resources);
      } else {
        // Three resources and a booty token: the resource is its own step, to keep to four buttons.
        this.statusBar.addActionButton(_("A resource"), () => this.showBarStep(title, () => {
          this.statusBar.setTitle(title, { player });
          this.addResourceButtons(steal, resources);
        }, () => {
          this.statusBar.removeActionButtons();
          this.addBoardingPartyChoices(target, addExit);
        }));
      }
      if (hasBooty) {
        this.statusBar.addActionButton(_("Booty token"), () => steal("booty_token"));
      }
      addExit();
    },

    /**
     * Merchant: which doubloons stand in for cannonballs and sails. All the mixes when they fit in
     * the bar; otherwise how many for cannonballs, then how many for sails.
     */
    addMerchantSubstituteChoices: function () {
      const combos = this._pendingMerchantPurchase.combinations;
      const swap = (n, res) => n + " " + this.resourceIcon("doubloon") + " → " + n + " " + this.resourceIcon(res);
      const cancel = () => this.statusBar.addActionButton(_("Cancel"), this.onMerchantSubstituteCancel.bind(this), {
        color: "alert",
      });
      if (combos.length <= 3) {
        for (const combo of combos) {
          const parts = [];
          if (combo.cb > 0) parts.push(swap(combo.cb, "cannonball"));
          if (combo.sail > 0) parts.push(swap(combo.sail, "sail"));
          this.statusBar.addActionButton(parts.length > 0 ? parts.join(", ") : _("No substitution"),
            () => this.onMerchantSubstituteChosen(combo.cb, combo.sail),
            { color: parts.length > 0 ? "primary" : "secondary" });
        }
        cancel();
        return;
      }
      const cbCounts = [...new Set(combos.map((c) => c.cb))].sort((a, b) => a - b);
      const sails = (cb) => {
        const counts = combos.filter((c) => c.cb === cb).map((c) => c.sail).sort((a, b) => a - b);
        if (counts.length === 1) {
          this.onMerchantSubstituteChosen(cb, counts[0]);
          return;
        }
        this.statusBar.removeActionButtons();
        this.statusBar.setTitle(_("Merchant: how many doubloons pay for sails?"));
        this.addCountChoice(counts, (n) => swap(n, "sail"), (sail) => this.onMerchantSubstituteChosen(cb, sail),
          cbCounts.length > 1
            ? () => this.statusBar.addActionButton(_("Back"), () => {
              this.statusBar.removeActionButtons();
              this.addMerchantSubstituteChoices();
            }, { color: "alert" })
            : cancel);
      };
      if (cbCounts.length === 1) {
        sails(cbCounts[0]);
        return;
      }
      this.statusBar.setTitle(_("Merchant: how many doubloons pay for cannonballs?"));
      this.addCountChoice(cbCounts, (n) => swap(n, "cannonball"), sails, cancel);
    },

    /**
     * Choose one of `counts` (ascending): a button each, or for four a stepper - "−", the current
     * count (choosing it), "+" - so with addExit()'s button the bar keeps to four.
     */
    addCountChoice: function (counts, label, onPick, addExit) {
      if (counts.length <= 3) {
        counts.forEach((n) => this.statusBar.addActionButton(label(n), () => onPick(n)));
        addExit();
        return;
      }
      const show = (i) => {
        this.statusBar.removeActionButtons();
        this.statusBar.addActionButton("\u2212", () => show(i - 1), { disabled: i === 0, title: _("Fewer") });
        this.statusBar.addActionButton(label(counts[i]), () => onPick(counts[i]));
        this.statusBar.addActionButton("+", () => show(i + 1), { disabled: i === counts.length - 1, title: _("More") });
        addExit();
      };
      show(0);
    },

    /**
     * Called when leaving a game state
     */
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);
      this.bga.gameArea.getElement().classList.remove("soh_placing");
      this.clearPendingSkiffSlot();
      // A hovered status bar button's board preview: the button goes away without a mouseleave.
      // (The card play dialog's own preview stays while the dialog is open.)
      if (!this._previewActions) this.clearCardPreview();
      this.clearChoosables();

      switch (stateName) {
        case "islandTurn":
          this.clearUpgradeActivationButtons();
          break;


        case "captainCard":
          this.cleanupCaptainCardSelection();
          this.cleanupCardPlayDialog();
          break;

        case "cardFlag":
        case "scrapCard":
        case "client_extortionScrap":
        case "rebelDiscard":
        case "collisionDiscard":
          this.cleanupCardPick();
          break;

        case "treasureSeekerAdjust":
          this.cleanupTreasureSeekerAdjust();
          break;

        case "chooseHeading":
          this.cleanupChooseHeading();
          break;

        case "draftCaptain":
        case "draftShip":
          this.cleanupDraft();
          break;

        case "resolveCollision":
          var w = document.getElementById("pivot_booty_wrap");
          if (w) domConstruct.destroy(w);
          break;

        case "timelyTrading":
          query(".soh_purchase_card_button").forEach(domConstruct.destroy);
          break;
      }
    },

    /**
     * Update action buttons in the status bar
     */
    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: " + stateName);
      console.log("isCurrentPlayerActive(): " + this.isCurrentPlayerActive());
      console.log("args:", args);
      // Note: updateHandSelectionMode is called from onEnteringState, not here.
      // checkAction() returns false during onUpdateActionButtons because the
      // BGA framework still has the interface locked at this point.

      if (this.isCurrentPlayerActive()) {
        switch (stateName) {
          case "seaTurn":
            this.statusBar.addActionButton(_("Row (discard 2 cards)"), () => this.startRowing(), {
              id: "soh_row_button",
              color: "secondary",
              disabled: this.playerHand.getCards().length < 2,
            });
            this.addTooltipHtml("soh_row_button", this.tooltipText("row_button"));
            break;

          case "captainCard":
            // Here rather than onEnteringState: the framework clears the status bar buttons before this.
            this.setupCaptainCardSelection(args);
            break;

          case "cardFlag":
            if (args.flag === "red") {
              this.setupScrapCardSelection(args._private.available_cards);
            } else if (args.flag === "green") {
              this.addResourceButtons((resource) => this.bgaPerformAction("actResolveCardFlag", { resource }));
            } else if (args.flag === "tan" || args.flag === "blue") {
              this.statusBar.addActionButton(args.flag === "tan" ? _("Draw a card") : _("Take another turn"), () => {
                this.bgaPerformAction("actResolveCardFlag", {});
              });
            }
            this.statusBar.addActionButton(_("Skip flag action"), () => {
              this.bgaPerformAction("actSkipCardFlag", {});
            }, { color: "secondary" });
            break;

          case "postCollisionFire": {
            // The collision ended the maneuver, but the outline after it still shows a cannon.
            const fireAction = args.action || {};
            const shots = [];
            if (fireAction.variants) {
              fireAction.variants.forEach((variant) => variant.sides.forEach((side) => shots.push(
                { weapon: variant, name: variant.name + " " + side, cost: variant.cost, range: variant.range },
              )));
            } else {
              const weapon = { name: fireAction.action, cost: fireAction.cost, range: fireAction.range };
              ["left", "right"].forEach((side) => shots.push(
                { weapon, name: fireAction.action + " " + side, cost: fireAction.cost, range: fireAction.range },
              ));
            }
            const affordable = shots.filter((shot) => this.canPlayerAfford(shot.cost, true, false));
            const addShot = (shot) => this.addPreviewedActionButton(
              this._choiceLabelHtml(shot),
              () => { this.fireAfterCollision(shot); },
              { actions: [fireAction], decisions: [shot.name], ship: args.ship },
            );
            if (affordable.length <= 3) {
              affordable.forEach(addShot);
            } else {
              // An upgraded ship has a side for each weapon: pick the weapon, then the side.
              for (const weapon of new Set(affordable.map((shot) => shot.weapon))) {
                this.statusBar.addActionButton(this._choiceLabelHtml(weapon), () => this.showBarStep(
                  _("Sea Phase: ${you} may fire to one side"),
                  () => affordable.filter((shot) => shot.weapon === weapon).forEach(addShot),
                ));
              }
            }
            this.statusBar.addActionButton(
              _("Don't fire"),
              () => { this.bgaPerformAction("actPostCollisionFire", { decision: "skip" }); },
              { color: "secondary" },
            );
            break;
          }

          case "bootyDiscard": {
            // Token values are secret, so the choice is private: show the player their own art.
            ((args._private || {}).booty_tokens || []).forEach((token) => {
              // An inline icon: the sprite scaled to the token's size, not the panel slot's.
              const node = this.createBootyTokenNode(false, token.image_id);
              node.classList.add("soh_booty-token-button-icon");
              node.id = "booty_discard_choice_" + token.id;
              this.setBootyTokenPosition(node, token.image_id, 36);
              this.statusBar.addActionButton(
                _("Discard") + " " + node.outerHTML,
                () => { this.bgaPerformAction("actDiscardBootyToken", { card_id: token.id }); },
              );
              this.addBootyTokenTooltip(document.getElementById(node.id), token.image_id);
            });
            break;
          }

          case "huntTheBountyExtraPlay": {
            this.statusBar.addActionButton(
              _("Play another card"),
              () => { this.bgaPerformAction("actHuntTheBountyPlayAnother", {}); },
              {},
            );
            this.statusBar.addActionButton(_("End turn"), () => {
              this.bgaPerformAction("actSkipHuntTheBountyExtraPlay", {});
            }, { color: "secondary" });
            break;
          }

          case "swiftHull": {
            this.statusBar.addActionButton(
              _("Pay 1") + " " + this.resourceIcon("sail") + " " + _("to play another card"),
              () => this.payWithOptionalBooty({ sail: 1 }, "actUseSwiftHull"),
              {},
            );
            this.statusBar.addActionButton(_("End turn"), () => {
              this.bgaPerformAction("actSkipSwiftHull", {});
            }, { color: "secondary" });
            break;
          }

          case "extraRations": {
            this.addExtraRationsButtons(args.extra_rations_resources, _("Pay 1"));
            this.statusBar.addActionButton(_("Skip"), () => {
              this.bgaPerformAction("actSkipExtraRations", {});
            }, { color: "secondary" });
            break;
          }

          case "islandTurn": {
            // A placement waiting on a choice comes back from the server's args, so a refresh
            // restores it.
            if (args.pending_resource_choice) {
              this.statusBar.setTitle(_("Island Phase: ${you} must select a resource"));
              this.addResourceButtons((resource) => this.bgaPerformAction("actResourcePickedInDialog", { resource }));
              break;
            }
            this.clearUpgradeActivationButtons();
            if (args.pending_workshop) {
              this.statusBar.setTitle(_("Island Phase: ${you} must choose a ship upgrade to activate"));
              // The server only occupies the slot once an upgrade is chosen; show the skiff meanwhile.
              const number = args.pending_workshop.slot_number;
              this.renderSkiffInSlot(document.getElementById(`skiff_slot_workshop_${number}`), "workshop", number, this.player_id, this.players, false);
              this.addUpgradeActivationButtons(args.pending_workshop.upgrades);
              break;
            }
            this.refreshSkiffSlotPlaceability();
            // Gives the free skiff slots a pointer cursor (see .soh_placing in the CSS).
            this.bga.gameArea.getElement().classList.add("soh_placing");
            if (
              this.player_captain === "corsair" &&
              this.corsairOccupiedPlacementAvailable &&
              (this.corsairOccupiedSlotNames || []).length > 0
            ) {
              this.showMessage(
                _("Corsair: you may place one skiff on a highlighted occupied space (resources only)"),
                "info",
              );
            }
            if (args && args.market_restocked) {
              this.statusBar.setTitle(_("Island Phase: ${you} must place a skiff on a newly revealed Market card"));
            }
            if (args && args.can_restock_market) {
              this.statusBar.addActionButton(
                _("Restock Market"),
                () => this.confirmIslandAction(_("Confirm restock"), () => this.bgaPerformAction("actRestockMarket", {})),
                { color: "secondary" },
              );
            }
            if (args) {
              this.addExtraRationsButtons(args.extra_rations_resources, _("Extra Rations") + ": " + _("pay 1"));
            }
            break;
          }

          case "cardPurchases":
          case "cardPurchasesPrivate":
            console.log("Adding End Purchase Phase button for state: " + stateName);
            this.statusBar.addActionButton(_("End Purchase Phase"), this.onCompletePurchasesClicked.bind(this));
            this.statusBar.addActionButton(_("Restart Purchases"), this.onRestartPurchasesClicked.bind(this), {
              color: "alert",
            });
            break;

          case "client_merchantSubstitute":
            if (this._pendingMerchantPurchase) {
              this.addMerchantSubstituteChoices();
            }
            break;

          case "client_bootyPurchaseConfirm":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onBootyPurchaseYes.bind(this));
            this.statusBar.addActionButton(_("No thanks"), this.onBootyPurchaseNo.bind(this), { color: "secondary" });
            this.statusBar.addActionButton(_("Cancel"), this.onBootyPurchaseCancel.bind(this), {
              color: "alert",
            });
            break;

          case "client_bootyPlayConfirm":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onBootyPlayYes.bind(this));
            this.statusBar.addActionButton(_("No thanks"), this.onBootyPlayNo.bind(this), { color: "secondary" });
            this.statusBar.addActionButton(_("Cancel"), this.onBootyPlayCancel.bind(this), {
              color: "alert",
            });
            break;

          case "client_tradingPostBootyChoice":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onTradingPostBootyYes.bind(this));
            this.statusBar.addActionButton(_("No, trade resources"), this.onTradingPostBootyNo.bind(this), { color: "secondary" });
            break;

          case "client_tradingPost":
            this.showTradingPostStep();
            break;

          case "resolveCollision":
            this.addPreviewedActionButton(
              "<div class='soh_resource soh_pivot_left' role='img' aria-label='" + _("Pivot left") + "' data-pivot='pivot left'></div>",
              this.onPivotButtonClicked.bind(this),
              { actions: [{ action: "pivot left" }], decisions: [], ship: args.ship },
              { classes: "soh_pivot_button" },
            );
            this.statusBar.addActionButton(
              "<div class='soh_resource soh_nope' role='img' aria-label='" + _("Do not pivot") + "' data-pivot='no pivot'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "soh_pivot_button" },
            );
            this.addPreviewedActionButton(
              "<div class='soh_resource soh_pivot_right' role='img' aria-label='" + _("Pivot right") + "' data-pivot='pivot right'></div>",
              this.onPivotButtonClicked.bind(this),
              { actions: [{ action: "pivot right" }], decisions: [], ship: args.ship },
              { classes: "soh_pivot_button" },
            );
            break;

          case "rallyTheFlagsChooseFlag": {
            var flagNames = { green_flag: _("Green Purser's Flag"), tan_flag: _("Tan Bosun's Flag"), blue_flag: _("Blue Sailor's Flag"), red_flag: _("Red Shipwright's Flag") };
            var self = this;
            (args.available_flags || []).forEach(function (flag) {
              var flagKey = flag.flag_key;
              var name = flagNames[flagKey] || flagKey;
              // Taking a flag off a neighbour is a different decision from taking a free one.
              var label = flag.owner_name
                ? self.format_string_recursive(_("Take ${flag} from ${player}"), { flag: name, player: flag.owner_name })
                : _("Take") + " " + name;
              self.statusBar.addActionButton(
                label,
                function () { self.bgaPerformAction("actRallyTheFlagsChooseFlag", { flag_key: flagKey }); },
                {},
              );
            });
            break;
          }

          case "extortion": {
            // "Use the action of each flag you control in any order" - one button per flag left.
            var extortionArgs2 = args || {};
            var extortionNames = {
              green: _("Green Purser's Flag: gain a resource"),
              tan: _("Tan Bosun's Flag: draw a card"),
              blue: _("Blue Sailor's Flag: extra island turn"),
              red: _("Red Shipwright's Flag: scrap a card"),
            };
            // Chosen on my player board rather than one button per flag: up to four, plus the skip.
            this.statusBar.setTitle(_("Sea Phase: ${you} may use each of your flags: choose one on your player board"));
            // Fade the rest of the row, so the flags still to use stand out from the used ones.
            document.getElementById(`player_token_board_p${this.player_id}`).classList.add("soh_choosing");
            (extortionArgs2.pending_flags || []).forEach((flag) => {
              this.makeChoosable(document.getElementById(`${flag}_flag_p${this.player_id}`), extortionNames[flag],
                () => this.onExtortionFlagChosen(flag, extortionArgs2));
            });
            this.statusBar.addActionButton(_("Skip the rest"), () => {
              this.bgaPerformAction("actSkipExtortion", {});
            }, { color: "secondary" });
            break;
          }

          case "scrapCard":
            this.setupScrapCardSelection(args.available_cards, _("Don't scrap"),
              () => this.bgaPerformAction("actSkipIslandScrap", {}));
            break;

          case "rebelDiscard":
          case "collisionDiscard":
            this.setupDiscardCardSelection(args.available_cards);
            break;

          case "client_extortionScrap":
            this.setupScrapCardSelection(this._extortionArgs.available_cards, _("Back"),
              () => this.restoreServerGameState(), "alert");
            break;

          case "client_extortionGreenResource": {
            this.addResourceButtons((resource) => this.bgaPerformAction("actExtortionUseFlag", { flag: "green", resource }));
            this.statusBar.addActionButton(_("Back"), () => { this.restoreServerGameState(); }, {
              color: "alert",
            });
            break;
          }

          case "barter": {
            const infamy = (args || {}).infamy || 0;
            const rates = { sail: 1, cannonball: 2, doubloon: 3 };
            const resources = Object.keys(rates);
            const toInfamy = resources.filter((res) => this.canPlayerAfford({ [res]: 1 }, true, false));
            const fromInfamy = resources.filter((res) => infamy >= rates[res]);
            const addTrades = (list, direction) => list.forEach((res) => this.statusBar.addActionButton(
              direction === "resource_to_infamy"
                ? "1 " + this.resourceIcon(res) + " → " + rates[res] + " " + this.resourceIcon("infamy")
                : rates[res] + " " + this.resourceIcon("infamy") + " → 1 " + this.resourceIcon(res),
              () => direction === "resource_to_infamy"
                ? this.payWithOptionalBooty({ [res]: 1 }, "actBarterExchange", { resource: res, direction })
                : this.bgaPerformAction("actBarterExchange", { resource: res, direction }),
            ));
            if (toInfamy.length + fromInfamy.length <= 3) {
              addTrades(toInfamy, "resource_to_infamy");
              addTrades(fromInfamy, "infamy_to_resource");
            } else {
              // Up to six trades: pick the direction, then the resource.
              this.statusBar.addActionButton(_("Trade a resource for") + " " + this.resourceIcon("infamy"),
                () => this.showBarStep(_("Sea Phase: ${you} may trade a resource for infamy"),
                  () => addTrades(toInfamy, "resource_to_infamy")));
              this.statusBar.addActionButton(_("Trade") + " " + this.resourceIcon("infamy") + " " + _("for a resource"),
                () => this.showBarStep(_("Sea Phase: ${you} may trade infamy for a resource"),
                  () => addTrades(fromInfamy, "infamy_to_resource")));
            }
            this.statusBar.addActionButton(_("Skip (No Exchange)"), () => this.bgaPerformAction("actSkipBarter", {}), {
              color: "secondary",
            });
            break;
          }

          case "chainShotLoss": {
            this.addResourceButtons((resource) => this.bgaPerformAction("actChainShotLose", { resource }), args.options);
            break;
          }

          case "timelyTrading": {
            // Two resources, picked one at a time (the six pairs would overflow the bar).
            const resources = ["sail", "cannonball", "doubloon"];
            const gain = (first, second) => this.bgaPerformAction("actTimelyTradingGainResources", { first, second });
            resources.forEach((first) => this.statusBar.addActionButton(
              _("Gain") + " " + this.resourceIcon(first),
              () => this.showBarStep(
                _("Sea Phase: ${you} gain") + " " + this.resourceIcon(first) + " " + _("and one more resource"),
                () => resources.forEach((second) => this.statusBar.addActionButton(
                  _("Gain") + " " + this.resourceIcon(first) + this.resourceIcon(second), () => gain(first, second),
                )),
              ),
            ));
            this.addTimelyTradingPurchaseButtons();
            break;
          }

          case "boardingParty": {
            const targets = (args || {}).targets || [];
            const skip = () => this.statusBar.addActionButton(_("Skip (No Steal)"),
              () => this.bgaPerformAction("actSkipBoardingParty", {}), { color: "secondary" });
            if (targets.length === 1) {
              this.addBoardingPartyChoices(targets[0], skip);
              break;
            }
            // Several ships alongside: choose whose on the board, then what to take.
            this.statusBar.setTitle(_("Sea Phase: ${you} may board a ship alongside: choose it on the board"));
            for (const target of targets) {
              const name = this.gamedatas.players[target.player_id].name;
              for (const ship of this.playerShipElements(target.player_id)) {
                this.makeChoosable(ship, name, () => {
                  this.clearChoosables();
                  this.statusBar.removeActionButtons();
                  this.addBoardingPartyChoices(target, () => this.statusBar.addActionButton(
                    _("Back"), () => this.restoreServerGameState(), { color: "alert" }));
                });
              }
            }
            skip();
            break;
          }

          case "huntTheBounty": {
            // The target is chosen on the board: every other ship can be one.
            this.statusBar.setTitle(_("Sea Phase: ${you} may choose a ship to hunt on the board"));
            for (const target of (args || {}).targets || []) {
              this.makeChoosable(document.getElementById("player_ship_" + target.ship),
                _(target.ship_name) + " (" + target.player_name + ")",
                () => this.bgaPerformAction("actHuntTheBountyChooseTarget", { target_ship: target.ship }));
            }
            this.statusBar.addActionButton(_("Skip (No Target)"), () => this.bgaPerformAction("actSkipHuntTheBounty", {}), {
              color: "secondary",
            });
            break;
          }

          case "treasureSeekerAdjust":
            this.statusBar.addActionButton(_("Keep current location"), this.onSkipTreasureSeekerAdjust.bind(this), {
              color: "secondary",
            });
            break;

        }
      }
    },

    /**
     * Handle pivot button click in collision resolution
     */
    onPivotButtonClicked: function (event) {
      event.preventDefault();
      // The data-pivot attribute is on the inner div, not the button itself
      // Use currentTarget (the button) and find the inner element with data-pivot
      const button = event.currentTarget;
      const pivotElement = button.querySelector("[data-pivot]") || event.target;
      const direction = pivotElement?.dataset?.pivot;

      console.log("pivot button clicked");
      console.log(pivotElement);
      console.log("pivot picked " + direction);

      if (direction == null) {
        throw new Error("Pivot button without a data-pivot direction");
      }
      // Picking a direction only selects it; Confirm (which counts down and fires on its own)
      // sends it, so a misclick can still be changed.
      for (const b of document.querySelectorAll("#generalactions .soh_pivot_button")) {
        b.classList.toggle("soh_pivot_selected", b === button);
      }
      document.getElementById("soh_pivot_confirm")?.remove();
      document.getElementById("soh_pivot_cancel")?.remove();
      this.statusBar.addActionButton(_("Confirm"), () => {
        this.bgaPerformAction("actPivotPickedInDialog", { direction: direction });
        this.statusBar.removeActionButtons();
      }, { id: "soh_pivot_confirm", autoclick: true });
      // Back to the three directions with nothing selected and no countdown running.
      this.statusBar.addActionButton(_("Cancel"), () => this.restoreServerGameState(), {
        id: "soh_pivot_cancel",
        color: "alert",
      });
    },
  };
});
