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
  "ebg/core/gamegui",
  "ebg/counter",
], function (dojo, declare) {
  return declare("bgagame.seasofhavoc", ebg.core.gamegui, {
    constructor: function () {
      console.log("seasofhavoc constructor");

      // Here, you can init the global variables of your user interface
      // Example:
      // this.myGlobalValue = 0;
    },

    updateResources: function (resources) {
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
      for (const [slot, info] of Object.entries(islandslots)) {
        var occupant = info['occupying_player_id'];
        console.log("occupant: " + occupant);
        if (occupant != null) {
          var skiff_id = "skiff_p" + occupant + "_" + slot;
          console.log("skiff_id: " + skiff_id);

          var skiff = this.format_block("jstpl_skiff", {
            player_color: players[occupant].color,
            id: skiff_id,
          });
          dojo.place(skiff, "skiff_slot_" + slot);
        }
      }
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

      // Setting up player boards
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        console.log(`player color: ${player.color}`);

        var skiff = this.format_block("jstpl_skiff", {
          player_color: player.color,
          id: "skiff_p" + player.id,
        });
        console.log(skiff);

        document.getElementById("player_board_" + player_id).insertAdjacentHTML(
          "beforeend",
          this.format_block("jstpl_resources_playerboard", {
            player_id: player.id,
            skiff: skiff,
          })
        );
      }

      // TODO: Set up your game interface here, according to "gamedatas"
      this.updateResources(gamedatas.resources);
      this.updateIslandSlots(gamedatas.islandslots, gamedatas.players);

      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      this.addEventToClass("skiff_slot", "onclick", "onClickSkiffSlot");

      console.log("Ending game setup");
    },

    onClickSkiffSlot: function (event) {
      console.log("$$$$ Event : onClickSkiffSlot");
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
      console.log(source.dataset.slotname);

      if (this.isCurrentPlayerActive()) {
        this.bgaPerformAction("actPlaceSkiff", {
          slotname: source.dataset.slotname,
        });
      }
    },
    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
        /* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */

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
        switch (
          stateName
          /*               
                 Example:
 
                 case 'myGameState':
                    
                    // Add 3 action buttons in the action status bar:
                    
                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                    break;
*/
        ) {
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

        console.log(
          "resource picked " + source.dataset.resource + " context: " + context
        );

        event.preventDefault();
        if (source.dataset.resource != null) {
          this.bgaPerformAction("actResourcePickedInDialog", {
            resource: source.dataset.resource,
            context: context,
          });
          this.myDlg.destroy();
        }
      });
    },
    notifResourcesChanged: function (notif) {
      console.log("Notification: resourcesChanged");
      console.log(notif);
      console.log(notif.args.resources);

      this.updateResources(notif.args.resources);
    },
    notifSkiffPlaced: function (notif) {
      console.log("Skiff placed");
      console.log(notif);
      var skiff_id =
        "skiff_p" + notif.args.player_id + "_" + notif.args.slot_name;
      console.log("skiff_id: " + skiff_id);
      var player_board_id = "overall_player_board_" + notif.args.player_id;
      console.log("player board id: " + player_board_id);

      var skiff = this.format_block("jstpl_skiff", {
        player_color: notif.args.player_color,
        id: skiff_id,
      });
      dojo.place(skiff, "skiff_slot_" + notif.args.slot_name);
      this.placeOnObject(skiff_id, player_board_id);
      dojo.style(skiff_id, "zIndex", 1);
      this.slideToObject(
        skiff_id,
        "skiff_slot_" + notif.args.slot_name,
        1000
      ).play();
      //slide.play();
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
