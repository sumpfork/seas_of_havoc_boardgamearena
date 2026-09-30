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
