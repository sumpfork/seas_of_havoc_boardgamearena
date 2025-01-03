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

define([
  "dojo",
  "dojo/_base/declare",
  "dojo/on",
  "dojo/dom",
  "dojo/dom-class",
  "dojo/dom-construct",
  "dojo/dom-style",
  "dojo/dom-attr",
  "dojo/_base/lang",
  "dojo/query",
  "dojo/NodeList-traverse",
  "dojo/NodeList-data",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/stock",
], function (
  dojo,
  declare,
  on,
  dom,
  domClass,
  domConstruct,
  domstyle,
  attr,
  lang,
  query
) {
  return declare("bgagame.seasofhavoc", ebg.core.gamegui, {
    constructor: function () {
      console.log("seasofhavoc constructor");

      // Here, you can init the global variables of your user interface
      // Example:
      // this.myGlobalValue = 0;
    },

    updateResources: function (resources) {
      console.log("updating resources");
      console.log(resources);
      for (const resource of resources) {
        console.log(resource);
        console.log(`${resource.resource_key}count_p${resource.player_id}`);
        document.getElementById(
          `${resource["resource_key"]}count_p${resource["player_id"]}`
        ).innerText = resource.resource_count;
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
            dojo.place(skiff, "skiff_slot_" + slot + "_" + number);
          }
        }
      }
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
          var diff =
            resource.resource_count - resource_cost[resource.resource_key];
          console.log("diff for " + resource.resource_key + ": " + diff);
          if (diff < 0) {
            this.showMessage(
              _("Player tried to spend more than they have"),
              "error"
            );
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
          console.log(
            "not enough " +
              resource_key +
              " (" +
              player_has +
              " vs " +
              num +
              ")"
          );
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
      console.log("Starting game setup");
      console.log("dojo version: " + dojo.version);
      console.log(gamedatas);

      this.playerHand = new ebg.stock(); // new stock object for hand
      //this.playerHand.create(this, $("myhand"), 287, 396);
      this.playerHand.create(this, $("myhand"), 144, 198);
      this.playerHand.image_items_per_row = 6; // 13 images per row
      this.playerHand.horizontal_overlap = 0;
      this.playerHand.setSelectionMode(1); // one item selected

      //resize background to 1/2 of actual size, card size accordingly
      this.playerHand.resizeItems(144, 198, 864, 2378);

      this.market = new ebg.stock();
      this.market.create(this, $("market"), 144, 198);
      this.market.image_items_per_row = 6;
      this.market.horizontal_overlap = 0;
      this.market.setSelectionMode(0);
      this.market.resizeItems(144, 198, 864, 2378);
      this.market.centerItems = true;
      this.market.jstpl_stock_item = `<div class="market_card_container"><div id="\${id}" class="stockitem \${extra_classes}" 
      style="top:\${top}px;left:\${left}px;width:\${width}px;height:\${height}px;\${position};background-image:url(\'\${image}\');\${additional_style}">
      </div></div>`;
      this.playable_cards = gamedatas.playable_cards;

      this.cards_purchased = [];

      dojo.connect(
        this.playerHand,
        "onChangeSelection",
        this,
        "onCardSelectedPlayerHand"
      );
      for (const card of Object.values(this.playable_cards)) {
        //console.log(card);
        console.log(
          "adding card type: " +
            card.card_type +
            " img id: " +
            card.image_id +
            " to hand/market stock"
        );
        this.playerHand.addItemType(
          card.card_type,
          0,
          g_gamethemeurl + "img/playable_cards.jpg",
          card.image_id
        );
        this.market.addItemType(
          card.card_type,
          0,
          g_gamethemeurl + "img/playable_cards.jpg",
          card.image_id
        );
      }
      for (var i in gamedatas.hand) {
        var card = this.gamedatas.hand[i];
        console.log(
          "adding card type: " +
            card.type +
            " id: " +
            card.id +
            " to player hand"
        );
        this.playerHand.addToStockWithId(card.type, card.id);
      }
      var slotno = 1;
      for (var card_id in gamedatas.market) {
        var card = this.gamedatas.market[card_id];
        console.log(
          "adding card type: " + card.type + " id: " + card.id + " to market"
        );
        this.market.addToStockWithId(card.type, card.id);
        var card_div = this.market.getItemDivId(card.id);
        attr.set(card_div, "data-slotnumber", "n" + slotno);
        attr.set(card_div, "data-cardid", card.id);

        // stick a buy skiff slot on it
        var skiff_slot = dojo.query(
          `.skiff_slot[data-slotname="market"][data-number]="n${slotno}"`
        )[0];
        dojo.place(skiff_slot, card_div);
        slotno++;
      }
      // Setting up player boards
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        console.log(`player color: ${player.color}`);

        var skiff = this.format_block("jstpl_skiff", {
          player_color: player.color,
          id: "skiff_p" + player.id,
        });

        document.getElementById("player_board_" + player_id).insertAdjacentHTML(
          "beforeend",
          this.format_block("jstpl_resources_playerboard", {
            player_id: player.id,
            skiff: skiff,
          })
        );
      }

      this.islandSlots = gamedatas.islandslots;
      this.players = gamedatas.players;
      this.resources = gamedatas.resources;

      this.updateResources(gamedatas.resources);
      this.updateIslandSlots(gamedatas.islandslots, gamedatas.players);

      for (var x = 0; x < 6; x++) {
        for (var y = 0; y < 6; y++) {
          var id = "seaboardlocation_" + x + "_" + y;
          var location = this.format_block("jstpl_seaboard_location", {
            id: id,
          });
          dojo.place(location, "seaboard");
          var seaboard = $("seaboard");
          var target_x = -seaboard.offsetWidth / 2 + 32 + 64 * x;
          var target_y = -seaboard.offsetWidth / 2 + 32 + 64 * y;
          this.placeOnObjectPos($(id), "seaboard", target_x, target_y);
        }
      }

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
            dojo.place(ship, "seaboard");
            console.log(target_id);
            this.placeOnObject(shipid, target_id);
            console.log(entry.heading);
            switch (entry.heading) {
              case "1":
                domstyle.set(shipid, "rotate", "90deg");
                console.log("NORTH!");
                break;
              case "2":
                dojo.attr(shipid, "transform", "scaleX(-1)");
                break;
              case "3":
                dojo.attr(shipid, "rotate", "-90deg");
                break;
            }
          //this.slideToObject(shipid, target_id, 10 ).play();
        }
      }
      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      var skiffslot_class = dojo.query(".skiff_slot");
      var handlers = skiffslot_class.on(
        "click",
        lang.hitch(this, "onClickSkiffSlot")
      );

      //this.showDummyDialog();
      console.log("Ending game setup");
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

      dojo.query(".dummy_button").connect("onclick", this, (event) => {
        console.log("dummy button clicked");

        event.preventDefault();
        this.bgaPerformAction("actExitDummyStart", {});
        this.myDlg.destroy();
      });
    },
    showCardPlayDialog: function (card, card_type) {
      domConstruct.destroy("card_display_dialog");
      var dlg = this.format_block("jstpl_card_play_dialog");
      dojo.place(dlg, "myhand_wrap", "first");
      var bga = this;
      var makeDecisionSummary = function (tree, decisionSummary) {
        if (typeof decisionSummary === "undefined") {
          decisionSummary = [];
        }
        console.log("making decision summary " + decisionSummary);
        tree.forEach((options) => {
          for (var option of options) {
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
      
      dojo.query(".play_card_button").connect("onclick", this, (event) => {
        console.log("play card button clicked");
        event.preventDefault();
        var decisionSummary = makeDecisionSummary(this.dep_tree);
        console.log("decisions");
        console.log(decisionSummary);
        console.log("card ")
        console.log(card);
        this.bgaPerformAction("actPlayCard", {
          card_type: card.card_type,
          decisions: JSON.stringify(decisionSummary),
        });
        this.dep_tree = null;
        domConstruct.destroy("card_display_dialog");
      });
      var dlg_dom = dom.byId("card_display_dialog");
      var existing_card_dom = dom.byId(card_type);
      //var card_pos = dojo.position(existing_card_dom);
      dojo.style(
        dlg_dom,
        "left",
        `${existing_card_dom.offsetLeft - existing_card_dom.offsetWidth / 2}px`
      );
      var new_card_dom = dojo.clone(existing_card_dom);
      attr.set(new_card_dom, "id", "tmp_display_card");

      dojo.place(new_card_dom, "card_display");
      dojo.style(new_card_dom, { top: "5px", left: "5px" });

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
                var id =
                  "card_choice_" + choice_count + "_option_" + option_count;
                children = make_card_dependency_tree(
                  [option],
                  choice_count + num_descendant_choices + 1
                );
                entry = {
                  name: choice_name,
                  id: id,
                  children: children
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
                  children: new Map()
                });
                option_count++;
              }
              tree.set("choice_" + choice_count, tree_choices);
              choice_count++;
              choice_count += num_descendant_choices;
              break;
            case "sequence":
              children = make_card_dependency_tree(
                action.actions,
                choice_count
              );
              children.forEach((value, key) => {
                tree.set(key, value);
              });
              break;
            default:
              if (typeof action.cost !== "undefined") {
                console.log("cost!");
                var tree_choices = [];
                var choice_name = action.name || action.action;
                tree_choices.push({
                  name: choice_name,
                  id: "card_choice_" + choice_count + "_option_0",
                  cost: action.cost,
                  children: new Map(),
                });
                tree_choices.push({
                  name: "Skip",
                  id: "card_choice_" + choice_count + "_option_1",
                  children: new Map(),
                });
                tree.set("choice_" + choice_count, tree_choices);
                choice_count++;
                num_descendant_choices += 1;
              }
          }
        }
        console.log("returning tree");
        console.log(tree);
        return tree;
      };
      console.log("dep tree:");
      this.dep_tree = make_card_dependency_tree(card.actions);
      console.log(this.dep_tree);

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
              })
            );
            rendered_choices = rendered_choices.concat(
              render_rows(option.children, row_number + 1)
            );
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
      var result = render_rows(this.dep_tree);
      console.log(result);

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
            if (checkbox.checked && typeof option.cost != "undefined") {
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
          totalCost = computeTotalPlayCost(tree);
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
              domstyle.set(checkbox.parentNode.parentNode, "display", "none");
              showHideControls(option.children, true, totalCost);
            } else {
              domstyle.set(
                checkbox.parentNode.parentNode,
                "display",
                "inline-block"
              );
              if (typeof option.cost !== "undefined") {
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
            console.log(
              "parent display: " +
                domstyle.get(checkbox.parentNode.parentNode, "display")
            );
            if (
              domstyle.get(checkbox.parentNode.parentNode, "display") != "none"
            ) {
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
        if (checkIsCardReadyToBePlayed(bga.dep_tree)) {
          console.log("ready to play card");
          domClass.add(button_id, "bgabutton_green");
          domClass.remove(button_id, "bgabutton_disabled");
          dojo.removeClass(button_id, "disabled");
        } else {
          console.log("not ready to play card");
          domClass.remove(button_id, "bgabutton_green");
          domClass.add(button_id, "bgabutton_disabled");
          dojo.addClass(button_id, "disabled");
        }
      };
      if (result.length > 0) {
        var choices_html = result.join("\n");
        dojo.place(choices_html, "card_choices");
        dojo.query(".card_choice_radio").connect("onchange", this, (event) => {
          console.log(event);
          showHideControls(this.dep_tree);
          updatePlayCardButton();
        });
      }
      showHideControls(this.dep_tree);
      updatePlayCardButton();
      this.cardPlayDialogShown = true;
    },
    updateCardPurchaseButtons: function (create) {
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
                console.log("children");
                //console.log(query(div));
                var slotnum = attr.get(
                  query("#" + div).children(".skiff_slot")[0],
                  "data-number"
                );
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
              var card = this.playable_cards[slot_card.type];
              console.log(card);
              if (create) {
                var purchase_button = this.format_block(
                  "jstpl_card_purchase_button",
                  {
                    id: button_id,
                    slotnumber: slotnum,
                  }
                );
                var card_div = this.market.getItemDivId(slot_card.id);
                //dojo.place(purchase_button, "skiff_slot_" + slot + "_" + number);
                console.log(card_div);
                console.log(
                  "placing on " +
                    card_div +
                    " card purchase button: " +
                    purchase_button
                );
                dojo.place(purchase_button, card_div);
              }
              if (!this.canPlayerAfford(card.cost)) {
                console.log("disabling");
                dojo.removeClass(button_id, "bgabutton_green");
                dojo.addClass(button_id, "bgabutton_disabled");
                dojo.addClass(button_id, "disabled");
                dojo.html.set(button_id, "Cannot Afford");
                if (typeof this.purchase_handler === "undefined") {
                  this.purchase_handler.remove();
                }
              } else {
                console.log(
                  "connecting purchase handler to " + dojo.query(button_id)
                );
                this.purchase_handler = on(
                  dom.byId(button_id),
                  "click",
                  lang.hitch(this, "onClickPurchaseButton")
                );
              }
            }
          }
        }
      }
    },
    onClickPurchaseButton: function (event) {
      console.log("onClickPurchaseButton");
      console.log(event);
      dojo.stopEvent(event);
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
      this.playerHand.addToStockWithId(
        slot_card.type,
        slot_card.id,
        this.market.getItemDivId(slot_card.id)
      );
      this.market.removeFromStockById(slot_card.id);
      this.updateCardPurchaseButtons(false);
      this.cards_purchased.push(slot_card.id);
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
      dojo.stopEvent(event);
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
        this.bgaPerformAction("actPlaceSkiff", {
          slotname: source.dataset.slotname,
          number: source.dataset.number,
        });
      }
    },
    onCardSelectedPlayerHand: function (name, item_id) {
      console.log("player hand selection " + name + " " + item_id);
      var items = this.playerHand.getSelectedItems();
      if (items.length == 1) {
        var card_type = items[0].type;
        console.log("type: " + card_type);
        var card = this.playable_cards[card_type];
        console.log(card);
        this.showCardPlayDialog(card, this.playerHand.getItemDivId(item_id));
      }
      console.log(items);
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
          query(".skiff").forEach(domConstruct.destroy);
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
          /*               
                 Example:
 
                 case 'myGameState':
                    
                    // Add 3 action buttons in the action status bar:
                    
                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                    break;
*/
          case "cardPurchases":
            this.addActionButton(
              "complete_purchase_phase_button",
              _("Complete Purchases"),
              "onCompletePurchasesClicked"
            );
        }
      }
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

      // TODO: here, associate your game notifications with local methods
      dojo.subscribe(
        "showResourceChoiceDialog",
        this,
        "notifShowResourceChoiceDialog"
      );
      dojo.subscribe("resourcesChanged", this, "notifResourcesChanged");
      dojo.subscribe("skiffPlaced", this, "notifSkiffPlaced");
      dojo.subscribe("newHand", this, "notifyNewHand");
      dojo.subscribe("shipMove", this, "notifyShipMove");

      // Example 1: standard notification handling
      // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );

      // Example 2: standard notification handling + tell the user interface to wait
      //            during 3 seconds after calling the method in order to let the players
      //            see what is happening in the game.
      // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
      // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
      //
    },

    // TODO: from this point and below, you can write your game notifications handling methods
    notifShowResourceChoiceDialog: function (notif) {
      this.myDlg = new ebg.popindialog();
      this.myDlg.create("resouceDialog");
      this.myDlg.setTitle(_("Pick a Resource"));
      this.myDlg.setMaxWidth(500); // Optional

      var html = this.format_block("jstpl_resource_dialog");

      this.myDlg.setContent(html);
      this.myDlg.hideCloseIcon();
      this.myDlg.show();

      this.setClientState("client_resourceDialog", {
        descriptionmyturn: _("${you} must select a resource"),
      });

      dojo.query(".resource_button").connect("onclick", this, (event) => {
        console.log("resource button clicked");
        const source = event.target || event.srcElement;

        var context = notif.args.context;
        var number = notif.args.context_number;

        console.log(
          "resource picked " +
            source.dataset.resource +
            " context: " +
            context +
            " number: " +
            number
        );

        event.preventDefault();
        if (source.dataset.resource != null) {
          this.bgaPerformAction("actResourcePickedInDialog", {
            resource: source.dataset.resource,
            context: context,
            number: number,
          });
          this.myDlg.destroy();
        }
      });
    },
    notifDummyStart: function (notif) {
      console.log("dummystart");
      this.bgaPerformAction("actExitDummyStart", {});
    },
    notifResourcesChanged: function (notif) {
      console.log("Notification: resourcesChanged");
      console.log(notif.args.resources);
      console.log("current resources:");
      console.log(this.resources);

      this.resources = notif.args.resources;
      this.updateResources(notif.args.resources);
    },
    notifSkiffPlaced: function (notif) {
      console.log("Skiff placed");
      console.log(notif);

      var slot_name = notif.args.slot_name;
      var slot_number = notif.args.slot_number;
      var postfix = "_" + slot_name + "_" + slot_number;
      var skiff_id = "skiff_p" + notif.args.player_id + postfix;

      this.islandSlots[slot_name][slot_number] = notif.args.player_id;

      console.log("skiff_id: " + skiff_id);
      var player_board_id = "overall_player_board_" + notif.args.player_id;
      console.log("player board id: " + player_board_id);

      var skiff = this.format_block("jstpl_skiff", {
        player_color: notif.args.player_color,
        id: skiff_id,
      });
      var skiff_slot = dojo.query(
        `.skiff_slot[data-slotname="${slot_name}"][data-number="${slot_number}"]`
      )[0];
      console.log("skiff slot:");
      console.log(skiff_slot);

      dojo.place(
        skiff,
        //"skiff_slot" + postfix
        skiff_slot
      );
      this.placeOnObject(skiff_id, player_board_id);
      dojo.style(skiff_id, "zIndex", 1);
      this.slideToObject(
        skiff_id,
        //"skiff_slot" + postfix,
        skiff_slot,
        1000
      ).play();
      dojo.removeClass(skiff_slot, "unoccupied");
    },
    notifyNewHand: function (notif) {
      this.playerHand.removeAll();

      for (var i in notif.args.cards) {
        var card = notif.args.cards[i];
        var color = card.type;
        var value = card.type_arg;
        this.playerHand.addToStockWithId(
          this.getCardUniqueId(color, value),
          card.id
        );
      }
    },
    notifyShipMove: function(notif) {
      console.log("notified ship move");
      console.log(notif);
      var shipid = "player_ship_" + notif.args.player_id;
      var target_id = "seaboardlocation_" + notif.args.new_x + "_" + notif.args.new_y;
      this.slideToObject(shipid, target_id, 10 ).play();
    }
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
