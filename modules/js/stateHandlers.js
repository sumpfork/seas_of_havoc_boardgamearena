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

        case "islandPhase": {
          if (this.player_captain === "corsair") {
            this.corsairOccupiedPlacementAvailable = true;
          }
          this.refreshSkiffSlotPlaceability();
          if (this.isCurrentPlayerActive() && this.gamedatas.pending_trading_post_slot) {
            this.initTradingPost(this.gamedatas.pending_trading_post_slot);
          }
          break;
        }

        case "islandPhaseSetup": {
          if (this.player_captain === "corsair") {
            this.corsairOccupiedPlacementAvailable = true;
          }
          this.refreshSkiffSlotPlaceability();
          break;
        }

        case "islandTurn": {
          this.refreshSkiffSlotPlaceability();
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

        case "cardFlag": {
          if (this.isCurrentPlayerActive() && args.args.flag === "red") {
            this.setupScrapCardSelection(args.args._private);
          }
          break;
        }

        case "scrapCard": {
          if (this.isCurrentPlayerActive()) {
            this.setupScrapCardSelection(args.args);
          }
          break;
        }

        case "rebelDiscard":
        case "collisionDiscard": {
          if (this.isCurrentPlayerActive()) {
            this.setupDiscardCardSelection(args.args, stateName === "collisionDiscard"
              ? _("Choose a card to discard (collision penalty)")
              : _("Choose a card to discard (Rebel ability)"));
          }
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

        case "rallyTheFlagsChooseFlag": {
          break;
        }

        case "extortion": {
          this._extortionArgs = args.args || {};
          break;
        }

        case "barter": {
          break;
        }

        case "timelyTrading": {
          break;
        }

        case "boardingParty": {
          break;
        }

        case "huntTheBounty": {
          break;
        }

        case "resolveCollision": {
          break;
        }

        case "dummmy":
          break;
      }
    },

    /**
     * Extortion: green and red need a second choice, tan and blue resolve straight away.
     */
    onExtortionFlagChosen: function (flag, args) {
      if (flag === "green") {
        this.setClientState("client_extortionGreenResource", {
          descriptionmyturn: _("${you} must choose a resource (Green Purser's Flag)"),
        });
        return;
      }
      if (flag === "red") {
        this.setupScrapCardSelection(args);
        return;
      }
      this.bgaPerformAction("actExtortionUseFlag", { flag });
    },

    /**
     * Called when leaving a game state
     */
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);
      this.bga.gameArea.getElement().classList.remove("soh_placing");
      this.clearPendingSkiffSlot();

      switch (stateName) {
        case "client_tradingPostBootyChoice":
        case "client_tradingPostSpend":
        case "client_tradingPostGain":
          this.cleanupTradingPostUi();
          break;

        case "extortion":
          this.cleanupScrapCardSelection();
          break;

        case "captainCard":
          this.cleanupCaptainCardSelection();
          this.cleanupCardPlayDialog();
          break;

        case "cardFlag":
        case "scrapCard":
          this.cleanupScrapCardSelection();
          break;

        case "rebelDiscard":
        case "collisionDiscard":
          this.cleanupDiscardCardSelection();
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

        case "dummmy":
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
          case "draftCaptain":
            args.captains.forEach(captain => this.statusBar.addActionButton(this.draftChoiceName(stateName, captain),
              () => this.bgaPerformAction("actDraftCaptain", { captain })));
            break;

          case "draftShip":
            args.ships.forEach(ship => this.statusBar.addActionButton(this.draftChoiceName(stateName, ship),
              () => this.bgaPerformAction("actDraftShip", { ship })));
            break;

          case "captainCard":
            // Here rather than onEnteringState: the framework clears the status bar buttons before this.
            this.setupCaptainCardSelection(args);
            break;

          case "cardFlag":
            if (args.flag === "green") {
              ["sail", "cannonball", "doubloon"].forEach(resource => {
                this.statusBar.addActionButton(this.resourceIcon(resource), () => {
                  this.bgaPerformAction("actResolveCardFlag", { resource });
                }, { color: "secondary" });
              });
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
                { name: variant.name + " " + side, cost: variant.cost, range: variant.range },
              )));
            } else {
              ["left", "right"].forEach((side) => shots.push(
                { name: fireAction.action + " " + side, cost: fireAction.cost, range: fireAction.range },
              ));
            }
            shots.forEach((shot) => {
              if (!this.canPlayerAfford(shot.cost, true, false)) {
                return;
              }
              this.statusBar.addActionButton(
                this._choiceLabelHtml(shot),
                () => { this.fireAfterCollision(shot); },
              );
            });
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
                { color: "secondary" },
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
              () => { this.bgaPerformAction("actUseSwiftHull", {}); },
              {},
            );
            this.statusBar.addActionButton(_("End turn"), () => {
              this.bgaPerformAction("actSkipSwiftHull", {});
            }, { color: "secondary" });
            break;
          }

          case "islandTurn": {
            // A placement waiting on a choice comes back from the server's args, so a refresh
            // restores it.
            if (args.pending_resource_choice) {
              this.statusBar.setTitle(_("${you} must select a resource"));
              for (const resource of ["sail", "cannonball", "doubloon"]) {
                this.statusBar.addActionButton(
                  this.resourceIcon(resource),
                  () => this.bgaPerformAction("actResourcePickedInDialog", { resource: resource }),
                  { color: "secondary" },
                );
              }
              break;
            }
            if (args.pending_workshop) {
              this.statusBar.setTitle(_("${you} must choose a ship upgrade to activate"));
              for (const upgrade of args.pending_workshop.upgrades) {
                const cost = Object.entries(upgrade.cost).map(([resource, amount]) => amount + " " + this.resourceIcon(resource));
                this.statusBar.addActionButton(
                  _(upgrade.name) + " (" + cost.join(" ") + ")",
                  () => this.bgaPerformAction("actActivateShipUpgrade", { upgrade_key: upgrade.upgrade_key }),
                  {},
                );
              }
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
              this.statusBar.setTitle(_("${you} must place a skiff on a newly revealed Market card"));
            }
            if (args && args.can_restock_market) {
              this.statusBar.addActionButton(
                _("Restock Market"),
                () => this.confirmIslandAction(_("Confirm restock"), () => this.bgaPerformAction("actRestockMarket", {})),
                { color: "secondary" },
              );
            }
            if (args && args.can_use_extra_rations) {
              this.statusBar.addActionButton(
                _("Extra Rations") + ": " + _("pay 1") + " " + this.resourceIcon("doubloon") + " " + _("to draw a card"),
                () => { this.bgaPerformAction("actExtraRations", {}); },
                {},
              );
            }
            break;
          }

          case "cardPurchases":
          case "cardPurchasesPrivate":
          case "cardPurchasesMaking":
            console.log("Adding Complete Purchases button for state: " + stateName);
            this.statusBar.addActionButton(_("Complete Purchases"), this.onCompletePurchasesClicked.bind(this));
            this.statusBar.addActionButton(_("Restart Purchases"), this.onRestartPurchasesClicked.bind(this), {
              color: "secondary",
            });
            break;

          case "client_merchantSubstitute":
            var ctx = this._pendingMerchantPurchase;
            if (ctx) {
              var self = this;
              ctx.combinations.forEach(function (combo) {
                var parts = [];
                if (combo.cb > 0) parts.push(combo.cb + " " + self.resourceIcon("doubloon") + " → " + combo.cb + " " + self.resourceIcon("cannonball"));
                if (combo.sail > 0) parts.push(combo.sail + " " + self.resourceIcon("doubloon") + " → " + combo.sail + " " + self.resourceIcon("sail"));
                var label = parts.length > 0 ? parts.join(", ") : _("No substitution");
                var isNone = combo.cb === 0 && combo.sail === 0;
                self.statusBar.addActionButton(label, function () {
                  self.onMerchantSubstituteChosen(combo.cb, combo.sail);
                }, { color: isNone ? "secondary" : "primary" });
              });
              this.statusBar.addActionButton(_("Cancel"), this.onMerchantSubstituteCancel.bind(this), {
                color: "alert",
              });
            }
            break;

          case "client_bootyPurchaseConfirm":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onBootyPurchaseYes.bind(this), {
              });
            this.statusBar.addActionButton(_("No thanks"), this.onBootyPurchaseNo.bind(this), {
              color: "secondary",
            });
            this.statusBar.addActionButton(_("Cancel"), this.onBootyPurchaseCancel.bind(this), {
              color: "alert",
            });
            break;

          case "client_bootyPlayConfirm":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onBootyPlayYes.bind(this), {
              });
            this.statusBar.addActionButton(_("No thanks"), this.onBootyPlayNo.bind(this), {
              color: "secondary",
            });
            this.statusBar.addActionButton(_("Cancel"), this.onBootyPlayCancel.bind(this), {
              color: "alert",
            });
            break;

          case "client_tradingPostBootyChoice":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onTradingPostBootyYes.bind(this), {
              });
            this.statusBar.addActionButton(_("No, trade resources"), this.onTradingPostBootyNo.bind(this), {
              color: "secondary",
            });
            break;

          case "client_tradingPostSpend":
            this.buildTradingPostSpendUI();
            break;

          case "client_tradingPostGain":
            this.buildTradingPostGainUI();
            break;

          case "resolveCollision":
            this.statusBar.addActionButton(
              "<div class='soh_resource soh_pivot_left' role='img' aria-label='" + _("Pivot left") + "' data-pivot='pivot left'></div>",
              this.onPivotButtonClicked.bind(this),
              { color: "secondary", classes: "soh_pivot_button" },
            );
            this.statusBar.addActionButton(
              "<div class='soh_resource soh_nope' role='img' aria-label='" + _("Do not pivot") + "' data-pivot='no pivot'></div>",
              this.onPivotButtonClicked.bind(this),
              { color: "secondary", classes: "soh_pivot_button" },
            );
            this.statusBar.addActionButton(
              "<div class='soh_resource soh_pivot_right' role='img' aria-label='" + _("Pivot right") + "' data-pivot='pivot right'></div>",
              this.onPivotButtonClicked.bind(this),
              { color: "secondary", classes: "soh_pivot_button" },
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
            (extortionArgs2.pending_flags || []).forEach((flag) => {
              this.statusBar.addActionButton(extortionNames[flag] || flag, () => {
                this.onExtortionFlagChosen(flag, extortionArgs2);
              });
            });
            this.statusBar.addActionButton(_("Skip the rest"), () => {
              this.bgaPerformAction("actSkipExtortion", {});
            }, { color: "secondary" });
            break;
          }

          case "client_extortionGreenResource": {
            ["sail", "cannonball", "doubloon"].forEach((resource) => {
              this.statusBar.addActionButton(this.resourceIcon(resource), () => {
                this.bgaPerformAction("actExtortionUseFlag", { flag: "green", resource });
              }, { color: "secondary" });
            });
            this.statusBar.addActionButton(_("Back"), () => { this.restoreServerGameState(); }, {
              color: "secondary",
            });
            break;
          }

          case "barter": {
            var barterArgs = args || {};
            var resources = barterArgs.resources || {};
            var infamy = barterArgs.infamy || 0;
            var rates = [["sail", 1], ["cannonball", 2], ["doubloon", 3]];
            var barterSelf = this;
            rates.forEach(function (pair) {
              var res = pair[0], amount = pair[1];
              if ((resources[res] || 0) > 0) {
                barterSelf.statusBar.addActionButton(
                  "1 " + barterSelf.resourceIcon(res) + " → " + amount + " " + barterSelf.resourceIcon("infamy"),
                  function () { barterSelf.bgaPerformAction("actBarterExchange", { resource: res, direction: "resource_to_infamy" }); },
                  {},
                );
              }
              if (infamy >= amount) {
                barterSelf.statusBar.addActionButton(
                  amount + " " + barterSelf.resourceIcon("infamy") + " → 1 " + barterSelf.resourceIcon(res),
                  function () { barterSelf.bgaPerformAction("actBarterExchange", { resource: res, direction: "infamy_to_resource" }); },
                  {},
                );
              }
            });
            this.statusBar.addActionButton(_("Skip (No Exchange)"), function () {
              barterSelf.bgaPerformAction("actSkipBarter", {});
            }, { color: "secondary" });
            break;
          }

          case "chainShotLoss": {
            for (const resource of args.options) {
              this.statusBar.addActionButton(
                _("Lose 1") + " " + this.resourceIcon(resource),
                () => { this.bgaPerformAction("actChainShotLose", { resource: resource }); },
              );
            }
            break;
          }

          case "timelyTrading": {
            var ttArgs = args || {};
            var ttMarket = ttArgs.market || [];
            var ttResources = ttArgs.resources || {};
            var ttSelf = this;
            this.statusBar.addActionButton(
              _("Gain") + " 2 " + this.resourceIcon("doubloon"),
              function () { ttSelf.bgaPerformAction("actTimelyTradingGainDoubloons", {}); },
              {},
            );
            var ttMarketArr = Array.isArray(ttMarket) ? ttMarket : Object.values(ttMarket);
            ttMarketArr.forEach(function (card) {
              var cardDef = ttSelf.playable_cards ? ttSelf.playable_cards[card.type] : null;
              if (!cardDef) return;
              var cost = cardDef.cost || {};
              var canAfford = ttSelf.canPlayerAfford(cost, false, false);
              var costStr = Object.entries(cost).map(function (e) { return e[1] + " " + ttSelf.resourceIcon(e[0]); }).join(", ");
              var label = _("Buy") + (costStr ? " (" + costStr + ")" : "");
              ttSelf.statusBar.addActionButton(
                label,
                function () {
                  ttSelf.bgaPerformAction("actTimelyTradingPurchaseCard", {
                    card_id: card.id,
                    doubloons_as_cannonballs: 0,
                    doubloons_as_sails: 0,
                  });
                },
                { disabled: !canAfford },
              );
            });
            break;
          }

          case "boardingParty": {
            var bpArgs = args || {};
            var bpTargets = bpArgs.targets || [];
            var bpSelf = this;
            bpTargets.forEach(function (target) {
              var tid = target.player_id;
              var tname = bpSelf.gamedatas.players[tid].name;
              var res = target.resources || {};
              ["sail", "cannonball", "doubloon"].forEach(function (r) {
                if ((res[r] || 0) > 0) {
                  bpSelf.statusBar.addActionButton(
                    _("Steal") + " 1 " + bpSelf.resourceIcon(r) + " " + _("from") + " " + tname,
                    function () { bpSelf.bgaPerformAction("actBoardingPartySteal", { target_player_id: tid, item: r }); },
                    {},
                  );
                }
              });
              if ((target.booty_token_count || 0) > 0) {
                bpSelf.statusBar.addActionButton(
                  _("Steal booty token from") + " " + tname,
                  function () { bpSelf.bgaPerformAction("actBoardingPartySteal", { target_player_id: tid, item: "booty_token" }); },
                  {},
                );
              }
            });
            this.statusBar.addActionButton(_("Skip (No Steal)"), function () {
              bpSelf.bgaPerformAction("actSkipBoardingParty", {});
            }, { color: "secondary" });
            break;
          }

          case "huntTheBounty": {
            var htbArgs = args || {};
            var htbTargets = htbArgs.targets || [];
            var htbSelf = this;
            htbTargets.forEach(function (target) {
              htbSelf.statusBar.addActionButton(
                _("Target") + " " + (target.player_name || target.player_id),
                function () { htbSelf.bgaPerformAction("actHuntTheBountyChooseTarget", { target_player_id: target.player_id }); },
                {},
              );
            });
            this.statusBar.addActionButton(_("Skip (No Target)"), function () {
              htbSelf.bgaPerformAction("actSkipHuntTheBounty", {});
            }, { color: "secondary" });
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
        b.classList.toggle("bgabutton_blue", b === button);
        b.classList.toggle("bgabutton_gray", b !== button);
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
        color: "secondary",
      });
    },
  };
});
