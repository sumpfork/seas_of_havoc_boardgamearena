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

        case "extortion":
          this.cleanupScrapCardSelection();
          break;

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
                if (combo.cb > 0) parts.push(combo.cb + (combo.cb === 1 ? " doubloon → cannonball" : " doubloons → cannonballs"));
                if (combo.sail > 0) parts.push(combo.sail + (combo.sail === 1 ? " doubloon → sail" : " doubloons → sails"));
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

          case "client_resourceDialog":
            this.statusBar.addActionButton(
              _("<div class='resource sail' data-resource='sail'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              _("<div class='resource cannonball' data-resource='cannonball'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              _("<div class='resource doubloon' data-resource='doubloon'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            break;

          case "resolveCollision":
            this.statusBar.addActionButton(
              "<div class='resource pivot_left' data-pivot='pivot left'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              "<div class='resource nope' data-pivot='no pivot'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              "<div class='resource pivot_right' data-pivot='pivot right'></div>",
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
            if (extortionArgs2.pending_red && !extortionArgs2.pending_green) {
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
                  "1 " + _(res) + " → " + amount + " " + _("infamy"),
                  function () { barterSelf.bgaPerformAction("actBarterExchange", { resource: res, direction: "resource_to_infamy" }); },
                  { classes: "bgabutton_green" },
                );
              }
              if (infamy >= amount) {
                barterSelf.statusBar.addActionButton(
                  amount + " " + _("infamy") + " → 1 " + _(res),
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
              _("Gain 2 Doubloons"),
              function () { ttSelf.bgaPerformAction("actTimelyTradingGainDoubloons", {}); },
              { classes: "bgabutton_green" },
            );
            var ttMarketArr = Array.isArray(ttMarket) ? ttMarket : Object.values(ttMarket);
            ttMarketArr.forEach(function (card) {
              var cardDef = ttSelf.playable_cards ? ttSelf.playable_cards[card.type] : null;
              if (!cardDef) return;
              var cost = cardDef.cost || {};
              var canAfford = ttSelf.canPlayerAfford(cost, false, false);
              var costStr = Object.entries(cost).map(function (e) { return e[1] + " " + e[0]; }).join(", ");
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
