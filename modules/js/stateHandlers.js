/**
 * Seas of Havoc - State Handlers Module
 * Game state enter/leave/update action button handlers
 */

define([
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/query",
], function(domClass, domConstruct, query) {
  
  return {
    /**
     * Called when entering a new game state
     */
    onEnteringState: function(stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
        case "dummyStart":
          // this.bgaPerformAction("actExitDummyStart", {});
          break;
          
        case "cardPurchases": {
          this.cards_purchased = [];
          this.updateCardPurchaseButtons(true);
          break;
        }
        
        case "cardPurchasesPrivate": {
          this.cards_purchased = [];
          this.updateCardPurchaseButtons(true);
          break;
        }
        
        case "cardPurchasesCompleted": {
          this.updateCardPurchaseButtons(false);
          break;
        }
        
        case "seaPhaseSetup": {
          // Clear all skiffs when entering sea phase
          query(".skiff_placed").forEach(domConstruct.destroy);
          query(".purchase_card_button").forEach(domConstruct.destroy);
          query(".skiff_slot").forEach(function(slot) {
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
          this.setupScrapCardSelection(args.args);
          break;
        }
        
        case "dummmy":
          break;
      }
    },

    /**
     * Called when leaving a game state
     */
    onLeavingState: function(stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        case "scrapCard":
          this.cleanupScrapCardSelection();
          break;

        case "dummmy":
          break;
      }
    },

    /**
     * Update action buttons in the status bar
     */
    onUpdateActionButtons: function(stateName, args) {
      console.log("onUpdateActionButtons: " + stateName);
      console.log("isCurrentPlayerActive(): " + this.isCurrentPlayerActive());
      console.log("args:", args);

      if (this.isCurrentPlayerActive()) {
        switch (stateName) {
          case "cardPurchases":
          case "cardPurchasesPrivate":
          case "cardPurchasesMaking":
            console.log("Adding Complete Purchases button for state: " + stateName);
            this.statusBar.addActionButton(_("Complete Purchases"), this.onCompletePurchasesClicked.bind(this));
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
        }
      }
    },

    /**
     * Handle resource button click in dialog
     */
    onResourceButtonClicked: function(event) {
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
    onPivotButtonClicked: function(event) {
      const source = event.target || event.srcElement;
      console.log("pivot button clicked");
      console.log(source);
      console.log("pivot picked " + source.dataset.pivot);
      this.bgaPerformAction("actPivotPickedInDialog", {
        direction: source.dataset.pivot,
      });
      this.statusBar.removeActionButtons();
      event.preventDefault();
    }
  };
});









