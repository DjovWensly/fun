/* Mot mystère — guess the hidden word in 6 tries. Marks: 2 right place, 1 elsewhere in the word, 0 absent.
   The three marks also differ by shape (square, round, faded) so they read without colours. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;
  var els = {}, ctx = null, g = null;
  var typed = '';
  var sending = false;
  var lastRound = null;
  var shownRows = 0;        // rows already revealed on screen (only new ones flip)

  var LAYOUTS = {
    fr: ['AZERTYUIOP', 'QSDFGHJKLM', '>WXCVBN<'],
    en: ['QWERTYUIOP', 'ASDFGHJKL', '>ZXCVBNM<']
  };

  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';
    els.label = h('div', { class: 'label center' });
    els.scores = h('div', { class: 'scoreboard' });
    els.grid = h('div', { class: 'mw-grid' });
    els.status = h('div', { class: 'mw-status' });
    els.keys = h('div', { class: 'mw-keys' });
    els.actions = h('div', { class: 'row center-row actions' });
    els.others = h('div', { class: 'mw-others' });
    root.appendChild(h('div', { class: 'mystery' }, [els.label, els.scores, els.grid, els.status, els.keys, els.actions, els.others]));
    document.addEventListener('keydown', function (e) {
      if (!g || document.getElementById('game-section').hidden) return;
      if (e.target && /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return;
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      if (e.key === 'Enter') { e.preventDefault(); press('>'); }
      else if (e.key === 'Backspace') { e.preventDefault(); press('<'); }
      else if (/^[a-zA-ZÀ-ÿ]$/.test(e.key)) press(e.key.toUpperCase().normalize('NFD').replace(/[̀-ͯ]/g, ''));
    });
  }

  function myBoard() { var you = ctx.you(); return you !== null && g.boards[you] ? g.boards[you] : []; }
  function playing() { var you = ctx.you(); return g.phase === 'playing' && you !== null && g.done[you] === undefined; }

  function press(k) {
    if (!playing()) return;
    if (k === '<') { typed = typed.slice(0, -1); drawGrid(); return; }
    if (k === '>') { submit(); return; }
    if (typed.length >= g.length || !/^[A-Z]$/.test(k)) return;
    typed += k;
    if (GP.sound) GP.sound.play('tick');
    drawGrid();
  }

  function submit() {
    if (sending) return;
    if (typed.length !== g.length) {
      if (GP.sound) GP.sound.play('wrong');
      els.grid.classList.remove('shake'); void els.grid.offsetWidth; els.grid.classList.add('shake');
      GP.toast(t('mw_need_letters', { n: g.length }), true);
      return;
    }
    sending = true;
    var word = typed;
    ctx.send({ type: 'guess', text: word }).then(function (r) {
      sending = false;
      if (r && r.ok) {
        typed = '';
        drawGrid();
        var you = ctx.you();
        if (r.game && r.game.done[you] > 0 && GP.sound) GP.sound.play('ding');
      }
    });
  }

  function cls(m) { return m === '2' ? 'm2' : m === '1' ? 'm1' : 'm0'; }

  function drawGrid() {
    els.grid.innerHTML = '';
    els.grid.style.setProperty('--len', g.length);
    var rows = myBoard();
    for (var r = 0; r < g.tries; r++) {
      var row = h('div', { class: 'mw-row' });
      var done = rows[r];
      var current = !done && r === rows.length && playing();
      for (var i = 0; i < g.length; i++) {
        var letter = '', c = 'mw-tile';
        if (done) { letter = done.g ? done.g[i] : ''; c += ' ' + cls(done.m[i]) + (r >= shownRows ? ' flip' : ''); }
        else if (current) {
          letter = typed[i] || '';
          if (!letter && i === 0 && g.first) { letter = g.first; c += ' hint'; }
          if (typed[i]) c += ' typed';
        }
        var tile = h('span', { class: c, style: done && r >= shownRows ? 'animation-delay:' + (i * 70) + 'ms' : null }, letter);
        row.appendChild(tile);
      }
      els.grid.appendChild(row);
    }
    shownRows = rows.length;
  }

  function keyState() {
    var best = {};
    myBoard().forEach(function (r) {
      if (!r.g) return;
      for (var i = 0; i < r.g.length; i++) {
        var k = r.g[i], m = Number(r.m[i]);
        if (best[k] === undefined || m > best[k]) best[k] = m;
      }
    });
    return best;
  }

  function drawKeys() {
    els.keys.innerHTML = '';
    var state = keyState();
    (LAYOUTS[g.opts.lang] || LAYOUTS.fr).forEach(function (line) {
      var row = h('div', { class: 'mw-krow' });
      line.split('').forEach(function (k) {
        var label = k === '>' ? t('mw_enter') : k === '<' ? '⌫' : k;
        var c = 'mw-key' + (k === '>' || k === '<' ? ' wide' : '') + (state[k] !== undefined ? ' ' + cls(String(state[k])) : '');
        row.appendChild(h('button', { type: 'button', class: c, 'aria-label': k === '<' ? t('mw_delete') : label, onclick: function () { press(k); } }, label));
      });
      els.keys.appendChild(row);
    });
    els.keys.hidden = !playing();
  }

  function update(view, c) {
    ctx = c;
    g = view;
    if (lastRound !== g.round) { typed = ''; lastRound = g.round; shownRows = 0; }
    var you = ctx.you();
    els.label.textContent = t('mw_round', { n: g.round, len: g.length });

    els.scores.innerHTML = '';
    g.order.forEach(function (s) {
      els.scores.appendChild(h('div', { 'data-seat': s, class: 'score' }, [h('span', { class: 'pname' }, ctx.name(s)), h('strong', null, String(g.scores[s] || 0))]));
    });

    drawGrid();
    drawKeys();

    // status line
    els.status.innerHTML = '';
    var mine = you !== null ? g.done[you] : undefined;
    if (mine > 0) els.status.appendChild(h('p', { class: 'ok' }, mine === 1 ? t('mw_found_one') : t('mw_found', { n: mine })));
    else if (mine === 0) els.status.appendChild(h('p', { class: 'no' }, t('mw_missed')));
    if (g.word && (g.phase === 'reveal' || mine !== undefined)) els.status.appendChild(h('p', { class: 'mw-word' }, g.display || g.word));
    if (g.phase === 'playing' && mine !== undefined && g.order.length > 1) els.status.appendChild(h('p', { class: 'muted small' }, t('mw_wait_others')));

    els.actions.innerHTML = '';
    if (playing()) els.actions.appendChild(h('button', { class: 'btn small ghost', onclick: function () { if (confirm(t('mw_giveup_q'))) ctx.send({ type: 'giveup' }); } }, t('mw_giveup')));
    if (you !== null && (g.phase === 'reveal' || (ctx.isHost() && g.order.length > 1 && mine !== undefined))) {
      els.actions.appendChild(h('button', { class: 'btn primary', onclick: function () { ctx.send({ type: 'next' }); } }, t('mw_next')));
    }

    // the others: only their marks while playing
    els.others.innerHTML = '';
    g.order.filter(function (s) { return s !== you; }).forEach(function (s) {
      var rows = g.boards[s] || [];
      var mini = h('div', { class: 'mw-mini' });
      rows.forEach(function (r) {
        var line = h('div', { class: 'mw-mini-row' });
        for (var i = 0; i < r.m.length; i++) line.appendChild(h('span', { class: cls(r.m[i]) }, g.phase === 'reveal' && r.g ? r.g[i] : ''));
        mini.appendChild(line);
      });
      var st = g.done[s];
      els.others.appendChild(h('div', { class: 'mw-other', 'data-seat': s }, [
        h('div', { class: 'mw-other-name' }, [ctx.name(s), st > 0 ? h('span', { class: 'tag ok' }, '✓ ' + st) : st === 0 ? h('span', { class: 'tag muted' }, '✗') : null]),
        mini
      ]));
    });
  }

  GP.games.mystery = { mount: mount, update: update };
})();
