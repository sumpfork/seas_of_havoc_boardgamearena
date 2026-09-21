/**
 * Seas of Havoc - Trading Post Module
 * Handles the trading post island slot: exchange up to 2 resources for others,
 * or spend a booty token to gain 2 resources.
 */

define([
  "dojo/dom",
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/query",
], function(dom, domClass, domConstruct, query) {

  var TRADEABLE_RESOURCES = ["sail", "cannonball", "doubloon"];

  return {
    cleanupTradingPostUi: function() {
      var rows = dom.byId("trading_post_rows");
      if (rows) {
        domConstruct.destroy(rows);
      }
    },

    /**
     * Entry point: called from notif_showTradingPostDialog.
     * Kicks off the trading post client-state flow.
     */
    initTradingPost: function(slotNumber, stateOverride) {
      this._tradingPostState = stateOverride || {
        slotNumber: slotNumber,
        useBooty: false,
        resourcesSpent: [],
        resourcesGained: [],
      };

      if (this._tradingPostState.useBooty || this._tradingPostState.resourcesSpent.length > 0) {
        this.setClientState("client_tradingPostGain", {
          descriptionmyturn: _("${you} must select resources to receive"),
        });
        return;
      }

      var tokenRes = this.getMyBootyTokenRes();
      if (tokenRes) {
        this.setClientState("client_tradingPostBootyChoice", {
          descriptionmyturn: _("Use your booty token at the trading post?"),
        });
      } else {
        this.setClientState("client_tradingPostSpend", {
          descriptionmyturn: _("${you} must select resources to trade away"),
        });
      }
    },

    onTradingPostBootyYes: function() {
      this._tradingPostState.useBooty = true;
      this.setClientState("client_tradingPostGain", {
        descriptionmyturn: _("${you} must select resources to receive"),
      });
    },

    onTradingPostBootyNo: function() {
      this._tradingPostState.useBooty = false;
      this.setClientState("client_tradingPostSpend", {
        descriptionmyturn: _("${you} must select resources to trade away"),
      });
    },

    /**
     * Build the spend-selection UI: two rows of resource buttons in the status bar.
     * Row 1: mandatory pick. Row 2: pick or skip (trade only 1).
     */
    buildTradingPostSpendUI: function() {
      var actionsDiv = dom.byId("generalactions");
      var playerRes = this.getPlayerResources();

      this.cleanupTradingPostUi();

      var html = '<div id="trading_post_rows" class="trading-post-rows">';

      // Row 1
      html += '<div class="trading-row">';
      html += '<span class="trading-row-label">1.</span>';
      for (var i = 0; i < TRADEABLE_RESOURCES.length; i++) {
        var res = TRADEABLE_RESOURCES[i];
        var count = parseInt(playerRes[res]) || 0;
        var disabledClass = count < 1 ? " disabled" : "";
        html += '<a class="bgabutton bgabutton_resource trading-resource-btn' + disabledClass
          + '" data-resource="' + res + '" data-row="1" data-phase="spend">'
          + this.resourceIcon(res) + '</a>';
      }
      html += '</div>';

      // Row 2
      html += '<div class="trading-row">';
      html += '<span class="trading-row-label">2.</span>';
      for (var i = 0; i < TRADEABLE_RESOURCES.length; i++) {
        var res = TRADEABLE_RESOURCES[i];
        var count = parseInt(playerRes[res]) || 0;
        var disabledClass = count < 1 ? " disabled" : "";
        html += '<a class="bgabutton bgabutton_resource trading-resource-btn' + disabledClass
          + '" data-resource="' + res + '" data-row="2" data-phase="spend">'
          + this.resourceIcon(res) + '</a>';
      }
      html += '<a class="bgabutton bgabutton_gray trading-resource-btn" '
        + 'data-resource="skip" data-row="2" data-phase="spend">Skip</a>';
      html += '</div>';

      html += '</div>';
      domConstruct.place(html, actionsDiv);

      this.statusBar.addActionButton(
        _("Confirm"),
        this.onTradingPostSpendConfirm.bind(this),
        { id: "trading_post_confirm_btn", classes: "bgabutton_green" },
      );
      domClass.add("trading_post_confirm_btn", "disabled");

      this._wireTradingResourceButtons("spend");
      this._restoreTradingSelections("spend");
      this._updateTradingSpendConstraints();
      this._updateTradingConfirmButton("spend");
    },

    /**
     * Build the gain-selection UI: 1 or 2 rows (matching spend count / 2 if booty).
     * All resources are always available.
     */
    buildTradingPostGainUI: function() {
      var actionsDiv = dom.byId("generalactions");
      var numRows = this._tradingPostState.useBooty
        ? 2
        : this._tradingPostState.resourcesSpent.length;

      this.cleanupTradingPostUi();

      var html = '<div id="trading_post_rows" class="trading-post-rows">';
      for (var row = 1; row <= numRows; row++) {
        html += '<div class="trading-row">';
        html += '<span class="trading-row-label">' + row + '.</span>';
        for (var i = 0; i < TRADEABLE_RESOURCES.length; i++) {
          var res = TRADEABLE_RESOURCES[i];
          html += '<a class="bgabutton bgabutton_resource trading-resource-btn'
            + '" data-resource="' + res + '" data-row="' + row + '" data-phase="gain">'
            + this.resourceIcon(res) + '</a>';
        }
        html += '</div>';
      }
      html += '</div>';
      domConstruct.place(html, actionsDiv);

      this.statusBar.addActionButton(
        _("Confirm"),
        this.onTradingPostGainConfirm.bind(this),
        { id: "trading_post_confirm_btn", classes: "bgabutton_green" },
      );
      domClass.add("trading_post_confirm_btn", "disabled");

      this._wireTradingResourceButtons("gain");
      this._restoreTradingSelections("gain");
      this._updateTradingConfirmButton("gain");
    },

    _restoreTradingSelections: function(phase) {
      var selectedResources = phase === "spend"
        ? this._tradingPostState.resourcesSpent
        : this._tradingPostState.resourcesGained;

      for (var row = 1; row <= selectedResources.length; row++) {
        var resource = selectedResources[row - 1];
        query('.trading-resource-btn[data-row="' + row + '"][data-phase="' + phase + '"]').forEach(function(btn) {
          if (btn.dataset.resource === resource) {
            domClass.add(btn, "trading-selected");
          }
        });
      }
    },

    _wireTradingResourceButtons: function(phase) {
      var self = this;
      query("#trading_post_rows .trading-resource-btn").forEach(function(btn) {
        btn.addEventListener("click", function(event) {
          event.preventDefault();
          self._onTradingResourceClicked(btn, phase);
        });
      });
    },

    _onTradingResourceClicked: function(btn, phase) {
      if (domClass.contains(btn, "disabled")) return;

      var row = btn.dataset.row;

      // Select this button, deselect others in the same row
      query('.trading-resource-btn[data-row="' + row + '"][data-phase="' + phase + '"]')
        .forEach(function(b) { domClass.remove(b, "trading-selected"); });
      domClass.add(btn, "trading-selected");

      if (phase === "spend") {
        this._updateTradingSpendConstraints();
      }
      this._updateTradingConfirmButton(phase);
    },

    /**
     * After row 1 selection changes, update which row 2 buttons are affordable.
     */
    _updateTradingSpendConstraints: function() {
      var playerRes = this.getPlayerResources();

      var row1Selected = null;
      query('.trading-resource-btn.trading-selected[data-row="1"][data-phase="spend"]')
        .forEach(function(btn) { row1Selected = btn.dataset.resource; });

      var remaining = {};
      for (var i = 0; i < TRADEABLE_RESOURCES.length; i++) {
        remaining[TRADEABLE_RESOURCES[i]] = parseInt(playerRes[TRADEABLE_RESOURCES[i]]) || 0;
      }
      if (row1Selected) {
        remaining[row1Selected] = Math.max(0, (remaining[row1Selected] || 0) - 1);
      }

      query('.trading-resource-btn[data-row="2"][data-phase="spend"]').forEach(function(btn) {
        if (btn.dataset.resource === "skip") return;
        var res = btn.dataset.resource;
        if ((remaining[res] || 0) < 1) {
          domClass.add(btn, "disabled");
          if (domClass.contains(btn, "trading-selected")) {
            domClass.remove(btn, "trading-selected");
          }
        } else {
          domClass.remove(btn, "disabled");
        }
      });
    },

    _updateTradingConfirmButton: function(phase) {
      var confirmBtn = dom.byId("trading_post_confirm_btn");
      if (!confirmBtn) return;

      var canConfirm = false;
      if (phase === "spend") {
        var row1Ok = query('.trading-resource-btn.trading-selected[data-row="1"][data-phase="spend"]').length > 0;
        var row2Ok = query('.trading-resource-btn.trading-selected[data-row="2"][data-phase="spend"]').length > 0;
        canConfirm = row1Ok && row2Ok;
      } else {
        canConfirm = true;
        var numRows = this._tradingPostState.useBooty
          ? 2
          : this._tradingPostState.resourcesSpent.length;
        for (var row = 1; row <= numRows; row++) {
          if (query('.trading-resource-btn.trading-selected[data-row="' + row + '"][data-phase="gain"]').length === 0) {
            canConfirm = false;
            break;
          }
        }
      }

      if (canConfirm) {
        domClass.remove(confirmBtn, "disabled");
      } else {
        domClass.add(confirmBtn, "disabled");
      }
    },

    onTradingPostSpendConfirm: function() {
      if (domClass.contains("trading_post_confirm_btn", "disabled")) return;

      var resourcesSpent = [];
      for (var row = 1; row <= 2; row++) {
        query('.trading-resource-btn.trading-selected[data-row="' + row + '"][data-phase="spend"]')
          .forEach(function(btn) {
            if (btn.dataset.resource !== "skip") {
              resourcesSpent.push(btn.dataset.resource);
            }
          });
      }

      this._tradingPostState.resourcesSpent = resourcesSpent;
      this.setClientState("client_tradingPostGain", {
        descriptionmyturn: _("${you} must select resources to receive"),
      });
    },

    onTradingPostGainConfirm: function() {
      if (domClass.contains("trading_post_confirm_btn", "disabled")) return;

      var numRows = this._tradingPostState.useBooty
        ? 2
        : this._tradingPostState.resourcesSpent.length;
      var resourcesGained = [];
      for (var row = 1; row <= numRows; row++) {
        query('.trading-resource-btn.trading-selected[data-row="' + row + '"][data-phase="gain"]')
          .forEach(function(btn) {
            resourcesGained.push(btn.dataset.resource);
          });
      }

      this._tradingPostState.resourcesGained = resourcesGained;
      this._submitTradingPostExchange();
    },

    _submitTradingPostExchange: function() {
      var self = this;
      var state = this._tradingPostState;
      var stateSnapshot = JSON.parse(JSON.stringify(state));
      var params = {
        resources_spent: JSON.stringify(state.resourcesSpent),
        resources_gained: JSON.stringify(state.resourcesGained),
        slot_number: state.slotNumber,
      };
      var previousBootyTokens = null;

      if (state.useBooty && this.booty_tokens && this.booty_tokens.length > 0) {
        previousBootyTokens = JSON.parse(JSON.stringify(this.booty_tokens));
        params.use_booty_card_id = this.booty_tokens[0].id;
        this.consumeBootyToken(params.use_booty_card_id);
      }

      this._tradingPostState = null;
      this.restoreServerGameState();
      var actionPromise = this.bgaPerformAction("actTradingPostExchange", params);
      if (actionPromise && typeof actionPromise.then === "function") {
        actionPromise.catch(function() {
          self._tradingPostState = stateSnapshot;
          if (previousBootyTokens) {
            self.booty_tokens = previousBootyTokens;
            self.updateMyBootyToken();
          }
          self.initTradingPost(stateSnapshot.slotNumber, stateSnapshot);
        });
      }
    },
  };
});
