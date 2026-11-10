/* Tic-Tac-Toe UI. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;
  var els = {}, ctx = null, busy = false;

  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';
    els.scores = h('div', { class: 'scoreboard' });
    els.status = h('div', { class: 'turn-status' });
    els.grid = h('div', { class: 'ttt-grid' });
    els.actions = h('div', { class: 'row center-row actions' });
    root.appendChild(h('div', { class: 'tictactoe' }, [els.scores, els.status, els.grid, els.actions]));
  }

  function send(move) {
    if (busy) return;
    busy = true;
    ctx.send(move).then(function () { busy = false; });
  }

  function update(g, c) {
    ctx = c;
    var you = ctx.you();
    var myTurn = !g.over && g.turn === you;

    els.scores.innerHTML = '';
    g.seats.forEach(function (s) {
      els.scores.appendChild(h('div', { class: 'score' + (!g.over && g.turn === s ? ' turn' : '') }, [
        h('span', { class: 'mark mark-' + g.marks[s] }, g.marks[s]),
        h('span', { class: 'pname' }, ctx.name(s)),
        h('strong', null, String(g.scores[s]))
      ]));
    });
    els.scores.appendChild(h('div', { class: 'score' }, [h('span', { class: 'pname muted' }, t('draws')), h('strong', null, String(g.scores.draw))]));

    if (g.over) els.status.textContent = g.winner === null ? t('ttt_draw') : t('ttt_win', { name: ctx.name(g.winner) });
    else els.status.textContent = myTurn ? t('your_turn') : t('turn_of', { name: ctx.name(g.turn) });
    els.status.className = 'turn-status' + (myTurn ? ' mine' : '');

    els.grid.innerHTML = '';
    g.board.forEach(function (v, i) {
      var win = g.line && g.line.indexOf(i) >= 0;
      els.grid.appendChild(h('button', {
        class: 'cell' + (v ? ' mark-' + v : '') + (win ? ' win' : ''),
        disabled: !myTurn || v !== null,
        'aria-label': 'cell ' + (i + 1),
        onclick: function () { send({ type: 'place', cell: i }); }
      }, v || ''));
    });

    els.actions.innerHTML = '';
    if (g.over && you !== null) {
      els.actions.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'rematch' }); } }, t('rematch')));
    }
  }

  GP.games.tictactoe = { mount: mount, update: update };
})();
