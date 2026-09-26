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
  getLibUrl("bga-cards", "1.x"),
], function (dom, domClass, domConstruct, domStyle, lang, on, query, attr, BgaCards) {
  return {
    /**
     * Clean up card play dialog
     */
    cleanupCardPlayDialog: function () {
      if (this._closeCardPlayDialogOnOutsideClick) {
        document.removeEventListener("click", this._closeCardPlayDialogOnOutsideClick);
        this._closeCardPlayDialogOnOutsideClick = null;
      }
      if (this.cardDisplayStock) {
        try {
          this.cardDisplayStock.removeAll();
        } catch (e) {
          console.log("Error removing cards from cardDisplayStock:", e);
        }
        this.cardDisplayStock = null;
      }
      this.dep_tree = null;
      this._captainCopyId = null;
      domConstruct.destroy("card_display_dialog");
    },

    /**
     * Show card play dialog with choices
     */
    showCardPlayDialog: function (card, card_id, captainCopyId = null) {
      var bga = this;

      // Clean up previous dialog properly
      this.cleanupCardPlayDialog();
      this._captainCopyId = captainCopyId;

      var dlg = this.format_block("jstpl_card_play_dialog");
      // On the body, like the scrap dialog: the game area can be zoomed/transformed, which would
      // otherwise turn the panel's fixed positioning into positioning against that transform.
      domConstruct.place(dlg, document.body);

      // Clicking away drops the card back: nothing has been sent to the server yet. The hand is
      // excluded so picking a different card just swaps dialogs, and the action buttons because the
      // booty prompt is answered from the status bar while this dialog is still up.
      this._closeCardPlayDialogOnOutsideClick = (event) => {
        if (event.target.closest("#card_display_dialog, #myhand, #captain_card_choices, .card-zoom-dialog, .bgabutton")) {
          return;
        }
        if (this._pendingBootySend) {
          return;
        }
        this.cleanupCardPlayDialog();
        this.playerHand.unselectAll();
      };
      document.addEventListener("click", this._closeCardPlayDialogOnOutsideClick);

      var makeDecisionSummary = function (tree, decisionSummary) {
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
          event.preventDefault();
          var decisionSummary = makeDecisionSummary(this.dep_tree);
          var totalCost = this._computeTotalPlayCost(this.dep_tree);
          var captainCopyId = this._captainCopyId;

          this._sendWithOptionalBooty(
            totalCost,
            (useBooty) => this._sendPlayCard(card, card_id, decisionSummary, useBooty, captainCopyId),
            _("Use your booty token to help pay for this card?"),
          );
          console.groupEnd();
        }),
      );

      on(
        query(".pass_card_button"),
        "click",
        lang.hitch(this, (event) => {
          console.groupCollapsed("pass card button clicked");
          if (this._captainCopyId !== null) {
            this.cleanupCardPlayDialog();
            return;
          }
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
          this.playerDiscard.addCard({
            id: card_id,
            type: card.card_type,
            location: "discard",
            fromStock: this.playerHand,
          });
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
      var hasPassOption = this._hoistCardPassOption(card.actions, this.dep_tree);
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
      if (captainCopyId !== null) {
        query(".pass_card_button").forEach(node => { node.textContent = _("Cancel copy"); });
      } else if (hasPassOption) {
        // Passing is already offered as an option on the first row - two buttons for one outcome.
        query(".pass_card_button").forEach(node => { domStyle.set(node, "display", "none"); });
      }
      this.cardPlayDialogShown = true;
    },

    /**
     * Send the actPlayCard action to the server and update local UI.
     */
    _sendPlayCard: function (card, card_id, decisions, useBooty, captainCopyId = this._captainCopyId) {
      if (captainCopyId != null) {
        const params = { choices: JSON.stringify({ card_id: captainCopyId }), decisions: JSON.stringify(decisions) };
        if (useBooty && this.booty_tokens.length > 0) {
          params.use_booty_card_id = this.getMyBootyTokenId(this._computeTotalPlayCost(this.dep_tree));
        }
        this.bgaPerformAction("actResolveCaptainCard", params);
        this.cleanupCardPlayDialog();
        return;
      }
      var params = {
        card_type: card.card_type,
        card_id: card_id,
        decisions: JSON.stringify(decisions),
      };
      if (useBooty && this.booty_tokens && this.booty_tokens.length > 0) {
        var totalCost = this._computeTotalPlayCost(this.dep_tree);
        params.use_booty_card_id = this.getMyBootyTokenId(totalCost);
        var tokenRes = this.getMyBootyTokenRes(totalCost);
        if (tokenRes && totalCost) {
          var bootyResolved = this.resolveBootyResources(tokenRes, totalCost);
          var effectiveCost = this.computeEffectiveCost(totalCost, bootyResolved);
          var msg = this.formatBootyUsageMessage(bootyResolved, totalCost);
          if (msg) this.showMessage(msg, "info");
          this.playerSpendResources(effectiveCost);
        }
        this.consumeBootyToken(params.use_booty_card_id);
      }
      this.bgaPerformAction("actPlayCard", params);
      this.cleanupCardPlayDialog();
      this.playerDiscard.addCard({
        id: card_id,
        type: card.card_type,
        location: "discard",
        fromStock: this.playerHand,
      });
      this.playerHand.removeCard({ id: card_id, type: card.card_type });
    },

    /**
     * Called from onUpdateActionButtons for client_bootyPlayConfirm state.
     */
    /**
     * Spend a booty token on a cost when it helps: pay with it outright if the player cannot afford
     * the cost otherwise, ask when either way works, and stay out of the way when it does not apply.
     * `send(useBooty)` performs the action.
     */
    _sendWithOptionalBooty: function (cost, send, question) {
      var tokenRes = this.getMyBootyTokenRes(cost);
      if (!tokenRes || !this.bootyOverlapsCost(tokenRes, cost)) {
        send(false);
        return;
      }
      if (!this.canPlayerAfford(cost, false, false)) {
        send(true);
        return;
      }
      this._pendingBootySend = send;
      this.setClientState("client_bootyPlayConfirm", { descriptionmyturn: question });
    },

    _resolveBootyChoice: function (useBooty) {
      var send = this._pendingBootySend;
      this._pendingBootySend = null;
      this.restoreServerGameState();
      if (send) {
        send(useBooty);
      }
    },

    onBootyPlayYes: function () {
      this._resolveBootyChoice(true);
    },

    onBootyPlayNo: function () {
      this._resolveBootyChoice(false);
    },

    onBootyPlayCancel: function () {
      this._pendingBootySend = null;
      this.restoreServerGameState();
    },

    /**
     * A collision ends the maneuver, but the cannon at the next ship outline may still be fired.
     */
    fireAfterCollision: function (shot) {
      this._sendWithOptionalBooty(
        shot.cost || {},
        (useBooty) => {
          var params = { decision: shot.name };
          if (useBooty) {
            params.use_booty_card_id = this.getMyBootyTokenId(shot.cost);
          }
          this.bgaPerformAction("actPostCollisionFire", params);
        },
        _("Use your booty token to help pay for this shot?"),
      );
    },

    /**
     * Build dependency tree from card actions
     * @private
     */
    _makeCardDependencyTree: function (actions, choice_count) {
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
              // Only carry the option's own cost when it has no children: a fire option's cost is
              // re-declared on the shot rows it generates, and counting it here too doubles it.
              if (typeof option.cost !== "undefined" && children.size === 0) {
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
            let choice_costs = [];
            let choice_ranges = [];
            let choice_name = action.name || action.action;
            if (action.variants) {
              // Ship upgrades turn a fire action into a list of shot types, each with its own
              // range, cost and firing sides. Names must match ShipUpgrades::parseFireDecision.
              for (const variant of action.variants) {
                for (const side of variant.sides) {
                  choice_names.push(variant.name + " " + side);
                  choice_costs.push(variant.cost);
                  choice_ranges.push(variant.range);
                }
              }
            } else if (choice_name == "fire" || choice_name == "2 x fire" || choice_name == "3 x fire") {
              choice_names.push(choice_name + " left", choice_name + " right");
              choice_costs.push(action.cost, action.cost);
              choice_ranges.push(action.range, action.range);
            } else {
              choice_names.push(choice_name);
              choice_costs.push(action.cost);
              choice_ranges.push(action.range);
            }
            if (typeof action.cost !== "undefined") {
              choice_names.push("skip");
              choice_costs.push(undefined);
              choice_ranges.push(undefined);
            }
            if (choice_names.length > 1) {
              var tree_choices = [];
              for (let i = 0; i < choice_names.length; i++) {
                var to_push = {
                  name: choice_names[i],
                  id: "card_choice_" + choice_count + "_option_" + i,
                  children: new Map(),
                };
                if (choice_names[i] != "skip") {
                  to_push["cost"] = choice_costs[i];
                  to_push["range"] = choice_ranges[i];
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
     * A card whose only action is optional can be played for no effect at all, so its auto-generated
     * "skip" rows all mean the same thing: pass. Replace them with a single "pass" option on the
     * first row, which sends the same decision as the pass button. Cards that also do something
     * mandatory keep their per-action "skip" - there, skipping is not passing.
     * @private
     */
    _hoistCardPassOption: function (actions, tree) {
      var optional = a => Object.hasOwn(a, "cost") ||
        (a.action === "choice" && a.choices.every(c => Object.hasOwn(c, "cost")));
      if (actions.length !== 1 || !optional(actions[0])) {
        return false;
      }
      var stripSkips = t => t.forEach((options, key) => {
        t.set(key, options.filter(o => o.name !== "skip"));
        options.forEach(o => stripSkips(o.children));
      });
      stripSkips(tree);
      var firstRow = tree.get(tree.keys().next().value);
      firstRow.push({ name: "pass", id: "card_choice_pass", children: new Map() });
      return true;
    },

    /**
     * Icon glyph for a choice, derived from its name. Firing options end in the side they fire to,
     * maneuvers are named after the move. Anything unrecognised simply gets no glyph.
     * @private
     */
    _choiceGlyph: function (name) {
      var sides = { left: "\u25C0", right: "\u25B6", fore: "\u25B2", aft: "\u25BC" };
      var moves = {
        forward: "\u2191",
        left: "\u21B0",
        right: "\u21B1",
        "pivot left": "\u21BA",
        "pivot right": "\u21BB",
        "pivot 180": "\u21BB",
        "scrap self": "\u2715",
        skip: "\u2715",
        pass: "\u2715",
      };
      if (Object.hasOwn(moves, name)) return moves[name];
      var side = name.split(" ").pop();
      return Object.hasOwn(sides, side) ? sides[side] : "";
    },

    /**
     * Chip contents for one choice: glyph, name, range and cost.
     * @private
     */
    _choiceLabelHtml: function (option) {
      var glyph = this._choiceGlyph(option.name);
      var parts = [];
      if (glyph) parts.push('<span class="chip_glyph">' + glyph + "</span>");
      parts.push('<span class="chip_text">' + (option.name === "skip" ? _("don\u2019t") : _(option.name)) + "</span>");
      if (option.range) parts.push('<span class="chip_range">' + _("range") + " " + option.range + "</span>");
      var cost = option.cost || {};
      var costHtml = Object.keys(cost)
        .filter(r => cost[r] > 0)
        .map(r => cost[r] + this.resourceIcon(r))
        .join("");
      if (costHtml) parts.push('<span class="chip_cost">' + costHtml + "</span>");
      return parts.join("");
    },

    /**
     * Render card choice rows HTML. Rows come back in tree order, each immediately followed by the
     * rows its options unlock - the display order players read top to bottom.
     * @private
     */
    _renderCardChoiceRows: function (tree) {
      var bga = this;
      var rendered_choices = [];

      tree.forEach((options, choice_id) => {
        var rendered_options = [];
        var child_rows = [];
        for (var option of options) {
          rendered_options.push(
            bga.format_block("jstpl_card_choice_radio", {
              id: option.id,
              name: choice_id,
              value: option.name,
              label: bga._choiceLabelHtml(option),
            }),
          );
          child_rows = child_rows.concat(this._renderCardChoiceRows(option.children));
        }
        rendered_choices.push(bga.format_block("jstpl_card_choices_row", { card_choices: rendered_options.join("\n") }));
        rendered_choices = rendered_choices.concat(child_rows);
      });

      return rendered_choices;
    },

    /**
     * Compute total play cost from selected choices
     * @private
     */
    _computeTotalPlayCost: function (tree, costAcc) {
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
    _showHideCardPlayControls: function (tree, hide, totalCost) {
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
            domStyle.set(checkbox.parentNode.parentNode, "display", "");
            if (typeof option.cost !== "undefined" && option.cost) {
              console.log("option cost:");
              console.log(option.cost);
              console.log("totalCost:");
              console.log(totalCost);
              var adjustedCost = bga.addResources(option.cost, totalCost);
              console.log("adjusted cost:");
              console.log(adjustedCost);
              // Merchant doubloon substitution is a market-purchase rule only; the server will not honour it here.
              if (bga.canPlayerAfford(adjustedCost, true, false)) {
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
    _checkIsCardReadyToBePlayed: function (tree) {
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
    _updatePlayCardButton: function () {
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
    setupCaptainCardSelection: function (args) {
      this.cleanupCaptainCardSelection();
      const ability = args.ability;
      const data = args._private;
      const cards = data.available_cards;
      const panel = domConstruct.create("div", { id: "captain_card_choices" }, "myhand_wrap", "first");
      const title = domConstruct.create("p", {}, panel);
      const buttons = domConstruct.create("div", {}, panel);
      const send = (choices) => this.bgaPerformAction("actResolveCaptainCard", {
        choices: JSON.stringify(choices), decisions: JSON.stringify([]),
      });
      const button = (label, action, icon = null) => {
        const node = domConstruct.create("button", { type: "button", className: "bgabutton bgabutton_blue", textContent: label }, buttons);
        if (icon) node.innerHTML = this.resourceIcon(icon);
        on(node, "click", action);
      };
      if (ability === "unearth_riches") {
        title.textContent = _("Unearth Riches — gain:") + " " + Object.entries(data.resources).map(([r, n]) => n + " " + r).join(", ");
        if (data.resources.choice) {
          ["sail", "cannonball", "doubloon"].forEach(r => button(_(r), () => send({ resource: r }), r));
        } else {
          button(_("Gain rewards"), () => send({}));
        }
        return;
      }
      title.textContent = ability === "spyglass" ? _("Spyglass: choose the card to keep, then the remaining cards in top-to-bottom deck order.") :
        ability === "retaliation" ? _("Retaliation: choose damage from your hand or discard pile.") : _("Improvisation: choose a card to copy.");
      const stockNode = domConstruct.create("div", {}, panel);
      this.captainChoiceStock = new BgaCards.LineStock(this.cardsManager, stockNode, { center: false });
      // Use display ids so these previews do not remove cards from the hand/discard stocks.
      const byId = new Map(cards.map(c => [20000 + Number(c.id), c]));
      this.captainChoiceStock.setSelectionMode("single");
      this.captainChoiceStock.addCards(cards.map(c => ({ id: 20000 + Number(c.id), type: c.type })));
      const order = [];
      this.captainChoiceStock.onCardClick = (preview) => {
        const card = byId.get(Number(preview.id));
        domConstruct.empty(buttons);
        if (ability === "spyglass") {
          order.push(Number(card.id));
          this.captainChoiceStock.removeCard(preview);
          title.textContent = order.length === 1 ? _("Card kept. Choose the next card for the top of your deck.") : _("Choose the next card below it.");
          if (order.length === cards.length) {
            title.textContent = _("Ready: keep the first card and return the others in the selected order.");
            button(_("Confirm"), () => send({ order: order }));
          }
          button(_("Start over"), () => this.setupCaptainCardSelection(args));
        } else if (ability === "retaliation") {
          title.textContent = card.location === "hand" ? _("Scrap the selected damage card from your hand:") : _("Scrap the selected damage card from your discard pile:");
          [[_("Fire left (free, range 3)"), "fire left"], [_("Fire right (free, range 3)"), "fire right"], [_("Scrap without firing"), "skip"]].forEach(([label, fire]) =>
            button(label, () => send({ card_id: Number(card.id), fire: fire })));
        } else {
          this.showCardPlayDialog(this.playable_cards[card.type], Number(card.id), Number(card.id));
        }
      };
    },

    cleanupCaptainCardSelection: function () {
      if (this.captainChoiceStock) {
        this.captainChoiceStock.removeAll();
        this.captainChoiceStock = null;
      }
      domConstruct.destroy("captain_card_choices");
    },

    setupScrapCardSelection: function (args) {
      console.log("Setting up scrap card selection");
      console.log(args);

      var overlay = document.createElement("div");
      overlay.id = "card_dialog_overlay";
      overlay.className = "card_dialog_overlay";
      document.body.appendChild(overlay);

      var scrapDialog = this.format_block("jstpl_scrap_card_dialog", {});
      document.body.insertAdjacentHTML("beforeend", scrapDialog);

      this.scrapCardSelection = new BgaCards.ScrollableStock(this.cardsManager, $("scrap_card_selection_wrapper"), {
        gap: "16px",
        center: true,
        scrollStep: 160,
        buttonGap: "4px",
        scrollbarVisible: false,
        leftButton: { html: "‹", classes: ["card_dialog_scroll_btn"] },
        rightButton: { html: "›", classes: ["card_dialog_scroll_btn"] },
      });

      this.scrapCardSelection.setSelectionMode("single");

      this.scrapPreviewCards = this._addPreviewCards(this.scrapCardSelection, args.available_cards);

      this.scrapCardSelection.onSelectionChange = (selection, lastChange) => {
        if (selection.length > 0) {
          var selectedCard = this.scrapPreviewCards.get(Number(selection[0].id));
          console.log("Card selected for scrapping:", selectedCard);

          if (!$("confirm_scrap_button")) {
            domConstruct.create(
              "a",
              {
                id: "confirm_scrap_button",
                class: "bgabutton bgabutton_red",
                innerHTML: _("Scrap Card"),
                href: "#",
              },
              $("cancel_scrap_button"),
              "before",
            );

            on($("confirm_scrap_button"), "click", (event) => {
              event.preventDefault();
              this.confirmScrapCard(selectedCard.id);
            });
          }
        } else {
          if ($("confirm_scrap_button")) {
            domConstruct.destroy("confirm_scrap_button");
          }
        }
      };

      on($("cancel_scrap_button"), "click", (event) => {
        event.preventDefault();
        if (this.checkAction("actSkipIslandScrap", true)) {
          this.bgaPerformAction("actSkipIslandScrap", {});
        } else if (this.checkAction("actSkipCardFlag", true)) {
          this.bgaPerformAction("actSkipCardFlag", {});
        }
        this.cleanupScrapCardSelection();
      });
    },

    /**
     * Set up discard card selection dialog (Rebel ability)
     */
    setupDiscardCardSelection: function (args, title) {
      console.log("Setting up discard card selection");
      console.log(args);

      var overlay = document.createElement("div");
      overlay.id = "card_dialog_overlay";
      overlay.className = "card_dialog_overlay";
      document.body.appendChild(overlay);

      var discardDialog = this.format_block("jstpl_discard_card_dialog", {});
      document.body.insertAdjacentHTML("beforeend", discardDialog);
      $("discard_card_dialog").querySelector("h3").innerHTML = title || _("Choose a card to discard (Rebel ability)");

      this.discardCardSelection = new BgaCards.ScrollableStock(this.cardsManager, $("discard_card_selection_wrapper"), {
        gap: "16px",
        center: true,
        scrollStep: 160,
        buttonGap: "4px",
        scrollbarVisible: false,
        leftButton: { html: "‹", classes: ["card_dialog_scroll_btn"] },
        rightButton: { html: "›", classes: ["card_dialog_scroll_btn"] },
      });

      this.discardCardSelection.setSelectionMode("single");

      this.discardPreviewCards = this._addPreviewCards(
        this.discardCardSelection,
        args.available_cards,
        card => card.location === "hand",
      );

      domClass.add("confirm_discard_button", "disabled");
      domClass.add("confirm_discard_button", "bgabutton_gray");
      domClass.remove("confirm_discard_button", "bgabutton_green");
      this.rebelDiscardSelectedCardId = null;

      this.discardCardSelection.onSelectionChange = (selection, lastChange) => {
        if (selection.length > 0) {
          var selectedCard = this.discardPreviewCards.get(Number(selection[0].id));
          this.rebelDiscardSelectedCardId = selectedCard.id;
          console.log("Card selected for discard:", selectedCard);
          domClass.remove("confirm_discard_button", "disabled");
          domClass.add("confirm_discard_button", "bgabutton_green");
          domClass.remove("confirm_discard_button", "bgabutton_gray");
        } else {
          this.rebelDiscardSelectedCardId = null;
          domClass.add("confirm_discard_button", "disabled");
          domClass.remove("confirm_discard_button", "bgabutton_green");
          domClass.add("confirm_discard_button", "bgabutton_gray");
        }
      };

      on($("confirm_discard_button"), "click", (event) => {
        event.preventDefault();
        if (this.rebelDiscardSelectedCardId != null) {
          this.confirmDiscardCard(this.rebelDiscardSelectedCardId);
        }
      });
    },

    /**
     * Open a pile (discard, scrap) in a read-only dialog when it is clicked. The pile itself is an
     * AllVisibleDeck, whose expanded layout is (card height + shift) x card count tall - past a
     * handful of cards that runs off the bottom of the screen and over everything under it.
     */
    bindPileViewer: function (element, title, getStock) {
      element.tabIndex = 0;
      element.setAttribute("role", "button");
      element.setAttribute("aria-label", title);
      const open = (event) => {
        // Capture phase: the top card's own click handler would otherwise zoom just that card.
        event.stopPropagation();
        event.preventDefault();
        this.showPileDialog(title, getStock().getCards());
      };
      element.addEventListener("click", open, true);
      element.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          open(event);
        }
      });
    },

    /**
     * Read-only view of a pile. Shows previews, so the real cards stay in the pile.
     */
    showPileDialog: function (title, cards) {
      this.cleanupPileDialog();
      if (!cards.length) {
        return;
      }

      const overlay = domConstruct.create(
        "div", { id: "card_dialog_overlay", className: "card_dialog_overlay" }, document.body,
      );
      const dialog = domConstruct.create(
        "div", { id: "pile_view_dialog", className: "scrap_card_dialog" }, document.body,
      );
      domConstruct.create("h3", { textContent: title + " (" + cards.length + ")" }, dialog);
      const wrapper = domConstruct.create(
        "div", { id: "pile_view_wrapper", className: "card_selection_wrapper" }, dialog,
      );
      const buttons = domConstruct.create("div", { className: "scrap_dialog_buttons" }, dialog);
      const close = domConstruct.create(
        "a", { href: "#", className: "bgabutton bgabutton_gray", textContent: _("Close") }, buttons,
      );

      this.pileViewStock = new BgaCards.ScrollableStock(this.cardsManager, wrapper, {
        gap: "16px",
        center: true,
        scrollStep: 160,
        buttonGap: "4px",
        scrollbarVisible: false,
        leftButton: { html: "\u2039", classes: ["card_dialog_scroll_btn"] },
        rightButton: { html: "\u203A", classes: ["card_dialog_scroll_btn"] },
      });
      this._addPreviewCards(this.pileViewStock, cards);

      on(close, "click", (event) => {
        event.preventDefault();
        this.cleanupPileDialog();
      });
      on(overlay, "click", () => this.cleanupPileDialog());
    },

    cleanupPileDialog: function () {
      this._destroySelectionStock(this.pileViewStock);
      this.pileViewStock = null;
      if ($("pile_view_dialog")) {
        domConstruct.destroy("pile_view_dialog");
      }
      if ($("card_dialog_overlay")) {
        domConstruct.destroy("card_dialog_overlay");
      }
    },

    /**
     * Fill a dialog selection stock with previews of the given cards, keyed by display id so the
     * real cards stay in the hand and discard piles. Moving the real ones instead makes the stock
     * race the hand's own layout - the card is booked into the dialog while its element stays
     * behind, which is how this dialog came up empty. Returns display id -> real card.
     * @private
     */
    _addPreviewCards: function (selectionStock, cards, filter) {
      const previews = new Map();
      Object.values(cards || {}).forEach((card) => {
        if (filter && !filter(card)) {
          return;
        }
        const previewId = 30000 + Number(card.id);
        previews.set(previewId, card);
        selectionStock.addCard({ id: previewId, type: card.type });
      });
      return previews;
    },

    /**
     * Drop a dialog selection stock and the preview cards it holds.
     * @private
     */
    _destroySelectionStock: function (selectionStock) {
      if (selectionStock) {
        selectionStock.removeAll();
        selectionStock.remove();
      }
    },

    /**
     * Remove one of my cards from the pile it was played from.
     * @private
     */
    _removeCardFromSelectionOrPile: function (card, originalLocation, playerId) {
      if (playerId != this.player_id) {
        return;
      }

      if (originalLocation === "hand") {
        this.playerHand.removeCard({ id: card.id });
      } else if (originalLocation === "player_discard") {
        this.playerDiscard.removeCard({ id: card.id });
      }
    },

    cleanupScrapCardSelection: function () {
      console.log("Cleaning up scrap card selection");

      this._destroySelectionStock(this.scrapCardSelection);
      this.scrapCardSelection = null;
      this.scrapPreviewCards = null;

      if ($("scrap_card_dialog")) {
        domConstruct.destroy("scrap_card_dialog");
      }
      if ($("card_dialog_overlay")) {
        domConstruct.destroy("card_dialog_overlay");
      }
    },

    /**
     * Clean up discard card selection dialog
     */
    cleanupDiscardCardSelection: function () {
      console.log("Cleaning up discard card selection");

      this._destroySelectionStock(this.discardCardSelection);
      this.discardCardSelection = null;
      this.discardPreviewCards = null;

      if ($("discard_card_dialog")) {
        domConstruct.destroy("discard_card_dialog");
      }
      if ($("card_dialog_overlay")) {
        domConstruct.destroy("card_dialog_overlay");
      }
    },

    /**
     * Confirm scrap card action
     */
    confirmScrapCard: function (cardId) {
      console.log("Confirming scrap of card:", cardId);

      if (this.checkAction("actResolveCardFlag", true)) {
        this.bgaPerformAction("actResolveCardFlag", { card_id: cardId });
      } else if (this.checkAction("actExtortionScrapCard", true)) {
        this.bgaPerformAction("actExtortionScrapCard", { card_id: cardId });
      } else if (this.checkAction("actScrapCard")) {
        this.bgaPerformAction("actScrapCard", { card_id: cardId });
      }
    },

    /**
     * Confirm discard card action (Rebel ability, or the collision penalty).
     */
    confirmDiscardCard: function (cardId) {
      console.log("Confirming discard of card:", cardId);

      if (this.checkAction("actCollisionDiscardCard", true)) {
        this.bgaPerformAction("actCollisionDiscardCard", { card_id: cardId });
      } else if (this.checkAction("actRebelDiscardCard")) {
        this.bgaPerformAction("actRebelDiscardCard", { card_id: cardId });
      }
    },
  };
});
