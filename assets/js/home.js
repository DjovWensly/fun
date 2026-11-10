/* Home page: pick a name, join with a code, or create a room. */
(function () {
  'use strict';
  var t = GP.t, h = GP.h;

  var nameInput = document.getElementById('player-name');
  var codeInput = document.getElementById('join-code');
  nameInput.value = GP.store('gp_name') || '';
  nameInput.addEventListener('input', function () { GP.store('gp_name', nameInput.value.trim()); });
  codeInput.addEventListener('input', function () {
    codeInput.value = codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  });
  if (codeInput.value) nameInput.focus();

  function renderCounts() {
    document.querySelectorAll('.players-count').forEach(function (el) {
      var min = el.getAttribute('data-min'), max = el.getAttribute('data-max');
      el.textContent = '👥 ' + (max >= 10 ? t('players_plus', { min: min })
        : min === max ? t('players_exact', { n: min }) : t('players_n', { min: min, max: max }));
    });
  }
  renderCounts();
  document.addEventListener('gp:lang', renderCounts);

  function needName() {
    var name = nameInput.value.trim();
    if (!name) {
      GP.toast(t('err_name_required'), true);
      nameInput.focus();
      document.getElementById('me-panel').scrollIntoView({ behavior: 'smooth', block: 'center' });
      return null;
    }
    return name;
  }

  function goToRoom(code, token) {
    GP.store('gp_tok_' + code, token);
    location.href = 'room.php?c=' + encodeURIComponent(code);
  }

  document.getElementById('join-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var name = needName();
    var code = codeInput.value.trim();
    if (!name || !code) { if (name) codeInput.focus(); return; }
    // Already in this room on this device? Just go back to it.
    if (GP.store('gp_tok_' + code)) { location.href = 'room.php?c=' + code; return; }
    GP.api('join', { code: code, name: name }).then(function (r) {
      if (r.ok) goToRoom(r.code, r.token);
      else GP.toast(GP.errText(r.error), true);
    });
  });

  // ----- options per game -----
  function select(name, label, choices, value) {
    return h('label', { class: 'field' }, [
      h('span', { text: label }),
      h('select', { name: name }, choices.map(function (c) {
        return h('option', { value: c[0], selected: String(c[0]) === String(value) }, c[1]);
      }))
    ]);
  }

  function optionFields(game, solo) {
    var f = [];
    if (game === 'dominoes') {
      f.push(select('target', t('opt_target'), [[50, '50'], [100, '100'], [150, '150'], [200, '200']], 100));
      if (solo) f.push(select('bots', t('opt_bots'), [[1, '1'], [2, '2'], [3, '3']], 3));
    }
    if (game === 'truthdare') {
      f.push(select('level', t('opt_level'), [['soft', t('lvl_soft')], ['party', t('lvl_party')]], 'soft'));
      f.push(select('order', t('opt_order'), [['circle', t('order_circle')], ['random', t('order_random')]], 'circle'));
      f.push(select('lang', t('opt_lang'), [['fr', 'Français'], ['en', 'English']], GP.lang()));
    }
    return f;
  }

  var dialog = document.getElementById('options-dialog');
  var fieldsBox = document.getElementById('options-fields');
  var pending = null;

  function create(game, solo) {
    var name = needName();
    if (!name) return;
    var fields = optionFields(game, solo);
    if (game === 'tictactoe' && solo) { send(game, name, {}, 1); return; }
    if (!fields.length || typeof dialog.showModal !== 'function') { send(game, name, {}, 0); return; }
    pending = { game: game, solo: solo, name: name };
    document.getElementById('options-title').textContent = t('g_' + game) + ' — ' + t('options');
    document.getElementById('options-ok').textContent = solo ? t('play') : t('create');
    fieldsBox.innerHTML = '';
    fields.forEach(function (el) { fieldsBox.appendChild(el); });
    dialog.showModal();
  }

  dialog.addEventListener('close', function () {
    if (dialog.returnValue !== 'ok' || !pending) return;
    var opts = {};
    fieldsBox.querySelectorAll('select').forEach(function (s) { opts[s.name] = s.value; });
    var bots = 0;
    if (pending.solo) bots = parseInt(opts.bots || '1', 10);
    delete opts.bots;
    send(pending.game, pending.name, opts, bots);
    pending = null;
  });

  function send(game, name, options, bots) {
    GP.api('create', { game: game, name: name, options: options, bots: bots }).then(function (r) {
      if (r.ok) goToRoom(r.code, r.token);
      else GP.toast(GP.errText(r.error), true);
    });
  }

  document.querySelectorAll('[data-create]').forEach(function (b) {
    b.addEventListener('click', function () { create(b.getAttribute('data-create'), false); });
  });
  document.querySelectorAll('[data-solo]').forEach(function (b) {
    b.addEventListener('click', function () { create(b.getAttribute('data-solo'), true); });
  });
})();
