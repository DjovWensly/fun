/* One-phone Truth or Dare: same cards as the online game, everything kept in this browser. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;
  var el = function (id) { return document.getElementById(id); };

  var saved = {};
  try { saved = JSON.parse(GP.store('gp_local_td') || '{}') || {}; } catch (e) { saved = {}; }
  var players = Array.isArray(saved.players) ? saved.players : [];
  var prompts = null;
  var game = null; // { turn, phase, card, used: {truth: [], dare: []} }

  el('opt-lang').value = saved.lang || GP.lang();
  if (saved.level) el('opt-level').value = saved.level;
  if (saved.order) el('opt-order').value = saved.order;

  function save() {
    GP.store('gp_local_td', JSON.stringify({
      players: players, lang: el('opt-lang').value, level: el('opt-level').value, order: el('opt-order').value
    }));
  }

  function renderPlayers() {
    var list = el('local-players');
    list.innerHTML = '';
    players.forEach(function (name, i) {
      list.appendChild(h('li', null, [
        h('span', { class: 'pname' }, name),
        h('button', { class: 'btn tiny ghost', type: 'button', onclick: function () { players.splice(i, 1); save(); renderPlayers(); } }, t('kick'))
      ]));
    });
  }

  el('add-player').addEventListener('submit', function (e) {
    e.preventDefault();
    var v = el('player-input').value.trim();
    if (!v) return;
    players.push(v.slice(0, 20));
    el('player-input').value = '';
    el('player-input').focus();
    save();
    renderPlayers();
  });

  function pool() {
    var lang = el('opt-lang').value, level = el('opt-level').value;
    var out = { truth: [], dare: [] };
    var levels = level === 'party' ? ['soft', 'party'] : ['soft'];
    ['truth', 'dare'].forEach(function (type) {
      levels.forEach(function (lv) { out[type] = out[type].concat(((prompts[lang] || {})[lv] || {})[type] || []); });
    });
    return out;
  }

  function draw(kind) {
    var p = pool()[kind];
    if (!p.length) return { type: kind, text: t('err_no_cards'), custom: false };
    var unused = [];
    for (var i = 0; i < p.length; i++) if (game.used[kind].indexOf(i) < 0) unused.push(i);
    if (!unused.length) { game.used[kind] = []; for (i = 0; i < p.length; i++) unused.push(i); }
    var pick = unused[Math.floor(Math.random() * unused.length)];
    game.used[kind].push(pick);
    return { type: kind, text: p[pick], custom: false };
  }

  function next() {
    if (el('opt-order').value === 'random' && players.length > 1) {
      var n;
      do { n = Math.floor(Math.random() * players.length); } while (n === game.turn);
      game.turn = n;
    } else {
      game.turn = (game.turn + 1) % players.length;
    }
    game.phase = 'choose';
    game.card = null;
    render();
  }

  function render() {
    var box = el('local-card');
    box.innerHTML = '';
    var name = players[game.turn];
    if (game.phase === 'choose') {
      box.appendChild(h('p', { class: 'center label' }, t('pass_phone', { name: name })));
    }
    box.appendChild(GP.tdCard({
      phase: game.phase,
      card: game.card,
      playerName: name,
      isTurn: true,
      canControl: true,
      onChoose: function (k) {
        if (k === 'random') k = Math.random() < 0.5 ? 'truth' : 'dare';
        game.card = draw(k);
        game.phase = 'card';
        render();
      },
      onDone: next,
      onSkip: next,
      onReroll: function () { var k = game.card.type; game.card = draw(k); game.card.rerolled = true; render(); }
    }));
  }

  el('local-start').addEventListener('click', function () {
    if (players.length < 2) { GP.toast(t('need_two'), true); return; }
    save();
    var start = function () {
      game = { turn: el('opt-order').value === 'random' ? Math.floor(Math.random() * players.length) : 0, phase: 'choose', card: null, used: { truth: [], dare: [] } };
      el('setup').hidden = true;
      el('local-game').hidden = false;
      render();
    };
    if (prompts) { start(); return; }
    GP.api('prompts').then(function (r) {
      if (r.ok) { prompts = r.prompts; start(); return; }
      // Fallback to the built-in list if the server is unreachable.
      fetch('data/prompts.json').then(function (x) { return x.json(); }).then(function (j) { prompts = j; start(); })
        .catch(function () { GP.toast(t('err_network'), true); });
    });
  });

  el('local-setup').addEventListener('click', function () {
    el('setup').hidden = false;
    el('local-game').hidden = true;
  });

  document.addEventListener('gp:lang', function () { if (game && !el('local-game').hidden) render(); });
  renderPlayers();
})();
