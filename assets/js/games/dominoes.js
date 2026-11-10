/* Dominoes UI. The server holds the real game; this only draws it and sends moves. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  // Pip positions on a 3x3 grid (1..9, reading order).
  var PIPS = { 0: [], 1: [5], 2: [1, 9], 3: [1, 5, 9], 4: [1, 3, 7, 9], 5: [1, 3, 5, 7, 9], 6: [1, 3, 4, 6, 7, 9] };

  function half(n) {
    var cells = [];
    for (var i = 1; i <= 9; i++) cells.push(h('i', { class: PIPS[n].indexOf(i) >= 0 ? 'pip' : '' }));
    return h('span', { class: 'half v' + n }, cells);
  }

  /** A domino. dir: 'v' (vertical) or 'h' (horizontal). */
  function tile(a, b, dir, extra) {
    return h('span', {
      class: 'domino ' + (dir || 'v') + (extra ? ' ' + extra : ''),
      'aria-label': a + '-' + b
    }, [half(a), h('span', { class: 'bar' }), half(b)]);
  }

  var els = {};
  var state = null;
  var ctx = null;
  var choosing = null; // tile index waiting for a left/right choice

  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';
    els.status = h('div', { class: 'turn-status' });
    els.scores = h('div', { class: 'scoreboard' });
    els.board = h('div', { class: 'board' });
    els.info = h('div', { class: 'board-info muted' });
    els.hand = h('div', { class: 'hand' });
    els.actions = h('div', { class: 'row center-row actions' });
    els.choose = h('div', { class: 'side-choice', hidden: true });
    els.result = h('div', { class: 'result panel', hidden: true });
    els.log = h('ul', { class: 'log muted' });
    root.appendChild(h('div', { class: 'dominoes' }, [
      els.scores, els.status,
      h('div', { class: 'board-wrap' }, [els.board]), els.info,
      els.result, els.choose, els.actions, els.hand, els.log
    ]));
  }

  function update(g, c) {
    ctx = c;
    state = g;
    choosing = null;
    var you = ctx.you();
    var myTurn = g.phase === 'playing' && g.turn === you;

    // scores + tile counts
    els.scores.innerHTML = '';
    els.scores.appendChild(h('div', { class: 'muted small' }, t('round_n', { n: g.round }) + ' · ' + t('target_pts', { n: g.target })));
    g.seats.forEach(function (s) {
      var p = ctx.player(s);
      els.scores.appendChild(h('div', { class: 'score' + (g.turn === s && g.phase === 'playing' ? ' turn' : '') + (p && !p.online ? ' offline' : '') }, [
        h('span', { class: 'pname' }, (p && p.bot ? '🤖 ' : '') + ctx.name(s)),
        h('span', { class: 'tiles-left', title: t('tiles_n', { n: g.counts[s] }) }, String(g.counts[s])),
        h('strong', null, String(g.scores[s]))
      ]));
    });

    // whose turn
    if (g.phase === 'playing') {
      els.status.textContent = myTurn ? t('your_turn') : t('turn_of', { name: ctx.name(g.turn) });
      els.status.className = 'turn-status' + (myTurn ? ' mine' : '');
    } else {
      els.status.textContent = '';
      els.status.className = 'turn-status';
    }

    // board
    els.board.innerHTML = '';
    var last = g.last && g.last.tile;
    var lastIdx = -1;
    if (last && g.chain.length) lastIdx = g.last.side === 'L' ? 0 : g.chain.length - 1;
    g.chain.forEach(function (tl, i) {
      var dbl = tl[0] === tl[1];
      els.board.appendChild(tile(tl[0], tl[1], dbl ? 'v' : 'h', i === lastIdx ? 'last' : ''));
    });
    if (!g.chain.length && g.phase === 'playing') {
      els.board.appendChild(h('span', { class: 'muted' }, t('turn_of', { name: ctx.name(g.turn) })));
    }
    els.info.textContent = g.boneyard > 0 || g.seats.length < 4 ? t('boneyard', { n: g.boneyard }) : '';

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
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter') pick(i, sides); });
      }
      els.hand.appendChild(el);
    });

    // draw / pass
    els.actions.innerHTML = '';
    var noPlay = myTurn && Object.keys(g.playable).length === 0;
    if (noPlay) {
      els.actions.appendChild(h('span', { class: 'muted' }, t('no_move')));
      if (g.canDraw) els.actions.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'draw' }); } }, t('draw') + ' (' + g.boneyard + ')'));
      else els.actions.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'pass' }); } }, t('pass')));
    }
    els.choose.hidden = true;

    renderResult(g);
    renderLog(g);
  }

  function pick(i, sides) {
    var tl = state.hand[i];
    var ends = state.chain.length ? [state.chain[0][0], state.chain[state.chain.length - 1][1]] : null;
    // Ask only when both sides are possible and it actually makes a difference.
    if (sides.length > 1 && ends && ends[0] !== ends[1]) {
      choosing = i;
      els.choose.innerHTML = '';
      els.choose.appendChild(h('span', null, t('where_play')));
      els.choose.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'play', tile: tl, side: 'L' }); } }, t('left') + ' (' + ends[0] + ')'));
      els.choose.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'play', tile: tl, side: 'R' }); } }, '(' + ends[1] + ') ' + t('right')));
      els.choose.appendChild(h('button', { class: 'btn ghost', onclick: function () { els.choose.hidden = true; } }, '✕'));
      els.choose.hidden = false;
      return;
    }
    send({ type: 'play', tile: tl, side: sides[sides.length - 1] });
  }

  var busy = false;
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
    var msg;
    if (r.winner === null) msg = t('blocked_tie');
    else if (r.reason === 'domino') msg = t('domino_win', { name: ctx.name(r.winner) });
    else msg = t('blocked_win', { name: ctx.name(r.winner) });
    els.result.appendChild(h('h2', null, g.phase === 'match_over' ? t('match_win', { name: ctx.name(g.winner) }) : msg));
    if (g.phase === 'match_over') els.result.appendChild(h('p', null, msg));
    if (r.winner !== null) els.result.appendChild(h('p', { class: 'points' }, t('points_won', { n: r.points })));

    // reveal everyone's remaining tiles
    var hands = h('div', { class: 'reveal' });
    g.seats.forEach(function (s) {
      var row = h('div', { class: 'reveal-row' }, [
        h('span', { class: 'pname' }, ctx.name(s) + ' — ' + t('pips', { n: r.pips[s] }))
      ]);
      var tiles = h('div', { class: 'mini-hand' });
      (g.hands && g.hands[s] || []).forEach(function (tl) { tiles.appendChild(tile(tl[0], tl[1], 'v', 'mini')); });
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
      var txt;
      var name = e.seat !== null && e.seat !== undefined ? ctx.name(e.seat) : '';
      if (e.t === 'play') txt = t('log_play', { name: name, tile: e.tile[0] + '|' + e.tile[1] });
      else if (e.t === 'draw') txt = t('log_draw', { name: name });
      else if (e.t === 'pass') txt = t('log_pass', { name: name });
      else if (e.t === 'round') txt = t('log_round', { n: e.n, name: name });
      else txt = t('log_end');
      els.log.appendChild(h('li', null, txt));
    });
  }

  GP.games.dominoes = { mount: mount, update: update, tile: tile };
})();
