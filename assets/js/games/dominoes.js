/* Dominoes UI. The server holds the real game; this only draws it and sends moves. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  // Pip positions on a 3x3 grid (1..9, reading order).
  var PIPS = { 0: [], 1: [5], 2: [1, 9], 3: [1, 5, 9], 4: [1, 3, 7, 9], 5: [1, 3, 5, 7, 9], 6: [1, 3, 4, 6, 7, 9] };

  function half(n) {
    var cells = [];
    for (var i = 1; i <= 9; i++) cells.push(h('i', { class: PIPS[n].indexOf(i) >= 0 ? 'pip' : '' }));
    return h('span', { class: 'half' }, cells);
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

  function update(g, c) {
    ctx = c;
    state = g;
    var you = ctx.you();
    var myTurn = g.phase === 'playing' && g.turn === you;

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

  // ---------- the table: chain laid out as a snake ----------
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

    var lastIdx = -1;
    if (g.last && g.last.tile) lastIdx = g.last.side === 'L' ? 0 : g.chain.length - 1;

    var W = felt.clientWidth - 24;
    var H = felt.clientHeight - 48;
    // Largest tiles that still fit the table: big while the chain is short, smaller as it grows.
    var maxU = W > 700 ? 44 : 30;
    var plan = null;
    for (var u = maxU; u >= 12; u -= 2) {
      plan = planRows(g.chain, u, W);
      if (plan.height <= H) break;
    }
    var snake = h('div', { class: 'snake' });
    plan.rows.forEach(function (row, r) {
      var rev = r % 2 === 1;
      var rowEl = h('div', { class: 'snake-row' + (rev ? ' rev' : '') });
      if (plan.rows.length === 1) rowEl.style.justifyContent = 'center';
      row.forEach(function (i) {
        var tl = g.chain[i];
        var dbl = tl[0] === tl[1];
        // On right-to-left rows the tile is drawn flipped so matching numbers still touch.
        var a = rev ? tl[1] : tl[0], b = rev ? tl[0] : tl[1];
        rowEl.appendChild(tile(a, b, dbl ? 'v' : 'h', i === lastIdx ? 'last' : '', plan.u));
      });
      snake.appendChild(rowEl);
    });
    felt.appendChild(snake);
  }

  /** Fill rows from left to right with tiles of half-size u; returns the rows and total height. */
  function planRows(chain, u, width) {
    var wide = 2 * u + 5, narrow = u + 3, gap = 2, rowGap = 8;
    var rows = [[]], x = 0, heights = [u + 3];
    chain.forEach(function (tl, i) {
      var dbl = tl[0] === tl[1];
      var w = dbl ? narrow : wide;
      if (x > 0 && x + w > width) { rows.push([]); heights.push(u + 3); x = 0; }
      rows[rows.length - 1].push(i);
      if (dbl) heights[heights.length - 1] = 2 * u + 5;
      x += w + gap;
    });
    var height = heights.reduce(function (a, b) { return a + b; }, 0) + rowGap * (rows.length - 1);
    return { rows: rows, height: height, u: u };
  }

  // ---------- moves ----------
  function pick(i, sides) {
    var tl = state.hand[i];
    var ends = state.chain.length ? [state.chain[0][0], state.chain[state.chain.length - 1][1]] : null;
    // Ask only when both sides are possible and it actually makes a difference.
    if (sides.length > 1 && ends && ends[0] !== ends[1]) {
      els.choose.innerHTML = '';
      els.choose.appendChild(h('span', null, t('where_play')));
      els.choose.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'play', tile: tl, side: 'L' }); } }, t('left') + ' (' + ends[0] + ')'));
      els.choose.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'play', tile: tl, side: 'R' }); } }, t('right') + ' (' + ends[1] + ')'));
      els.choose.appendChild(h('button', { class: 'btn ghost', onclick: function () { els.choose.hidden = true; } }, t('cancel')));
      els.choose.hidden = false;
      els.choose.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
