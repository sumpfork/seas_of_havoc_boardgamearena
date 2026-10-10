/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/**
 * Seas of Havoc - Constants Module
 * Shared constants used throughout the game
 */

define([], function () {
  // Debug logging is on in Studio and off in production. To turn it on in production, run
  // localStorage.setItem("soh_debug", "1") in the game frame's console and reload.
  let debugLogging = false;
  try {
    debugLogging = location.hostname.startsWith("studio.") || localStorage.getItem("soh_debug") === "1";
  } catch (e) {
    // No location or storage (tests, sandboxed frames): stay quiet.
  }
  const silent = () => {};
  const FIRE_COUNTS = { fire: 1, "2 x fire": 2, "3 x fire": 3 };

  /** A fire action's shots. Without ship upgrades the server sends none: just the plain shot. */
  const fireVariants = (action) => action.variants || [{
    name: action.action, range: action.range, cost: action.cost, count: FIRE_COUNTS[action.action],
    shot: "cannon", sides: ["left", "right"], both_sides: false,
  }];

  return {
    /**
     * What the modules use as `console`: the real one when debugging, otherwise one whose chatty
     * methods do nothing. Warnings and errors always get through.
     */
    console: debugLogging
      ? globalThis.console
      : {
          log: silent,
          groupCollapsed: silent,
          groupEnd: silent,
          warn: (...args) => globalThis.console.warn(...args),
          error: (...args) => globalThis.console.error(...args),
        },

    FIRE_COUNTS: FIRE_COUNTS,
    fireVariants: fireVariants,

    /**
     * The decisions a card action accepts, as the server checks them (CardPlay::actionOptions). A
     * choice offers its options by name (`choice` holds the option's action), fire each shot to each
     * side, anything else itself. A cost makes the action optional: "skip".
     */
    actionOptions: function (action) {
      let options;
      if (action.action === "choice") {
        options = action.choices.map((choice) => ({ name: choice.name || choice.action, choice: choice }));
      } else if (Object.hasOwn(FIRE_COUNTS, action.action)) {
        options = fireVariants(action).flatMap((variant) =>
          variant.sides.map((side) => ({ name: variant.name + " " + side, cost: variant.cost, range: variant.range })));
      } else {
        options = [{ name: action.name || action.action, cost: action.cost, range: action.range }];
      }
      if (action.cost !== undefined) options.push({ name: "skip" });
      return options;
    },

    /** Whether an action takes a decision: a choice, or more than one option to pick from. */
    needsDecision: function (action) {
      return action.action === "choice" || this.actionOptions(action).length > 1;
    },

    // Direction constants - must match PHP versions
    NORTH: 1,
    EAST: 2,
    SOUTH: 3,
    WEST: 4,

    // Convert direction to degrees for rotation
    getHeadingDegrees: function (direction) {
      var deg;
      switch (Number(direction)) {
        case 1: // NORTH
          deg = 90;
          break;
        case 2: // EAST
          deg = 180;
          break;
        case 3: // SOUTH
          deg = 270;
          break;
        case 4: // WEST
          deg = 0;
          break;
        default:
          console.error("couldn't convert " + direction + " to degrees");
          return 0;
      }
      console.log(deg + " degrees");
      return deg;
    },
  };
});
