{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- SeasOfHavoc implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    seasofhavoc_seasofhavoc.tpl

    This is the HTML template of your game.

    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.

    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format

    See your "view" PHP file to check how to set variables and control blocks

    Please REMOVE this comment before publishing your game on BGA
-->

<div id="board" class="board shadow">
    <div id="skiff_slot_capitol_n1" class="skiff_slot unoccupied" data-slotname="capitol" data-number="n1"></div>
    <div id="skiff_slot_bank_n1" class="skiff_slot unoccupied" data-slotname="bank" data-number="n1"></div>
    <div id="skiff_slot_shipyard_n1" class="skiff_slot unoccupied" data-slotname="shipyard" data-number="n1"></div>   
    <div id="skiff_slot_blacksmith_n1" class="skiff_slot unoccupied" data-slotname="blacksmith" data-number="n1"></div>
    <div id="skiff_slot_green_flag_n1" class="skiff_slot unoccupied" data-slotname="green_flag" data-number="n1"></div>
    <div id="skiff_slot_tan_flag_n1" class="skiff_slot unoccupied" data-slotname="tan_flag" data-number="n1"></div>
    <div id="skiff_slot_blue_flag_n1" class="skiff_slot unoccupied" data-slotname="blue_flag" data-number="n1"></div>
    <div id="skiff_slot_red_flag_n1" class="skiff_slot unoccupied" data-slotname="red_flag" data-number="n1"></div>
    <div id="seaboard"></div>
    <div id="skiff_slot_market_n1" class="skiff_slot unoccupied" data-slotname="market" data-number="n1"></div>
    <div id="skiff_slot_market_n2" class="skiff_slot unoccupied" data-slotname="market" data-number="n2"></div>
    <div id="skiff_slot_market_n3" class="skiff_slot unoccupied" data-slotname="market" data-number="n3"></div>
    <div id="skiff_slot_market_n4" class="skiff_slot unoccupied" data-slotname="market" data-number="n4"></div>
    <div id="skiff_slot_market_n5" class="skiff_slot unoccupied" data-slotname="market" data-number="n5"></div>
</div>
<div class="whiteblock market-section">
    <div class="market-container">
        <div class="market-area">
            <h3>Market</h3>
            <div id="market"></div>
        </div>
        <div class="scrap-area">
            <h3>Scrap Pile</h3>
            <div id="scrap"></div>
        </div>
    </div>
</div>

<div id="mycards">
<div id="mydeck_wrap" class="whiteblock">
    <h3>My Deck</h3>
    <div id="mydeck">
    </div>
</div>
<div id="myhand_wrap" class="whiteblock">
    <h3>My Hand</h3>
    <div id="myhand">
    </div>
</div>
<div id="mydiscard_wrap" class="whiteblock">
    <h3>My Discard</h3>
    <div id="mydiscard">
    </div>
</div>
</div>

<div id="myship">
<div id="captain_wrap" class="whiteblock">
    <h3>Captain</h3>
    <div id="captain_stock">
    </div>
</div>
<div id="upgrades_wrap" class="whiteblock">
    <h3>Ship Upgrades</h3>
    <div id="upgrades_stock">
    </div>
</div>
</div>

<div id="damage_deck">
</div>

<script type="text/javascript">
    var jstpl_dummy_dialog=`<div class="dummy_dialog" id="dummy_dialog">
                        <div id="dummy_button" class="dummy_button">Yep, this is dumb</div>
                        </div>`;
    var jstpl_card_play_dialog=`<div id="card_display_dialog">
                        <div id="card_display"></div>
                        <div id="card_choices"></div>
                        <div id="play_card_button" class="bgabutton bgabutton_green play_card_button">Play Card</div>
                        </div>`;
    var jstpl_resources_playerboard=`
                    <div class="cp_board" id="player_resource_board_p\${player_id}">
                        <div id="sail_p\${player_id}" class="sail resource"></div><span id="sailcount_p\${player_id}" class="resource_count">0</span>
                        <div id="cannonball_p\${player_id}" class="cannonball resource"></div><span id="cannonballcount_p\${player_id}" class="resource_count">0</span>
                        <div id="doubloon_p\${player_id}" class="doubloon resource"></div><span id="doublooncount_p\${player_id}" class="resource_count">0</span>
                        \${skiff} <span id="skiffcount_p\${player_id}" class="resource_count">0</span>
                    </div>
                    <div class="cp_board" id="player_token_board_p\${player_id}">
                        <div id="green_flag_p\${player_id}" class="flagish no_own_flag" data-tokenkey="green_flag"></div>
                        <div id="tan_flag_p\${player_id}" class="flagish no_own_flag" data-tokenkey="tan_flag"></div>
                        <div id="blue_flag_p\${player_id}" class="flagish no_own_flag" data-tokenkey="blue_flag"></div>
                        <div id="red_flag_p\${player_id}" class="flagish no_own_flag" data-tokenkey="red_flag"></div>
                        <div id="first_player_token_p\${player_id}" class="flagish no_own_flag" data-tokenkey="first_player_token"></div>
                    </div>`;
    var jstpl_card_purchase_button=`<a id="\${id}" class="bgabutton bgabutton_green purchase_card_button" data-slotnumber="\${slotnumber}">Purchase Card</a>`;
    var jstpl_scrap_card_dialog=`<div id="scrap_card_dialog" class="scrap_card_dialog">
                        <h3>Choose a card to scrap</h3>
                        <div id="scrap_card_selection_wrapper">
                            <div id="scrap_card_selection"></div>
                        </div>
                        <div class="scrap_dialog_buttons">
                            <button id="cancel_scrap_button" class="bgabutton bgabutton_gray">Cancel</button>
                        </div>
                    </div>`;
    var jstpl_card_choices_row=`<div class="card_choices_row"><div class="card_choice_row_num">\${row_number}</div>\${card_choices}</div>`;
    var jstpl_card_choice_radio=`<div class="card_choice_radio_container"><input type="radio" class="card_choice_radio" id="\${id}" name="\${name}" value="\${value}"/><label for="\${id}">\${label}</label></div>`;
    var jstpl_skiff=`<div id="\${id}" class="skiff">
    <svg width="100%" height="100%" viewBox="0 0 298 265" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">
    <g transform="matrix(1,0,0,1,-702.792,-710.191)">
        <g transform="matrix(4.16667,0,0,4.16667,703.292,801.562)">
            <g id="Shapes">
                <path d="M0,19.624C5.469,20.959 28.423,22.913 29.458,22.081C24.755,18.445 20.071,14.823 15.468,11.265C16.694,9.088 17.977,6.986 19.089,4.798C21.739,-0.414 23.617,-5.906 24.651,-11.663C24.982,-13.508 25.143,-15.392 25.246,-17.266C25.313,-18.489 25.132,-19.725 25.067,-20.955C25.055,-21.182 25.065,-21.41 25.065,-21.809C25.635,-21.364 26.091,-21.022 26.531,-20.662C29.626,-18.128 32.719,-15.593 35.809,-13.054C38.539,-10.811 41.265,-8.563 43.992,-6.317C44.584,-5.829 45.149,-5.304 45.774,-4.864C46.15,-4.6 46.277,-4.263 46.31,-3.868C46.429,-2.491 46.521,-1.112 46.63,0.266C46.784,2.225 46.947,4.183 47.1,6.142C47.214,7.603 47.316,9.065 47.429,10.527C47.585,12.535 47.751,14.543 47.905,16.552C48.02,18.063 48.122,19.576 48.234,21.087C48.258,21.416 48.303,21.743 48.342,22.107C56.038,21.562 63.693,21.038 71.027,18.434L71.108,18.586C70.899,19.191 70.695,19.798 70.48,20.4C68.343,26.39 66.23,32.389 64.039,38.359C63.704,39.272 63.028,40.066 62.459,40.879C62.343,41.046 61.999,41.122 61.758,41.124C58.118,41.151 54.478,41.151 50.838,41.173C37.213,41.255 23.589,41.342 9.964,41.427C9.11,41.433 8.272,41.425 7.516,40.904C6.719,40.354 6.485,39.489 6.206,38.668C4.424,33.419 2.668,28.16 0.904,22.905C0.63,22.087 0.346,21.272 0.091,20.448C0.018,20.211 0.033,19.947 0,19.624" 
                style="fill:#\${player_color};fill-rule:nonzero;stroke:#\${player_color};stroke-width:0.24px;"/>
            </g>
        </g>
    </g>
    </svg></div>`;
    var jstpl_cannon_fire=`<div id="cannonfire">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="height: 64px; width: 64px;">
        <defs><linearGradient id="lorc-cannon-shot-gradient-1" x1="0" x2="1" y1="0" y2="1"><stop offset="0%" stop-color="#4a4a4a" stop-opacity="1"></stop><stop offset="100%" stop-color="#d0021b" stop-opacity="1"></stop></linearGradient></defs>
        <g class="" transform="translate(0,0)" style="">
            <path d="M168.875 11.395l86.455 98.443c2.175-1.122 4.337-2.206 6.47-3.215 14.37-6.805 27.684-11.083 39.76-12.103.75-.064 1.498-.113 2.243-.15L242.69 11.396h-73.815zM18.348 64.145v27.722l.21-.25 128.432 107.3c3.262-3.764 6.643-7.526 10.127-11.276L18.347 64.147zm287.896 48.835c-.982.017-2.017.07-3.11.163-8.734.738-20.327 4.21-33.337 10.37-.564.268-1.146.567-1.715.844l30.996 35.295c1.406-.72 2.808-1.43 4.193-2.1 13.245-6.395 25.504-10.477 36.683-11.554.592-.057 1.183-.1 1.774-.14l-21.385-29.032-.344.344c-2.62-2.62-6.88-4.304-13.754-4.19zm-287.896 2.817V264.15l69.13 47.274c.036-.995.088-1.993.172-2.996 1.02-12.077 5.298-25.392 12.104-39.762 8.213-17.34 20.215-36.21 35.324-55.348l-116.73-97.52zm326.18 48.625c-.875.025-1.802.083-2.784.178-7.853.756-18.432 4.027-30.346 9.78-23.826 11.508-53.028 32.712-80.87 60.554-27.843 27.84-49.048 57.044-60.555 80.87-5.754 11.914-9.025 22.494-9.782 30.346-.755 7.853.795 12.184 3.197 14.586 2.402 2.402 6.735 3.952 14.588 3.196 7.852-.757 18.432-4.028 30.345-9.782 7.81-3.77 16.202-8.6 24.928-14.347-17.195 39.23-28.067 89.333-34.394 153.564 37.517-129.093 80.838-109.43 114.544-6.287-18.62-109.564 99.38-61.623 185.008 5.397-66.417-101.782-124.625-177.518 4.55-188.135-124.058-5.07-140.995-44.53-21.876-102.653-58.372 6.19-105.555 15.9-143.54 32.65 4.806-7.536 8.915-14.8 12.206-21.613 5.754-11.914 9.023-22.494 9.78-30.346.756-7.852-.794-12.183-3.196-14.585-1.8-1.8-4.688-3.122-9.332-3.352-.774-.038-1.596-.046-2.47-.02zm-173.442 35.65c-3.353 3.61-6.6 7.226-9.734 10.842l37.066 30.97c2.84-3.234 5.753-6.464 8.768-9.687l-36.1-32.125zm142.27 1.117c3.84.122 6.953 1.23 9.142 3.42 6.837 6.836 3.118 22.676-8.182 41.52-29.24 17.088-52.02 39.92-69.58 70.706-20.12 12.694-37.26 17.173-44.45 9.984-11.437-11.437 6.648-48.066 40.396-81.814 26.365-26.366 54.49-43.17 70.986-43.81.577-.02 1.14-.024 1.69-.007zm-163.9 24.138c-14.242 18.11-25.428 35.748-32.81 51.338-6.163 13.01-9.634 24.602-10.373 33.336-.738 8.734 1.033 13.87 4.026 16.86l-.1.1 31.152 21.304c.034-1.296.112-2.6.238-3.91 1.076-11.177 5.158-23.437 11.555-36.68 7.777-16.104 19.084-33.65 33.275-51.465l-36.965-30.882z" fill="url(#lorc-cannon-shot-gradient-1)" transform="translate(76.8, 76.8) scale(0.7, 0.7) rotate(45, 256, 256) skewX(0) skewY(0)"></path>
        </g>
    </svg></div>`;
    var jstpl_explosion=`<div id="explosion">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="height: 64px; width: 64px;">
        <--<defs><linearGradient x1="0" x2="0" y1="0" y2="1" id="lorc-mine-explosion-gradient-1"><stop offset="0%" stop-color="#483636" stop-opacity="1"></stop><stop offset="100%" stop-color="#ae2828" stop-opacity="1"></stop></linearGradient></defs>-->
        <g class="" transform="translate(0,0)" style="fill:white">
            <path d="M287.586 15.297l3.504 110.963 31.537-110.963h-35.04zm-95.78.238l-1.75 236.047-170.533-43.33L130.486 377.69l-88.77-5.174 114.432 112.357-44.466-75.867L186.896 417l-51.748-109.94 110.114 79.956-12.635-185.23.002.003 75.212 170.57 75.816-89.95-6.62 154.582 60.173-39.978-20.388 79.486 75.756-142.787-75.924 1.94L487.32 155.87l-131.402 73.08-12.264-139.69-65.41 140.336-86.435-214.06h-.003zM45.503 44.095L39.355 75.94 154.285 218h.002l-77.6-166.836-31.185-7.07zm422.27 24.776l-31.184 7.07-43.738 107.37 81.068-82.59-6.147-31.85zM279.208 403.61c-40.176 0-72.708 32.537-72.708 72.71 0 5.725.636 10.706 1.887 16.05 7.25-32.545 36.097-56.655 70.82-56.655 34.82 0 63.673 23.97 70.82 56.656 1.218-5.277 1.888-10.404 1.888-16.05 0-40.175-32.536-72.71-72.71-72.71z" fill="url(#lorc-mine-explosion-gradient-1)"></path>
        </g>
    </svg>
    </div>`;
    var jstpl_seaboard_location=`<div class="seaboardlocation" id="\${id}"></div>`;
    var jstpl_unique_token=`<div class="flagish" data-tokenkey="\${token_key}"></div>`;
    var jstpl_player_ship=`<div class="player_ship" id="\${id}" 
        data-shipname="\${shipname}"></div>`;

</script>

{OVERALL_GAME_FOOTER}