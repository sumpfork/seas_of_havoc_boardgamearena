/**
 * Seas of Havoc - State Handlers Module
 * Game state enter/leave/update action button handlers
 */

define(["dojo/dom-class", "dojo/dom-construct", "dojo/query"], function (domClass, domConstruct, query) {
  return {
    /**
     * Called when entering a new game state
     */
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);
      this.updateHandSelectionMode();

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
          query(".purchase_card_button").forEach(domConstruct.destroy);
          query(".skiff_slot").forEach(function (slot) {
            domClass.add(slot, "unoccupied");
            query(".skiff", slot).forEach(domConstruct.destroy);
          });
          break;
        }

        case "seaTurn": {
          query(".skiff_placed").forEach(domConstruct.destroy);
          query(".purchase_card_button").forEach(domConstruct.destroy);
          break;
        }

        case "captainCard": {
          if (this.isCurrentPlayerActive()) this.setupCaptainCardSelection(args.args);
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

        case "rebelDiscard": {
          if (this.isCurrentPlayerActive()) {
            this.setupDiscardCardSelection(args.args);
          }
          break;
        }

        case "treasureSeekerAdjust": {
          this.setupTreasureSeekerAdjust(args.args);
          break;
        }

        case "rallyTheFlagsChooseFlag": {
          break;
        }

        case "extortion": {
          if (this.isCurrentPlayerActive()) {
            var extortionArgs = args.args || {};
            if (extortionArgs.pending_red && !extortionArgs.pending_green) {
              this.setupScrapCardSelection(extortionArgs);
            }
          }
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
     * Called when leaving a game state
     */
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        case "client_tradingPostBootyChoice":
        case "client_tradingPostSpend":
        case "client_tradingPostGain":
          this.cleanupTradingPostUi();
          break;

        case "client_workshopChooseUpgrade":
          this.clientStateVars.workshop_upgrades = null;
          this.clientStateVars.workshop_slot_number = null;
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
          this.cleanupDiscardCardSelection();
          break;

        case "treasureSeekerAdjust":
          this.cleanupTreasureSeekerAdjust();
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
          case "cardFlag":
            if (args.flag === "green") {
              ["sail", "cannonball", "doubloon"].forEach(resource => {
                this.statusBar.addActionButton(this.resourceIcon(resource), () => {
                  this.bgaPerformAction("actResolveCardFlag", { resource });
                }, { classes: "bgabutton_resource" });
              });
            } else if (args.flag === "tan" || args.flag === "blue") {
              this.statusBar.addActionButton(args.flag === "tan" ? _("Draw a card") : _("Take another turn"), () => {
                this.bgaPerformAction("actResolveCardFlag", {});
              });
            }
            this.statusBar.addActionButton(_("Skip flag action"), () => {
              this.bgaPerformAction("actSkipCardFlag", {});
            }, { classes: "bgabutton_gray" });
            break;

          case "cardPurchases":
          case "cardPurchasesPrivate":
          case "cardPurchasesMaking":
            console.log("Adding Complete Purchases button for state: " + stateName);
            this.statusBar.addActionButton(_("Complete Purchases"), this.onCompletePurchasesClicked.bind(this));
            this.statusBar.addActionButton(_("Restart Purchases"), this.onRestartPurchasesClicked.bind(this), {
              classes: "bgabutton_gray",
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
                }, { classes: isNone ? "bgabutton_gray" : "bgabutton_green" });
              });
              this.statusBar.addActionButton(_("Cancel"), this.onMerchantSubstituteCancel.bind(this), {
                classes: "bgabutton_red",
              });
            }
            break;

          case "client_bootyPurchaseConfirm":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onBootyPurchaseYes.bind(this), {
              classes: "bgabutton_green",
            });
            this.statusBar.addActionButton(_("No thanks"), this.onBootyPurchaseNo.bind(this), {
              classes: "bgabutton_gray",
            });
            this.statusBar.addActionButton(_("Cancel"), this.onBootyPurchaseCancel.bind(this), {
              classes: "bgabutton_red",
            });
            break;

          case "client_bootyPlayConfirm":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onBootyPlayYes.bind(this), {
              classes: "bgabutton_green",
            });
            this.statusBar.addActionButton(_("No thanks"), this.onBootyPlayNo.bind(this), {
              classes: "bgabutton_gray",
            });
            this.statusBar.addActionButton(_("Cancel"), this.onBootyPlayCancel.bind(this), {
              classes: "bgabutton_red",
            });
            break;

          case "client_tradingPostBootyChoice":
            this.statusBar.addActionButton(_("Yes, use booty token"), this.onTradingPostBootyYes.bind(this), {
              classes: "bgabutton_green",
            });
            this.statusBar.addActionButton(_("No, trade resources"), this.onTradingPostBootyNo.bind(this), {
              classes: "bgabutton_gray",
            });
            break;

          case "client_tradingPostSpend":
            this.buildTradingPostSpendUI();
            break;

          case "client_tradingPostGain":
            this.buildTradingPostGainUI();
            break;

          case "client_workshopChooseUpgrade": {
            var upgrades = this.clientStateVars.workshop_upgrades || [];
            var self2 = this;
            upgrades.forEach(function (upgrade) {
              var costParts = Object.entries(upgrade.cost || {}).map(function ([resource, amount]) {
                return amount + " " + self2.resourceIcon(resource);
              });
              var label = (upgrade.upgrade_key + " (" + costParts.join(" ") + ")");
              var affordable = self2.canPlayerAfford(upgrade.cost, false, false);
              self2.statusBar.addActionButton(label, function () {
                if (!affordable) return;
                self2.bgaPerformAction("actActivateShipUpgrade", { upgrade_key: upgrade.upgrade_key });
              }, { classes: affordable ? "bgabutton_green" : "bgabutton_gray disabled" });
            });
            break;
          }

          case "client_resourceDialog":
            this.statusBar.addActionButton(
              this.resourceIcon("sail"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              this.resourceIcon("cannonball"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              this.resourceIcon("doubloon"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            break;

          case "resolveCollision":
            this.statusBar.addActionButton(
              "<div class='resource pivot_left' role='img' aria-label='Pivot left' data-pivot='pivot left'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              "<div class='resource nope' role='img' aria-label='Do not pivot' data-pivot='no pivot'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              "<div class='resource pivot_right' role='img' aria-label='Pivot right' data-pivot='pivot right'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            break;

          case "rallyTheFlagsChooseFlag": {
            var flagNames = { green_flag: _("Green Flag"), tan_flag: _("Tan Flag"), blue_flag: _("Blue Flag"), red_flag: _("Red Flag") };
            var availableFlags = (args.available_flags || []);
            var self = this;
            availableFlags.forEach(function (flagKey) {
              self.statusBar.addActionButton(
                _("Take") + " " + (flagNames[flagKey] || flagKey),
                function () { self.bgaPerformAction("actRallyTheFlagsChooseFlag", { flag_key: flagKey }); },
                { classes: "bgabutton_green" },
              );
            });
            break;
          }

          case "extortion": {
            var extortionArgs2 = args || {};
            if (extortionArgs2.pending_green) {
              ["sail", "cannonball", "doubloon"].forEach(resource => {
                this.statusBar.addActionButton(this.resourceIcon(resource), () => {
                  this.bgaPerformAction("actResourcePickedInDialog", {
                    resource, context: "extortion_green_flag", number: "0",
                  });
                }, { classes: "bgabutton_resource" });
              });
            } else if (extortionArgs2.pending_red) {
              this.statusBar.addActionButton(_("Scrap a Card (Red Flag)"), function () {}, {
                classes: "bgabutton_gray disabled",
              });
            }
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
                  { classes: "bgabutton_green" },
                );
              }
              if (infamy >= amount) {
                barterSelf.statusBar.addActionButton(
                  amount + " " + barterSelf.resourceIcon("infamy") + " → 1 " + barterSelf.resourceIcon(res),
                  function () { barterSelf.bgaPerformAction("actBarterExchange", { resource: res, direction: "infamy_to_resource" }); },
                  { classes: "bgabutton_green" },
                );
              }
            });
            this.statusBar.addActionButton(_("Skip (No Exchange)"), function () {
              barterSelf.bgaPerformAction("actSkipBarter", {});
            }, { classes: "bgabutton_gray" });
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
              { classes: "bgabutton_green" },
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
                { classes: canAfford ? "bgabutton_green" : "bgabutton_gray" },
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
                    { classes: "bgabutton_green" },
                  );
                }
              });
              if ((target.booty_token_count || 0) > 0) {
                bpSelf.statusBar.addActionButton(
                  _("Steal booty token from") + " " + tname,
                  function () { bpSelf.bgaPerformAction("actBoardingPartySteal", { target_player_id: tid, item: "booty_token" }); },
                  { classes: "bgabutton_green" },
                );
              }
            });
            this.statusBar.addActionButton(_("Skip (No Steal)"), function () {
              bpSelf.bgaPerformAction("actSkipBoardingParty", {});
            }, { classes: "bgabutton_gray" });
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
                { classes: "bgabutton_green" },
              );
            });
            this.statusBar.addActionButton(_("Skip (No Target)"), function () {
              htbSelf.bgaPerformAction("actSkipHuntTheBounty", {});
            }, { classes: "bgabutton_gray" });
            break;
          }

          case "treasureSeekerAdjust":
            this.statusBar.addActionButton(_("Keep current location"), this.onSkipTreasureSeekerAdjust.bind(this), {
              classes: "bgabutton_gray",
            });
            break;

          case "islandTurn":
            this.refreshSkiffSlotPlaceability();
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
            break;
        }
      }
    },

    /**
     * Handle resource button click in dialog
     */
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

      if (direction != null) {
        this.bgaPerformAction("actPivotPickedInDialog", {
          direction: direction,
        });
        this.statusBar.removeActionButtons();
      }
    },
  };
});
