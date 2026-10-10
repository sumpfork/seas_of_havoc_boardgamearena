/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

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
  g_gamethemeurl + "modules/js/constants.js",
], function (dom, domClass, domConstruct, domStyle, lang, on, query, attr, BgaCards, Constants) {
  // Debug logging only in Studio (see constants.js).
  const console = Constants.console;

  // Card actions with no choice in them that get a locked row of their own in the play dialog.
  const FIXED_MOVES = ["forward", "left", "right", "pivot left", "pivot right", "pivot 180"];

  return {
    /**
     * Clean up card play dialog
     */
    cleanupCardPlayDialog: function () {
      if (this._closeCardPlayDialogOnOutsideClick) {
        document.removeEventListener("click", this._closeCardPlayDialogOnOutsideClick);
        this._closeCardPlayDialogOnOutsideClick = null;
      }
      if (this._closeCardPlayDialogOnEscape) {
        document.removeEventListener("keydown", this._closeCardPlayDialogOnEscape);
        this._closeCardPlayDialogOnEscape = null;
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
      this._previewActions = null;
      this.clearCardPreview();
      domConstruct.destroy("card_display_dialog");
    },

    /**
     * Rowing: "Instead of passing or resolving a card, a player may instead row their ship by
     * discarding 2 cards." First the two discards are picked from the hand; picking the second
     * opens the play dialog with rowing's maneuvers (see onCardSelectedPlayerHand).
     */
    startRowing: function () {
      this.cleanupCardPlayDialog();
      this.playerHand.unselectAll();
      this._rowing = true;
      this.playerHand.setSelectionMode("multiple");
      this.statusBar.setTitle(_("${you} must select 2 cards to discard for rowing"));
      this.statusBar.removeActionButtons();
      this.statusBar.addActionButton(_("Cancel"), () => this.cancelRowing(), { color: "secondary" });
    },

    cancelRowing: function () {
      this.cleanupCardPlayDialog();
      this.updateHandSelectionMode(); // ends rowing
      this.playerHand.unselectAll();
      this.updatePageTitle(); // the seaTurn title and buttons
    },

    showRowingDialog: function () {
      const card = Object.values(this.playable_cards).find((c) => c.category === "rowing");
      if (!card) throw new Error("No rowing card in playable_cards");
      this.showCardPlayDialog(card, null);
      dom.byId("play_card_button").textContent = _("Row");
      dom.byId("pass_card_button").textContent = _("Cancel");
    },

    _sendRow: function (decisions) {
      const [first, second] = this.playerHand.getSelection();
      const params = { discard_card_id_1: first.id, discard_card_id_2: second.id, decision: decisions[0] };
      const shipChoice = document.querySelector('input[name="card_ship"]:checked');
      if (shipChoice) {
        params.ship = Number(shipChoice.value);
      }
      // Back to the plain turn first, so a rejected row leaves the player able to try again.
      this.cancelRowing();
      this.bgaPerformAction("actRow", params);
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
        if (event.target.closest("#card_display_dialog, #myhand, #captain_card_choices, .soh_card-zoom-dialog, .bgabutton")) {
          return;
        }
        if (this._pendingBootySend) {
          return;
        }
        this.cleanupCardPlayDialog();
        this.playerHand.unselectAll();
      };
      document.addEventListener("click", this._closeCardPlayDialogOnOutsideClick);
      // Escape drops the card back too. A card zoom open on top takes the key first, and the
      // booty prompt is answered from the status bar, so the dialog stays for both.
      this._closeCardPlayDialogOnEscape = (event) => {
        if (event.key !== "Escape" || this._pendingBootySend || document.querySelector("dialog[open]")) {
          return;
        }
        this.cleanupCardPlayDialog();
        this.playerHand.unselectAll();
      };
      document.addEventListener("keydown", this._closeCardPlayDialogOnEscape);


      on(
        query(".play_card_button"),
        "click",
        lang.hitch(this, (event) => {
          console.groupCollapsed("card play button clicked");
          event.preventDefault();
          var decisionSummary = this._decisionSummary(this.dep_tree);
          if (this._rowing) {
            this._sendRow(decisionSummary);
            console.groupEnd();
            return;
          }
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
          if (this._rowing) {
            this.cancelRowing();
            return;
          }
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
          console.groupEnd();
        }),
      );

      // 2 Ship Variant: "Each card you play applies only to one ship."
      var me = this.gamedatas.playerinfo[this.player_id];
      if (me.player_ship2) {
        domConstruct.place(
          `<div class="card_ship_choice_label">${_("Ship")}:</div>` +
            [[1, me.player_ship], [2, me.player_ship2]].map(([n, name]) =>
              // The ship as it looks on the board: players know their ships by sight, not by name.
              `<label class="soh_card_ship_option" title="${_(name)}"><input type="radio" name="card_ship" value="${n}" ${n === 1 ? "checked" : ""} aria-label="${_(name)}">` +
              `<div class="soh_player_ship soh_card_ship_sprite" data-shipname="${name}"></div></label>`,
            ).join(" "),
          "card_ship_choice",
        );
        query('input[name="card_ship"]').connect("onchange", this, () => this.updateCardPreview());
      }

      var display_dom = query("#card_display");
      console.log("display dom:");
      console.log(display_dom);
      this.cardDisplayStock = new BgaCards.LineStock(this.cardsManager, display_dom[0], { center: false });
      this.cardDisplayStock.addCard({ id: 10000, type: card.card_type });

      console.log(card);

      // Build card dependency tree
      this._previewActions = card.actions;
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
        this._lockFixedRows(this.dep_tree);
        query(".soh_card_choice_radio").connect("onchange", this, (event) => {
          // The player has answered this row themselves: nothing in it is an automatic pick now.
          query(`input[name="${event.target.name}"]`).forEach((radio) => delete radio.dataset.autoTicked);
          this._updateCardPlayControls();
        });
      }

      this._updateCardPlayControls();
      if (captainCopyId !== null) {
        query(".pass_card_button").forEach(node => { node.textContent = _("Cancel copy"); });
      }
      this.cardPlayDialogShown = true;
    },

    /**
     * The tree's rows, each followed by the rows its options unlock: the order the dialog shows
     * them and the server consumes decisions. With activeOnly, only rows whose parent option is
     * ticked - the rows on screen.
     * @private
     */
    _rows: function* (tree, activeOnly = false) {
      for (const [choiceId, options] of tree.entries()) {
        yield [choiceId, options];
        for (const option of options) {
          if (!activeOnly || dom.byId(option.id).checked) yield* this._rows(option.children, activeOnly);
        }
      }
    },

    /** The element holding a row's radios. */
    _choiceRow: function (options) {
      return dom.byId(options[0].id).parentNode.parentNode;
    },

    /** Tick the rows for moves the card always makes, and stop them being changed. */
    _lockFixedRows: function (tree) {
      for (const [, options] of this._rows(tree)) {
        for (const option of options.filter(o => o.fixed)) {
          const radio = dom.byId(option.id);
          radio.checked = true;
          radio.disabled = true;
          radio.classList.add("soh_card_choice_fixed");
        }
      }
    },

    /** The decisions ticked in the dialog so far, in the order the server consumes them. */
    _decisionSummary: function (tree) {
      const decisions = [];
      for (const [, options] of this._rows(tree, true)) {
        const ticked = options.find(o => !o.fixed && dom.byId(o.id).checked);
        if (ticked) decisions.push(ticked.name);
      }
      return decisions;
    },

    /**
     * Send the actPlayCard action to the server and update local UI.
     */
    _sendPlayCard: function (card, card_id, decisions, useBooty, captainCopyId = this._captainCopyId) {
      if (captainCopyId != null) {
        const params = { choices: JSON.stringify({ card_id: captainCopyId }), decisions: JSON.stringify(decisions) };
        const shipChoice = document.querySelector('input[name="card_ship"]:checked');
        if (shipChoice) {
          params.ship = Number(shipChoice.value);
        }
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
      var shipChoice = document.querySelector('input[name="card_ship"]:checked');
      if (shipChoice) {
        params.ship = Number(shipChoice.value);
      }
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
    },

    /**
     * Hand to discard, driven by the server's cardPlayed notification rather than the action's
     * promise, so plays this tab did not send (another tab, a timeout's zombie play) also leave it.
     */
    movePlayedCardToDiscard: function (card_id, card_type) {
      if (!this.playerHand.getCards().some(c => c.id == card_id)) {
        throw new Error(`Played card ${card_id} is not in the hand`);
      }
      this.playerDiscard.addCard({
        id: card_id,
        type: card_type,
        location: "discard",
        fromStock: this.playerHand,
      });
      this.playerHand.removeCard({ id: card_id, type: card_type });
    },

    /**
     * Called from onUpdateActionButtons for client_bootyPlayConfirm state.
     */
    /**
     * Spend a booty token on a cost when it helps: pay with it outright if the player cannot afford
     * the cost otherwise, ask when either way works, and stay out of the way when it does not apply.
     * `send(useBooty)` performs the action. `canAffordWithout` overrides the plain affordability
     * check, for payments with other ways round a shortfall (the Merchant's doubloons).
     */
    _sendWithOptionalBooty: function (cost, send, question, canAffordWithout) {
      var tokenRes = this.getMyBootyTokenRes(cost);
      if (!tokenRes || !this.bootyOverlapsCost(tokenRes, cost)) {
        send(false);
        return;
      }
      if (!(canAffordWithout ?? this.canPlayerAfford(cost, false, false))) {
        send(true);
        return;
      }
      this._pendingBootySend = send;
      this.setClientState("client_bootyPlayConfirm", { descriptionmyturn: question });
    },

    /** Perform `action` paying `cost`, with the booty token if it helps (asking when it is optional). */
    payWithOptionalBooty: function (cost, action, args = {}) {
      this._sendWithOptionalBooty(
        cost,
        (useBooty) =>
          this.bgaPerformAction(action, useBooty ? { ...args, use_booty_card_id: this.getMyBootyTokenId(cost) } : args),
        _("Use your booty token to help pay?"),
      );
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
    _makeCardDependencyTree: function (actions, counter = { next: 0 }, topLevel = true) {
      const tree = new Map();
      for (const action of actions) {
        if (action.action === "sequence") {
          for (const [key, row] of this._makeCardDependencyTree(action.actions, counter, topLevel)) {
            tree.set(key, row);
          }
          continue;
        }
        if (!Constants.needsDecision(action)) {
          // A move the card always makes: shown as its own row, ticked and locked, so the dialog
          // lists everything the card does. The server takes no decision for it. Inside a choice
          // the option's chip already names the move.
          if (topLevel && FIXED_MOVES.includes(action.name || action.action)) {
            const rowNumber = counter.next++;
            tree.set("choice_" + rowNumber, [{
              name: action.name || action.action,
              id: "card_choice_" + rowNumber + "_option_0",
              fixed: true,
              children: new Map(),
            }]);
          }
          continue;
        }
        const rowNumber = counter.next++;
        tree.set("choice_" + rowNumber, Constants.actionOptions(action).map((option, i) => {
          const entry = { name: option.name, id: "card_choice_" + rowNumber + "_option_" + i, children: new Map() };
          if (option.name === "skip") return entry;
          if (option.choice) {
            entry.children = this._makeCardDependencyTree([option.choice], counter, false);
            // A choice's cost is paid whichever option is taken. Otherwise an option carries its
            // own cost only without children: a fire option's cost is re-declared on the shot rows
            // it generates, and counting it here too doubles it.
            const cost = action.cost !== undefined ? action.cost : entry.children.size === 0 ? option.choice.cost : undefined;
            if (cost !== undefined) entry.cost = cost;
            return entry;
          }
          entry.cost = option.cost;
          entry.range = option.range;
          return entry;
        }));
      }
      return tree;
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
        backward: "\u2193",
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
     * A choice's name, translated. Firing options are "<shot> <side>" ("chain shot left"): the shot
     * and the side are translated separately (both are registered in ShipUpgrades::translatedNames).
     * @private
     */
    _choiceName: function (name) {
      if (name === "nimble hull maneuver twice") return _("Nimble Hull: maneuver twice");
      var words = name.split(" ");
      var side = words.pop();
      return ["left", "right", "fore", "aft"].includes(side) && words.length > 0 && !name.startsWith("pivot")
        ? _(words.join(" ")) + " " + _(side)
        : _(name);
    },

    /**
     * Chip contents for one choice: glyph, name, range and cost.
     * @private
     */
    _choiceLabelHtml: function (option) {
      var glyph = this._choiceGlyph(option.name);
      var parts = [];
      if (glyph) parts.push('<span class="soh_chip_glyph">' + glyph + "</span>");
      parts.push('<span class="chip_text">' + this._choiceName(option.name) + "</span>");
      if (option.range) parts.push('<span class="soh_chip_range">' + _("range") + " " + option.range + "</span>");
      var cost = option.cost || {};
      var costHtml = Object.keys(cost)
        .filter(r => cost[r] > 0)
        .map(r => cost[r] + this.resourceIcon(r))
        .join("");
      if (costHtml) parts.push('<span class="soh_chip_cost">' + costHtml + "</span>");
      return parts.join("");
    },

    /**
     * Render card choice rows HTML. Rows come back in tree order, each immediately followed by the
     * rows its options unlock - the display order players read top to bottom.
     * @private
     */
    _renderCardChoiceRows: function (tree) {
      return Array.from(this._rows(tree), ([choiceId, options]) => this.format_block("jstpl_card_choices_row", {
        card_choices: options.map(option => this.format_block("jstpl_card_choice_radio", {
          id: option.id,
          name: choiceId,
          value: option.name,
          label: this._choiceLabelHtml(option),
        })).join("\n"),
      }));
    },

    /**
     * Total cost of the ticked options. Rows under an unticked option are unticked too, so
     * walking every row counts only what will be played.
     * @private
     */
    _computeTotalPlayCost: function (tree) {
      let cost = {};
      for (const [, options] of this._rows(tree)) {
        for (const option of options) {
          if (option.cost !== undefined && dom.byId(option.id).checked) {
            cost = this.addResources(option.cost, cost);
          }
        }
      }
      return cost;
    },

    /**
     * Show the rows under ticked options, hide and untick the rest, and disable what the player
     * cannot afford.
     * @private
     */
    _showHideCardPlayControls: function (tree) {
      const totalCost = this._computeTotalPlayCost(tree);
      const active = new Set(Array.from(this._rows(tree, true), ([, options]) => options));
      for (const [choiceId, options] of this._rows(tree)) {
        const row = this._choiceRow(options);
        if (!active.has(options)) {
          for (const option of options) dom.byId(option.id).checked = false;
          domStyle.set(row, "display", "none");
          continue;
        }
        domStyle.set(row, "display", "");
        // Options in a row are exclusive: judge each against the total without this row's current
        // pick (and what it unlocks), or a selected option is charged twice and disables itself.
        const rowCost = this._computeTotalPlayCost(new Map([[choiceId, options]]));
        const costWithoutRow = this.addResources(totalCost,
          Object.fromEntries(Object.entries(rowCost).map(([r, n]) => [r, -n])));
        for (const option of options.filter(o => o.cost)) {
          const checkbox = dom.byId(option.id);
          // Merchant doubloon substitution is a market-purchase rule only; the server will not honour it here.
          if (this.canPlayerAfford(this.addResources(option.cost, costWithoutRow), true, false)) {
            attr.remove(checkbox, "disabled");
          } else {
            attr.set(checkbox, "disabled", "true");
          }
        }
      }
    },

    /**
     * Refresh the dialog after a selection: what is affordable, which rows are stuck, and whether
     * the play button can light up. Preselecting a stuck row changes the running total, so the
     * pass repeats until nothing more flips.
     * @private
     */
    _updateCardPlayControls: function () {
      console.groupCollapsed("show/hide play controls");
      // Hiding a row unticks it and lowers the total cost, which can make other rows affordable
      // again, so go round until neither the cost nor the automatic picks change.
      let costBefore;
      do {
        costBefore = JSON.stringify(this._computeTotalPlayCost(this.dep_tree));
        this._showHideCardPlayControls(this.dep_tree);
      } while (
        this._markUnaffordableRows(this.dep_tree) ||
        JSON.stringify(this._computeTotalPlayCost(this.dep_tree)) !== costBefore
      );
      console.groupEnd();
      this._updatePlayCardButton();
      this.updateCardPreview();
    },

    /**
     * A row whose every paid option is out of reach leaves the player one real answer. Say so on
     * the skip chip - "can't afford" rather than "skip" - and pick it for them. Returns true when
     * a box was ticked, since that changes what the other rows cost.
     * @private
     */
    _markUnaffordableRows: function (tree) {
      var changed = false;
      for (const [, options] of this._rows(tree)) {
        var optOut = options.find(o => o.name === "skip");
        var paid = options.filter(o => o.cost && Object.keys(o.cost).some(r => o.cost[r] > 0));
        if (optOut && paid.length) {
          var checkbox = dom.byId(optOut.id);
          var stuck = domStyle.get(this._choiceRow(options), "display") !== "none" &&
            // A free alternative (e.g. carronade) keeps the row open even when every paid shot is out of reach.
            options.every(o => o === optOut || dom.byId(o.id).disabled);
          var chipText = query(".chip_text", checkbox.parentNode)[0];
          if (chipText) {
            chipText.textContent = stuck ? _("can\u2019t afford") : _("skip");
          }
          if (stuck && !checkbox.checked) {
            checkbox.checked = true;
            checkbox.dataset.autoTicked = "1";
            changed = true;
          } else if (!stuck && checkbox.checked && checkbox.dataset.autoTicked) {
            // Ticked for the player only because nothing else was affordable: now something is,
            // so leave the choice to them again.
            checkbox.checked = false;
            delete checkbox.dataset.autoTicked;
            changed = true;
          }
        }
      }
      return changed;
    },

    /** Highlight unanswered visible rows and return how many still need a choice. */
    _updateCardChoiceHints: function (tree) {
      let remaining = 0;
      for (const [, options] of this._rows(tree)) {
        const unanswered = domStyle.get(this._choiceRow(options), "display") !== "none" &&
          !options.every(option => option.fixed) &&
          !options.some(option => dom.byId(option.id).checked);
        domClass.toggle(this._choiceRow(options), "soh_needs_choice", unanswered);
        if (unanswered) remaining++;
      }
      return remaining;
    },

    /** The hints, count and Play Card button all use the same unanswered rows. */
    _updatePlayCardButton: function () {
      const remaining = this._updateCardChoiceHints(this.dep_tree);
      domClass.toggle("play_card_button", "disabled", remaining > 0);
      dom.byId("card_choices_remaining").textContent = remaining === 0 ? "" :
        remaining === 1 ? _("1 choice remaining") :
          _("${count} choices remaining").replace("${count}", remaining);
    },

    /**
     * Set up scrap card selection dialog
     */
    setupCaptainCardSelection: function (args) {
      this.cleanupCaptainCardSelection();
      const ability = args.ability;
      const data = args._private;
      const cards = data.available_cards;
      const send = (choices) => this.bgaPerformAction("actResolveCaptainCard", {
        choices: JSON.stringify(choices), decisions: JSON.stringify([]),
      });
      const button = (label, action, color = "primary") => this.statusBar.addActionButton(label, action, { color });
      if (ability === "unearth_riches") {
        const gains = Object.entries(data.resources).map(([r, n]) => n + " " + (r === "choice" ? _("resource of your choice") : this.resourceIcon(r)));
        this.statusBar.setTitle(_("Unearth Riches — gain:") + " " + gains.join(" + "));
        if (data.resources.choice) {
          this.addResourceButtons((resource) => send({ resource }));
        } else {
          button(_("Gain rewards"), () => send({}));
        }
        return;
      }
      if (ability === "retaliation") {
        // Every damage card is identical, so there is nothing to pick between: go straight to the shot.
        this.statusBar.setTitle(_("Retaliation: scrap a damage card, then you may fire."));
        // The free shot the server resolves (see actResolveCaptainCard), previewed on the board.
        const shot = [{ action: "fire", range: 3 }];
        ["left", "right"].forEach((side) =>
          this.addPreviewedActionButton(
            side === "left" ? _("Fire left (free, range 3)") : _("Fire right (free, range 3)"),
            () => send({ fire: "fire " + side }),
            { actions: shot, decisions: ["fire " + side], ship: args.ship },
          ),
        );
        button(_("Scrap without firing"), () => send({ fire: "skip" }), "secondary");
        return;
      }
      const panel = domConstruct.create("div", { id: "captain_card_choices" }, "myhand_wrap", "first");
      const title = domConstruct.create("p", {}, panel);
      title.textContent = ability === "spyglass" ? _("Spyglass: choose the card to keep, then the remaining cards in top-to-bottom deck order.") :
        _("Improvisation: choose a card to copy.");
      const stockNode = domConstruct.create("div", {}, panel);
      this.captainChoiceStock = new BgaCards.LineStock(this.cardsManager, stockNode, { center: false });
      // Use display ids so these previews do not remove cards from the hand/discard stocks.
      const byId = new Map(cards.map(c => [20000 + Number(c.id), c]));
      this.captainChoiceStock.setSelectionMode("single");
      this.captainChoiceStock.addCards(cards.map(c => ({ id: 20000 + Number(c.id), type: c.type })));
      const order = [];
      this.captainChoiceStock.onCardClick = (preview) => {
        const card = byId.get(Number(preview.id));
        this.statusBar.removeActionButtons();
        if (ability === "spyglass") {
          order.push(Number(card.id));
          this.captainChoiceStock.removeCard(preview);
          title.textContent = order.length === 1 ? _("Card kept. Choose the next card for the top of your deck.") : _("Choose the next card below it.");
          if (order.length === cards.length) {
            title.textContent = _("Ready: keep the first card and return the others in the selected order.");
            button(_("Confirm"), () => send({ order: order }));
          }
          button(_("Start over"), () => { this.statusBar.removeActionButtons(); this.setupCaptainCardSelection(args); }, "secondary");
        } else {
          this.showCardPlayDialog(this.playable_cards[card.type], Number(card.id), Number(card.id));
        }
      };
      if (ability === "improvisation") {
        panel.scrollIntoView({ behavior: "smooth", block: "center" });
      }
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
      overlay.className = "soh_card_dialog_overlay";
      document.body.appendChild(overlay);

      var scrapDialog = this.format_block("jstpl_scrap_card_dialog", {});
      document.body.insertAdjacentHTML("beforeend", scrapDialog);

      // Hand and discard pile are shown as separate rows, so the player can see where each
      // card comes from. One selection across both.
      const makeStock = (wrapperId) => {
        const stock = new BgaCards.ScrollableStock(this.cardsManager, $(wrapperId), {
          gap: "16px",
          center: true,
          scrollStep: 160,
          buttonGap: "4px",
          scrollbarVisible: false,
          leftButton: { html: "‹", classes: ["soh_card_dialog_scroll_btn"] },
          rightButton: { html: "›", classes: ["soh_card_dialog_scroll_btn"] },
        });
        stock.setSelectionMode("single");
        return stock;
      };
      this.scrapCardSelection = makeStock("scrap_card_selection_wrapper");
      this.scrapDiscardSelection = makeStock("scrap_discard_selection_wrapper");

      const inHand = (card) => card.location === "hand";
      const handPreviews = this._addPreviewCards(this.scrapCardSelection, args.available_cards, inHand);
      const discardPreviews = this._addPreviewCards(this.scrapDiscardSelection, args.available_cards, (card) => !inHand(card));
      this.scrapPreviewCards = new Map([...handPreviews, ...discardPreviews]);
      domStyle.set("scrap_hand_group", "display", handPreviews.size ? "" : "none");
      domStyle.set("scrap_discard_group", "display", discardPreviews.size ? "" : "none");

      this.scrapSelectedCardId = null;
      const onSelect = (stock, other) => (selection) => {
        if (selection.length > 0) {
          other.unselectAll(true);
          this.scrapSelectedCardId = this.scrapPreviewCards.get(Number(selection[0].id)).id;
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
            // Reads the selection when clicked: the player may change their mind after the
            // button appears.
            on($("confirm_scrap_button"), "click", (event) => {
              event.preventDefault();
              this.confirmScrapCard(this.scrapSelectedCardId);
            });
          }
        } else if (other.getSelection().length === 0) {
          this.scrapSelectedCardId = null;
          if ($("confirm_scrap_button")) {
            domConstruct.destroy("confirm_scrap_button");
          }
        }
      };
      this.scrapCardSelection.onSelectionChange = onSelect(this.scrapCardSelection, this.scrapDiscardSelection);
      this.scrapDiscardSelection.onSelectionChange = onSelect(this.scrapDiscardSelection, this.scrapCardSelection);

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
      overlay.className = "soh_card_dialog_overlay";
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
        leftButton: { html: "‹", classes: ["soh_card_dialog_scroll_btn"] },
        rightButton: { html: "›", classes: ["soh_card_dialog_scroll_btn"] },
      });

      this.discardCardSelection.setSelectionMode("single");

      this.discardPreviewCards = this._addPreviewCards(
        this.discardCardSelection,
        args.available_cards,
        card => card.location === "hand",
      );

      domClass.add("confirm_discard_button", "disabled");
      this.rebelDiscardSelectedCardId = null;

      this.discardCardSelection.onSelectionChange = (selection, lastChange) => {
        if (selection.length > 0) {
          var selectedCard = this.discardPreviewCards.get(Number(selection[0].id));
          this.rebelDiscardSelectedCardId = selectedCard.id;
          console.log("Card selected for discard:", selectedCard);
          domClass.remove("confirm_discard_button", "disabled");
        } else {
          this.rebelDiscardSelectedCardId = null;
          domClass.add("confirm_discard_button", "disabled");
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
     * Open a pile (deck, discard, scrap) in a read-only dialog when it is clicked. The discard is an
     * AllVisibleDeck, whose expanded layout is (card height + shift) x card count tall - past a
     * handful of cards that runs off the bottom of the screen and over everything under it.
     */
    bindPileViewer: function (element, title, show) {
      element.tabIndex = 0;
      element.setAttribute("role", "button");
      element.setAttribute("aria-label", title);
      const open = (event) => {
        // Capture phase: the top card's own click handler would otherwise zoom just that card.
        event.stopPropagation();
        event.preventDefault();
        show();
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
    showPileDialog: function (title, cards, note = null) {
      this.cleanupPileDialog();
      if (!cards.length) {
        return;
      }

      const overlay = domConstruct.create(
        "div", { id: "card_dialog_overlay", className: "soh_card_dialog_overlay" }, document.body,
      );
      const dialog = domConstruct.create(
        "div", { id: "pile_view_dialog", className: "soh_scrap_card_dialog" }, document.body,
      );
      domConstruct.create("h3", { textContent: title + " (" + cards.length + ")" }, dialog);
      if (note) {
        domConstruct.create("p", { className: "soh_pile_view_note", textContent: note }, dialog);
      }
      const wrapper = domConstruct.create(
        "div", { id: "pile_view_wrapper", className: "soh_card_selection_wrapper" }, dialog,
      );
      const buttons = domConstruct.create("div", { className: "soh_scrap_dialog_buttons" }, dialog);
      const close = domConstruct.create(
        "a", { href: "#", className: "bgabutton bgabutton_gray", textContent: _("Close") }, buttons,
      );

      this.pileViewStock = new BgaCards.ScrollableStock(this.cardsManager, wrapper, {
        gap: "16px",
        center: true,
        scrollStep: 160,
        buttonGap: "4px",
        scrollbarVisible: false,
        leftButton: { html: "\u2039", classes: ["soh_card_dialog_scroll_btn"] },
        rightButton: { html: "\u203A", classes: ["soh_card_dialog_scroll_btn"] },
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
      this._destroySelectionStock(this.scrapDiscardSelection);
      this.scrapCardSelection = null;
      this.scrapDiscardSelection = null;
      this.scrapPreviewCards = null;
      this.scrapSelectedCardId = null;

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
