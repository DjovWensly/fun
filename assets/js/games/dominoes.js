/* Dominoes UI. The server holds the real game; this only draws it and sends moves. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  // Pip positions on a 3x3 grid (1..9, reading order).
  var PIPS = { 0: [], 1: [5], 2: [1, 9], 3: [1, 5, 9], 4: [1, 3, 7, 9], 5: [1, 3, 5, 7, 9], 6: [1, 3, 4, 6, 7, 9] };

  function half(n) {
    var cells = [];
    for (var i = 1; i <= 9; i++) cells.push(h('i', { class: PIPS[n].indexOf(i) >= 0 ? 'pip' : '' }));
    return h('span', { class: 'half n' + n }, cells); // nN lets the 'coloured dots' style colour each number
  }

  /** A domino. dir: 'v' (vertical) or 'h' (horizontal). size: half size in px (optional). */
  function tile(a, b, dir, extra, size) {
    var attrs = { class: 'domino ' + (dir || 'v') + (extra ? ' ' + extra : ''), 'aria-label': a + '-' + b };
    if (size) attrs.style = '--s:' + size + 'px';
    return h('span', attrs, [half(a), h('span', { class: 'bar' }), half(b)]);
  }

  var els = {};
  var state = null;
  var ctx = null;
  var busy = false;

  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';
    els.scores = h('div', { class: 'scoreboard' });
    els.status = h('div', { class: 'turn-status' });
    els.felt = h('div', { class: 'felt' });
    els.info = h('div', { class: 'felt-info' });
    els.choose = h('div', { class: 'side-choice', hidden: true });
    els.result = h('div', { class: 'result panel', hidden: true });
    els.actions = h('div', { class: 'row center-row actions' });
    els.hand = h('div', { class: 'hand' });
    els.log = h('ul', { class: 'log' });
    root.appendChild(h('div', { class: 'dominoes' }, [
      els.scores, els.status,
      h('div', { class: 'table-frame' }, [els.felt]),
      els.choose, els.result,
      h('div', { class: 'rack' }, [els.hand, els.actions]),
      els.log
    ]));
    var timer;
    window.addEventListener('resize', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { if (state) drawBoard(state); }, 120);
    });
  }

  // ---------- names of players / teams ----------
  function sideOf(g, seat) { return g.sideOf[seat]; }

  function sideName(g, sideId) {
    if (!g.teams) return ctx.name(sideId);
    var idx = 0, names = [];
    g.sides.forEach(function (s, i) {
      if (s.id === sideId) { idx = i; names = s.seats.map(function (x) { return ctx.player(x) ? ctx.player(x).name : '?'; }); }
    });
    return t('team_n', { n: idx + 1 }) + ' (' + names.join(' & ') + ')';
  }

  var heard = { round: null, len: 0 };
  function update(g, c) {
    ctx = c;
    state = g;
    var you = ctx.you();
    var myTurn = g.phase === 'playing' && g.turn === you;
    // "clac" each time a tile lands on the table
    if (heard.round === g.round && g.chain.length > heard.len) if (GP.sound) GP.sound.play('clack');
    heard = { round: g.round, len: g.chain.length };

    renderScores(g);

    if (g.phase === 'playing') {
      els.status.textContent = myTurn ? t('your_turn') : t('turn_of', { name: ctx.name(g.turn) });
      els.status.className = 'turn-status' + (myTurn ? ' mine' : '');
    } else {
      els.status.textContent = '';
      els.status.className = 'turn-status';
    }

    drawBoard(g);

    // hand
    els.hand.innerHTML = '';
    g.hand.forEach(function (tl, i) {
      var sides = g.playable[i];
      var cls = myTurn ? (sides ? 'playable' : 'dim') : '';
      var el = tile(tl[0], tl[1], 'v', cls);
      if (myTurn && sides) {
        el.setAttribute('role', 'button');
        el.setAttribute('tabindex', '0');
        el.addEventListener('click', function () { pick(i, sides); });
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(i, sides); } });
      }
      els.hand.appendChild(el);
    });
    if (!g.hand.length && you === null) els.hand.appendChild(h('p', { class: 'hand-empty' }, t('spectating')));

    // draw / pass
    els.actions.innerHTML = '';
    var noPlay = myTurn && Object.keys(g.playable).length === 0;
    if (noPlay) {
      els.actions.appendChild(h('span', { class: 'hand-empty' }, t('no_move')));
      if (g.canDraw) els.actions.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'draw' }); } }, t('draw') + ' (' + g.boneyard + ')'));
      else els.actions.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'pass' }); } }, t('pass')));
    }
    els.choose.hidden = true;

    renderResult(g);
    renderLog(g);
  }

  function renderScores(g) {
    els.scores.innerHTML = '';
    var goal = g.goal === 'rounds' ? t('target_rounds', { n: g.target }) : t('target_pts', { n: g.target });
    els.scores.appendChild(h('div', { class: 'label' }, [t('round_n', { n: g.round }), t('mode_' + g.mode), goal].join(' · ')));
    var main = function (sideId) { return String(g.goal === 'rounds' ? g.wins[sideId] : g.scores[sideId]); };
    var sub = function (sideId) { return g.goal === 'rounds' ? t('pips', { n: g.scores[sideId] }) : ''; };

    if (g.teams) {
      g.sides.forEach(function (s, i) {
        var members = h('div', { class: 'members' }, s.seats.map(function (seat) {
          var p = ctx.player(seat);
          return h('span', { 'data-seat': seat, class: g.turn === seat && g.phase === 'playing' ? 'turn' : '' }, [
            ctx.name(seat) + ' ', h('span', { class: 'tiles-left', title: t('tiles_n', { n: g.counts[seat] }) }, String(g.counts[seat])),
            p && !p.online ? ' · ' + t('offline') : ''
          ]);
        }));
        els.scores.appendChild(h('div', { class: 'team-box' }, [
          h('span', { class: 'label' }, t('team_n', { n: i + 1 })), members,
          h('strong', null, main(s.id)), sub(s.id) ? h('span', { class: 'sub' }, sub(s.id)) : null
        ]));
      });
      return;
    }
    g.seats.forEach(function (s) {
      var p = ctx.player(s);
      els.scores.appendChild(h('div', { 'data-seat': s, class: 'score' + (g.turn === s && g.phase === 'playing' ? ' turn' : '') + (p && !p.online ? ' offline' : '') }, [
        h('span', { class: 'pname' }, ctx.name(s)),
        h('span', { class: 'tiles-left', title: t('tiles_n', { n: g.counts[s] }) }, String(g.counts[s])),
        h('strong', null, main(s)),
        sub(s) ? h('span', { class: 'sub' }, sub(s)) : null
      ]));
    });
  }

  // ---------- the table: the first tile stays in the middle, the chain grows on both sides ----------
  //
  // Positions are computed on a grid where 1 unit = half a domino. A tile lying flat is 2 x 1,
  // a double (placed crosswise) or an upright tile is 1 x 2. The right arm goes right and turns
  // downward at the edge of the table; the left arm goes left and turns upward. Each turn is a
  // column of two upright tiles, which leaves room for the doubles of the next row.
  var TURN_TILES = 2;

  /** Lay out one arm. tiles: [{i, near, far}] from the centre outwards. */
  function walkArm(tiles, x, dir, vdir, B) {
    var rects = [], axis = 0, mode = 'row', colX = 0, colY = 0, colN = 0;
    tiles.forEach(function (t) {
      var dbl = t.near === t.far;
      if (mode === 'row') {
        var w = dbl ? 1 : 2;
        if (dir > 0 ? x + w > B - 1 : x - w < -(B - 1)) {
          mode = 'col'; colX = dir > 0 ? x : x - 1; colY = vdir > 0 ? axis - 0.5 : axis + 0.5; colN = 0;
        } else {
          rects.push({ i: t.i, x: dir > 0 ? x : x - w, y: axis - (dbl ? 1 : 0.5), w: w, h: dbl ? 2 : 1, o: dbl ? 'v' : 'h',
            a: dir > 0 ? t.near : t.far, b: dir > 0 ? t.far : t.near });
          x += dir * w;
          return;
        }
      }
      // upright tile in the turn
      rects.push({ i: t.i, x: colX, y: vdir > 0 ? colY : colY - 2, w: 1, h: 2, o: 'v',
        a: vdir > 0 ? t.near : t.far, b: vdir > 0 ? t.far : t.near });
      colY += vdir * 2;
      colN++;
      if (colN >= TURN_TILES) {
        mode = 'row';
        axis = colY - vdir * 0.5;
        x = dir > 0 ? colX : colX + 1;
        dir = -dir;
      }
    });
    return { rects: rects };
  }

  function layoutChain(chain, origin, B) {
    var o = chain[origin];
    var dbl = o[0] === o[1];
    var half = dbl ? 0.5 : 1;
    var rects = [{ i: origin, x: -half, y: dbl ? -1 : -0.5, w: dbl ? 1 : 2, h: dbl ? 2 : 1, o: dbl ? 'v' : 'h', a: o[0], b: o[1] }];
    var right = [], left = [];
    for (var i = origin + 1; i < chain.length; i++) right.push({ i: i, near: chain[i][0], far: chain[i][1] });
    for (var j = origin - 1; j >= 0; j--) left.push({ i: j, near: chain[j][1], far: chain[j][0] });
    var R = walkArm(right, half, 1, 1, B);
    var L = walkArm(left, -half, -1, -1, B);
    rects = rects.concat(R.rects, L.rects);
    var reach = 1;
    rects.forEach(function (r) { reach = Math.max(reach, Math.abs(r.y), Math.abs(r.y + r.h)); });
    return { rects: rects, reach: reach };
  }

  function drawBoard(g) {
    var felt = els.felt;
    felt.innerHTML = '';
    var info = [];
    if (g.mode === 'block') { if (g.boneyard) info.push(t('sleeping', { n: g.boneyard })); }
    else info.push(t('boneyard', { n: g.boneyard }));
    els.info.innerHTML = '';
    els.info.appendChild(h('span', null, info.join('')));
    els.info.appendChild(h('span', null, g.mode === 'fives' && g.chain.length ? t('ends_count', { n: g.ends }) : ''));
    felt.appendChild(els.info);

    if (!g.chain.length) {
      if (g.phase === 'playing') felt.appendChild(h('p', { class: 'felt-empty' }, t('turn_of', { name: ctx.name(g.turn) })));
      return;
    }

    var board = h('div', { class: 'chain-board' });
    felt.appendChild(board);
    var W = board.clientWidth, H = board.clientHeight;
    var origin = Math.max(0, Math.min(g.chain.length - 1, g.origin !== undefined ? g.origin : Math.floor(g.chain.length / 2)));

    // Biggest tiles that fit: large while the chain is short, smaller as it grows.
    var u = Math.min(W > 700 ? 46 : 34, Math.floor(W / 9));
    var plan;
    for (; u >= 10; u--) {
      plan = layoutChain(g.chain, origin, W / (2 * u) - 0.15);
      if (2 * plan.reach * u <= H) break;
    }
    var cx = W / 2, cy = H / 2;
    var lastIdx = -1;
    if (g.last && g.last.tile) lastIdx = g.last.side === 'L' ? 0 : g.chain.length - 1;
    var s = Math.max(6, u - 4);

    plan.rects.forEach(function (r) {
      var cell = h('div', { class: 'chain-cell', 'data-i': r.i, style: 'left:' + (cx + r.x * u) + 'px;top:' + (cy + r.y * u) + 'px;width:' + (r.w * u) + 'px;height:' + (r.h * u) + 'px' });
      cell.appendChild(tile(r.a, r.b, r.o, (r.i === lastIdx ? 'last' : '') + (r.i === origin ? ' origin' : ''), s));
      board.appendChild(cell);
    });
  }

  // ---------- moves ----------
  function pick(i, sides) {
    var tl = state.hand[i];
    var ends = state.chain.length ? [state.chain[0][0], state.chain[state.chain.length - 1][1]] : null;
    // Ask only when both sides are possible and it actually makes a difference.
    if (sides.length > 1 && ends && ends[0] !== ends[1]) {
      var play = function (side) { send({ type: 'play', tile: tl, side: side }); };
      els.choose.innerHTML = '';
      els.choose.appendChild(h('span', null, t('where_play')));
      els.choose.appendChild(h('button', { class: 'btn primary', onclick: function () { play('L'); } }, t('left') + ' (' + ends[0] + ')'));
      els.choose.appendChild(h('button', { class: 'btn primary', onclick: function () { play('R'); } }, t('right') + ' (' + ends[1] + ')'));
      els.choose.appendChild(h('button', { class: 'btn ghost', onclick: function () { els.choose.hidden = true; } }, t('cancel')));
      els.choose.hidden = false;
      return;
    }
    send({ type: 'play', tile: tl, side: sides[sides.length - 1] });
  }

  function send(move) {
    if (busy) return;
    busy = true;
    els.choose.hidden = true;
    ctx.send(move).then(function () { busy = false; });
  }

  function renderResult(g) {
    var r = g.result;
    if (g.phase === 'playing' || !r) { els.result.hidden = true; return; }
    els.result.hidden = false;
    els.result.innerHTML = '';
    var who = r.side === null || r.side === undefined ? null : (g.teams ? sideName(g, r.side) : ctx.name(r.winner));
    var msg;
    if (who === null) msg = t('blocked_tie');
    else if (r.reason === 'domino') msg = t('domino_win', { name: g.teams ? ctx.name(r.winner) : who });
    else msg = t('blocked_win', { name: who });
    els.result.appendChild(h('h2', null, g.phase === 'match_over' ? t('match_win', { name: sideName(g, g.winner) }) : msg));
    if (g.phase === 'match_over') els.result.appendChild(h('p', null, msg));
    if (who !== null) els.result.appendChild(h('p', { class: 'points' }, (g.teams ? sideName(g, r.side) + ' ' : '') + t('points_won', { n: r.points })));

    // reveal everyone's remaining tiles
    var hands = h('div', { class: 'reveal' });
    g.seats.forEach(function (s) {
      var row = h('div', { class: 'reveal-row' }, [
        h('span', { class: 'pname' }, ctx.name(s) + ' · ' + t('pips', { n: r.pips[s] }))
      ]);
      var tiles = h('div', { class: 'mini-hand' });
      ((g.hands && g.hands[s]) || []).forEach(function (tl) { tiles.appendChild(tile(tl[0], tl[1], 'v', 'mini')); });
      row.appendChild(tiles);
      hands.appendChild(row);
    });
    els.result.appendChild(hands);

    if (g.phase === 'round_over') {
      els.result.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'next_round' }); } }, t('next_round')));
    } else if (ctx.isHost()) {
      els.result.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'next_round' }); } }, t('new_match')));
    } else {
      els.result.appendChild(h('p', { class: 'muted' }, t('host_restart')));
    }
  }

  function renderLog(g) {
    els.log.innerHTML = '';
    g.log.slice(-5).reverse().forEach(function (e) {
      var name = e.seat !== null && e.seat !== undefined ? ctx.name(e.seat) : '';
      var li = h('li');
      if (e.t === 'play') {
        li.appendChild(document.createTextNode(t('log_play', { name: name, tile: e.tile[0] + '-' + e.tile[1] })));
        if (e.scored) li.appendChild(h('span', { class: 'scored' }, ' · ' + t('fives_scored', { name: '', n: e.scored }).trim()));
      } else if (e.t === 'draw') li.textContent = t('log_draw', { name: name });
      else if (e.t === 'pass') li.textContent = t('log_pass', { name: name });
      else if (e.t === 'round') li.textContent = t('log_round', { n: e.n, name: name });
      else li.textContent = t('log_end');
      els.log.appendChild(li);
    });
  }

  GP.games.dominoes = { mount: mount, update: update, tile: tile };
})();
