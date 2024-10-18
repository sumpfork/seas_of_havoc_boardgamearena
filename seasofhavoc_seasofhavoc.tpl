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
    <div id="skiff_slot_capitol" class="skiff_slot" data-slotname="capitol"></div>
    <div id="skiff_slot_bank" class="skiff_slot" data-slotname="bank"></div>
    <div id="skiff_slot_shipyard" class="skiff_slot" data-slotname="shipyard"></div>   
    <div id="skiff_slot_blacksmith" class="skiff_slot" data-slotname="blacksmith"></div>    
</div>
<div id="myhand_wrap" class="whiteblock">
    <h3>My Hand</h3>
    <div id="myhand">
    </div>
</div>


<script type="text/javascript">
    // Javascript HTML templates
    var jstpl_resource_dialog=`<div class="resource_choice_dialog" id="resource_choice_dialog">
                        <div id="sail_button" class="sail resource resource_button" data-resource="sail"></div>
                        <div id="cannonball_button" class="cannonball resource resource_button" data-resource="cannonball"></div>
                        <div id="doubloon_button" class="doubloon resource resource_button" data-resource="doubloon"></div>
                        </div>`;
    var jstpl_dummy_dialog=`<div class="dummy_dialog" id="dummy_dialog">
                        <div id="dummy_button" class="dummy_button">Yep, this is dumb</div>
                        </div>`;
    var jstpl_resources_playerboard=`
                    <div class="cp_board">
                        <div id="sail_p\${player_id}" class="sail resource"></div><span id="sailcount_p\${player_id}" class="resource_count">0</span>
                        <div id="cannonball_p\${player_id}" class="cannonball resource"></div><span id="cannonballcount_p\${player_id}" class="resource_count">0</span>
                        <div id="doubloon_p\${player_id}" class="doubloon resource"></div><span id="doublooncount_p\${player_id}" class="resource_count">0</span>
                        \${skiff} <span id="skiffcount_p\${player_id}" class="resource_count">0</span>
                    </div>
                    `;
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
</script>

{OVERALL_GAME_FOOTER}