/**
 * Seas of Havoc - Dialogs Module
 * Dialog creation and card play dialog logic
 */

define([
  "dojo/dom",
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/dom-style",
  "dojo/_base/lang",
  "dojo/on",
  "dojo/query",
  "dojo/dom-attr",
  getLibUrl('bga-cards', '1.x'),
], function(dom, domClass, domConstruct, domStyle, lang, on, query, attr, BgaCards) {
  
  return {
    /**
     * Show dummy dialog (for BGA logging requirement)
     */
    showDummyDialog: function() {
      this.myDlg = new ebg.popindialog();
      this.myDlg.create("dummyDialog");
      this.myDlg.setTitle(_("Yep, this is dumb"));
      this.myDlg.setMaxWidth(500);

      var html = this.format_block("jstpl_dummy_dialog");

      this.myDlg.setContent(html);
      this.myDlg.hideCloseIcon();
      this.myDlg.show();

      this.setClientState("client_dummyDialog", {
        descriptionmyturn: _("${you} must abide by BGA's dumb logging"),
      });

      on(
        query(".dummy_button"),
        "click",
        lang.hitch(this, (event) => {
          console.log("dummy button clicked");
          event.preventDefault();
          this.bgaPerformAction("actExitDummyStart", {});
          this.myDlg.destroy();
        }),
      );
    },

    /**
     * Clean up card play dialog
     */
    cleanupCardPlayDialog: function() {
      if (this.cardDisplayStock) {
        try {
          this.cardDisplayStock.removeAll();
        } catch (e) {
          console.log("Error removing cards from cardDisplayStock:", e);
        }
        this.cardDisplayStock = null;
      }
      this.dep_tree = null;
      domConstruct.destroy("card_display_dialog");
    },

    /**
     * Show card play dialog with choices
     */
    showCardPlayDialog: function(card, card_id) {
      var bga = this;
      
      // Clean up previous dialog properly
      this.cleanupCardPlayDialog();
      
      var dlg = this.format_block("jstpl_card_play_dialog");
      domConstruct.place(dlg, "myhand_wrap", "first");
      
      var makeDecisionSummary = function(tree, decisionSummary) {
        if (typeof decisionSummary === "undefined") {
          decisionSummary = [];
        }
        console.log("making decision summary " + decisionSummary);
        tree.forEach((options) => {
          for (let i = 0; i < options.length; i++) {
            let option = options[i];
            console.log(option);
            var checkbox = dom.byId(option.id);
            console.log("checked: " + checkbox.checked);
            if (checkbox.checked) {
              decisionSummary.push(option.name);
              makeDecisionSummary(option.children, decisionSummary);
            }
          }
        });
        return decisionSummary;
      };

      on(
        query(".play_card_button"),
        "click",
        lang.hitch(this, (event) => {
          console.groupCollapsed("card play button clicked");
          console.log("play card button clicked");
          event.preventDefault();
          var decisionSummary = makeDecisionSummary(this.dep_tree);
          console.log("decisions");
          console.log(decisionSummary);
          console.log("card ");
          console.log(card);
          this.bgaPerformAction("actPlayCard", {
            card_type: card.card_type,
            card_id: card_id,
            decisions: JSON.stringify(decisionSummary),
          });
          this.cleanupCardPlayDialog();
          console.log("moving card with type: " + card.card_type + " id :" + card_id);
          this.playerDiscard.addCard({id: card_id, type: card.card_type, location: "discard", fromStock: this.playerHand});
          this.playerHand.removeCard({ id: card_id, type: card.card_type });
          console.groupEnd();
        }),
      );
      
      on(
        query(".pass_card_button"),
        "click",
        lang.hitch(this, (event) => {
          console.groupCollapsed("pass card button clicked");
          console.log("pass card button clicked");
          event.preventDefault();
          console.log("card ");
          console.log(card);
          this.bgaPerformAction("actPlayCard", {
            card_type: card.card_type,
            card_id: card_id,
            decisions: JSON.stringify(["pass"]),
          });
          this.cleanupCardPlayDialog();
          console.log("moving card with type: " + card.card_type + " id :" + card_id);
          this.playerDiscard.addCard({id: card_id, type: card.card_type, location: "discard", fromStock: this.playerHand});
          this.playerHand.removeCard({ id: card_id, type: card.card_type });
          console.groupEnd();
        }),
      );
      
      var display_dom = query("#card_display");
      console.log("display dom:");
      console.log(display_dom);
      this.cardDisplayStock = new BgaCards.LineStock(this.cardsManager, display_dom[0], { center: false });
      this.cardDisplayStock.addCard({ id: 10000, type: card.card_type });

      console.log(card);
      
      // Build card dependency tree
      this.dep_tree = this._makeCardDependencyTree(card.actions);
      console.log(this.dep_tree);

      // Render choice rows
      console.groupCollapsed("render play rows");
      var result = this._renderCardChoiceRows(this.dep_tree);
      console.log(result);
      console.groupEnd();

      if (result.length > 0) {
        var choices_html = result.join("\n");
        domConstruct.place(choices_html, "card_choices");
        query(".card_choice_radio").connect("onchange", this, (event) => {
          console.groupCollapsed("show/hide play controls");
          this._showHideCardPlayControls(this.dep_tree);
          console.groupEnd();
          this._updatePlayCardButton();
        });
      }
      
      console.groupCollapsed("show/hide play controls");
      this._showHideCardPlayControls(this.dep_tree);
      console.groupEnd();
      this._updatePlayCardButton();
      this.cardPlayDialogShown = true;
    },

    /**
     * Build dependency tree from card actions
     * @private
     */
    _makeCardDependencyTree: function(actions, choice_count) {
      var bga = this;
      var tree = new Map();
      if (typeof choice_count === "undefined") {
        choice_count = 0;
      }
      
      for (const action of actions) {
        console.log("tree considering action:");
        console.log(action);
        var option_count = 0;
        var num_descendant_choices = 0;
        
        switch (action.action) {
          case "choice":
            var tree_choices = [];
            for (const option of action.choices) {
              var choice_name = option.name || option.action;
              var id = "card_choice_" + choice_count + "_option_" + option_count;
              var children = this._makeCardDependencyTree([option], choice_count + num_descendant_choices + 1);
              var entry = {
                name: choice_name,
                id: id,
                children: children,
              };
              if (typeof option.cost !== "undefined") {
                entry.cost = option.cost;
              }
              if (typeof action.cost !== "undefined") {
                if (typeof entry.cost !== "undefined") {
                  console.warn("overwriting cost for " + choice_name);
                }
                entry.cost = action.cost;
              }
              tree_choices.push(entry);
              console.log("choice added: " + choice_name + " " + id);
              num_descendant_choices += children.size;
              option_count++;
            }
            if (typeof action.cost !== "undefined") {
              tree_choices.push({
                name: "skip",
                id: "card_choice_" + choice_count + "_option_" + option_count,
                children: new Map(),
              });
              option_count++;
            }
            tree.set("choice_" + choice_count, tree_choices);
            choice_count++;
            choice_count += num_descendant_choices;
            break;
            
          case "sequence":
            var children = this._makeCardDependencyTree(action.actions, choice_count);
            children.forEach((value, key) => {
              tree.set(key, value);
            });
            break;
            
          default: {
            let choice_names = [];
            let choice_name = action.name || action.action;
            if (choice_name == "fire" || choice_name == "2 x fire" || choice_name == "3 x fire") {
              choice_names.push(choice_name + " left");
              choice_names.push(choice_name + " right");
            } else {
              choice_names.push(choice_name);
            }
            if (typeof action.cost !== "undefined") {
              choice_names.push("skip");
            }
            if (choice_names.length > 1) {
              var tree_choices = [];
              let parent_cost = action.cost;
              delete action.cost;
              for (let i = 0; i < choice_names.length; i++) {
                var to_push = {
                  name: choice_names[i],
                  id: "card_choice_" + choice_count + "_option_" + i,
                  children: new Map(),
                };
                if (choice_names[i] != "skip") {
                  to_push["cost"] = parent_cost;
                }
                tree_choices.push(to_push);
              }
              tree.set("choice_" + choice_count, tree_choices);
              choice_count++;
              num_descendant_choices += 1;
            }
          }
        }
      }
      
      console.log("returning tree");
      console.log(tree);
      return tree;
    },

    /**
     * Render card choice rows HTML
     * @private
     */
    _renderCardChoiceRows: function(tree, row_number) {
      var bga = this;
      var rendered_choices = [];
      row_number = row_number || 1;
      
      tree.forEach((options, choice_id) => {
        var rendered_options = [];
        console.log(options);
        for (var option of options) {
          console.log("rendering option " + option.name);
          rendered_options.push(
            bga.format_block("jstpl_card_choice_radio", {
              id: option.id,
              name: choice_id,
              value: option.name,
              label: option.name,
            }),
          );
          rendered_choices = rendered_choices.concat(this._renderCardChoiceRows(option.children, row_number + 1));
        }
        var choice_html = bga.format_block("jstpl_card_choices_row", {
          row_number: row_number + ".",
          card_choices: rendered_options.join("\n"),
        });
        rendered_choices.unshift(choice_html);
        row_number++;
      });
      
      return rendered_choices;
    },

    /**
     * Compute total play cost from selected choices
     * @private
     */
    _computeTotalPlayCost: function(tree, costAcc) {
      var bga = this;
      if (typeof costAcc === "undefined") {
        costAcc = {};
      }
      console.log("computing total play cost " + costAcc);
      
      tree.forEach((options) => {
        for (var option of options) {
          console.log(option);
          var checkbox = dom.byId(option.id);
          console.log("checked: " + checkbox.checked);
          console.log(option.cost);
          console.log(costAcc);
          if (checkbox.checked && typeof option.cost !== "undefined") {
            costAcc = bga.addResources(option.cost, costAcc);
          }
          costAcc = this._computeTotalPlayCost(option.children, costAcc);
        }
      });
      
      return costAcc;
    },

    /**
     * Show/hide controls based on current selections
     * @private
     */
    _showHideCardPlayControls: function(tree, hide, totalCost) {
      var bga = this;
      console.log("showing/hiding controls " + hide);
      
      if (typeof totalCost === "undefined") {
        console.groupCollapsed("compute total play cost");
        totalCost = this._computeTotalPlayCost(tree);
        console.groupEnd();
        console.log("total play cost is:");
        console.log(totalCost);
      }
      
      tree.forEach((options) => {
        for (var option of options) {
          var checkbox = dom.byId(option.id);
          console.log(option);
          console.log(checkbox);
          console.log(checkbox.checked);
          if (hide) {
            checkbox.checked = false;
            domStyle.set(checkbox.parentNode.parentNode, "display", "none");
            this._showHideCardPlayControls(option.children, true, totalCost);
          } else {
            domStyle.set(checkbox.parentNode.parentNode, "display", "inline-block");
            if (typeof option.cost !== "undefined" && option.cost) {
              console.log("option cost:");
              console.log(option.cost);
              console.log("totalCost:");
              console.log(totalCost);
              var adjustedCost = bga.addResources(option.cost, totalCost);
              console.log("adjusted cost:");
              console.log(adjustedCost);
              if (bga.canPlayerAfford(adjustedCost)) {
                attr.remove(checkbox, "disabled");
              } else {
                attr.set(checkbox, "disabled", "true");
              }
            }
            this._showHideCardPlayControls(option.children, !checkbox.checked, totalCost);
          }
        }
      });
    },

    /**
     * Check if card is ready to be played (all choices made)
     * @private
     */
    _checkIsCardReadyToBePlayed: function(tree) {
      var isReady = true;
      if (tree.length == 0) {
        return true;
      }
      console.log("starting ready to play check");
      
      tree.forEach((options) => {
        if (!isReady) {
          return;
        }
        var anythingChecked = false;
        for (var option of options) {
          var checkbox = dom.byId(option.id);
          console.log("starting to check option:");
          console.log(option);
          console.log("parent display: " + domStyle.get(checkbox.parentNode.parentNode, "display"));
          if (domStyle.get(checkbox.parentNode.parentNode, "display") != "none") {
            console.log("checking children:");
            console.log(option.children);
            if (!this._checkIsCardReadyToBePlayed(option.children)) {
              console.log("nothing checked in children");
              isReady = false;
              return;
            }
            anythingChecked |= checkbox.checked;
            console.log("checkbox is checked: " + checkbox.checked);
            console.log("anything checked now: " + anythingChecked);
          } else {
            console.log("skipping because option is hidden:");
            console.log(option);
            return;
          }
          console.log("anything checked at end of loop " + anythingChecked);
        }
        console.log("after options anything checked: " + anythingChecked);
        isReady &= anythingChecked;
        console.log("updated isReady to " + isReady);
      });
      
      console.log("final ready to play: " + isReady);
      return isReady;
    },

    /**
     * Update play card button enabled state
     * @private
     */
    _updatePlayCardButton: function() {
      const button_id = "play_card_button";
      console.groupCollapsed("check whether card is ready to be played");
      let ready = this._checkIsCardReadyToBePlayed(this.dep_tree);
      console.groupEnd();
      if (ready) {
        domClass.add(button_id, "bgabutton_green");
        domClass.remove(button_id, "disabled");
      } else {
        domClass.remove(button_id, "bgabutton_green");
        domClass.add(button_id, "disabled");
      }
    },

    /**
     * Set up scrap card selection dialog
     */
    setupScrapCardSelection: function(args) {
      console.log("Setting up scrap card selection");
      console.log(args);
      
      var scrapDialog = this.format_block('jstpl_scrap_card_dialog', {});
      document.body.insertAdjacentHTML('beforeend', scrapDialog);
      
      this.scrapCardSelection = new BgaCards.ScrollableStock(
        this.cardsManager, 
        $("scrap_card_selection_wrapper"), 
        {
          gap: '8px',
          center: true,
          scrollStep: 150,
          leftButton: { html: '◀' },
          rightButton: { html: '▶' }
        }
      );
      
      this.scrapCardSelection.setSelectionMode("single");
      
      if (args.available_cards) {
        for (var i in args.available_cards) {
          var card = args.available_cards[i];
          this.scrapCardSelection.addCard({
            id: card.id,
            type: card.type,
            location: card.location
          });
        }
      }
      
      this.scrapCardSelection.onSelectionChange = (selection, lastChange) => {
        if (selection.length > 0) {
          var selectedCard = selection[0];
          console.log("Card selected for scrapping:", selectedCard);
          
          if (!$("confirm_scrap_button")) {
            domConstruct.create("button", {
              id: "confirm_scrap_button",
              class: "bgabutton bgabutton_red",
              innerHTML: "Scrap Card"
            }, $("cancel_scrap_button"), "before");
            
            on($("confirm_scrap_button"), "click", () => {
              this.confirmScrapCard(selectedCard.id);
            });
          }
        } else {
          if ($("confirm_scrap_button")) {
            domConstruct.destroy("confirm_scrap_button");
          }
        }
      };
      
      on($("cancel_scrap_button"), "click", () => {
        this.cleanupScrapCardSelection();
      });
    },

    /**
     * Clean up scrap card selection dialog
     */
    cleanupScrapCardSelection: function() {
      console.log("Cleaning up scrap card selection");
      
      if (this.scrapCardSelection) {
        this.scrapCardSelection = null;
      }
      
      if ($("scrap_card_dialog")) {
        domConstruct.destroy("scrap_card_dialog");
      }
    },

    /**
     * Confirm scrap card action
     */
    confirmScrapCard: function(cardId) {
      console.log("Confirming scrap of card:", cardId);
      
      if (this.checkAction("actScrapCard")) {
        this.bgaPerformAction("actScrapCard", {
          card_id: cardId
        });
      }
    }
  };
});









