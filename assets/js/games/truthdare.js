/* Truth or Dare UI (online rooms). GP.tdCard is also reused by the one-phone mode (local.php). */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  /**
   * Draw the central card.
   * o = { phase, card, playerName, isTurn, canControl, rerolled, onChoose(kind), onDone(), onSkip(), onReroll() }
   */
  function tdCard(o) {
    var box = h('div', { class: 'td-card ' + (o.card ? o.card.type : 'choose') });
    if (o.phase === 'choose') {
      box.appendChild(h('div', { class: 'td-player' }, o.playerName));
      if (o.isTurn) {
        box.appendChild(h('h2', null, t('choose_td')));
        box.appendChild(h('div', { class: 'td-choices' }, [
          h('button', { class: 'btn td-truth', onclick: function () { o.onChoose('truth'); } }, t('truth')),
          h('button', { class: 'btn td-dare', onclick: function () { o.onChoose('dare'); } }, t('dare')),
          h('button', { class: 'btn', onclick: function () { o.onChoose('random'); } }, t('random'))
        ]));
      } else {
        box.appendChild(h('h2', { class: 'muted' }, t('waiting_choice', { name: o.playerName })));
      }
    } else {
      var kind = o.card.type === 'truth' ? t('truth') : t('dare');
      box.appendChild(h('span', { class: 'td-corner', 'aria-hidden': 'true' }, kind));
      box.appendChild(h('span', { class: 'td-corner br', 'aria-hidden': 'true' }, kind));
      box.appendChild(h('div', { class: 'td-player' }, o.playerName));
      box.appendChild(h('p', { class: 'td-text' }, o.card.text));
      if (o.card.custom) box.appendChild(h('div', { class: 'td-custom muted small' }, t('custom_card')));
    }
    if (o.canControl) {
      var row = h('div', { class: 'row center-row td-actions' });
      if (o.phase === 'card') {
        row.appendChild(h('button', { class: 'btn primary', onclick: o.onDone }, t('done')));
        if (o.isTurn && !o.card.rerolled) row.appendChild(h('button', { class: 'btn', onclick: o.onReroll }, t('another')));
      }
      row.appendChild(h('button', { class: 'btn ghost', onclick: o.onSkip }, t('skip')));
      box.appendChild(row);
      if (!o.isTurn) box.appendChild(h('p', { class: 'muted small' }, t('host_can_skip')));
    }
    return box;
  }
  GP.tdCard = tdCard;

  var els = {}, ctx = null, busy = false;

  function send(move) {
    if (busy) return Promise.resolve({ ok: false });
    busy = true;
    return ctx.send(move).then(function (r) { busy = false; return r; });
  }

  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';
    els.card = h('div');
    els.last = h('p', { class: 'muted center small' });
    els.players = h('ul', { class: 'td-stats' });
    els.info = h('p', { class: 'muted small' });

    // The "add a card" form lives outside the re-rendered area so typing isn't interrupted by polling.
    var kind = h('select', { name: 'kind' }, [h('option', { value: 'truth' }, t('truth')), h('option', { value: 'dare' }, t('dare'))]);
    var text = h('textarea', { maxlength: 200, rows: 2, placeholder: t('add_card_ph') });
    var form = h('form', { class: 'td-add' }, [
      h('div', { class: 'row' }, [kind, h('button', { class: 'btn', type: 'submit' }, t('add'))]),
      text
    ]);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = text.value.trim();
      if (!v) return;
      send({ type: 'add_card', kind: kind.value, text: v }).then(function (r) {
        if (r.ok) { text.value = ''; GP.toast(t('card_added')); }
      });
    });
    els.form = form;

    root.appendChild(h('div', { class: 'truthdare' }, [
      els.card, els.last,
      h('div', { class: 'td-side' }, [
        h('div', { class: 'panel' }, [h('h3', null, t('players')), els.players, els.info]),
        h('details', { class: 'panel' }, [h('summary', null, t('add_card')), form])
      ])
    ]));
  }

  var heardCount = null;
  function update(g, c) {
    ctx = c;
    var you = ctx.you();
    if (heardCount !== null && g.count > heardCount) if (GP.sound) GP.sound.play('pop');
    heardCount = g.count;
    var isTurn = g.turn === you;
    els.card.innerHTML = '';
    els.card.appendChild(tdCard({
      phase: g.phase,
      card: g.card,
      playerName: ctx.name(g.turn),
      isTurn: isTurn,
      canControl: you !== null && (isTurn || ctx.isHost()),
      onChoose: function (k) { send({ type: 'choose', kind: k }); },
      onDone: function () { send({ type: 'done' }); },
      onSkip: function () { send({ type: 'skip' }); },
      onReroll: function () { send({ type: 'reroll' }); }
    }));

    if (g.last) {
      els.last.textContent = t('last_turn', { name: ctx.name(g.last.seat) }) + ', ' +
        (g.last.skipped || !g.last.card ? t('skipped') : (g.last.card.type === 'truth' ? t('truth') : t('dare')).toLowerCase() + ' : « ' + g.last.card.text + ' »');
    } else {
      els.last.textContent = '';
    }

    els.players.innerHTML = '';
    g.order.forEach(function (s) {
      var st = g.stats[s] || { truth: 0, dare: 0, skip: 0 };
      els.players.appendChild(h('li', { 'data-seat': s, class: s === g.turn ? 'turn' : '' }, [
        h('span', { class: 'pname' }, ctx.name(s)),
        h('span', { class: 'muted small' }, t('stats', { t: st.truth, d: st.dare, s: st.skip }))
      ]));
    });
    els.info.textContent = t('cards_played', { n: g.count }) + (g.customCount ? ' · ' + t('custom_count', { n: g.customCount }) : '');
    els.form.parentNode.hidden = you === null;
  }

  GP.games.truthdare = { mount: mount, update: update };
})();
