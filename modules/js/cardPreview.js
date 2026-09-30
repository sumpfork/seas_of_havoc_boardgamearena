/**
 *------
 * SeasOfHavoc implementation : © Peter Gorniak
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/**
 * Seas of Havoc - Card Play Preview
 * While the card play dialog is open, draws what the card will do on the sea board: the route the
 * ship sails as one arrow (bending round corners, as on the cards), a ghost ship where it ends
 * up, a cross where it would ram something, and chevrons out to a shot's range.
 *
 * The movement rules mirror SeaBoard::computeForwardMovement / turnHeading and the action walk in
 * processCardActions on the server; keep them in step (PlaytestUiRegressionTest.js checks both).
 */

define(["dojo/dom", "dojo/dom-construct", g_gamethemeurl + "modules/js/constants.js"], function (dom, domConstruct, Constants) {
  // Debug logging only in Studio (see constants.js).
  const console = Constants.console;

  const NORTH = 1, EAST = 2, SOUTH = 3, WEST = 4;
  const SIZE = 6; // SeaBoard::WIDTH / HEIGHT
  const STEP = { [NORTH]: [0, -1], [EAST]: [1, 0], [SOUTH]: [0, 1], [WEST]: [-1, 0] };
  const TURNS = {
    "pivot left": { [NORTH]: WEST, [WEST]: SOUTH, [SOUTH]: EAST, [EAST]: NORTH },
    "pivot right": { [NORTH]: EAST, [EAST]: SOUTH, [SOUTH]: WEST, [WEST]: NORTH },
    "pivot 180": { [NORTH]: SOUTH, [SOUTH]: NORTH, [EAST]: WEST, [WEST]: EAST },
  };
  // Which way each side fires, as a turn from the ship's heading (the server's fireTurn).
  const SIDE_TURN = { left: "pivot left", right: "pivot right", aft: "pivot 180", fore: null };
  const FIRE_COUNTS = { fire: 1, "2 x fire": 2, "3 x fire": 3 };
  const OPPOSITE = { left: "right", right: "left", fore: "aft", aft: "fore" };

  const forwardOf = (x, y, heading) => {
    const [dx, dy] = STEP[heading];
    return [(x + dx + SIZE) % SIZE, (y + dy + SIZE) % SIZE];
  };

  /**
   * The marks a card would leave, walking its actions with the decisions made so far.
   * `ship` is {x, y, heading}; `blocked(x, y)` says whether a rock or ship is there.
   * Stops where the decisions run out, and at a collision, as the server does.
   */
  function simulateCardPlay(actions, decisions, ship, blocked) {
    const marks = [];
    if (decisions[0] === "pass") return marks; // the whole card is passed: nothing happens
    const state = { x: ship.x, y: ship.y, heading: Number(ship.heading), moved: false, done: false, lastForward: false };
    let lastPivot = null;
    decisions = decisions.slice();
    // The route as lines through cell centres (in cell units). Sailing off an edge ends a line
    // half a cell past the last centre and starts the next half a cell before the first one.
    const routes = [[[ship.x, ship.y]]];

    const forward = () => {
      const [nx, ny] = forwardOf(state.x, state.y, state.heading);
      const [dx, dy] = STEP[state.heading];
      // A pivot between two moves already shows as the route bending round the corner.
      if (lastPivot && lastPivot.afterMove) lastPivot.hidden = true;
      lastPivot = null;
      state.lastForward = true;
      if (blocked(nx, ny)) {
        // The route runs on to the edge of the square it would ram, so the arrow points at it.
        routes[routes.length - 1].push([state.x + dx * 0.55, state.y + dy * 0.55]);
        marks.push({ type: "collision", x: nx, y: ny });
        state.done = true;
        return;
      }
      const route = routes[routes.length - 1];
      if (nx !== state.x + dx || ny !== state.y + dy) {
        route.push([state.x + dx / 2, state.y + dy / 2]);
        routes.push([[nx - dx / 2, ny - dy / 2], [nx, ny]]);
      } else {
        route.push([nx, ny]);
      }
      state.x = nx;
      state.y = ny;
      state.moved = true;
    };
    const pivot = (turn) => {
      lastPivot = { type: "pivot", x: state.x, y: state.y, turn: turn, from: state.heading, afterMove: state.lastForward };
      marks.push(lastPivot);
      state.heading = TURNS[turn][state.heading];
      state.moved = true;
      state.lastForward = false;
    };
    const fire = (action, decision) => {
      const variants = action.variants || [{
        name: action.action, range: action.range, count: FIRE_COUNTS[action.action],
        sides: ["left", "right"], both_sides: false,
      }];
      for (const variant of variants) {
        for (const side of variant.sides) {
          if (decision !== variant.name + " " + side) continue;
          // The server's ShipUpgrades::shotSides: both-sides shots split across the two sides.
          const sides = variant.both_sides ? [side, OPPOSITE[side]] : [side];
          for (const shotSide of sides) {
            const turn = SIDE_TURN[shotSide];
            const heading = turn ? TURNS[turn][state.heading] : state.heading;
            const [dx, dy] = STEP[heading];
            // The shot's line: from just outside the ship through each cell in range, to the far
            // edge of the last one. Like a route, it breaks where it crosses the board edge.
            const lines = [[[state.x + dx * 0.35, state.y + dy * 0.35]]];
            let [x, y] = [state.x, state.y];
            for (let d = 0; d < variant.range; d++) {
              const [nx, ny] = forwardOf(x, y, heading);
              if (nx !== x + dx || ny !== y + dy) {
                lines[lines.length - 1].push([x + dx / 2, y + dy / 2]);
                lines.push([[nx - dx / 2, ny - dy / 2]]);
              }
              lines[lines.length - 1].push([nx, ny]);
              [x, y] = [nx, ny];
              marks.push({ type: "chevron", x: x, y: y, heading: heading });
            }
            lines[lines.length - 1].push([x + dx * 0.45, y + dy * 0.45]);
            marks.push({ type: "shot", lines: lines, heading: heading, from: [state.x, state.y] });
          }
          return;
        }
      }
    };

    const walk = (list) => {
      for (const action of list) {
        if (state.done) return;
        // An optional (costed) action is skipped with a "skip" decision, as on the server.
        if (action.cost !== undefined) {
          if (decisions.length === 0) { state.done = true; return; }
          if (decisions[0] === "skip") { decisions.shift(); continue; }
        }
        switch (action.action) {
          case "sequence":
            walk(action.actions);
            break;
          case "choice": {
            if (decisions.length === 0) { state.done = true; return; }
            const decision = decisions.shift();
            const chosen = action.choices.find((c) => (c.name || c.action) === decision);
            if (chosen) walk([chosen]);
            break;
          }
          case "forward":
            forward();
            break;
          case "left":
          case "right":
            forward();
            if (!state.done) pivot("pivot " + action.action);
            if (!state.done) forward();
            break;
          case "pivot left":
          case "pivot right":
          case "pivot 180":
            pivot(action.action);
            break;
          case "fire":
          case "2 x fire":
          case "3 x fire":
            if (decisions.length === 0) { state.done = true; return; }
            fire(action, decisions.shift());
            break;
          default:
            // Captain abilities, repairs: nothing to show on the board.
            break;
        }
      }
    };
    walk(actions);

    // Pivots inside a route's bend are drawn by the bend.
    for (let i = marks.length - 1; i >= 0; i--) {
      if (marks[i].hidden) marks.splice(i, 1);
    }
    for (const points of routes) {
      if (points.length > 1) marks.push({ type: "route", points: points });
    }
    if (state.moved) {
      marks.push({ type: "ghost", x: state.x, y: state.y, heading: state.heading });
    }
    return marks;
  }

  // Glyphs drawn pointing north; each mark is rotated to its heading.
  const CHEVRON = '<svg viewBox="0 0 40 40"><path d="M8 22 L20 10 L32 22 L32 30 L20 18 L8 30 Z"/></svg>';
  const CROSS = '<svg viewBox="0 0 40 40"><path d="M10 6 L20 16 L30 6 L34 10 L24 20 L34 30 L30 34 L20 24 L10 34 L6 30 L16 20 L6 10 Z"/></svg>';

  /**
   * An SVG path through the points, each corner rounded off over half a cell so a turn reads as
   * one arrow bending round it, as the cards draw "left" and "right".
   */
  function roundedPath(points, radius) {
    let d = `M ${points[0][0]} ${points[0][1]}`;
    for (let i = 1; i < points.length - 1; i++) {
      const [px, py] = points[i - 1], [vx, vy] = points[i], [nx, ny] = points[i + 1];
      const inLength = Math.hypot(vx - px, vy - py), outLength = Math.hypot(nx - vx, ny - vy);
      const r = Math.min(radius, inLength / 2, outLength / 2);
      const ax = vx - ((vx - px) / inLength) * r, ay = vy - ((vy - py) / inLength) * r;
      const bx = vx + ((nx - vx) / outLength) * r, by = vy + ((ny - vy) / outLength) * r;
      d += ` L ${ax} ${ay} Q ${vx} ${vy} ${bx} ${by}`;
    }
    const last = points[points.length - 1];
    return d + ` L ${last[0]} ${last[1]}`;
  }

  return {
    simulateCardPlay: simulateCardPlay,

    /** Redraw the preview for the card in the play dialog and the options chosen so far. */
    updateCardPreview: function () {
      this.clearCardPreview();
      if (!this._previewActions || !this.dep_tree) return;
      const shipChoice = document.querySelector('input[name="card_ship"]:checked');
      const shipArg = shipChoice && shipChoice.value === "2" ? this.player_id + "_2" : String(this.player_id);
      const ship = this.getObjectOnSeaboard("player_ship", shipArg);
      if (!ship) return;
      const blocked = (x, y) =>
        this.seaboard.some((e) => e.x == x && e.y == y && (e.type === "rock" || e.type === "player_ship"));
      const marks = simulateCardPlay(this._previewActions, this._decisionSummary(this.dep_tree), ship, blocked);

      // Cell centres in the board's own pixels, measured from the cell anchors (and unscaled, in
      // case the page is zoomed).
      const board = dom.byId("seaboard");
      const boardRect = board.getBoundingClientRect();
      const scale = boardRect.width / board.offsetWidth;
      const centreOf = (id) => {
        const r = dom.byId(id).getBoundingClientRect();
        return [(r.left + r.width / 2 - boardRect.left) / scale, (r.top + r.height / 2 - boardRect.top) / scale];
      };
      const [ox, oy] = centreOf("seaboardlocation_0_0");
      const cell = centreOf("seaboardlocation_1_0")[0] - ox;
      const toPixels = ([x, y]) => [ox + x * cell, oy + y * cell];

      const routes = marks.filter((m) => m.type === "route");
      const pivots = marks.filter((m) => m.type === "pivot");
      // Pivots where the ship ends up are played out: the ghost starts at the old heading and
      // turns, and their arrows fade once it has (an arrow beside the already turned ship reads
      // as a turn still to come).
      const ghostMark = marks.find((m) => m.type === "ghost");
      const turnsInPlace = pivots.filter((p) => ghostMark && p.x === ghostMark.x && p.y === ghostMark.y);
      const shots = marks.filter((m) => m.type === "shot");
      if (routes.length > 0 || pivots.length > 0 || shots.length > 0) {
        const paths = routes.map((route, i) =>
          `<path d="${roundedPath(route.points.map(toPixels), cell / 2)}"` +
          (i === routes.length - 1 ? ' marker-end="url(#card_preview_arrowhead)"' : "") + "/>",
        );
        // A pivot on the spot: an arc round the ship from where its bow points to where it will.
        const BOW_ANGLE = { [NORTH]: -90, [EAST]: 0, [SOUTH]: 90, [WEST]: 180 };
        for (const pivot of pivots) {
          const [cx, cy] = toPixels([pivot.x, pivot.y]);
          const r = cell * 0.42;
          const sweep = pivot.turn === "pivot left" ? -90 : pivot.turn === "pivot right" ? 90 : 180;
          const at = (deg) => [cx + r * Math.cos((deg * Math.PI) / 180), cy + r * Math.sin((deg * Math.PI) / 180)];
          const [sx, sy] = at(BOW_ANGLE[pivot.from]);
          // A half turn is drawn as two quarter arcs: a single 180-degree arc is ambiguous in SVG.
          const [mx, my] = at(BOW_ANGLE[pivot.from] + sweep / 2);
          const [ex, ey] = at(BOW_ANGLE[pivot.from] + sweep);
          const flag = sweep > 0 ? 1 : 0;
          paths.push(
            `<path${turnsInPlace.includes(pivot) ? ' class="card_preview_pivot_fading"' : ""}` +
              ` d="M ${sx} ${sy} A ${r} ${r} 0 0 ${flag} ${mx} ${my} A ${r} ${r} 0 0 ${flag} ${ex} ${ey}"` +
              ' marker-end="url(#card_preview_arrowhead)"/>',
          );
        }
        // Shots: a dashed line through the range and a bar across its end. Where the shot leaves
        // the ship gets the muzzle flash, drawn below.
        for (const shot of shots) {
          const lines = shot.lines.map((line) => line.map(toPixels));
          for (const line of lines) {
            paths.push(`<path class="card_preview_shot" d="M ${line.map((p) => p.join(" ")).join(" L ")}"/>`);
          }
          const [ex, ey] = lines[lines.length - 1][lines[lines.length - 1].length - 1];
          const [dx, dy] = STEP[shot.heading];
          const half = cell * 0.3;
          paths.push(
            `<path class="card_preview_shot_end" d="M ${ex - dy * half} ${ey - dx * half} L ${ex + dy * half} ${ey + dx * half}"/>`,
          );
        }
        domConstruct.place(
          `<svg class="card_preview_route" width="${board.offsetWidth}" height="${board.offsetHeight}">` +
            '<defs><marker id="card_preview_arrowhead" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="3.2" markerHeight="3.2" orient="auto-start-reverse">' +
            '<path d="M0 0 L10 5 L0 10 Z"/></marker></defs>' + paths.join("") + "</svg>",
          board,
        );
      }

      // The cannon fire itself on the firing ship, as the shot animation draws it (see
      // muzzleFlashAnimation), but held still.
      for (const shot of shots) {
        const [fx, fy] = toPixels(shot.from);
        const flash = domConstruct.place(
          this.format_block("jstpl_cannon_fire", { id: "card_preview_flash_" + shots.indexOf(shot) }),
          board,
        );
        flash.classList.add("card_preview_flash");
        flash.style.left = fx + "px";
        flash.style.top = fy + "px";
        flash.style.rotate = (((shot.heading - 1) * 90 + 180) % 360) + "deg";
      }

      for (const mark of marks) {
        if (mark.type === "route" || mark.type === "pivot" || mark.type === "shot") continue;
        const anchor = dom.byId("seaboardlocation_" + mark.x + "_" + mark.y);
        if (!anchor) continue;
        let html;
        let rotate = mark.heading ? (mark.heading - 1) * 90 : 0;
        if (mark.type === "ghost") {
          // The ship sprite's own orientation, as the board draws ships.
          const origin = document.getElementById("player_ship_" + shipArg);
          const shipName = origin.dataset.shipname;
          // Fade the real ship so the ghost reads as where it will be, even in the same square.
          origin.classList.add("card_preview_origin");
          html = `<div class="player_ship card_preview_ghost" data-shipname="${shipName}"></div>`;
          rotate = this.getHeadingDegrees(mark.heading);
        } else {
          const glyph = { chevron: CHEVRON, collision: CROSS }[mark.type];
          html = `<div class="card_preview_mark card_preview_${mark.type}">${glyph}</div>`;
        }
        const node = domConstruct.place(html, anchor);
        node.style.rotate = rotate + "deg";
        if (mark.type === "ghost" && turnsInPlace.length > 0) {
          // Start at the heading before the pivots and turn through them: left is anticlockwise,
          // right clockwise, around a half turn.
          const start = this.getHeadingDegrees(turnsInPlace[0].from);
          const turn = turnsInPlace.reduce((sum, p) => sum + (p.turn === "pivot left" ? -90 : p.turn === "pivot right" ? 90 : 180), 0);
          node.style.rotate = start + "deg";
          node.getBoundingClientRect(); // commit the start angle so the change below animates
          node.classList.add("card_preview_turning");
          node.style.rotate = start + turn + "deg";
        }
      }
    },

    clearCardPreview: function () {
      document.querySelectorAll(".card_preview_mark, .card_preview_ghost, .card_preview_route, .card_preview_flash")
        .forEach((node) => node.remove());
      document.querySelectorAll(".card_preview_origin").forEach((node) => node.classList.remove("card_preview_origin"));
    },
  };
});
