/* Dessine et devine — drawing canvas, guesses, timer. The drawing travels separately from the
   game state: the drawer sends small batches of strokes, the others receive them with each poll. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  var COLORS = ['#1c1915', '#c4381d', '#1f4592', '#2f7d4f', '#e3a21a', '#7b4a22', '#e85d9a', '#7a3fc0', '#8a8a8a'];
  var WIDTHS = [4, 8, 16, 32];
  var W = 800, H = 600;

  var els = {}, ctx = null, g = null;
  var cv = null, c2 = null;
  var turn = null;          // turn number the canvas currently shows
  var cursor = 0;           // last stroke row received
  var items = [];           // every drawing item of this turn, in order (strokes, 'undo', 'clear')
  var drewHere = false;     // this browser has drawn during this turn (so it ignores its own echo)
  var pending = [];         // items waiting to be sent
  var current = null;       // stroke being drawn { k, c, w, p, sent }
  var color = COLORS[0], width = 8, eraser = false;
  var clockOffset = 0;
  var sending = Promise.resolve();

  // ---------------------------------------------------------------- layout
  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';
    els.round = h('span', { class: 'label' });
    els.timer = h('span', { class: 'dg-timer' });
    els.word = h('div', { class: 'dg-word' });
    cv = h('canvas', { width: W, height: H, class: 'dg-canvas' });
    c2 = cv.getContext('2d');
    els.overlay = h('div', { class: 'dg-overlay', hidden: true });
    els.tools = h('div', { class: 'dg-tools', hidden: true });
    els.scores = h('ul', { class: 'dg-scores' });
    els.chat = h('ul', { class: 'dg-chat' });
    els.input = h('input', { maxlength: 60, autocomplete: 'off', placeholder: t('guess_ph'), enterkeyhint: 'send' });
    els.form = h('form', { class: 'dg-guess row' }, [els.input, h('button', { class: 'btn primary', type: 'submit' }, t('send'))]);
    els.form.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = els.input.value.trim();
      if (!v) return;
      els.input.value = '';
      // Guesses go out one after another so a fast typist never loses one.
      sending = sending.then(function () { return ctx.send({ type: 'guess', text: v }); });
    });

    root.appendChild(h('div', { class: 'dg' }, [
      h('div', { class: 'dg-top' }, [els.round, els.timer]),
      els.word,
      h('div', { class: 'dg-main' }, [
        h('div', { class: 'dg-board' }, [h('div', { class: 'dg-paper' }, [cv, els.overlay]), els.tools]),
        h('div', { class: 'dg-side' }, [
          h('div', { class: 'panel dg-panel' }, [els.scores]),
          h('div', { class: 'panel dg-panel dg-chat-panel' }, [els.chat, els.form])
        ])
      ])
    ]));
    buildTools();
    bindDrawing();
    clear();
    setInterval(tickClock, 250);
    setInterval(flush, 450);
  }

  function buildTools() {
    var colorBox = h('div', { class: 'dg-colors' });
    COLORS.forEach(function (col) {
      var b = h('button', { type: 'button', class: 'dg-color', style: 'background:' + col, 'aria-label': col });
      b.addEventListener('click', function () { color = col; eraser = false; markTools(); });
      colorBox.appendChild(b);
    });
    var sizeBox = h('div', { class: 'dg-sizes' });
    WIDTHS.forEach(function (w) {
      var b = h('button', { type: 'button', class: 'dg-size', 'data-w': w, 'aria-label': String(w) }, [h('span', { style: 'width:' + Math.min(22, w / 1.4 + 3) + 'px;height:' + Math.min(22, w / 1.4 + 3) + 'px' })]);
      b.addEventListener('click', function () { width = w; markTools(); });
      sizeBox.appendChild(b);
    });
    els.eraserBtn = h('button', { type: 'button', class: 'btn small', onclick: function () { eraser = !eraser; markTools(); } }, t('eraser'));
    els.tools.appendChild(colorBox);
    els.tools.appendChild(sizeBox);
    els.tools.appendChild(h('div', { class: 'row wrap-row' }, [
      els.eraserBtn,
      h('button', { type: 'button', class: 'btn small', onclick: undo }, t('undo')),
      h('button', { type: 'button', class: 'btn small', onclick: clearAll }, t('clear')),
      h('button', { type: 'button', class: 'btn small ghost', onclick: function () { ctx.send({ type: 'skip' }); } }, t('skip_turn'))
    ]));
    markTools();
  }

  function markTools() {
    els.tools.querySelectorAll('.dg-color').forEach(function (b) { b.classList.toggle('on', !eraser && b.getAttribute('aria-label') === color); });
    els.tools.querySelectorAll('.dg-size').forEach(function (b) { b.classList.toggle('on', Number(b.getAttribute('data-w')) === width); });
    els.eraserBtn.classList.toggle('primary', eraser);
  }

  // ---------------------------------------------------------------- drawing on the canvas
  function clear() {
    c2.fillStyle = '#ffffff';
    c2.fillRect(0, 0, W, H);
  }

  function drawItem(it) {
    var p = it.p;
    c2.strokeStyle = it.c; c2.fillStyle = it.c;
    c2.lineWidth = it.w; c2.lineCap = 'round'; c2.lineJoin = 'round';
    if (p.length === 2) {
      c2.beginPath(); c2.arc(p[0], p[1], it.w / 2, 0, Math.PI * 2); c2.fill();
      return;
    }
    c2.beginPath();
    c2.moveTo(p[0], p[1]);
    for (var i = 2; i < p.length; i += 2) c2.lineTo(p[i], p[i + 1]);
    c2.stroke();
  }

  /** What is actually on the paper once undo/clear are applied. */
  function visible(list) {
    var out = [];
    list.forEach(function (it) {
      if (it.t === 'clear') out = [];
      else if (it.t === 'undo') {
        // remove the last stroke (it may have been sent in several pieces with the same k)
        if (out.length) { var k = out[out.length - 1].k; out = out.filter(function (x) { return x.k !== k; }); }
      } else out.push(it);
    });
    return out;
  }

  function redraw() {
    clear();
    visible(items).forEach(drawItem);
    if (current && current.p.length) drawItem(current);
  }

  function isDrawing() { return g && g.phase === 'drawing' && g.drawer === ctx.you(); }

  function toCanvas(e) {
    var r = cv.getBoundingClientRect();
    return [Math.round((e.clientX - r.left) * W / r.width), Math.round((e.clientY - r.top) * H / r.height)];
  }

  function nextK() {
    var k = 0;
    items.forEach(function (it) { if (it.k > k) k = it.k; });
    return k + 1;
  }

  function bindDrawing() {
    cv.addEventListener('pointerdown', function (e) {
      if (!isDrawing()) return;
      e.preventDefault();
      cv.setPointerCapture(e.pointerId);
      drewHere = true;
      var pt = toCanvas(e);
      current = { k: nextK(), c: eraser ? '#ffffff' : color, w: eraser ? Math.max(width, 16) : width, p: pt.slice(), sent: 0 };
      drawItem(current);
    });
    cv.addEventListener('pointermove', function (e) {
      if (!current) return;
      var pt = toCanvas(e);
      var p = current.p;
      var dx = pt[0] - p[p.length - 2], dy = pt[1] - p[p.length - 1];
      if (dx * dx + dy * dy < 9) return;
      // draw just the new segment
      c2.strokeStyle = current.c; c2.lineWidth = current.w; c2.lineCap = 'round'; c2.lineJoin = 'round';
      c2.beginPath(); c2.moveTo(p[p.length - 2], p[p.length - 1]); c2.lineTo(pt[0], pt[1]); c2.stroke();
      p.push(pt[0], pt[1]);
      if (p.length - current.sent > 500) cut();
    });
    var end = function () { if (current) { cut(); current = null; } };
    cv.addEventListener('pointerup', end);
    cv.addEventListener('pointercancel', end);
  }

  /** Move the not-yet-sent part of the current stroke into the send queue. */
  function cut() {
    if (!current) return;
    var from = Math.max(0, current.sent - 2); // repeat the last point so pieces join up
    if (current.p.length - from < 2 || (current.sent && current.p.length <= current.sent)) return;
    var piece = { k: current.k, c: current.c, w: current.w, p: current.p.slice(from) };
    current.sent = current.p.length;
    items.push(piece);
    pending.push(piece);
  }

  function flush() {
    if (current) cut();
    if (!pending.length || !g || g.phase !== 'drawing') { if (g && g.phase !== 'drawing') pending = []; return; }
    var batch = pending.splice(0, 40);
    ctx.call('draw', { turn: turn, items: batch });
  }

  function undo() {
    if (!isDrawing()) return;
    current = null;
    items.push({ t: 'undo' });
    pending.push({ t: 'undo' });
    redraw();
  }

  function clearAll() {
    if (!isDrawing()) return;
    current = null;
    items.push({ t: 'clear' });
    pending.push({ t: 'clear' });
    redraw();
  }

  function newTurn(n) {
    turn = n;
    cursor = 0;
    items = [];
    pending = [];
    current = null;
    drewHere = false;
    clear();
  }

  // ---------------------------------------------------------------- data from the server
  function pollParams() { return turn === null ? {} : { st: turn, sk: cursor }; }

  function onPoll(r) {
    var s = r.strokes;
    if (!s) return;
    if (s.turn !== turn) newTurn(s.turn);
    cursor = s.cursor;
    if (drewHere) return; // I'm the drawer: my canvas already has all of it
    if (s.reset) { items = s.items.slice(); redraw(); return; }
    if (!s.items.length) return;
    var needFull = s.items.some(function (it) { return it.t; });
    s.items.forEach(function (it) { items.push(it); });
    if (needFull) redraw();
    else s.items.forEach(drawItem);
  }

  function fastPoll() { return true; }

  // ---------------------------------------------------------------- screen
  function update(view, c) {
    ctx = c;
    g = view;
    if (g.turnNo !== turn) newTurn(g.turnNo);
    clockOffset = g.now - Date.now() / 1000;
    var you = ctx.you();
    var drawerNow = g.drawer === you;

    els.round.textContent = t('round_of', { n: g.round, m: g.rounds });
    els.tools.hidden = !(g.phase === 'drawing' && drawerNow);
    cv.classList.toggle('can-draw', g.phase === 'drawing' && drawerNow);
    tickClock();

    // word / hint line
    els.word.innerHTML = '';
    if (g.phase === 'drawing') {
      if (drawerNow) {
        els.word.appendChild(h('span', { class: 'label' }, t('you_draw')));
        els.word.appendChild(h('strong', { class: 'dg-secret' }, g.word));
      } else if (g.word) {
        els.word.appendChild(h('span', { class: 'label' }, t('you_found')));
        els.word.appendChild(h('strong', { class: 'dg-secret' }, g.word));
      } else {
        var letters = h('span', { class: 'dg-mask' });
        Array.from(g.mask).forEach(function (ch) { letters.appendChild(h('span', { class: ch === '_' ? 'blank' : (ch === ' ' ? 'gap' : 'shown') }, ch === '_' ? '' : ch)); });
        els.word.appendChild(letters);
        els.word.appendChild(h('span', { class: 'muted small' }, t('letters_n', { n: g.mask.replace(/[^_\p{L}\p{N}]/gu, '').length })));
      }
    } else if (g.phase === 'choose') {
      els.word.appendChild(h('span', { class: 'muted' }, drawerNow ? t('choose_word') : t('choosing_word', { name: ctx.name(g.drawer) })));
    }

    renderOverlay(drawerNow);
    renderScores();
    renderChat();
    var lockGuess = g.phase === 'drawing' && (drawerNow || g.guessed.indexOf(you) >= 0);
    els.input.disabled = you === null || lockGuess;
    els.form.querySelector('button').disabled = els.input.disabled;
  }

  function renderOverlay(drawerNow) {
    var o = els.overlay;
    o.innerHTML = '';
    if (g.phase === 'drawing') { o.hidden = true; return; }
    o.hidden = false;
    if (g.phase === 'choose') {
      if (drawerNow) {
        o.appendChild(h('p', { class: 'dg-overlay-title' }, t('choose_word')));
        var row = h('div', { class: 'row center-row' });
        g.choices.forEach(function (w, i) {
          row.appendChild(h('button', { class: 'btn primary dg-choice', onclick: function () { ctx.send({ type: 'pick', i: i }); } }, w));
        });
        o.appendChild(row);
      } else {
        o.appendChild(h('p', { class: 'dg-overlay-title' }, t('choosing_word', { name: ctx.name(g.drawer) })));
      }
      return;
    }
    if (g.phase === 'reveal') {
      var last = g.chat.filter(function (m) { return m.kind === 'word'; }).pop();
      o.appendChild(h('p', { class: 'dg-overlay-title' }, t('word_was', { w: g.word || '' })));
      if (last) o.appendChild(h('p', { class: 'muted' }, t('why_' + last.why)));
      return;
    }
    if (g.phase === 'over') {
      var ranked = g.order.slice().sort(function (a, b) { return (g.scores[b] || 0) - (g.scores[a] || 0); });
      o.appendChild(h('p', { class: 'dg-overlay-title' }, t('winner_is', { name: ctx.name(ranked[0]) })));
      var list = h('ol', { class: 'dg-podium' });
      ranked.forEach(function (s) { list.appendChild(h('li', null, [h('span', null, ctx.name(s)), h('strong', null, String(g.scores[s] || 0))])); });
      o.appendChild(list);
      if (ctx.isHost()) o.appendChild(h('button', { class: 'btn primary', onclick: function () { ctx.send({ type: 'restart' }); } }, t('new_match')));
      else o.appendChild(h('p', { class: 'muted small' }, t('host_restart')));
    }
  }

  function renderScores() {
    els.scores.innerHTML = '';
    g.order.slice().sort(function (a, b) { return (g.scores[b] || 0) - (g.scores[a] || 0); }).forEach(function (s) {
      var found = g.guessed.indexOf(s) >= 0;
      els.scores.appendChild(h('li', { 'data-seat': s, class: (s === g.drawer ? 'drawer ' : '') + (found ? 'found' : '') }, [
        h('span', { class: 'pname' }, ctx.name(s)),
        s === g.drawer && g.phase !== 'over' ? h('span', { class: 'tag host' }, '✎') : null,
        found ? h('span', { class: 'tag ok' }, '✓') : null,
        h('strong', null, String(g.scores[s] || 0))
      ]));
    });
  }

  function renderChat() {
    els.chat.innerHTML = '';
    g.chat.forEach(function (m) {
      var who = m.seat !== undefined ? ctx.name(m.seat) : '';
      if (m.kind === 'guess' || m.kind === 'chat') els.chat.appendChild(h('li', null, [h('strong', null, who + ' : '), m.text]));
      else if (m.kind === 'found') els.chat.appendChild(h('li', { class: 'ok' }, t('found_msg', { name: who, n: m.points })));
      else if (m.kind === 'close') els.chat.appendChild(h('li', { class: 'close' }, t('close_msg')));
      else if (m.kind === 'word') els.chat.appendChild(h('li', { class: 'sys' }, t('word_was', { w: m.text })));
    });
    els.chat.scrollTop = els.chat.scrollHeight;
  }

  function tickClock() {
    if (!g || !els.timer) return;
    if (!g.endsAt || g.phase === 'over') { els.timer.textContent = ''; return; }
    var left = Math.max(0, Math.ceil(g.endsAt - (Date.now() / 1000 + clockOffset)));
    els.timer.textContent = left + ' s';
    els.timer.classList.toggle('urgent', g.phase === 'drawing' && left <= 10);
  }

  GP.games.drawguess = { mount: mount, update: update, pollParams: pollParams, onPoll: onPoll, fastPoll: fastPoll };
})();
