/* Morpion / Tic-Tac-Toe UI: 3x3, or a big squared-paper grid (up to 25x25) that scrolls and zooms. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;
  var els = {}, ctx = null, busy = false, state = null;
  var cellSize = 32;          // big grids only, changed with the zoom buttons
  var centeredFor = null;     // which game we already scrolled to the middle of

  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';
    els.scores = h('div', { class: 'scoreboard' });
    els.status = h('div', { class: 'turn-status' });
    els.grid = h('div', { class: 'ttt-grid' });
    els.frame = h('div', { class: 'paper-frame' }, [els.grid]);
    els.tools = h('div', { class: 'board-tools' });
    els.actions = h('div', { class: 'row center-row actions' });
    root.appendChild(h('div', { class: 'tictactoe' }, [els.scores, els.status, els.frame, els.tools, els.actions]));

    // One listener for the whole board (a 25x25 grid has 625 cells).
    els.grid.addEventListener('click', function (e) {
      var cell = e.target.closest('.cell');
      if (!cell || cell.disabled) return;
      send({ type: 'place', cell: parseInt(cell.getAttribute('data-i'), 10) });
    });
    try { cellSize = parseInt(GP.store('gp_ttt_zoom') || '32', 10) || 32; } catch (e) { cellSize = 32; }
  }

  function send(move) {
    if (busy) return;
    busy = true;
    ctx.send(move).then(function () { busy = false; });
  }

  function markEl(mark) {
    var el = h('span', { class: 'mark-chip mark-' + mark });
    if (mark === 'X') el.appendChild(h('i'));
    return el;
  }

  function update(g, c) {
    ctx = c;
    state = g;
    var you = ctx.you();
    var myTurn = !g.over && g.turn === you;
    var size = g.size || 3;
    var big = size > 3;

    els.scores.innerHTML = '';
    els.scores.appendChild(h('div', { class: 'label' }, (big ? size + ' × ' + size + ' · ' : '') + t('align_n', { n: g.need || 3 })));
    g.seats.forEach(function (s) {
      els.scores.appendChild(h('div', { class: 'score' + (!g.over && g.turn === s ? ' turn' : '') }, [
        markEl(g.marks[s]),
        h('span', { class: 'pname' }, ctx.name(s)),
        h('strong', null, String(g.scores[s]))
      ]));
    });
    els.scores.appendChild(h('div', { class: 'score' }, [h('span', { class: 'pname muted' }, t('draws')), h('strong', null, String(g.scores.draw))]));

    if (g.over) els.status.textContent = g.winner === null ? t('ttt_draw') : t('ttt_win', { name: ctx.name(g.winner) });
    else els.status.textContent = myTurn ? t('your_turn') : t('turn_of', { name: ctx.name(g.turn) });
    els.status.className = 'turn-status' + (myTurn ? ' mine' : '');

    drawGrid(g, myTurn);

    els.tools.innerHTML = '';
    if (big) {
      els.tools.appendChild(h('button', { class: 'btn small', type: 'button', 'aria-label': t('zoom_out'), onclick: function () { zoom(-6); } }, '−'));
      els.tools.appendChild(h('button', { class: 'btn small', type: 'button', 'aria-label': t('zoom_in'), onclick: function () { zoom(6); } }, '+'));
      els.tools.appendChild(h('button', { class: 'btn small', type: 'button', onclick: function () { scrollTo(state.lastCell, true); } }, t('center_board')));
    }

    els.actions.innerHTML = '';
    if (g.over && you !== null) {
      els.actions.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'rematch' }); } }, t('rematch')));
    }

    // First look at a big board: put the middle (or the last move) in view.
    var key = size + ':' + g.first + ':' + (g.moves === 0 ? 'new' : 'on');
    if (big && centeredFor !== key) { centeredFor = key; scrollTo(g.lastCell, false); }
  }

  function drawGrid(g, myTurn) {
    var size = g.size || 3;
    var big = size > 3;
    var board = typeof g.board === 'string' ? g.board : g.board.map(function (v) { return v || '.'; }).join('');
    var win = {};
    (g.line || []).forEach(function (i) { win[i] = true; });

    els.frame.className = 'paper-frame' + (big ? '' : ' small-board');
    els.grid.className = 'ttt-grid' + (big ? '' : ' small');
    els.grid.style.setProperty('--n', size);
    if (big) els.grid.style.setProperty('--cell', cellSize + 'px');
    else els.grid.style.removeProperty('--cell');

    var frag = document.createDocumentFragment();
    for (var i = 0; i < board.length; i++) {
      var v = board[i] === '.' ? null : board[i];
      var cls = 'cell' + (v ? ' mark-' + v : '') + (win[i] ? ' win' : '') + (g.lastCell === i && !g.over ? ' lastmove' : '');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = cls;
      b.setAttribute('data-i', i);
      b.setAttribute('aria-label', (Math.floor(i / size) + 1) + ',' + (i % size + 1) + (v ? ' ' + v : ''));
      if (!myTurn || v) b.disabled = true;
      if (v === 'X') b.appendChild(document.createElement('i'));
      frag.appendChild(b);
    }
    els.grid.innerHTML = '';
    els.grid.appendChild(frag);
  }

  function zoom(delta) {
    cellSize = Math.max(18, Math.min(56, cellSize + delta));
    GP.store('gp_ttt_zoom', String(cellSize));
    var f = els.frame;
    // keep the same point in the middle of the view while zooming
    var cx = (f.scrollLeft + f.clientWidth / 2) / f.scrollWidth;
    var cy = (f.scrollTop + f.clientHeight / 2) / f.scrollHeight;
    els.grid.style.setProperty('--cell', cellSize + 'px');
    f.scrollLeft = cx * f.scrollWidth - f.clientWidth / 2;
    f.scrollTop = cy * f.scrollHeight - f.clientHeight / 2;
  }

  /** Scroll the big board so cell i (or the middle) is in the centre of the frame. */
  function scrollTo(i, smooth) {
    var size = state.size || 3;
    if (size <= 3) return;
    if (i === null || i === undefined) i = Math.floor(size / 2) * size + Math.floor(size / 2);
    var cell = els.grid.querySelector('[data-i="' + i + '"]');
    if (!cell) return;
    var f = els.frame;
    var left = cell.offsetLeft - f.clientWidth / 2 + cell.offsetWidth / 2;
    var top = cell.offsetTop - f.clientHeight / 2 + cell.offsetHeight / 2;
    if (f.scrollTo) f.scrollTo({ left: left, top: top, behavior: smooth ? 'smooth' : 'auto' });
    else { f.scrollLeft = left; f.scrollTop = top; }
  }

  GP.games.tictactoe = { mount: mount, update: update };
})();
