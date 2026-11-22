/* C'est plus, c'est moins — secret number, estimation, higher or lower (cards), guess how much (duo). */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;
  var els = {}, ctx = null, g = null, busy = false, lastSig = '', lastCardKey = null, built = null;
  var SUITS = { S: '♠', H: '♥', D: '♦', C: '♣' };
  var RANKS = { 11: 'J', 12: 'Q', 13: 'K', 14: 'A' };

  function send(move) {
    if (busy) return Promise.resolve({ ok: false });
    busy = true;
    return ctx.send(move).then(function (r) {
      busy = false;
      if (!r.ok && GP.sound) GP.sound.play('wrong');
      return r;
    });
  }
  function fmt(n) {
    if (n === null || n === undefined) return '?';
    try { return Number(n).toLocaleString(GP.lang() === 'en' ? 'en-US' : 'fr-FR', { maximumFractionDigits: 3 }); } catch (e) { return String(n); }
  }
  function btn(label, cls, fn) { return h('button', { type: 'button', class: 'btn ' + (cls || ''), onclick: fn }, label); }
  function you() { return ctx.you(); }

  /** One input form that survives re-renders (polling must not erase what you are typing). */
  function numberForm(label, onSubmit, inputmode) {
    var input = h('input', { inputmode: inputmode || 'decimal', autocomplete: 'off', class: 'pm-input', placeholder: '…' });
    var f = h('form', { class: 'pm-form' }, [h('label', { class: 'pm-form-label' }, label), h('div', { class: 'row' }, [input, h('button', { class: 'btn primary', type: 'submit' }, t('pm_send'))])]);
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = input.value.trim();
      if (!v) return;
      onSubmit(v).then(function (r) { if (r && r.ok) { input.value = ''; input.focus(); } });
    });
    f.input = input;
    f.label = f.querySelector('.pm-form-label');
    return f;
  }

  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';
    els.head = h('div', { class: 'label center pm-head' });
    els.scores = h('div', { class: 'scoreboard' });
    els.main = h('div', { class: 'pm-main' });
    els.formBox = h('div', { class: 'pm-formbox' });
    els.after = h('div', { class: 'pm-after' });
    els.gage = h('div', { class: 'pm-gage', hidden: true });
    els.actions = h('div', { class: 'row center-row wrap-row actions' });
    root.appendChild(h('div', { class: 'plusmoins' }, [els.head, els.scores, els.main, els.formBox, els.after, els.gage, els.actions]));
    built = null;
  }

  function buildForms(mode) {
    built = mode;
    els.formBox.innerHTML = '';
    if (mode === 'secret') {
      els.form = numberForm(t('pm_your_guess'), function (v) { return send({ type: 'guess', value: v }); }, 'numeric');
    } else if (mode === 'estimate') {
      els.form = numberForm(t('pm_your_estimate'), function (v) { return send({ type: 'answer', value: v }); });
    } else if (mode === 'couple') {
      els.form = numberForm('', function (v) {
        return send({ type: g.phase === 'setting' ? 'secret' : 'guess', value: v });
      });
    } else {
      els.form = null;
      return;
    }
    els.formBox.appendChild(els.form);
  }

  function showForm(on, label) {
    if (!els.form) return;
    els.form.hidden = !on;
    if (label !== undefined) els.form.label.textContent = label;
  }

  function scores(extra) {
    els.scores.innerHTML = '';
    g.order.forEach(function (s) {
      var sub = extra ? extra(s) : '';
      els.scores.appendChild(h('div', { class: 'score' + (g.turn === s || g.answerer === s ? ' turn' : '') }, [
        h('span', { class: 'pname' }, ctx.name(s)), h('strong', null, String(g.scores[s] || 0)), sub ? h('span', { class: 'sub' }, sub) : null
      ]));
    });
  }

  function gageBox() {
    els.gage.hidden = !g.gage;
    els.gage.innerHTML = '';
    if (g.gage) {
      els.gage.appendChild(h('strong', null, t('pm_gage_for', { name: ctx.name(g.gage.seat) })));
      els.gage.appendChild(h('p', null, g.gage.text));
    }
  }

  // ---------- secret number & guess how much: a list of tries with arrows ----------
  function triesList(rows, showHeat) {
    var ol = h('ol', { class: 'pm-tries' });
    rows.slice().reverse().forEach(function (r) {
      var cls = r.r === 'ok' ? 'ok' : r.r;
      var txt = r.r === 'ok' ? t('pm_found') : r.r === 'up' ? t('pm_more') : t('pm_less');
      ol.appendChild(h('li', { class: cls }, [
        h('b', null, fmt(r.g)), h('span', { class: 'pm-arrow' }, txt),
        showHeat && r.r !== 'ok' && r.h !== undefined ? h('span', { class: 'pm-heat h' + r.h }, t('pm_heat_' + r.h)) : null
      ]));
    });
    return ol;
  }

  /** Known interval from my tries, drawn as a bar. */
  function rangeBar(rows, max) {
    var lo = 1, hi = max;
    rows.forEach(function (r) {
      if (r.r === 'up') lo = Math.max(lo, r.g + 1);
      if (r.r === 'down') hi = Math.min(hi, r.g - 1);
      if (r.r === 'ok') { lo = r.g; hi = r.g; }
    });
    var bar = h('div', { class: 'pm-bar' }, [h('div', { class: 'pm-bar-in', style: 'left:' + ((lo - 1) / max * 100) + '%;width:' + Math.max(0.8, (hi - lo + 1) / max * 100) + '%' })]);
    return h('div', { class: 'pm-range' }, [bar, h('div', { class: 'pm-range-txt' }, t('pm_between', { a: fmt(lo), b: fmt(hi) }))]);
  }

  function othersLine(skip) {
    var box = h('ul', { class: 'pm-others' });
    g.order.forEach(function (s) {
      if (s === you() || s === skip || !g.boards[s]) return;
      var n = g.boards[s].length, d = g.done[s];
      var txt = d > 0 ? t('pm_found_in', { n: d }) : d === 0 ? t('pm_missed') : t('pm_tries_n', { n: n });
      box.appendChild(h('li', { class: d > 0 ? 'ok' : '' }, [h('span', { class: 'pname' }, ctx.name(s)), ' ', txt]));
    });
    return box.children.length ? box : null;
  }

  function renderSecret() {
    var me = you(), mine = me !== null && g.boards[me] ? g.boards[me] : [];
    var finished = me === null || g.done[me] !== undefined;
    els.head.textContent = t('pm_secret_head', { max: fmt(g.opts.range), n: g.round });
    scores(function (s) { return g.best[s] ? t('pm_best', { n: g.best[s] }) : ''; });
    els.main.innerHTML = '';
    var card = h('div', { class: 'pm-card' });
    if (g.phase === 'reveal' || finished) {
      card.appendChild(h('div', { class: 'pm-small' }, t('pm_number_was')));
      card.appendChild(h('div', { class: 'pm-big' }, fmt(g.target)));
      if (me !== null && g.done[me] > 0) card.appendChild(h('p', { class: 'pm-win' }, t('pm_you_found', { n: g.done[me] })));
      else if (me !== null) card.appendChild(h('p', null, t('pm_you_missed')));
    } else {
      var last = mine[mine.length - 1];
      card.appendChild(h('div', { class: 'pm-big ' + (last ? last.r : '') }, last ? (last.r === 'up' ? t('pm_more') : t('pm_less')) : '?'));
      if (last && g.opts.hint) card.appendChild(h('div', { class: 'pm-heat big h' + last.h }, t('pm_heat_' + last.h)));
      card.appendChild(rangeBar(mine, g.opts.range));
      card.appendChild(h('p', { class: 'pm-small' }, g.limit ? t('pm_tries_left', { n: g.limit - mine.length }) : t('pm_tries_n', { n: mine.length })));
    }
    els.main.appendChild(card);
    if (mine.length) els.main.appendChild(triesList(mine, g.opts.hint));
    var o = othersLine(null);
    if (o) els.main.appendChild(o);
    showForm(g.phase === 'playing' && !finished);
    els.actions.innerHTML = '';
    if (g.phase === 'playing' && !finished) els.actions.appendChild(btn(t('pm_giveup'), 'ghost', function () { send({ type: 'giveup' }); }));
    if (g.phase === 'reveal' || (ctx.isHost() && finished)) els.actions.appendChild(btn(t('pm_new_number'), 'primary', function () { send({ type: 'next' }); }));
    else if (finished && g.phase === 'playing') els.actions.appendChild(h('p', { class: 'muted small' }, t('pm_wait_others')));
  }

  // ---------- estimation ----------
  function renderEstimate() {
    var me = you();
    els.head.textContent = g.phase === 'over' ? t('pm_final') : t('pm_question_n', { n: g.round, total: g.rounds });
    scores();
    els.main.innerHTML = '';
    els.actions.innerHTML = '';
    if (g.phase === 'over') {
      els.main.appendChild(finalRanking());
      els.actions.appendChild(btn(t('pm_again'), 'primary', function () { send({ type: 'again' }); }));
      showForm(false);
      return;
    }
    var card = h('div', { class: 'pm-card pm-q' }, [h('p', { class: 'pm-qtext' }, g.q.text)]);
    if (g.q.unit) card.appendChild(h('div', { class: 'pm-small' }, t('pm_unit', { u: g.q.unit })));
    els.main.appendChild(card);
    if (g.phase === 'answer') {
      var answered = me !== null && g.answered.indexOf(me) >= 0;
      if (answered) card.appendChild(h('p', { class: 'pm-mine' }, t('pm_your_answer', { v: fmt(g.mine) })));
      var waiting = g.order.filter(function (s) { return g.answered.indexOf(s) < 0; }).map(function (s) { return ctx.name(s); });
      if (waiting.length) els.main.appendChild(h('p', { class: 'muted center small' }, t('pm_waiting_for', { names: waiting.join(', ') })));
      showForm(me !== null && !answered);
      if (ctx.isHost() && g.answered.length && waiting.length) els.actions.appendChild(btn(t('pm_reveal_now'), 'ghost', function () { send({ type: 'reveal' }); }));
      return;
    }
    showForm(false);
    card.appendChild(h('div', { class: 'pm-small' }, t('pm_answer_was')));
    card.appendChild(h('div', { class: 'pm-big' }, fmt(g.q.answer) + (g.q.unit ? ' ' + g.q.unit : '')));
    var ol = h('ol', { class: 'pm-results' });
    (g.result || []).forEach(function (r) {
      ol.appendChild(h('li', { class: r.closest ? 'closest' : '' }, [
        h('span', { class: 'pname' }, ctx.name(r.s)),
        h('b', null, fmt(r.v)),
        h('span', { class: 'muted small' }, r.diff === 0 ? t('pm_exact') : t('pm_off_by', { d: fmt(r.diff) })),
        h('strong', null, '+' + r.pts)
      ]));
    });
    els.main.appendChild(ol);
    els.actions.appendChild(btn(g.round >= g.rounds ? t('pm_see_final') : t('pm_next_q'), 'primary', function () { send({ type: 'next' }); }));
  }

  function finalRanking() {
    var list = g.order.slice().sort(function (a, b) { return (g.scores[b] || 0) - (g.scores[a] || 0); });
    var ol = h('ol', { class: 'pm-results final' });
    list.forEach(function (s, i) {
      ol.appendChild(h('li', { class: i === 0 ? 'closest' : '' }, [h('span', { class: 'pname' }, ctx.name(s)), h('strong', null, t('pm_pts', { n: g.scores[s] || 0 }))]));
    });
    return h('div', { class: 'pm-card' }, [h('div', { class: 'pm-big win' }, t('pm_winner', { name: ctx.name(list[0]) })), ol]);
  }

  // ---------- higher or lower ----------
  function cardEl(c, cls) {
    var red = c.s === 'H' || c.s === 'D';
    var rank = RANKS[c.v] || String(c.v);
    return h('div', { class: 'pm-pcard ' + (red ? 'red ' : '') + (cls || '') }, [
      h('span', { class: 'corner' }, rank + SUITS[c.s]),
      h('span', { class: 'mid' }, SUITS[c.s]),
      h('span', { class: 'corner br' }, rank + SUITS[c.s])
    ]);
  }

  function renderHighLow() {
    var me = you(), mine = me !== null && g.turn === me && g.phase === 'playing';
    els.head.textContent = g.phase === 'over' ? t('pm_final') : (mine ? t('pm_your_turn') : t('pm_turn_of', { name: ctx.name(g.turn) }));
    els.head.classList.toggle('mine', mine);
    scores(function (s) { return t('pm_target', { n: g.target }); });
    els.main.innerHTML = '';
    els.actions.innerHTML = '';
    showForm(false);
    if (g.phase === 'over') {
      els.main.appendChild(finalRanking());
      els.actions.appendChild(btn(t('pm_again'), 'primary', function () { send({ type: 'again' }); }));
      return;
    }
    var key = g.card.v + g.card.s + ':' + (g.last ? JSON.stringify(g.last) : '');
    var fresh = lastCardKey !== null && key !== lastCardKey;
    lastCardKey = key;
    var table = h('div', { class: 'pm-table' });
    if (g.last && g.last.from) table.appendChild(cardEl(g.last.from, 'prev'));
    table.appendChild(cardEl(g.card, fresh ? 'flip' : ''));
    els.main.appendChild(table);
    if (fresh && GP.sound) GP.sound.play(g.last && g.last.res === 'lost' ? 'wrong' : 'clack');

    if (g.last) {
      var l = g.last, msg;
      if (l.bank !== undefined) msg = t('pm_banked', { name: ctx.name(l.seat), n: l.bank });
      else if (l.res === 'same') msg = t('pm_same', { name: ctx.name(l.seat) });
      else if (l.res === 'ok') msg = t('pm_hl_ok', { name: ctx.name(l.seat), n: l.streak });
      else msg = t('pm_hl_lost', { name: ctx.name(l.seat) });
      els.main.appendChild(h('p', { class: 'pm-last ' + (l.res || 'bank') }, msg));
    }
    els.main.appendChild(h('div', { class: 'pm-streak' }, [
      h('span', null, t('pm_streak', { n: g.streak })),
      h('span', { class: 'muted small' }, t('pm_next_worth', { n: g.nextValue }))
    ]));
    if (mine) {
      els.actions.appendChild(btn(t('pm_higher'), 'pm-up', function () { send({ type: 'guess', dir: 'up' }); }));
      els.actions.appendChild(btn(t('pm_lower'), 'pm-down', function () { send({ type: 'guess', dir: 'down' }); }));
      if (g.streak > 0) els.actions.appendChild(btn(t('pm_bank', { n: g.bankValue }), 'primary', function () { send({ type: 'bank' }); }));
    }
    els.main.appendChild(h('p', { class: 'muted center small' }, t('pm_hl_rules')));
  }

  // ---------- guess how much (couple / duo) ----------
  function renderCouple() {
    var me = you(), isAns = me === g.answerer, aName = ctx.name(g.answerer);
    var mine = me !== null && g.boards[me] ? g.boards[me] : null;
    var finished = !mine || g.done[me] !== undefined;
    els.head.textContent = t('pm_couple_head', { name: aName, n: g.round });
    scores();
    els.main.innerHTML = '';
    els.actions.innerHTML = '';
    var card = h('div', { class: 'pm-card pm-q heat-' + g.opts.level }, [
      h('div', { class: 'pm-small' }, t('pm_about', { name: aName })),
      h('p', { class: 'pm-qtext' }, g.q.text)
    ]);
    if (g.q.unit) card.appendChild(h('div', { class: 'pm-small' }, t('pm_unit', { u: g.q.unit })));
    els.main.appendChild(card);

    if (g.phase === 'setting') {
      showForm(isAns, t('pm_answer_secret'));
      if (isAns) els.actions.appendChild(btn(t('pm_other_q'), 'ghost', function () { send({ type: 'skipq' }); }));
      else card.appendChild(h('p', { class: 'pm-mine' }, t('pm_answering', { name: aName })));
      return;
    }
    if (g.phase === 'reveal') {
      showForm(false);
      card.appendChild(h('div', { class: 'pm-small' }, t('pm_answer_was')));
      card.appendChild(h('div', { class: 'pm-big' }, fmt(g.secret) + (g.q.unit ? ' ' + g.q.unit : '')));
    } else if (isAns) {
      showForm(false);
      card.appendChild(h('p', { class: 'pm-mine' }, t('pm_your_secret', { v: fmt(g.secret) })));
    } else {
      var last = mine && mine[mine.length - 1];
      if (last) card.appendChild(h('div', { class: 'pm-big ' + last.r }, last.r === 'ok' ? t('pm_found') : last.r === 'up' ? t('pm_more') : t('pm_less')));
      if (mine && !finished) card.appendChild(h('p', { class: 'pm-small' }, t('pm_tries_left', { n: g.limit - mine.length })));
      showForm(!finished, t('pm_guess_of', { name: aName }));
    }
    // everyone's tries (the answerer and the reveal see the numbers)
    g.order.forEach(function (s) {
      if (s === g.answerer || !g.boards[s]) return;
      var rows = g.boards[s];
      if (!rows.length) return;
      var visible = rows[0].g !== undefined;
      var d = g.done[s];
      var title = ctx.name(s) + ' · ' + (d > 0 ? t('pm_found_in', { n: d }) : d === 0 ? t('pm_missed') : t('pm_tries_n', { n: rows.length }));
      els.main.appendChild(h('div', { class: 'pm-who' }, [h('div', { class: 'pm-small' }, title), visible ? triesList(rows, false) : null]));
    });
    if (g.phase === 'reveal' || (ctx.isHost() && g.phase === 'playing')) {
      els.actions.appendChild(btn(t('pm_next_person'), g.phase === 'reveal' ? 'primary' : 'ghost', function () { send({ type: 'next' }); }));
    }
  }

  function update(gs, c) {
    ctx = c;
    g = gs;
    if (built !== g.mode) buildForms(g.mode);
    var sig = JSON.stringify(g) + GP.lang();
    if (sig === lastSig) return;
    var prev = lastSig;
    lastSig = sig;
    els.head.classList.remove('mine');
    if (g.mode === 'secret') renderSecret();
    else if (g.mode === 'estimate') renderEstimate();
    else if (g.mode === 'highlow') renderHighLow();
    else renderCouple();
    gageBox();
    if (prev && GP.sound && g.mode !== 'highlow') GP.sound.play('pop');
  }

  GP.games.plusmoins = { mount: mount, update: update };
  document.addEventListener('gp:lang', function () { lastSig = ''; built = null; if (g && ctx) update(g, ctx); });
})();
