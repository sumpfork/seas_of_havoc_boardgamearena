/**
 * Seas of Havoc - Constants Module
 * Shared constants used throughout the game
 */

define([], function() {
  return {
    // Direction constants - must match PHP versions
    NORTH: 1,
    EAST: 2,
    SOUTH: 3,
    WEST: 4,
    
    // Convert direction to degrees for rotation
    getHeadingDegrees: function(direction) {
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
    }
  };
});









