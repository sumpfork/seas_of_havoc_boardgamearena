/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SeasOfHavoc implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * seasofhavoc.js
 *
 * SeasOfHavoc user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

//could maybe share these via bga mechanisms
//but it's awkward, these have to match the php versions though
const NORTH = 1;
const EAST = 2;
const SOUTH = 3;
const WEST = 4;

define([
  "dojo",
  "dojo/_base/declare",
  "dojo/on",
  "dojo/dom",
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/html",
  "dojo/dom-style",
  "dojo/dom-attr",
  "dojo/_base/lang",
  "dojo/query",
  "dojo/_base/fx",
  "dojo/fx",
  "dojo/aspect",
  "dojo/NodeList-traverse",
  "dojo/NodeList-data",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/stock",
], function (dojo, declare, on, dom, domClass, domConstruct, html, domStyle, attr, lang, query, baseFX, fx, aspect) {
  return declare("bgagame.seasofhavoc", ebg.core.gamegui, {
    constructor: function () {
      console.log("seasofhavoc constructor");

      // Here, you can init the global variables of your user interface
      // Example:
      // this.myGlobalValue = 0;
      this.clientStateVars = {};
    },
    getHeadingDegrees: function (direction) {
      switch (Number(direction)) {
        case NORTH:
          deg = 90;
          break;
        case EAST:
          deg = 180;
          break;
        case SOUTH:
          deg = 270;
          break;
        case WEST:
          deg = 0;
          break;
        default:
          console.error("couldn't convert " + direction + " to degrees");
      }
      console.log(deg + " degrees");
      return deg;
    },
    getObjectOnSeaboard: function (object_type, arg) {
      for (const entry of this.seaboard) {
        if (entry.type == object_type && entry.arg == arg) {
          return entry;
        }
      }
    },
    updateUniqueTokens: function (unique_tokens) {
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
    updateResources: function (resources) {
      console.log("updating resources");
      console.log(resources);
      for (const resource of resources) {
        console.log(resource);
        console.log(`${resource.resource_key}count_p${resource.player_id}`);
        document.getElementById(`${resource["resource_key"]}count_p${resource["player_id"]}`).innerText =
          resource.resource_count;
      }
    },

    updateIslandSlots: function (islandslots, players) {
      console.log("updating island slots");
      console.log(islandslots);
      console.log(players);
      for (const [slot, numbers] of Object.entries(islandslots)) {
        for (const [number, occupant] of Object.entries(numbers)) {
          console.log("occupant: " + occupant);
          if (occupant != null) {
            var skiff_id = "skiff_p" + occupant + "_" + slot;
            console.log("skiff_id: " + skiff_id);

            var skiff = this.format_block("jstpl_skiff", {
              player_color: players[occupant].color,
              id: skiff_id,
            });
            domConstruct.place(skiff, "skiff_slot_" + slot + "_" + number);
          }
        }
      }
    },
    updateDeckCount: function () {
      console.log("updating deck count");
      console.log("deck size: " + this.deckSize);
      document.getElementById("deck_count").innerText = "(" +this.deckSize + " Cards)";
    },
    getPlayerResources: function () {
      var playerResources = {};
      console.log("adtive player id: " + this.getActivePlayerId());
      console.log("this.player_id: " + this.player_id);
      for (const resource of this.resources) {
        if (resource.player_id == this.player_id) {
          playerResources[resource.resource_key] = resource.resource_count;
        }
      }
      return playerResources;
    },
    addResources: function (r1, r2) {
      var sum = {};
      for (const [resource_key, num] of Object.entries(r1)) {
        sum[resource_key] = num;
      }
      for (const [resource_key, num] of Object.entries(r2)) {
        if (Object.hasOwn(resource_key)) sum[resource_key] += num;
        else sum[resource_key] = num;
      }
      return sum;
    },
    playerSpendResources: function (resource_cost) {
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
    canPlayerAfford: function (resource_cost) {
      var playerResources = this.getPlayerResources();
      console.log("checking cost, player resources are");
      console.log(playerResources);
      console.log("cost is");
      console.log(resource_cost);
      if (typeof resource_cost === "undefined") {
        //can always afford something without cost
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
    },
    /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */

    setup: function (gamedatas) {
      console.groupCollapsed("Game Setup");
      console.log("dojo version: " + dojo.version);
      console.log(gamedatas);

      this.playerHand = new ebg.stock(); // new stock object for hand
      //this.playerHand.create(this, $("myhand"), 287, 396);
      this.playerHand.create(this, $("myhand"), 144, 198);
      this.playerHand.image_items_per_row = 6; // 13 images per row
      this.playerHand.horizontal_overlap = 0;
      this.playerHand.setSelectionMode(1); // one item selected

      //resize background to 1/2 of actual size, card size accordingly
      this.playerHand.resizeItems(144, 198, 864, 2378); // 12 rows of cards in images

      this.playerDiscard = new ebg.stock();
      this.playerDiscard.create(this, $("mydiscard"), 144, 198);
      this.playerDiscard.image_items_per_row = 6; // 13 images per row
      this.playerDiscard.horizontal_overlap = 1;
      this.playerDiscard.vertical_overlap = 0;
      this.playerDiscard.item_margin = 0;
      this.playerDiscard.setSelectionMode(0);
      this.playerDiscard.resizeItems(144, 198, 864, 2378);

      this.playerDeck = new ebg.stock();
      this.playerDeck.create(this, $("mydeck"), 144, 198);
      this.playerDeck.image_items_per_row = 6;
      this.playerDeck.horizontal_overlap = 1;
      this.playerDeck.vertical_overlap = 0;
      this.playerDeck.item_margin = 0;
      this.playerDeck.setSelectionMode(0); // deck is not selectable
      this.playerDeck.resizeItems(144, 198, 864, 1189); // 6 rows of cards in image

      this.market = new ebg.stock();
      this.market.create(this, $("market"), 144, 198);
      this.market.image_items_per_row = 6;
      this.market.horizontal_overlap = 0;
      this.market.setSelectionMode(0);
      this.market.resizeItems(144, 198, 864, 2378); // 12 rows of cards in images
      this.market.centerItems = true;
      this.market.jstpl_stock_item = `<div class="market_card_container"><div id="\${id}" class="stockitem \${extra_classes}" 
      style="top:\${top}px;left:\${left}px;width:\${width}px;height:\${height}px;\${position};background-image:url(\'\${image}\');\${additional_style}">
      </div></div>`;

      this.playable_cards = gamedatas.playable_cards;

      this.cards_purchased = [];

      aspect.after(this.playerHand, "onChangeSelection", lang.hitch(this, "onCardSelectedPlayerHand"));
      for (const card of Object.values(this.playable_cards)) {
        //console.log(card);
        console.log(
          "adding card type: " + card.card_type + " img id: " + card.image_id + " to hand/market/discard stock",
        );
        this.playerHand.addItemType(card.card_type, 0, g_gamethemeurl + "img/playable_cards.jpg", card.image_id);
        this.market.addItemType(card.card_type, 0, g_gamethemeurl + "img/playable_cards.jpg", card.image_id);
        this.playerDiscard.addItemType(card.card_type, 0, g_gamethemeurl + "img/playable_cards.jpg", card.image_id);
      }
      for (var i in gamedatas.hand) {
        var card = this.gamedatas.hand[i];
        console.log("adding card type: " + card.type + " id: " + card.id + " to player hand");
        this.playerHand.addToStockWithId(card.type, card.id);
      }
      for (var i in gamedatas.discard) {
        var card = this.gamedatas.discard[i];
        console.log("adding card type: " + card.type + " id: " + card.id + " to player discard");
        this.playerDiscard.addToStockWithId(card.type, card.id);
      }

      // Add card back type for deck (assuming image_id 0 represents card back)
      let card_back_type = Object.keys(this.playable_cards).length;
      this.playerDeck.addItemType(
        card_back_type,
        0,
        g_gamethemeurl + "img/non_playable_cards.png",
        gamedatas.non_playable_cards.card_back.image_id,
      );

      // Add a single card back to show in the deck (the count will show the actual number)
      this.playerDeck.addToStockWithId(card_back_type, 0);

      // Initialize deck count from game data or default
      this.deckSize = gamedatas.deck_size || 0;
      this.updateDeckCount();
      var slotno = 1;
      for (var card_id in gamedatas.market) {
        var card = this.gamedatas.market[card_id];
        console.log("adding card type: " + card.type + " id: " + card.id + " to market");
        this.market.addToStockWithId(card.type, card.id);
        var card_div = this.market.getItemDivId(card.id);
        attr.set(card_div, "data-slotnumber", "n" + slotno);
        attr.set(card_div, "data-cardid", card.id);

        // stick a buy skiff slot on it
        var skiff_slot = query(`.skiff_slot[data-slotname="market"][data-number]="n${slotno}"`)[0];
        domConstruct.place(skiff_slot, card_div);
        slotno++;
      }
      // Setting up player boards
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        console.log(`player color: ${player.color}`);

        var skiff = this.format_block("jstpl_skiff", {
          player_color: player.color,
          id: "skiff_p" + player_id,
        });

        document.getElementById("player_board_" + player_id).insertAdjacentHTML(
          "beforeend",
          this.format_block("jstpl_resources_playerboard", {
            player_id: player.id,
            skiff: skiff,
          }),
        );
      }

      this.islandSlots = gamedatas.islandslots;
      this.players = gamedatas.players;
      this.resources = gamedatas.resources;
      this.unique_tokens = gamedatas.unique_tokens;

      this.updateResources(gamedatas.resources);
      this.updateIslandSlots(gamedatas.islandslots, gamedatas.players);
      this.updateUniqueTokens(gamedatas.unique_tokens);

      for (var x = -1; x <= 6; x++) {
        for (var y = -1; y <= 6; y++) {
          var id = "seaboardlocation_" + x + "_" + y;
          var location = this.format_block("jstpl_seaboard_location", {
            id: id,
          });
          domConstruct.place(location, "seaboard");
          var seaboard = $("seaboard");
          var target_x = -seaboard.offsetWidth / 2 + 32 + 64 * x;
          var target_y = -seaboard.offsetWidth / 2 + 32 + 64 * y;
          this.placeOnObjectPos($(id), "seaboard", target_x, target_y);
        }
      }

      this.seaboard = gamedatas.seaboard;
      for (const entry of gamedatas.seaboard) {
        var target_id = "seaboardlocation_" + entry.x + "_" + entry.y;
        switch (entry.type) {
          case "player_ship":
            var shipid = "player_ship_" + entry.arg;
            var subs = {
              id: shipid,
              //player id is in 'arg'
              shipname: gamedatas.playerinfo[entry.arg].player_ship,
            };
            var ship = this.format_block("jstpl_player_ship", subs);
            domConstruct.place(ship, "seaboard");
            console.log(target_id);
            this.placeOnObject(shipid, target_id);
            domStyle.set(shipid, "rotate", this.getHeadingDegrees(entry.heading) + "deg");
          //this.slideToObject(shipid, target_id, 10 ).play();
        }
      }
      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      var skiffslot_class = query(".skiff_slot");
      var handlers = skiffslot_class.on("click", lang.hitch(this, "onClickSkiffSlot"));

      //this.showDummyDialog();
      console.log("Ending game setup");
      console.groupEnd();
    },
    showDummyDialog: function () {
      this.myDlg = new ebg.popindialog();
      this.myDlg.create("dummyDialog");
      this.myDlg.setTitle(_("Yep, this is dumb"));
      this.myDlg.setMaxWidth(500); // Optional

      var html = this.format_block("jstpl_dummy_dialog");

      this.myDlg.setContent(html);
      this.myDlg.hideCloseIcon();
      this.myDlg.show();

      this.setClientState("client_dummyDialog", {
        descriptionmyturn: _("${you} must abide by BGA's dumb logging"),
      });

      on(
        query(".dummy_button"),
        "click",
        lang.hitch(this, (event) => {
          console.log("dummy button clicked");

          event.preventDefault();
          this.bgaPerformAction("actExitDummyStart", {});
          this.myDlg.destroy();
        }),
      );
    },
    showCardPlayDialog: function (card, card_id) {
      card_div_id = this.playerHand.getItemDivId(card_id);
      domConstruct.destroy("card_display_dialog");
      var dlg = this.format_block("jstpl_card_play_dialog");
      domConstruct.place(dlg, "myhand_wrap", "first");
      var bga = this;
      var makeDecisionSummary = function (tree, decisionSummary) {
        if (typeof decisionSummary === "undefined") {
          decisionSummary = [];
        }
        console.log("making decision summary " + decisionSummary);
        tree.forEach((options) => {
          for (let i = 0; i < options.length; i++) {
            let option = options[i];
            console.log(option);
            var checkbox = dom.byId(option.id);
            console.log("checked: " + checkbox.checked);
            if (checkbox.checked) {
              decisionSummary.push(option.name);
              makeDecisionSummary(option.children, decisionSummary);
            }
          }
        });
        return decisionSummary;
      };

      on(
        query(".play_card_button"),
        "click",
        lang.hitch(this, (event) => {
          console.groupCollapsed("card play button clicked");
          console.log("play card button clicked");
          event.preventDefault();
          var decisionSummary = makeDecisionSummary(this.dep_tree);
          console.log("decisions");
          console.log(decisionSummary);
          console.log("card ");
          console.log(card);
          this.bgaPerformAction("actPlayCard", {
            card_type: card.card_type,
            card_id: card_id,
            decisions: JSON.stringify(decisionSummary),
          });
          this.dep_tree = null;
          domConstruct.destroy("card_display_dialog");
          console.log("moving card with type: " + card.card_type + " id :" + card_id);
          this.playerDiscard.addToStockWithId(card.card_type, card_id, card_div_id);
          this.playerHand.removeFromStockById(card_id);
          console.groupEnd();
        }),
      );
      var dlg_dom = dom.byId("card_display_dialog");
      var existing_card_dom = dom.byId(card_div_id);
      //var card_pos = dojo.position(existing_card_dom);
      domStyle.set(dlg_dom, "left", `${existing_card_dom.offsetLeft - existing_card_dom.offsetWidth / 2}px`);
      var new_card_dom = lang.clone(existing_card_dom);
      attr.set(new_card_dom, "id", "tmp_display_card");

      domConstruct.place(new_card_dom, "card_display");
      domStyle.set(new_card_dom, { top: "5px", left: "5px" });

      console.log(card);
      var bga = this;
      var make_card_dependency_tree = function (actions, choice_count) {
        var tree = new Map();
        if (typeof choice_count === "undefined") {
          choice_count = 0;
        }
        for (const action of actions) {
          console.log("tree considering action:");
          console.log(action);
          var option_count = 0;
          var num_descendant_choices = 0;
          switch (action.action) {
            case "choice":
              var tree_choices = [];
              for (const option of action.choices) {
                var choice_name = option.name || option.action;
                var id = "card_choice_" + choice_count + "_option_" + option_count;
                children = make_card_dependency_tree([option], choice_count + num_descendant_choices + 1);
                entry = {
                  name: choice_name,
                  id: id,
                  children: children,
                };
                if (typeof option.cost !== "undefined") {
                  entry.cost = option.cost;
                }
                if (typeof action.cost !== "undefined") {
                  //propagate parent cost down
                  if (typeof entry.cost !== "undefined") {
                    console.warn("overwriting cost for " + choice_name);
                  }
                  entry.cost = action.cost;
                }

                tree_choices.push(entry);
                console.log("choice added: " + choice_name + " " + id);
                num_descendant_choices += children.size;
                option_count++;
              }
              if (typeof action.cost !== "undefined") {
                tree_choices.push({
                  name: "skip",
                  id: "card_choice_" + choice_count + "_option_" + option_count,
                  children: new Map(),
                });
                option_count++;
              }
              tree.set("choice_" + choice_count, tree_choices);
              choice_count++;
              choice_count += num_descendant_choices;
              break;
            case "sequence":
              children = make_card_dependency_tree(action.actions, choice_count);
              children.forEach((value, key) => {
                tree.set(key, value);
              });
              break;
            default: {
              let choice_names = [];
              let choice_name = action.name || action.action;
              if (choice_name == "fire" || choice_name == "2 x fire" || choice_name == "3 x fire") {
                choice_names.push(choice_name + " left");
                choice_names.push(choice_name + " right");
              } else {
                choice_names.push(choice_name);
              }
              if (typeof action.cost !== "undefined") {
                choice_names.push("skip");
              }
              if (choice_names.length > 1) {
                var tree_choices = [];
                let parent_cost = action.cost;
                delete action.cost;
                for (let i = 0; i < choice_names.length; i++) {
                  to_push = {
                    name: choice_names[i],
                    id: "card_choice_" + choice_count + "_option_" + i,
                    children: new Map(),
                  };
                  if (choice_names[i] != "skip") {
                    to_push["cost"] = parent_cost;
                  }
                  tree_choices.push(to_push);
                }
                tree.set("choice_" + choice_count, tree_choices);
                choice_count++;
                num_descendant_choices += 1;
              }
            }
          }
        }
        console.log("returning tree");
        console.log(tree);
        return tree;
      };
      console.groupCollapsed("make dependency tree");
      this.dep_tree = make_card_dependency_tree(card.actions);
      console.log(this.dep_tree);
      console.groupEnd();

      var render_rows = function (tree, row_number) {
        var rendered_choices = [];
        var row_number = row_number || 1;
        tree.forEach((options, choice_id) => {
          var rendered_options = [];
          console.log(options);
          for (var option of options) {
            console.log("rendering option " + option.name);
            rendered_options.push(
              bga.format_block("jstpl_card_choice_radio", {
                id: option.id,
                name: choice_id,
                value: option.name,
                label: option.name,
              }),
            );
            rendered_choices = rendered_choices.concat(render_rows(option.children, row_number + 1));
          }
          var choice_html = bga.format_block("jstpl_card_choices_row", {
            row_number: row_number + ".",
            card_choices: rendered_options.join("\n"),
          });
          rendered_choices.unshift(choice_html);
          row_number++;
        });
        return rendered_choices;
      };
      console.groupCollapsed("render play rows");
      var result = render_rows(this.dep_tree);
      console.log(result);
      console.groupEnd();

      var bga = this;
      var computeTotalPlayCost = function (tree, costAcc) {
        if (typeof costAcc === "undefined") {
          costAcc = {};
        }
        console.log("computing total play cost " + costAcc);
        tree.forEach((options) => {
          for (var option of options) {
            console.log(option);
            var checkbox = dom.byId(option.id);
            console.log("checked: " + checkbox.checked);
            console.log(option.cost);
            console.log(costAcc);
            if (checkbox.checked && typeof option.cost !== "undefined") {
              costAcc = bga.addResources(option.cost, costAcc);
            }
            costAcc = computeTotalPlayCost(option.children, costAcc);
          }
        });
        return costAcc;
      };

      var showHideControls = function (tree, hide, totalCost) {
        console.log("showing/hiding controls " + hide);
        if (typeof totalCost === "undefined") {
          console.groupCollapsed("compute total play cose");
          totalCost = computeTotalPlayCost(tree);
          console.groupEnd();
          console.log("total play cost is:");
          console.log(totalCost);
        }
        tree.forEach((options) => {
          for (var option of options) {
            var checkbox = dom.byId(option.id);
            console.log(option);
            console.log(checkbox);
            console.log(checkbox.checked);
            if (hide) {
              checkbox.checked = false;
              domStyle.set(checkbox.parentNode.parentNode, "display", "none");
              showHideControls(option.children, true, totalCost);
            } else {
              domStyle.set(checkbox.parentNode.parentNode, "display", "inline-block");
              if (typeof option.cost !== "undefined" && option.cost) {
                console.log("option cost:");
                console.log(option.cost);
                console.log("totalCost:");
                console.log(totalCost);
                var adjustedCost = bga.addResources(option.cost, totalCost);
                console.log("adjusted cost:");
                console.log(adjustedCost);
                if (bga.canPlayerAfford(adjustedCost)) {
                  attr.remove(checkbox, "disabled");
                } else {
                  attr.set(checkbox, "disabled", "true");
                }
              }
              showHideControls(option.children, !checkbox.checked, totalCost);
            }
          }
        });
      };

      var checkIsCardReadyToBePlayed = function (tree) {
        var isReady = true;
        if (tree.length == 0) {
          return;
        }
        console.log("starting ready to play check");
        tree.forEach((options) => {
          if (!isReady) {
            return;
          }
          var anythingChecked = false;
          for (var option of options) {
            var checkbox = dom.byId(option.id);
            console.log("starting to check option:");
            console.log(option);
            console.log("parent display: " + domStyle.get(checkbox.parentNode.parentNode, "display"));
            if (domStyle.get(checkbox.parentNode.parentNode, "display") != "none") {
              console.log("checking children:");
              console.log(option.children);
              if (!checkIsCardReadyToBePlayed(option.children)) {
                console.log("nothing checked in children");
                isReady = false;
                return;
              }
              anythingChecked |= checkbox.checked;
              console.log("checkbox is checked: " + checkbox.checked);
              console.log("anything checked now: " + anythingChecked);
            } else {
              console.log("skipping because option is hidden:");
              console.log(option);
              return;
            }
            console.log("anything checked at end of loop " + anythingChecked);
          }
          console.log("after options anything checked: " + anythingChecked);
          isReady &= anythingChecked;
          console.log("updated isReady to " + isReady);
        });
        console.log("final ready to play: " + isReady);
        return isReady;
      };

      var updatePlayCardButton = function () {
        const button_id = "play_card_button";
        console.groupCollapsed("check whether card is ready to be played");
        let ready = checkIsCardReadyToBePlayed(bga.dep_tree);
        console.groupEnd();
        if (ready) {
          domClass.add(button_id, "bgabutton_green");
          domClass.remove(button_id, "disabled");
        } else {
          domClass.remove(button_id, "bgabutton_green");
          domClass.add(button_id, "disabled");
        }
      };
      if (result.length > 0) {
        var choices_html = result.join("\n");
        domConstruct.place(choices_html, "card_choices");
        query(".card_choice_radio").connect("onchange", this, (event) => {
          console.groupCollapsed("show/hide play controls");
          showHideControls(this.dep_tree);
          console.groupEnd();
          updatePlayCardButton();
        });
      }
      console.groupCollapsed("show/hide play controls");
      showHideControls(this.dep_tree);
      console.groupEnd();
      updatePlayCardButton();
      this.cardPlayDialogShown = true;
    },
    updateCardPurchaseButtons: function (create) {
      console.groupCollapsed("update card purchase buttons");
      console.log("showing card purchase buttons");
      console.log(this.islandSlots);
      for (const [slot, numbers] of Object.entries(this.islandSlots)) {
        if (slot == "market") {
          for (const [number, occupant] of Object.entries(numbers)) {
            console.log("market occupant: " + occupant);
            if (occupant == this.player_id) {
              var button_id = "purchase_button_" + number;
              console.log("number " + number + " is ours");
              var slot_card = null;
              for (const cardspec of this.market.getAllItems()) {
                console.log(cardspec);
                var div = this.market.getItemDivId(cardspec.id);
                console.log(div);
                //console.log("children");
                //console.log(query(div));
                var slotnum = attr.get(query("#" + div).children(".skiff_slot")[0], "data-number");
                console.log("slotnum " + slotnum);
                if (slotnum == number) {
                  slot_card = cardspec;
                  console.log("found it");
                  break;
                }
              }
              if (slot_card === null) {
                continue;
              }
              console.log(slot_card);
              var card = this.playable_cards[slot_card.type];
              console.log(card);
              if (create) {
                var purchase_button = this.format_block("jstpl_card_purchase_button", {
                  id: button_id,
                  slotnumber: slotnum,
                });
                var card_div = this.market.getItemDivId(slot_card.id);
                //dojo.place(purchase_button, "skiff_slot_" + slot + "_" + number);
                console.log(card_div);
                console.log("placing on " + card_div + " card purchase button: " + purchase_button);
                domConstruct.place(purchase_button, card_div);
              }
              // Always ensure event handler is connected
              console.log("=== PURCHASE BUTTON EVENT HANDLER DEBUG ===");
              console.log("Looking for button with ID: " + button_id);
              var buttonNodes = query("#" + button_id);
              console.log("Found button nodes:", buttonNodes);
              console.log("Number of nodes found:", buttonNodes.length);

              // Remove any existing handler first to avoid duplicates
              buttonNodes.forEach(function (node, index) {
                console.log("Processing node " + index + ":", node);
                if (node._purchaseHandler) {
                  console.log("Removing existing handler from node " + index);
                  node._purchaseHandler.remove();
                } else {
                  console.log("No existing handler found on node " + index);
                }
              });

              // Connect the event handler and store reference on the DOM node
              console.log("Attempting to connect click handler...");
              var handler = buttonNodes.on("click", lang.hitch(this, "onClickPurchaseButton"));
              console.log("Handler created:", handler);

              buttonNodes.forEach(function (node, index) {
                console.log("Storing handler reference on node " + index);
                node._purchaseHandler = handler;
              });
              console.log("=== END PURCHASE BUTTON EVENT HANDLER DEBUG ===");

              if (!this.canPlayerAfford(card.cost)) {
                console.log("disabling");
                buttonNodes.forEach(function (node) {
                  domClass.remove(node, "bgabutton_green");
                  domClass.add(node, "disabled");
                  html.set(node, "Cannot Afford");
                });
              } else {
                console.log("enabling purchase button");
                buttonNodes.forEach(function (node) {
                  domClass.add(node, "bgabutton_green");
                  domClass.remove(node, "disabled");
                  html.set(node, "Purchase");
                });
              }
            }
          }
        }
      }
      console.groupEnd();
    },
    onClickPurchaseButton: function (event) {
      console.groupCollapsed("card purchase button clicked");
      console.log("=== PURCHASE BUTTON CLICKED ===");
      console.log("onClickPurchaseButton function called!");
      console.log("Event object:", event);
      console.log("Event type:", event.type);
      console.log("Event target:", event.target);
      console.log("Event currentTarget:", event.currentTarget);
      event.preventDefault();
      const source = event.target || event.srcElement;
      const slotnumber = source.dataset.slotnumber;
      console.log("slotnumber: " + slotnumber);

      var card_dom = query(`.stockitem[data-slotnumber=${slotnumber}`)[0];
      console.log(card_dom);
      var card_id = attr.get(card_dom, "data-cardid");
      console.log("card id " + card_id);
      var slot_card = this.market.getItemById(parseInt(card_id));
      console.log(slot_card);
      var card = this.playable_cards[slot_card.type];
      this.playerSpendResources(card.cost);
      console.log(card);
      console.log(slot_card);
      this.playerHand.addToStockWithId(slot_card.type, slot_card.id, this.market.getItemDivId(slot_card.id));
      this.market.removeFromStockById(slot_card.id);
      this.updateCardPurchaseButtons(false);
      this.cards_purchased.push(slot_card.id);
      console.groupEnd();
    },
    onCompletePurchasesClicked: function (event) {
      console.log("onCompletePurchasesClicked");
      console.log(this.cards_purchased);
      this.bgaPerformAction("actCompletePurchases", {
        cards_purchased: JSON.stringify(this.cards_purchased),
      });
    },
    onClickSkiffSlot: function (event) {
      console.log("$$$$ Event : onClickSkiffSlot");
      console.log(event);
      event.preventDefault();
      const source = event.target || event.srcElement;
      if (!this.checkAction("actPlaceSkiff")) {
        console.log("nope");
        return;
      }

      if (!source.classList.contains("skiff_slot")) {
        console.log("not a skiff slot");
        return;
      }
      console.log(source.dataset.slotname, source.dataset.number);

      if (this.isCurrentPlayerActive()) {
        console.log("calling actPlaceSkiff");
        this.bgaPerformAction("actPlaceSkiff", {
          slotname: source.dataset.slotname,
          number: source.dataset.number,
        });
      }
    },
    onCardSelectedPlayerHand: function (name, item_id) {
      console.groupCollapsed("player card selected");
      console.log("player hand selection " + name + " " + item_id);
      var items = this.playerHand.getSelectedItems();
      if (items.length == 1) {
        var card_type = items[0].type;
        console.log("type: " + card_type);
        var card = this.playable_cards[card_type];
        console.log(card);
        this.showCardPlayDialog(card, items[0].id);
      }
      console.log(items);
      console.groupEnd();
    },
    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
        case "dummyStart":
        //this.bgaPerformAction("actExitDummyStart", {});
        case "cardPurchases": {
          this.cards_purchased = [];
          this.updateCardPurchaseButtons(true);
          break;
        }
        case "seaTurn": {
          query(".skiff_placed").forEach(domConstruct.destroy);
          query(".purchase_card_button").forEach(domConstruct.destroy);
          break;
        }
        case "dummmy":
          break;
      }
    },

    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */

        case "dummmy":
          break;
      }
    },

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: " + stateName);

      if (this.isCurrentPlayerActive()) {
        switch (stateName) {
          case "cardPurchases":
            this.statusBar.addActionButton(_("Complete Purchases"), this.onCompletePurchasesClicked.bind(this));
            break;
          case "client_resourceDialog":
            this.statusBar.addActionButton(
              _("<div class='resource sail' data-resource='sail'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              _("<div class='resource cannonball' data-resource='cannonball'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              _("<div class='resource doubloon' data-resource='doubloon'></div>"),
              this.onResourceButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            break;
          case "resolveCollision":
            this.statusBar.addActionButton(
              "<div class='resource pivot_left' data-pivot='pivot left'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              "<div class='resource nope' data-pivot='no pivot'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );
            this.statusBar.addActionButton(
              "<div class='resource pivot_right' data-pivot='pivot right'></div>",
              this.onPivotButtonClicked.bind(this),
              { classes: "bgabutton_resource" },
            );

            break;
        }
      }
    },
    onResourceButtonClicked: function (event) {
      console.log("resource button clicked");
      const source = event.target || event.srcElement;
      console.log(
        "resource picked " +
          source.dataset.resource +
          " context: " +
          this.clientStateVars.slot_context +
          " number: " +
          this.clientStateVars.slot_number,
      );

      event.preventDefault();
      if (source.dataset.resource != null) {
        this.bgaPerformAction("actResourcePickedInDialog", {
          resource: source.dataset.resource,
          context: this.clientStateVars.slot_context,
          number: this.clientStateVars.slot_number,
        });
        this.statusBar.removeActionButtons();
      }
    },
    onPivotButtonClicked: function (event) {
      const source = event.target || event.srcElement;
      console.log("pivot button clicked");
      console.log(source);
      console.log("pivot picked " + source.dataset.pivot);
      this.bgaPerformAction("actPivotPickedInDialog", {
        direction: source.dataset.pivot,
      });
      this.statusBar.removeActionButtons();
      event.preventDefault();
    },
    ///////////////////////////////////////////////////
    //// Utility methods

    /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

    ///////////////////////////////////////////////////
    //// Player's action

    /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */

    /* Example:
        
        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );
            
            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/seasofhavoc/seasofhavoc/myAction.html", { 
                                                                    lock: true, 
                                                                    myArgument1: arg1, 
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 }, 
                         this, function( result ) {
                            
                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)
                            
                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );        
        },        
        
        */

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your seasofhavoc.game.php file.
        
        */
    setupNotifications: function () {
      console.log("notifications subscriptions setup");
      this.bgaSetupPromiseNotifications();
    },

    // TODO: from this point and below, you can write your game notifications handling methods
    notif_deckSizeChanged: function (args) {
      console.log("notify: deck size changed");
      console.log(args);
      if (args.player_id == this.player_id) {
        this.deckSize = args.deck_size;
        this.updateDeckCount();

        // Show/hide deck based on whether there are cards
        if (this.deckSize === 0) {
          domStyle.set("mydeck_wrap", "display", "none");
        } else {
          domStyle.set("mydeck_wrap", "display", "block");
        }
      }
    },

    // TODO: from this point and below, you can write your game notifications handling methods
    notif_showResourceChoiceDialog: function (args) {
      console.groupCollapsed("show resource choice dialog");
      // this.myDlg = new ebg.popindialog();
      // this.myDlg.create("resouceDialog");
      // this.myDlg.setTitle(_("Pick a Resource"));
      // this.myDlg.setMaxWidth(500); // Optional

      // var html = this.format_block("jstpl_resource_dialog");

      // this.myDlg.setContent(html);
      // this.myDlg.hideCloseIcon();
      // this.myDlg.show();

      this.clientStateVars.slot_context = args.context;
      this.clientStateVars.slot_number = args.context_number;
      console.log("context: " + this.clientStateVars.slot_context);
      console.log("number: " + this.clientStateVars.slot_number);

      this.setClientState("client_resourceDialog", {
        descriptionmyturn: _("${you} must select a resource"),
      });

      console.groupEnd();
    },
    notif_dummyStart: function (args) {
      console.log("dummystart");
      this.bgaPerformAction("actExitDummyStart", {});
    },
    notif_resourcesChanged: function (args) {
      console.groupCollapsed("notify: resources changed");
      console.log("Notification: resourcesChanged");
      console.log(args.resources);
      console.log("current resources:");
      console.log(this.resources);

      this.resources = args.resources;
      this.updateResources(args.resources);
      console.groupEnd();
    },
    notif_skiffPlaced: function (args) {
      console.groupCollapsed("notify: skiff placed");
      console.log(args);

      var slot_name = args.slot_name;
      var slot_number = args.slot_number;
      var postfix = "_" + slot_name + "_" + slot_number;
      var skiff_id = "skiff_p" + args.player_id + postfix;

      this.islandSlots[slot_name][slot_number] = args.player_id;

      console.log("skiff_id: " + skiff_id);
      var player_board_id = "overall_player_board_" + args.player_id;
      console.log("player board id: " + player_board_id);

      var skiff = this.format_block("jstpl_skiff", {
        player_color: args.player_color,
        id: skiff_id,
      });
      var skiff_slot = query(`.skiff_slot[data-slotname="${slot_name}"][data-number="${slot_number}"]`)[0];
      console.log("skiff slot:");
      console.log(skiff_slot);

      domConstruct.place(
        skiff,
        //"skiff_slot" + postfix
        skiff_slot,
      );
      this.placeOnObject(skiff_id, player_board_id);
      domStyle.set(skiff_id, "zIndex", 1);
      this.slideToObject(
        skiff_id,
        //"skiff_slot" + postfix,
        skiff_slot,
        1000,
      ).play();
      domClass.remove(skiff_slot, "unoccupied");
      domClass.add(skiff_id, "skiff_placed");
      console.groupEnd();
    },
    notif_tokenAcquired: function (args) {
      console.groupCollapsed("notify: token acquired");
      console.log(args);

      var token_key = args.token_key;
      var player_id = args.player_id;

      var tokens = query("#" + token_key);
      var token_element = null;
      if (token_element != null) {
        var token_element = tokens[0];
      } else {
        console.log("creating token");
        var token_html = this.format_block("jstpl_unique_token", { token_key: args.token_key });
        if (token_key == "first_player_token") {
          var token_element = domConstruct.place(token_html, `skiff_slot_capitol_n1`);
        } else {
          var token_element = domConstruct.place(token_html, `skiff_slot_${token_key}_n1`);
        }
      }

      this.unique_tokens[token_key] = args.player_id;

      if (player_id != null) {
        var target = `${token_key}_p${player_id}`;
        domStyle.set(token_element, "zIndex", 1);
        this.slideToObject(token_element, target, 1000).play();
      }
      console.groupEnd();
    },
    notif_cardPlayed: function (args) {
      console.groupCollapsed("notify: card played");
      console.log(args);
      var shipid = "player_ship_" + args.player_id;

      this.playerSpendResources(args.cost);

      var anims = [];
      for (var move of args.moveChain) {
        console.log("processing move");
        console.log(move);
        switch (move.type) {
          case "move": {
            if (move.teleport_at != null) {
              let target_id = "seaboardlocation_" + move.teleport_at.x + "_" + move.teleport_at.y;
              let forward = this.slideToObject(shipid, target_id, 1000);
              //let fadeout = baseFX.fadeOut({node: shipid});
              //anims.push(fx.combine(forward, fadeout));
              anims.push(forward);
              target_id = "seaboardlocation_" + move.teleport_to.x + "_" + move.teleport_to.y;
              anims.push(this.slideToObject(shipid, target_id, 0));
              //anims.push(baseFX.fadeIn({node: shipid}));
            }
            var target_id = "seaboardlocation_" + move.new_x + "_" + move.new_y;
            anims.push(this.slideToObject(shipid, target_id, 1000));
            break;
          }
          case "turn": {
            let player_ship = this.getObjectOnSeaboard("player_ship", args.player_id);
            console.log(
              args.player_id +
                " my old heading " +
                player_ship.heading +
                " event old heading " +
                move.old_heading +
                " new heading " +
                move.new_heading,
            );
            let current_deg = this.getHeadingDegrees(player_ship.heading);
            let target_deg = this.getHeadingDegrees(move.new_heading);
            let diff = Math.abs(target_deg - current_deg);
            let curve = [current_deg, target_deg];
            console.log("target: " + target_deg + " current: " + current_deg + " diff: " + diff);
            if (diff > 180) {
              if (target_deg > current_deg) {
                curve = [current_deg, -(360 - target_deg)];
                console.log("adjusted target to: " + -(360 - target_deg));
              } else {
                curve = [-(360 - current_deg), target_deg];
                console.log("adjusted current to: " + -(360 - current_deg));
              }
            }
            let anim = new baseFX.Animation({
              curve: curve,
              onAnimate: function (v) {
                domStyle.set(shipid, "rotate", v + "deg");
              },
            });
            console.log(anim);
            anims.push(anim);
            player_ship.heading = move.new_heading;
            console.log(this.seaboard);
            break;
          }
          case "fire_hit": {
            let cannon_fire = this.format_block("jstpl_cannon_fire", {});
            let rotation = this.getHeadingDegrees(move.fire_heading);
            console.log("fire rotation " + rotation);
            domConstruct.place(cannon_fire, shipid);
            domStyle.set("cannonfire", "rotate", rotation + "deg");
            let offset = null;
            switch (move.fire_heading) {
              case NORTH:
                offset = ["top", "20px"];
                break;
              case SOUTH:
                offset = ["top", "-20px"];
                break;
              case EAST:
                offset = ["left", "20px"];
                break;
              case WEST:
                offset = ["left", "-20px"];
                break;
            }
            console.log(offset);
            domStyle.set("cannonfire", "rotate", rotation + "deg");
            domStyle.set("cannonfire", offset[0], offset[1], rotation + "deg");
            let explosion = this.format_block("jstpl_explosion", {});
            let target_id = "seaboardlocation_" + move.hit_x + "_" + move.hit_y;
            domConstruct.place(explosion, target_id);
            domStyle.set("explosion", "opacity", "0");
            domStyle.set("cannonfire", "opacity", 0);
            fx.chain([
              baseFX.fadeIn({ node: "cannonfire", duration: 100 }),
              baseFX.fadeOut({
                node: "cannonfire",
                duration: 100,
                delay: 1000,
                onEnd: function () {
                  domConstruct.destroy("cannonfire");
                },
              }),
            ]).play();
            fx.chain([
              baseFX.fadeIn({ node: "explosion", delay: 100 }),
              baseFX.fadeOut({
                node: "explosion",
                delay: 1000,
                onEnd: function () {
                  domConstruct.destroy("explosion");
                },
              }),
            ]).play();
            break;
          }
        }
      }
      console.log(anims);
      if (anims.length) {
        fx.chain(anims).play();
      }
      console.groupEnd();
    },
    notif_score: function (args) {
      console.log("score for " + args.player_id + " " + args.player_score);
      this.scoreCtrl[args.player_id].setValue(args.player_score);
    },
    notif_damageReceived: function (args) {
      console.log("notify damage received");
      let damage_card = args.damage_card;
      let player_id = args.player_id;
      var shipid = "player_ship_" + args.player_id;
      if (player_id == this.player_id) {
        this.playerDiscard.addToStockWithId(damage_card.type, damage_card.id, shipid);
      }
    },
    notif_cardDrawn: function (args) {
      console.log("notify card drawn");
      let card = args.card;
      let player_id = args.player_id;
      console.log(card);
      console.log(player_id);
      if (player_id == this.player_id) {
        this.deckSize = args.deck_size;
        this.updateDeckCount();
        this.playerHand.addToStockWithId(card.type, card.id, "mydeck");
      }
    },
    /*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */
  });
});
