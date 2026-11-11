/* Shared screen parts for the "answer in secret, then reveal" games (Qui de nous deux, Tu préfères). */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  /**
   * Build a game UI. spec:
   *   cardClass                 extra class on the card
   *   renderQuestion(g, ctx)    -> element: the question part of the card
   *   renderChoices(g, ctx, vote) -> element: buttons to answer (vote(value) sends it)
   *   renderReveal(g, ctx)      -> element: the answers once revealed
   *   renderScore(g, ctx)       -> element: the score panel
   */
  GP.pollGame = function (spec) {
    var els = {}, ctx = null, busy = false;

    function send(move) {
      if (busy) return Promise.resolve({ ok: false });
      busy = true;
      return ctx.send(move).then(function (r) { busy = false; return r; });
    }

    function mount(root, c) {
      ctx = c;
      root.innerHTML = '';
      els.label = h('div', { class: 'label center' });
      els.card = h('div', { class: 'poll-card ' + (spec.cardClass || '') });
      els.status = h('div', { class: 'poll-status' });
      els.actions = h('div', { class: 'row center-row actions' });
      els.score = h('div', { class: 'panel poll-score' });
      root.appendChild(h('div', { class: 'poll-game' }, [els.label, els.card, els.status, els.actions, els.score]));
    }

    function names(seats) {
      return seats.map(function (s) { var p = ctx.player(s); return p ? p.name : '?'; }).join(', ');
    }

    function update(g, c) {
      ctx = c;
      var you = ctx.you();
      var done = g.round - (g.phase === 'vote' ? 1 : 0);
      els.label.textContent = t('round_n', { n: g.round });

      els.card.innerHTML = '';
      els.card.appendChild(spec.renderQuestion(g, ctx));
      if (g.phase === 'vote') {
        els.card.appendChild(spec.renderChoices(g, ctx, function (value) { send({ type: 'vote', value: value }); }));
      } else {
        els.card.appendChild(spec.renderReveal(g, ctx));
      }

      // who we are still waiting for
      els.status.innerHTML = '';
      if (g.phase === 'vote') {
        var missing = g.order.filter(function (s) { return g.voted.indexOf(s) < 0; });
        if (you !== null && g.mine !== null) els.status.appendChild(h('p', { class: 'saved' }, t('your_answer_saved')));
        if (missing.length) els.status.appendChild(h('p', { class: 'muted' }, t('waiting_for', { names: names(missing) })));
        var dots = h('div', { class: 'vote-dots' });
        g.order.forEach(function (s) {
          dots.appendChild(h('span', { 'data-seat': s, class: 'vote-dot' + (g.voted.indexOf(s) >= 0 ? ' in' : ''), title: names([s]) }, names([s])));
        });
        els.status.appendChild(dots);
      }

      els.actions.innerHTML = '';
      if (g.phase === 'reveal' && you !== null) {
        els.actions.appendChild(h('button', { class: 'btn primary', onclick: function () { send({ type: 'next' }); } }, t('next_card')));
      }
      if (g.phase === 'vote' && ctx.isHost()) {
        if (g.voted.length) els.actions.appendChild(h('button', { class: 'btn small', onclick: function () { send({ type: 'reveal' }); } }, t('reveal_now')));
        els.actions.appendChild(h('button', { class: 'btn small ghost', onclick: function () { send({ type: 'next' }); } }, t('skip_card')));
      }

      els.score.innerHTML = '';
      els.score.appendChild(spec.renderScore(g, ctx, done));
    }

    return { mount: mount, update: update };
  };

  /** Big "x out of n" meter used by both games. */
  GP.pollMeter = function (label, a, n) {
    var pct = n ? Math.round(100 * a / n) : 0;
    return h('div', { class: 'meter' }, [
      h('div', { class: 'meter-head' }, [h('span', { class: 'label' }, label), h('strong', null, n ? pct + ' %' : '–')]),
      h('div', { class: 'meter-bar' }, [h('span', { style: 'width:' + pct + '%' })]),
      h('div', { class: 'muted small' }, t('complicity_n', { a: a, n: n }))
    ]);
  };
})();
