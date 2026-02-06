 /**
 * Seas of Havoc - Utility Methods Module
 * Helper functions for resource management and game state utilities
 */

define([
  "dojo/dom",
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/dom-style",
  "dojo/query",
], function(dom, domClass, domConstruct, domStyle, query) {
  
  return {
    /**
     * Find an object on the seaboard by type and arg
     */
    getObjectOnSeaboard: function(object_type, arg) {
      for (const entry of this.seaboard) {
        if (entry.type == object_type && entry.arg == arg) {
          return entry;
        }
      }
    },

    /**
     * Update unique token displays (first player, etc.)
     */
    updateUniqueTokens: function(unique_tokens) {
      console.log("updating unique tokens");
      console.log(unique_tokens);
      for (const [token_key, player_id] of Object.entries(unique_tokens)) {
        console.log("token id: " + token_key + " player id: " + player_id);
        if (player_id === null) {
          continue;
        }
        var token_html = this.format_block("jstpl_unique_token", { token_key: token_key });
        var token_element = domConstruct.place(token_html, `player_token_board_p${player_id}`);
        this.placeOnObject(token_element, `${token_key}_p${player_id}`);
        domStyle.set(token_element, "zIndex", 1);
      }
    },

    setBootyTokenImage: function(node, imageId) {
      console.log("[booty] setBootyTokenImage", { imageId });
      const index = parseInt(imageId, 10);
      if (Number.isNaN(index)) {
        console.warn("[booty] setBootyTokenImage invalid imageId", imageId);
        return;
      }
      const col = index % 2;
      const row = Math.floor(index / 2);
      const spriteX = (col + 1) * 63;
      const spriteY = row * 63;
      domStyle.set(node, "backgroundPosition", `-${spriteX}px -${spriteY}px`);
    },

    // Scaled sprite positioning for 50px slot (background-size: 150px 200px)
    setBootyTokenImageForSlot: function(node, imageId) {
      console.log("[booty] setBootyTokenImageForSlot", { imageId });
      const index = parseInt(imageId, 10);
      if (Number.isNaN(index)) {
        console.warn("[booty] setBootyTokenImageForSlot invalid imageId", imageId);
        return;
      }
      const col = index % 2;
      const row = Math.floor(index / 2);
      // Scaled for 150x200 background-size (50px tokens instead of 63px)
      const spriteX = (col + 1) * 50;
      const spriteY = row * 50;
      domStyle.set(node, "backgroundPosition", `-${spriteX}px -${spriteY}px`);
    },

    createBootyTokenNode: function(isBack, imageId) {
      if (isBack) {
        console.log("[booty] createBootyTokenNode facedown (back art)");
      } else {
        console.log("[booty] createBootyTokenNode faceup", { imageId });
      }
      const classes = isBack ? "booty-token booty-token-back" : "booty-token";
      const node = domConstruct.create("div", { className: classes });
      if (!isBack) {
        this.setBootyTokenImage(node, imageId);
      }
      return node;
    },

    updateMyBootyToken: function(typeArgOverride) {
      console.groupCollapsed("[booty] updateMyBootyToken");
      const tokens = this.booty_tokens || [];
      const mySlot = dom.byId(`booty_token_p${this.player_id}`);
      // Use override if provided, otherwise use lastBootyTokenTypeArg, otherwise use first token
      let typeArg = typeArgOverride;
      if (typeArg === undefined && this.lastBootyTokenTypeArg !== undefined) {
        typeArg = this.lastBootyTokenTypeArg;
      }
      if (typeArg === undefined && tokens.length > 0) {
        typeArg = tokens[0].type_arg;
      }
      console.log("[booty] typeArg:", typeArg, "tokens:", tokens);
      domConstruct.empty(mySlot);
      domClass.remove(mySlot, "has-token");
      if (typeArg !== undefined) {
        const node = this.createBootyTokenNode(false, typeArg);
        this.setBootyTokenImageForSlot(node, typeArg);
        domConstruct.place(node, mySlot);
        domClass.add(mySlot, "has-token");
      } else if (tokens.length > 0) {
        // Fallback: show facedown token only if we KNOW player has tokens
        console.warn("[booty] typeArg undefined but has tokens, showing facedown as fallback");
        const node = this.createBootyTokenNode(true, null);
        domConstruct.place(node, mySlot);
        domClass.add(mySlot, "has-token");
      }
      console.groupEnd();
    },

    renderFacedownTokenForPlayer: function(playerId) {
      console.log("[booty] renderFacedownTokenForPlayer", playerId);
      const slot = dom.byId(`booty_token_p${playerId}`);
      domConstruct.empty(slot);
      const node = this.createBootyTokenNode(true, null);
      domConstruct.place(node, slot);
      domClass.add(slot, "has-token");
    },

    animateBootyTokenPickup: function(event, playerId) {
      if (!event) {
        return;
      }
      console.groupCollapsed("[booty] animateBootyTokenPickup");
      console.log("event", event, "playerId", playerId);
      const targetSlotId = `booty_token_p${playerId}`;
      const seaboardNode = dom.byId("seaboard");
      const isOwner = playerId == this.player_id;
      const faceupTypeArg = isOwner ? this.lastBootyTokenTypeArg : null;
      console.log("[booty] pickup animation", { isOwner, faceupTypeArg });
      const tokenNode = isOwner && faceupTypeArg != null
        ? this.createBootyTokenNode(false, faceupTypeArg)
        : this.createBootyTokenNode(true, null);
      const tokenId = `booty_pickup_${playerId}_${event.shipwreck_arg}_${Date.now()}`;
      tokenNode.id = tokenId;
      domClass.add(tokenNode, "booty-token-pickup");
      const overlayRoot = dom.byId("overall_game") || seaboardNode;
      domConstruct.place(tokenNode, overlayRoot);
      const startId = `seaboardlocation_${event.old_x}_${event.old_y}`;
      this.placeOnObject(tokenId, startId);
      const anim = this.slideToObject(tokenId, targetSlotId, 1000);
      const self = this;
      anim.onEnd = function() {
        console.log("[booty] animation onEnd", {tokenId, playerId, myPlayerId: self.player_id, lastTypeArg: self.lastBootyTokenTypeArg});
        if (!self.debugBootyPickup) {
          domConstruct.destroy(tokenId);
        }
        if (String(playerId) === String(self.player_id)) {
          self.updateMyBootyToken();
        } else {
          self.renderFacedownTokenForPlayer(playerId);
        }
      };
      anim.play();
      console.groupEnd();
    },

    applyShipwreckEvents: function(events) {
      if (!events || !events.length) {
        return;
      }
      console.groupCollapsed("[booty] applyShipwreckEvents");
      console.log(events);
      for (const event of events) {
        console.log("[booty] applyShipwreckEvent", event);
        const shipwreckId = `shipwreck_${event.shipwreck_arg}`;
        if (dom.byId(shipwreckId)) {
          domConstruct.destroy(shipwreckId);
        }
        const targetId = `seaboardlocation_${event.new_x}_${event.new_y}`;
        const seafeature = this.format_block("jstpl_seafeature", {
          id: shipwreckId,
          seafeature_type: "shipwreck",
        });
        domConstruct.place(seafeature, "seaboard");
        this.placeOnObject(shipwreckId, targetId);
        let updated = false;
        for (const entry of this.seaboard) {
          if (entry.type === "shipwreck" && entry.arg == event.shipwreck_arg) {
            entry.x = event.new_x;
            entry.y = event.new_y;
            updated = true;
            break;
          }
        }
        if (!updated) {
          this.seaboard.push({
            type: "shipwreck",
            arg: event.shipwreck_arg,
            x: event.new_x,
            y: event.new_y,
            heading: 0,
          });
        }
      }
      console.groupEnd();
    },

    /**
     * Update resource count displays for all players
     */
    updateResources: function(resources) {
      console.log("updating resources");
      console.log(resources);
      for (const resource of resources) {
        console.log(resource);
        console.log(`${resource.resource_key}count_p${resource.player_id}`);
        document.getElementById(`${resource["resource_key"]}count_p${resource["player_id"]}`).innerText =
          resource.resource_count;
      }
    },

    /**
     * Update deck card count display
     */
    updateDeckCount: function(deckSize) {
      console.log("updating deck count");
      console.log("deck size: " + deckSize);
      const deckSizeNum = parseInt(deckSize, 10);
      this.playerDeck.setCardNumber(deckSizeNum);
    },

    /**
     * Get current player's resources as an object
     */
    getPlayerResources: function() {
      var playerResources = {};
      console.log("active player id: " + this.getActivePlayerId());
      console.log("this.player_id: " + this.player_id);
      for (const resource of this.resources) {
        if (resource.player_id == this.player_id) {
          playerResources[resource.resource_key] = resource.resource_count;
        }
      }
      return playerResources;
    },

    /**
     * Add two resource objects together
     */
    addResources: function(r1, r2) {
      var sum = {};
      for (const [resource_key, num] of Object.entries(r1)) {
        sum[resource_key] = num;
      }
      for (const [resource_key, num] of Object.entries(r2)) {
        if (Object.hasOwn(sum, resource_key)) {
          sum[resource_key] += num;
        } else {
          sum[resource_key] = num;
        }
      }
      return sum;
    },

    /**
     * Spend resources for current player (local update)
     */
    playerSpendResources: function(resource_cost) {
      console.log("player " + this.player_id + " spending resources:");
      console.log(resource_cost);
      for (var resource of this.resources) {
        if (resource.player_id == this.player_id) {
          if (!Object.hasOwn(resource_cost, resource.resource_key)) {
            continue;
          }
          var diff = resource.resource_count - resource_cost[resource.resource_key];
          console.log("diff for " + resource.resource_key + ": " + diff);
          if (diff < 0) {
            this.showMessage(_("Player tried to spend more than they have"), "error");
          }
          resource.resource_count = diff;
        }
      }
      this.updateResources(this.resources);
    },

    /**
     * Check if current player can afford a resource cost
     */
    canPlayerAfford: function(resource_cost) {
      var playerResources = this.getPlayerResources();
      console.log("checking cost, player resources are");
      console.log(playerResources);
      console.log("cost is");
      console.log(resource_cost);
      if (typeof resource_cost === "undefined") {
        return true;
      }
      for (const [resource_key, num] of Object.entries(resource_cost)) {
        var player_has = playerResources[resource_key] || 0;
        if (player_has - num < 0) {
          console.log("not enough " + resource_key + " (" + player_has + " vs " + num + ")");
          return false;
        }
      }
      return true;
    }
  };
});









