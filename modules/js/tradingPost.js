/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/**
 * Seas of Havoc - Trading Post Module
 * Handles the trading post island slot: exchange up to 2 resources for others,
 * or spend a booty token to gain 2 resources. One resource per step in the action bar.
 */

define([
  g_gamethemeurl + "modules/js/constants.js",
], function (Constants) {
  // Debug logging only in Studio (see constants.js).
  const console = Constants.console;

  return {
    /**
     * Entry point: called on entering islandTurn with a pending trading post.
     * Starts the exchange over from its first step.
     */
    initTradingPost: function (slotNumber) {
      this._tradingPostState = { slotNumber, useBooty: false, resourcesSpent: [], resourcesGained: [], spendDone: false };
      if (this.getMyBootyTokenRes()) {
        this.setClientState("client_tradingPostBootyChoice", {
          descriptionmyturn: _("Island Phase: Use your booty token at the trading post?"),
        });
      } else {
        this.setClientState("client_tradingPost", { descriptionmyturn: "" });
      }
    },

    onTradingPostBootyYes: function () {
      this._tradingPostState.useBooty = true;
      this._tradingPostState.spendDone = true;
      this.setClientState("client_tradingPost", { descriptionmyturn: "" });
    },

    onTradingPostBootyNo: function () {
      this.setClientState("client_tradingPost", { descriptionmyturn: "" });
    },

    /**
     * The current step: a resource to trade away (first or second, with a skip), then one to
     * receive for each traded away. The title shows the trade so far.
     */
    showTradingPostStep: function () {
      const state = this._tradingPostState;
      const spent = state.resourcesSpent;
      const gained = state.resourcesGained;
      const icons = (resources) => resources.map((resource) => this.resourceIcon(resource)).join("");
      let trade = state.useBooty ? _("booty token") : icons(spent);
      if (gained.length) trade += " → " + icons(gained);

      this.statusBar.removeActionButtons();
      if (!state.spendDone) {
        const held = this.getPlayerResources();
        const left = (resource) => (parseInt(held[resource]) || 0) - spent.filter((r) => r === resource).length;
        const first = spent.length === 0;
        this.statusBar.setTitle(first
          ? _("Trading Post: ${you} may select a resource to trade away")
          : _("Trading Post (${trade}): ${you} may select a second resource to trade away"), { trade });
        this.addResourceButtons((resource) => {
          spent.push(resource);
          state.spendDone = spent.length === 2;
          this.showTradingPostStep();
        }, ["sail", "cannonball", "doubloon"].filter((resource) => left(resource) > 0));
        this.statusBar.addActionButton(first ? _("Don't trade") : _("Only one"), () => {
          if (first) {
            this.confirmIslandAction(_("Confirm no trade"), () => this._submitTradingPostExchange());
            return;
          }
          state.spendDone = true;
          this.showTradingPostStep();
        }, { color: "secondary" });
        return;
      }

      const receiving = state.useBooty ? 2 : spent.length;
      this.statusBar.setTitle(receiving === 1
        ? _("Trading Post (${trade}): ${you} must select the resource to receive")
        : gained.length === 0
          ? _("Trading Post (${trade}): ${you} must select the first resource to receive")
          : _("Trading Post (${trade}): ${you} must select the second resource to receive"), { trade });
      this.addResourceButtons((resource) => {
        gained.push(resource);
        if (gained.length < receiving) {
          this.showTradingPostStep();
          return;
        }
        this.confirmIslandAction(_("Confirm trade"), () => this._submitTradingPostExchange());
      });
    },

    _submitTradingPostExchange: function () {
      const state = this._tradingPostState;
      const params = {
        resources_spent: JSON.stringify(state.resourcesSpent),
        resources_gained: JSON.stringify(state.resourcesGained),
        slot_number: state.slotNumber,
      };
      let previousBootyTokens = null;

      if (state.useBooty && this.booty_tokens && this.booty_tokens.length > 0) {
        previousBootyTokens = JSON.parse(JSON.stringify(this.booty_tokens));
        params.use_booty_card_id = this.booty_tokens[0].id;
        this.consumeBootyToken(params.use_booty_card_id);
      }

      this._tradingPostState = null;
      this.restoreServerGameState();
      this.bgaPerformAction("actTradingPostExchange", params).catch(() => {
        if (previousBootyTokens) {
          this.booty_tokens = previousBootyTokens;
          this.updateMyBootyToken();
        }
        this.initTradingPost(state.slotNumber);
      });
    },
  };
});
