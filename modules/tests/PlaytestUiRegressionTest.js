const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const path = require("node:path");

// `deps` supplies stubs for the dojo modules a handler actually uses, keyed by the tail of the
// dependency path ("dojo/_base/fx" -> "fx"). Anything not supplied stays undefined, as before.
function loadModule(name, deps = {}) {
  let module;
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, "../js", name), "utf8"), {
    define: (dependencies, factory) => {
      module = factory(...dependencies.map(dep => deps[dep.split("/").pop()]));
    },
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
  restoreServerGameState() {},
  setClientState() {},
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
// Extortion uses every flag you control, in an order you choose: one button per flag, plus skip.
let chosenFlag = null;
iconGame.onExtortionFlagChosen = flag => { chosenFlag = flag; };
handlers.onUpdateActionButtons.call(iconGame, "extortion", { pending_flags: ["green", "red", "tan"] });
assert.equal(buttons.length, 4, "a button per pending flag, in any order, plus skip");
buttons[2].callback();
assert.equal(chosenFlag, "tan", "the player picks which flag resolves next");
buttons[3].callback();
assert.equal(action.name, "actSkipExtortion");
buttons.length = 0;
handlers.onUpdateActionButtons.call(iconGame, "client_extortionGreenResource", {});
assert.equal(buttons.length, 4, "three resources and a way back");
buttons[1].callback();
assert.equal(action.name, "actExtortionUseFlag");
assert.deepEqual({ ...action.args }, { flag: "green", resource: "cannonball" });
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
  highlightActivePlayerShips() {},
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
  _choiceName: dialogs._choiceName,
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

// Every optional action keeps its own "skip" chip; passing the whole card is the dialog's button.
const skipRows = actions =>
  dialogGame._renderCardChoiceRows(dialogGame._makeCardDependencyTree.call(dialogGame, actions)).join("");
const optionalRows = skipRows([
  { action: "choice", choices: [
    { action: "fire", range: 3, cost: { cannonball: 1 } },
    { action: "2 x fire", range: 2, cost: { cannonball: 2 } },
  ] },
]);
assert.ok(optionalRows.includes('value="skip"'), "an all-optional card still offers skip on its row");
assert.ok(!optionalRows.includes('value="pass"'), "passing is the button's job, not a row option");

// Moves the card always makes get a locked row of their own; moves inside a choice do not.
const fixedTree = dialogGame._makeCardDependencyTree.call(dialogGame, [
  { action: "forward" },
  { action: "fire", range: 3, cost: { cannonball: 1 } },
  { action: "choice", choices: [{ action: "left" }, { action: "right" }] },
]);
const rowsOf = tree => [...tree.values()].map(options => options.map(o => o.name + (o.fixed ? " (fixed)" : "")));
assert.deepEqual(JSON.parse(JSON.stringify(rowsOf(fixedTree))), [
  ["forward (fixed)"],
  ["fire left", "fire right", "skip"],
  ["left", "right"],
], "the fixed forward is listed, with no skip; the choice's options add no rows of their own");

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

// Damage cards must reach the discard pile as cards; a typeless entry renders face down.
const damageAdded = [];
notifications.notif_damageReceived.call({
  player_id: "1",
  playerDiscard: { addCard: card => damageAdded.push(card) },
  updateDamageDeckCount() {},
}, { player_id: "1", damage_card: { id: "88", type: "0" }, damage_deck_size: 17 });
assert.equal(damageAdded.length, 1);
assert.equal(damageAdded[0].id, "88");
assert.equal(damageAdded[0].type, "0", "the card keeps its type, or the pile shows a card back");


// A card that moves and then fires must show the shot after the move: every effect is queued into
// the animation chain, not played the moment the notification arrives.
const played = [];
const anim = label => ({ label, play() { played.push(label); } });
const fxStub = {
  chain: list => anim("chain(" + list.map(a => a.label).join(",") + ")"),
  combine: list => anim("combine(" + list.map(a => a.label).join(",") + ")"),
};
const seqNotifications = loadModule("notifications.js", {
  fx: fxStub,
  dom: { byId: () => ({}) },
  "dom-construct": { place() {}, destroy() {} },
  "dom-style": { set() {} },
  "dom-class": { add() {}, remove() {} },
  "dom-attr": { set() {}, get() {} },
  query: () => ({ forEach() {} }),
});
// notifications.js takes dojo/_base/fx as `baseFX` and dojo/fx as `fx`; both tails collide, so the
// fade helpers are stubbed on the same object.
fxStub.fadeIn = settings => anim("fadeIn");
fxStub.fadeOut = settings => anim("fadeOut");
fxStub.Animation = function (settings) { return anim("turn"); };

const chained = [];
seqNotifications.notif_cardPlayed.call({
  slideToObject: () => anim("slide"),
  getHeadingDegrees: () => 0,
  getObjectOnSeaboard: () => ({ heading: 1 }),
  format_block: () => "<div></div>",
  _nextEffectId: () => 1,
  explosionAnimation: (x, y, extraClass) => anim(extraClass ? "blast:" + extraClass : "blast"),
  shotAnimation: () => anim("shot"),
  // Present so that firing effects played immediately show up in `played` ahead of the moves,
  // which is exactly what this test is here to rule out.
  animateExplosionAt: () => { played.push("blast-played-immediately"); },
  applyShipwreckEvents() {},
}, {
  player_id: "1",
  moveChain: [
    { type: "move", new_x: 1, new_y: 1 },
    { type: "fire_hit", fire_heading: 1, hit_x: 2, hit_y: 2 },
    { type: "move", new_x: 3, new_y: 1 },
    { type: "fire_miss", fire_heading: 1, miss_x: 4, miss_y: 1 },
    { type: "collision", collision_x: 5, collision_y: 1 },
  ],
  shipwreck_event: null,
});
assert.deepEqual(played, [
  "chain(slide,chain(shot,blast),slide,chain(shot,blast:splash),blast:ram)",
], "one chain plays, holding every effect in moveChain order");

// A row where nothing paid is affordable: the skip chip says so and is picked, so the player is
// not left hunting for the one enabled radio.
const nodes = {};
const node = (id, disabled) => (nodes[id] = {
  id, disabled, checked: false, dataset: {}, chipText: { textContent: "" },
  parentNode: { id: id + "_container", parentNode: { display: "" } },
});
const affordDialogs = loadModule("dialogs.js", {
  dom: { byId: id => nodes[id] },
  "dom-style": { get: container => container.display },
  query: (sel, container) => [nodes[container.id.replace("_container", "")].chipText],
});
const row = (fireDisabled) => new Map([["card_choice_0", [
  { name: "fire left", id: "fire", cost: { cannonball: 1 }, children: new Map() },
  { name: "skip", id: "skip", children: new Map() },
]]].map(([k, v]) => { node("fire", fireDisabled); node("skip", false); return [k, v]; }));

let tree = row(true);
assert.equal(affordDialogs._markUnaffordableRows(tree), true, "an unaffordable row ticks its skip");
assert.equal(nodes.skip.checked, true);
assert.equal(nodes.skip.chipText.textContent, "can’t afford");
assert.equal(affordDialogs._markUnaffordableRows(tree), false, "already ticked: nothing left to change");

tree = row(false);
assert.equal(affordDialogs._markUnaffordableRows(tree), false, "an affordable row is left alone");
assert.equal(nodes.skip.checked, false);
assert.equal(nodes.skip.chipText.textContent, "skip");

// Something else on the card stops needing the resources (its row got hidden): the automatic
// "can't afford" pick is taken back, leaving the row unanswered again.
tree = row(true);
affordDialogs._markUnaffordableRows(tree);
nodes.fire.disabled = false;
assert.equal(affordDialogs._markUnaffordableRows(tree), true, "an automatic pick is undone once affordable");
assert.equal(nodes.skip.checked, false);
assert.equal(nodes.skip.chipText.textContent, "skip");

// A skip the player picked themselves stays picked.
tree = row(false);
nodes.skip.checked = true;
assert.equal(affordDialogs._markUnaffordableRows(tree), false, "the player's own skip is left alone");
assert.equal(nodes.skip.checked, true);

// Card play preview: the client's copy of the movement rules must agree with the server's.
{
  // The module runs in its own vm context, whose arrays fail strict deepEqual here: copy them out.
  const preview = loadModule("cardPreview.js");
  const simulateCardPlay = (...args) => JSON.parse(JSON.stringify(preview.simulateCardPlay(...args)));
  const N = 1, E = 2, S = 3, W = 4;
  const open = () => false;
  const routes = (marks) => marks.filter(m => m.type === "route").map(m => m.points);
  const only = (marks, type) => marks.filter(m => m.type === type).map(m => [m.x, m.y, m.heading]);

  // Seen on Studio: facing north at (1,5), "left" ended at (0,4) facing west.
  let marks = simulateCardPlay([{ action: "left" }], [], { x: 1, y: 5, heading: N }, open);
  assert.deepEqual(routes(marks), [[[1, 5], [1, 4], [0, 4]]], "left is one route bending round the corner");
  assert.deepEqual(marks.filter(m => m.type === "pivot"), [], "the pivot in left is shown by the bend, not an arc");
  assert.deepEqual(only(marks, "ghost"), [[0, 4, W]], "the ghost ship shows where the move ends");

  marks = simulateCardPlay([{ action: "forward" }], [], { x: 3, y: 0, heading: N }, open);
  assert.deepEqual(only(marks, "ghost"), [[3, 5, N]], "moving off the top edge comes back in at the bottom");
  assert.deepEqual(routes(marks), [[[3, 0], [3, -0.5]], [[3, 5.5], [3, 5]]],
    "a route off an edge stops at the edge and carries on from the opposite one");

  marks = simulateCardPlay([{ action: "forward" }, { action: "forward" }], [], { x: 2, y: 2, heading: E },
    (x, y) => x === 3 && y === 2);
  assert.deepEqual(marks.map(m => m.type), ["collision", "route"], "a ram stops the card and the ship does not move");
  assert.deepEqual(routes(marks), [[[2, 2], [2.55, 2]]], "the arrow runs on to the edge of the rammed square");

  const choice = [{ action: "choice", choices: [{ action: "pivot left" }, { action: "pivot right" }] }];
  assert.deepEqual(simulateCardPlay(choice, [], { x: 0, y: 0, heading: N }, open), [],
    "nothing is drawn for a choice not yet made");
  marks = simulateCardPlay(choice, ["pivot right"], { x: 0, y: 0, heading: N }, open);
  assert.deepEqual(only(marks, "ghost"), [[0, 0, E]], "the chosen option is what is drawn");
  assert.deepEqual(marks.filter(m => m.type === "pivot").map(m => [m.turn, m.from]), [["pivot right", N]],
    "a pivot on the spot gets its own arc, starting from the old heading");

  const fire = [{ action: "fire", range: 3, cost: { cannonball: 1 } }];
  marks = simulateCardPlay(fire, ["fire left"], { x: 2, y: 2, heading: N }, open);
  assert.deepEqual(only(marks, "chevron"), [[1, 2, W], [0, 2, W], [5, 2, W]], "a shot's range is marked, wrapping");
  assert.deepEqual(marks.find(m => m.type === "shot").lines,
    [[[1.65, 2], [1, 2], [0, 2], [-0.5, 2]], [[5.5, 2], [5, 2], [4.55, 2]]],
    "the shot line starts just outside the ship, breaks at the edge, and ends at the far edge of its range");
  assert.deepEqual(simulateCardPlay(fire, ["skip"], { x: 2, y: 2, heading: N }, open), [], "a skipped shot draws nothing");

  const bothSides = [{ action: "2 x fire", range: 2, variants: [
    { name: "both sides", range: 1, count: 2, sides: ["left", "right"], both_sides: true },
  ] }];
  marks = simulateCardPlay(bothSides, ["both sides left"], { x: 2, y: 2, heading: N }, open);
  assert.deepEqual(only(marks, "chevron"), [[1, 2, W], [3, 2, E]], "both sides fires one shot each way");

  assert.deepEqual(simulateCardPlay([{ action: "forward" }], ["pass"], { x: 0, y: 0, heading: N }, open), [],
    "passing the card shows nothing");
}
