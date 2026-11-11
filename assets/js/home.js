/* Home page: pick a name, join with a code, or open a table with its settings. */
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
      el.textContent = max >= 10 ? t('players_plus', { min: min })
        : min === max ? t('players_exact', { n: min }) : t('players_n', { min: min, max: max });
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
    // Already at this table on this device? Just go back to it.
    if (GP.store('gp_tok_' + code)) { location.href = 'room.php?c=' + code; return; }
    GP.api('join', { code: code, name: name }).then(function (r) {
      if (r.ok) goToRoom(r.code, r.token);
      else if (!r.silent) GP.toast(GP.errText(r.error), true);
    });
  });

  // ----- settings dialog -----
  var dialog = document.getElementById('options-dialog');
  var fieldsBox = document.getElementById('options-fields');
  var pending = null;

  /** A row of tap-to-pick choices. choices: [[value, label, help?], ...] */
  function choices(name, label, list, value, onChange) {
    var help = h('p', { class: 'choice-help' });
    var box = h('div', { class: 'choices', role: 'radiogroup' });
    list.forEach(function (c) {
      var input = h('input', { type: 'radio', name: name, value: String(c[0]), checked: String(c[0]) === String(value) });
      input.addEventListener('change', function () {
        help.textContent = c[2] || '';
        if (onChange) onChange(String(c[0]));
      });
      if (String(c[0]) === String(value)) help.textContent = c[2] || '';
      box.appendChild(h('label', null, [input, h('span', null, c[1])]));
    });
    var hasHelp = list.some(function (c) { return c[2]; });
    return h('div', { class: 'field', 'data-field': name }, [h('span', null, label), box, hasHelp ? help : null]);
  }

  function val(name) {
    var el = fieldsBox.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : null;
  }

  function goalChoices(mode) {
    var pts = mode === 'fives' ? [100, 150, 250] : [50, 100, 150];
    return pts.map(function (n) { return ['points:' + n, t('goal_points', { n: n })]; })
      .concat([3, 5, 7].map(function (n) { return ['rounds:' + n, t('goal_rounds', { n: n })]; }));
  }

  function buildFields(game, solo) {
    fieldsBox.innerHTML = '';
    if (game === 'dominoes') {
      var goalHolder = h('div');
      var renderGoal = function (mode) {
        goalHolder.innerHTML = '';
        goalHolder.appendChild(choices('goal', t('opt_goal'), goalChoices(mode), mode === 'fives' ? 'points:150' : 'points:100'));
      };
      fieldsBox.appendChild(choices('mode', t('opt_mode'), [
        ['draw', t('mode_draw'), t('mode_draw_d')],
        ['block', t('mode_block'), t('mode_block_d')],
        ['fives', t('mode_fives'), t('mode_fives_d')]
      ], 'draw', renderGoal));
      var teams = choices('teams', t('opt_teams'), [['0', t('teams_off')], ['1', t('teams_on')]], '0', function (v) {
        // Team play needs 4 at the table, so in solo it means 3 bots.
        if (solo && v === '1') fieldsBox.querySelector('input[name="bots"][value="3"]').checked = true;
      });
      fieldsBox.appendChild(teams);
      if (solo) {
        fieldsBox.appendChild(choices('bots', t('opt_bots'), [1, 2, 3].map(function (n) { return [n, t('n_bots', { n: n })]; }), 3, function (v) {
          if (v !== '3') fieldsBox.querySelector('input[name="teams"][value="0"]').checked = true;
        }));
      }
      fieldsBox.appendChild(goalHolder);
      renderGoal('draw');
    }
    if (game === 'tictactoe') {
      fieldsBox.appendChild(choices('size', t('opt_size'), [[3, t('size_small')]].concat([10, 15, 19, 25].map(function (n) {
        return [n, t('size_big', { n: n })];
      })), 3));
    }
    if (game === 'truthdare') {
      fieldsBox.appendChild(choices('level', t('opt_level'), [['soft', t('lvl_soft')], ['party', t('lvl_party')]], 'soft'));
      fieldsBox.appendChild(choices('order', t('opt_order'), [['circle', t('order_circle')], ['random', t('order_random')]], 'circle'));
      fieldsBox.appendChild(choices('lang', t('opt_lang'), [['fr', 'Français'], ['en', 'English']], GP.lang()));
    }
  }

  function collect(game, solo) {
    var o = {};
    var bots = 0;
    if (game === 'dominoes') {
      o.mode = val('mode');
      o.teams = val('teams') === '1';
      var goal = (val('goal') || 'points:100').split(':');
      o.goal = goal[0];
      o.target = parseInt(goal[1], 10);
      if (solo) bots = parseInt(val('bots') || '3', 10);
    }
    if (game === 'tictactoe') {
      o.size = parseInt(val('size') || '3', 10);
      if (solo) bots = 1;
    }
    if (game === 'truthdare') {
      o.level = val('level'); o.order = val('order'); o.lang = val('lang');
    }
    return { options: o, bots: bots };
  }

  function open(game, solo) {
    var name = needName();
    if (!name) return;
    if (typeof dialog.showModal !== 'function') { send(game, name, {}, solo ? (game === 'dominoes' ? 3 : 1) : 0); return; }
    pending = { game: game, solo: solo, name: name };
    document.getElementById('options-title').textContent = t('g_' + game);
    document.getElementById('options-ok').textContent = solo ? t('play') : t('create');
    buildFields(game, solo);
    dialog.showModal();
  }

  dialog.addEventListener('close', function () {
    if (dialog.returnValue !== 'ok' || !pending) return;
    var c = collect(pending.game, pending.solo);
    send(pending.game, pending.name, c.options, c.bots);
    pending = null;
  });

  function send(game, name, options, bots) {
    GP.api('create', { game: game, name: name, options: options, bots: bots }).then(function (r) {
      if (r.ok) goToRoom(r.code, r.token);
      else if (!r.silent) GP.toast(GP.errText(r.error), true);
    });
  }

  document.querySelectorAll('[data-create]').forEach(function (b) {
    b.addEventListener('click', function () { open(b.getAttribute('data-create'), false); });
  });
  document.querySelectorAll('[data-solo]').forEach(function (b) {
    b.addEventListener('click', function () { open(b.getAttribute('data-solo'), true); });
  });
})();
