const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const path = require("node:path");

function loadModule(name) {
  let module;
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, "../js", name), "utf8"), {
    define: (dependencies, factory) => { module = factory(); },
    console: { log() {}, groupCollapsed() {}, groupEnd() {} },
    _: text => text,
    getLibUrl: name => name,
  });
  return module;
}

const notifications = loadModule("notifications.js");
const occupied = { shipyard: { n1: { occupying_player_id: "1", corsair_occupying_player_id: "2" } } };
const cleared = { shipyard: { n1: { occupying_player_id: null, corsair_occupying_player_id: null } } };
let renderedSlots;
const game = {
  market: { getCards: () => [] },
  islandSlots: occupied,
  players: {},
  gamedatas: { islandslots: occupied },
  positionMarketSkiffSlots() {},
  updateIslandSlots(slots) { renderedSlots = slots; },
};
notifications.notif_marketUpdated.call(game, { market: [], islandslots: cleared });
assert.equal(renderedSlots, cleared, "Refill must render cleared server slots, including Corsair overlays");
assert.equal(game.gamedatas.islandslots, cleared);
notifications.notif_marketUpdated.call(game, { market: [] });
assert.equal(renderedSlots, cleared, "Timely Trading must preserve slots when no slot update is sent");

const discarded = [];
let removed = 0;
const discardGame = {
  player_id: '1',
  playerDiscard: { addCard: card => discarded.push(card) },
  _removeCardFromSelectionOrPile() { removed++; },
  cleanupDiscardCardSelection() {},
};
notifications.notif_cardsDiscarded.call(discardGame, { player_id: '2', cards: [{ id: 47, type: 12 }] });
assert.equal(discarded.length, 0, "Another player's discard must not enter My Discard");
assert.equal(removed, 0);
notifications.notif_cardsDiscarded.call(discardGame, { player_id: 1, cards: [{ id: 29, type: 74 }] });
assert.equal(discarded[0].id, 29);
assert.equal(removed, 1, "Own discard must move out of hand");

const handlers = loadModule("stateHandlers.js");
const utils = loadModule("utils.js");
const buttons = [];
let action;
handlers.onUpdateActionButtons.call({
  resourceIcon: utils.resourceIcon,
  isCurrentPlayerActive: () => true,
  gamedatas: { players: { 2389208: { name: "pgorniak4" } } },
  statusBar: { addActionButton: (label, callback) => buttons.push({ label, callback }) },
  bgaPerformAction: (name, args) => { action = { name, args }; },
}, "boardingParty", { targets: [{ player_id: "2389208", resources: { sail: 1 }, booty_token_count: 1 }] });
assert.equal(buttons[0].label, "Steal 1 " + utils.resourceIcon("sail") + " from pgorniak4");
assert.equal(buttons[1].label, "Steal booty token from pgorniak4");
buttons[0].callback();
assert.equal(action.name, "actBoardingPartySteal");
assert.equal(action.args.target_player_id, "2389208", "Actions must still send the target ID");
// Resource labels must stay readable to assistive technology and preserve action payloads.
for (const resource of ["sail", "cannonball", "doubloon", "infamy"]) {
  assert.match(utils.resourceIcon(resource), new RegExp('aria-label="' + resource + '"'));
}
assert.throws(() => utils.resourceIcon("unknown"), /Unknown resource icon/);
const iconGame = {
  resourceIcon: utils.resourceIcon,
  isCurrentPlayerActive: () => true,
  statusBar: { addActionButton: (label, callback) => buttons.push({ label, callback }) },
  bgaPerformAction: (name, args) => { action = { name, args }; },
  canPlayerAfford: () => true,
  playable_cards: { 1: { cost: { sail: 2, cannonball: 1, doubloon: 3 } } },
  _pendingMerchantPurchase: { combinations: [{ cb: 2, sail: 1 }] },
  onMerchantSubstituteChosen: (cb, sail) => { action = { cb, sail }; },
  onMerchantSubstituteCancel() {},
};
buttons.length = 0;
handlers.onUpdateActionButtons.call(iconGame, "client_merchantSubstitute", {});
assert.equal(buttons[0].label, '2 ' + utils.resourceIcon('doubloon') + ' → 2 ' + utils.resourceIcon('cannonball') + ', 1 ' + utils.resourceIcon('doubloon') + ' → 1 ' + utils.resourceIcon('sail'));
buttons[0].callback();
assert.deepEqual(action, { cb: 2, sail: 1 });
buttons.length = 0;
handlers.onUpdateActionButtons.call(iconGame, "barter", { resources: { sail: 1, cannonball: 1, doubloon: 1 }, infamy: 3 });
assert.equal(buttons.length, 7);
for (const button of buttons.slice(0, 6)) {
  assert.equal((button.label.match(/role="img"/g) || []).length, 2);
  button.callback();
  assert.equal(action.name, "actBarterExchange");
}
buttons.length = 0;
handlers.onUpdateActionButtons.call(iconGame, "timelyTrading", { market: [{ id: 42, type: 1 }] });
assert.match(buttons[0].label, /Gain 2 <span/);
assert.equal((buttons[1].label.match(/role="img"/g) || []).length, 3);
buttons[1].callback();
assert.equal(action.args.card_id, 42);
buttons.length = 0;
handlers.onUpdateActionButtons.call(iconGame, "extortion", { pending_green: true, pending_red: true });
assert.equal(buttons.length, 3, "Extortion resource choices must render from server state, including after reload");
buttons[1].callback();
assert.equal(action.name, "actResourcePickedInDialog");
assert.equal(action.args.resource, "cannonball");
assert.equal(action.args.context, "extortion_green_flag");
const dialogs = loadModule("dialogs.js");
let scrapAction;
dialogs.confirmScrapCard.call({
  checkAction(name, silent) {
    if (name !== "actScrapCard") {
      assert.equal(silent, true, "Probing other scrap actions must not show an error during ordinary scrap");
      return false;
    }
    return true;
  },
  bgaPerformAction(name) { scrapAction = name; },
}, 30);
assert.equal(scrapAction, "actScrapCard");
for (const flag of ["green", "tan", "blue", "red"]) {
  buttons.length = 0;
  handlers.onUpdateActionButtons.call(iconGame, "cardFlag", { flag });
  assert.equal(buttons.length, flag === "green" ? 4 : flag === "red" ? 1 : 2);
  if (flag === "green") {
    assert.match(buttons[0].label, /aria-label="sail"/);
    buttons[0].callback();
    assert.equal(action.name, "actResolveCardFlag");
    assert.equal(action.args.resource, "sail");
  } else if (flag !== "red") {
    buttons[0].callback();
    assert.equal(action.name, "actResolveCardFlag");
  }
  buttons.at(-1).callback();
  assert.equal(action.name, "actSkipCardFlag");
}
dialogs.confirmScrapCard.call({
  checkAction: name => name === "actResolveCardFlag",
  bgaPerformAction(name, args) { action = { name, args }; },
}, 42);
assert.equal(action.name, "actResolveCardFlag");
assert.equal(action.args.card_id, 42);
const privateScraps = { available_cards: [{ id: 42, type: 19 }] };
let shownScraps;
handlers.onEnteringState.call({
  updateHandSelectionMode() {},
  isCurrentPlayerActive: () => true,
  setupScrapCardSelection: args => { shownScraps = args; },
}, "cardFlag", { args: { flag: "red", _private: privateScraps } });
assert.equal(shownScraps, privateScraps, "Red flag must use only the active player's private scrap choices");
let gameMethods;
const templates = {};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, "../../seasofhavoc.js"), "utf8"), {
  define: (dependencies, factory) => factory(...dependencies.map(name =>
    name === "dojo/_base/declare" ? (name, base, methods) => {
      gameMethods = methods;
      return { prototype: {} };
    } : {})),
  getLibUrl: name => name,
  g_gamethemeurl: "",
  ebg: { core: { gamegui: {} } },
  window: templates,
  _: text => text,
  console,
});
gameMethods.setupGameArea.call({ bga: { gameArea: { getElement: () => ({ insertAdjacentHTML() {} }) } } });
const formatBlock = (name, args) => templates[name].replace(/\$\{(\w+)\}/g, (_, key) => args[key]);
const logArgs = { resource_change: "gains 3 [skiff]", booty_usage: "1 [cannonball]" };
gameMethods.bgaFormatText.call({ format_block: formatBlock }, "resources", logArgs);
assert.match(logArgs.resource_change, /<svg /);
assert.match(logArgs.resource_change, /aria-label='skiff'/);
assert.doesNotMatch(logArgs.resource_change, /\bid=/, "Repeated log icons must not duplicate DOM IDs");
assert.match(logArgs.booty_usage, /log_resource cannonball/);
assert.match(formatBlock("jstpl_skiff", { id: "board-skiff", player_color: "ff0000" }), /fill:#ff0000/);
console.log("Playtest UI regressions passed");

const treasureSeeker = loadModule("treasureSeeker.js");
let relocationCleaned = false;
treasureSeeker.setupTreasureSeekerAdjust.call({
  cleanupTreasureSeekerAdjust() { relocationCleaned = true; },
  isCurrentPlayerActive() { return false; },
}, { shipwreck_arg: '0', valid_positions: [{ x: 1, y: 1 }] });
assert.equal(relocationCleaned, true, "Inactive players clear old relocation controls without creating new ones");

// Card play dialog: rows must render in tree order, each followed by the rows its options unlock.
const dialogGame = {
  resourceIcon: utils.resourceIcon,
  format_block: (name, args) => Object.entries(args).reduce((html, [k, v]) => html.split("${" + k + "}").join(v),
    name === "jstpl_card_choices_row" ? '<div class="card_choices_row">${card_choices}</div>'
      : '<input id="${id}" name="${name}" value="${value}"/><label>${label}</label>'),
  _makeCardDependencyTree: dialogs._makeCardDependencyTree,
  _renderCardChoiceRows: dialogs._renderCardChoiceRows,
  _choiceLabelHtml: dialogs._choiceLabelHtml,
  _choiceGlyph: dialogs._choiceGlyph,
  _hoistCardPassOption: dialogs._hoistCardPassOption,
};
const renderRows = actions =>
  dialogGame._renderCardChoiceRows(dialogGame._makeCardDependencyTree.call(dialogGame, actions));

const twoFires = renderRows([
  { action: "fire", range: 3, cost: { cannonball: 1 } },
  { action: "fire", range: 2, cost: { cannonball: 1 } },
]);
assert.equal(twoFires.length, 2);
assert.ok(twoFires[0].includes("card_choice_0_option_0"), "sibling rows must keep tree order");
assert.ok(twoFires[1].includes("card_choice_1_option_0"));

// Market card 58: one choice, each branch unlocking its own side/skip row.
const choiceRows = renderRows([
  { action: "choice", choices: [
    { action: "fire", range: 3, cost: { cannonball: 1 } },
    { action: "2 x fire", range: 2, cost: { cannonball: 2 } },
  ] },
]);
assert.equal(choiceRows.length, 3);
assert.ok(choiceRows[0].includes('value="2 x fire"'), "the choice itself comes first");
assert.ok(choiceRows[1].includes('value="fire left"'), "then the rows its first option unlocks");
assert.ok(choiceRows[2].includes('value="2 x fire right"'));
assert.ok(choiceRows[2].includes("range 2") && choiceRows[2].includes('data-resource="cannonball"'),
  "chips must show range and cost");

// A card that only does optional things is passable from its options line; the redundant per-branch
// "skip" chips go away. A card with a mandatory action keeps them - there, skipping is not passing.
const passRows = actions => {
  const tree = dialogGame._makeCardDependencyTree.call(dialogGame, actions);
  const hoisted = dialogGame._hoistCardPassOption(actions, tree);
  return { hoisted, rows: dialogGame._renderCardChoiceRows(tree) };
};
const optional = passRows([
  { action: "choice", choices: [
    { action: "fire", range: 3, cost: { cannonball: 1 } },
    { action: "2 x fire", range: 2, cost: { cannonball: 2 } },
  ] },
]);
assert.equal(optional.hoisted, true);
assert.ok(optional.rows[0].includes('value="pass"'), "an all-optional card passes from its first row");
assert.ok(!optional.rows.join("").includes('value="skip"'), "no per-branch skips duplicating the pass");
const mandatory = passRows([
  { action: "forward" },
  { action: "fire", range: 3, cost: { cannonball: 1 } },
]);
assert.equal(mandatory.hoisted, false);
assert.ok(mandatory.rows[0].includes('value="skip"'), "skipping the fire still sails the card's move");
assert.ok(!mandatory.rows.join("").includes('value="pass"'));

// Selection dialogs show previews under display ids: the real cards must stay in the hand, or the
// stock books a card it never receives the element for and the dialog renders empty.
const added = [];
const previews = dialogs._addPreviewCards.call({}, { addCard: c => added.push(c) }, {
  11: { id: "11", type: "77", location: "hand" },
  12: { id: "12", type: "11", location: "player_discard" },
}, card => card.location === "hand");
assert.equal(added.length, 1, "filtered cards are skipped");
assert.equal(added[0].id, 30011, "stocks get a display id, never the real one");
assert.equal(added[0].type, "77");
assert.equal(previews.get(30011).id, "11", "display id maps back to the real card");
assert.equal(previews.size, 1);

// Clicking a pile opens the bounded viewer, and must beat the top card's own zoom handler.
let opened = null;
let stopped = false;
const pileEl = { listeners: {}, setAttribute() {}, addEventListener(type, fn, capture) { this.listeners[type] = { fn, capture }; } };
dialogs.bindPileViewer.call({ showPileDialog: (title, cards) => { opened = { title, cards }; } },
  pileEl, "My Discard", () => ({ getCards: () => [{ id: "7", type: "3" }] }));
assert.equal(pileEl.listeners.click.capture, true, "capture phase, or the card zoom wins the click");
pileEl.listeners.click.fn({ stopPropagation: () => { stopped = true; }, preventDefault() {} });
assert.equal(stopped, true);
assert.deepEqual(opened.cards.map(c => c.id), ["7"]);
assert.equal(opened.title, "My Discard");

// Booty is offered only when it helps: spent outright when the cost is otherwise unaffordable,
// asked about when either way works, skipped when the token cannot cover any of the cost.
const bootyCalls = [];
const bootyGame = {
  getMyBootyTokenRes: () => ({ sail: 1 }),
  bootyOverlapsCost: (tokenRes, cost) => Object.keys(cost).some(r => tokenRes[r] > 0),
  canPlayerAfford: () => true,
  setClientState: (state, args) => bootyCalls.push({ state, args }),
  _sendWithOptionalBooty: dialogs._sendWithOptionalBooty,
  _resolveBootyChoice: dialogs._resolveBootyChoice,
  restoreServerGameState() {},
};
let sent = null;
bootyGame._sendWithOptionalBooty.call(bootyGame, { cannonball: 1 }, useBooty => { sent = useBooty; }, "q");
assert.equal(sent, false, "a token that covers none of the cost must not trigger the prompt");
assert.equal(bootyCalls.length, 0);

sent = null;
bootyGame._sendWithOptionalBooty.call(bootyGame, { sail: 1 }, useBooty => { sent = useBooty; }, "q");
assert.equal(sent, null, "affordable either way: ask first");
assert.equal(bootyCalls[0].state, "client_bootyPlayConfirm");
bootyGame._resolveBootyChoice.call(bootyGame, true);
assert.equal(sent, true, "answering yes sends with the booty token");

sent = null;
bootyGame.canPlayerAfford = () => false;
bootyGame._sendWithOptionalBooty.call(bootyGame, { sail: 1 }, useBooty => { sent = useBooty; }, "q");
assert.equal(sent, true, "unaffordable without booty: spend it without asking");
