/**
 * Treasure Seeker captain ability UI
 */
define(["dojo", "dojo/_base/declare", "dojo/dom-class", "dojo/dom-construct", "dojo/on", "dojo/query"], function (
  dojo,
  declare,
  domClass,
  domConstruct,
  on,
  query,
) {
  return {
    setupTreasureSeekerAdjust: function (args) {
      console.log("Setting up treasure seeker adjust");
      console.log(args);
      this.cleanupTreasureSeekerAdjust();
      if (!this.isCurrentPlayerActive()) return;

      var shipwreckId = "shipwreck_" + args.shipwreck_arg;
      if ($(shipwreckId)) {
        domClass.add(shipwreckId, "treasure_seeker_shipwreck_highlight");
      }

      this._treasureSeekerAdjustHandlers = [];

      if (args.valid_positions) {
        for (const pos of args.valid_positions) {
          var locId = "seaboardlocation_" + pos.x + "_" + pos.y;
          var markerId = "treasure_seeker_marker_" + pos.x + "_" + pos.y;
          domConstruct.create(
            "div",
            {
              id: markerId,
              class: "treasure_seeker_marker",
            },
            "seaboard",
          );
          this.placeOnObject(markerId, locId);
          var handler = on($(markerId), "click", () => {
            this.onTreasureSeekerPositionChosen(pos.x, pos.y);
          });
          this._treasureSeekerAdjustHandlers.push({ id: markerId, handler: handler });
        }
      }
    },

    cleanupTreasureSeekerAdjust: function () {
      console.log("Cleaning up treasure seeker adjust");
      query(".treasure_seeker_marker").forEach(domConstruct.destroy);
      query(".treasure_seeker_shipwreck_highlight").forEach(function (node) {
        domClass.remove(node, "treasure_seeker_shipwreck_highlight");
      });
      if (this._treasureSeekerAdjustHandlers) {
        for (var i = 0; i < this._treasureSeekerAdjustHandlers.length; i++) {
          this._treasureSeekerAdjustHandlers[i].handler.remove();
        }
        this._treasureSeekerAdjustHandlers = null;
      }
    },

    onTreasureSeekerPositionChosen: function (x, y) {
      console.log("Treasure seeker chose position:", x, y);
      if (this.checkAction("actAdjustShipwreck")) {
        this.bgaPerformAction("actAdjustShipwreck", {
          x: x,
          y: y,
        });
      }
    },

    onSkipTreasureSeekerAdjust: function () {
      if (this.checkAction("actSkipTreasureSeekerAdjust")) {
        this.bgaPerformAction("actSkipTreasureSeekerAdjust", {});
      }
    },
  };
});
