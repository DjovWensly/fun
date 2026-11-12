/* Settings window for a game (home page: open a table; room page: switch to another game). */
(function () {
  'use strict';
  var t = GP.t, h = GP.h;
  var box = null; // the element holding the fields currently shown

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
    var el = box.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : null;
  }

  function goalChoices(mode) {
    var pts = mode === 'fives' ? [100, 150, 250] : [50, 100, 150];
    return pts.map(function (n) { return ['points:' + n, t('goal_points', { n: n })]; })
      .concat([3, 5, 7].map(function (n) { return ['rounds:' + n, t('goal_rounds', { n: n })]; }));
  }

  function buildFields(game, solo) {
    box.innerHTML = '';
    if (game === 'dominoes') {
      var goalHolder = h('div');
      var renderGoal = function (mode) {
        goalHolder.innerHTML = '';
        goalHolder.appendChild(choices('goal', t('opt_goal'), goalChoices(mode), mode === 'fives' ? 'points:150' : 'points:100'));
      };
      box.appendChild(choices('mode', t('opt_mode'), [
        ['draw', t('mode_draw'), t('mode_draw_d')],
        ['block', t('mode_block'), t('mode_block_d')],
        ['fives', t('mode_fives'), t('mode_fives_d')]
      ], 'draw', renderGoal));
      var teams = choices('teams', t('opt_teams'), [['0', t('teams_off')], ['1', t('teams_on')]], '0', function (v) {
        // Team play needs 4 at the table, so in solo it means 3 bots.
        if (solo && v === '1') box.querySelector('input[name="bots"][value="3"]').checked = true;
      });
      box.appendChild(teams);
      if (solo) {
        box.appendChild(choices('bots', t('opt_bots'), [1, 2, 3].map(function (n) { return [n, t('n_bots', { n: n })]; }), 3, function (v) {
          if (v !== '3') box.querySelector('input[name="teams"][value="0"]').checked = true;
        }));
      }
      box.appendChild(goalHolder);
      renderGoal('draw');
    }
    if (game === 'tictactoe') {
      box.appendChild(choices('size', t('opt_size'), [[3, t('size_small')]].concat([10, 15, 19, 25].map(function (n) {
        return [n, t('size_big', { n: n })];
      })), 3));
    }
    if (game === 'whoofus' || game === 'wouldrather') {
      box.appendChild(choices('level', t('opt_level'), [['soft', t('lvl_soft')], ['party', t('lvl_party')]], 'soft'));
      box.appendChild(choices('lang', t('opt_lang'), [['fr', 'Français'], ['en', 'English']], GP.lang() === 'en' ? 'en' : 'fr'));
    }
    if (game === 'mystery') {
      box.appendChild(choices('length', t('mw_length'), [['5', t('mw_letters', { n: 5 })], ['6', t('mw_letters', { n: 6 })], ['7', t('mw_letters', { n: 7 })], ['mix', t('mw_mix')]], '5'));
      box.appendChild(choices('first', t('mw_first'), [['1', t('mw_first_yes')], ['0', t('mw_first_no')]], '0'));
      box.appendChild(choices('level', t('opt_level'), [['soft', t('words_easy')], ['party', t('words_hard')]], 'soft'));
      box.appendChild(choices('lang', t('opt_lang'), [['fr', 'Français'], ['en', 'English']], GP.lang() === 'en' ? 'en' : 'fr'));
    }
    if (game === 'drawguess') {
      box.appendChild(choices('rounds', t('opt_rounds'), [1, 2, 3, 4].map(function (n) { return [n, n === 1 ? t('round_one') : t('rounds_n', { n: n })]; }), 2));
      box.appendChild(choices('time', t('opt_time'), [60, 80, 100, 120].map(function (n) { return [n, t('secs', { n: n })]; }), 80));
      box.appendChild(choices('level', t('opt_level'), [['soft', t('words_easy')], ['party', t('words_hard')]], 'soft'));
      box.appendChild(choices('lang', t('opt_lang'), [['fr', 'Français'], ['en', 'English']], GP.lang() === 'en' ? 'en' : 'fr'));
    }
    if (game === 'truthdare') {
      box.appendChild(choices('level', t('opt_level'), [['soft', t('lvl_soft')], ['party', t('lvl_party')], ['hot', t('lvl_hot'), t('lvl_hot_d')]], 'soft'));
      box.appendChild(choices('order', t('opt_order'), [['circle', t('order_circle')], ['random', t('order_random')]], 'circle'));
      box.appendChild(choices('lang', t('opt_lang'), [['fr', 'Français'], ['en', 'English']], GP.lang() === 'en' ? 'en' : 'fr'));
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
    if (game === 'whoofus' || game === 'wouldrather') {
      o.level = val('level'); o.lang = val('lang');
    }
    if (game === 'mystery') {
      o.length = val('length'); o.first = val('first') === '1'; o.level = val('level'); o.lang = val('lang');
    }
    if (game === 'drawguess') {
      o.rounds = parseInt(val('rounds') || '2', 10); o.time = parseInt(val('time') || '80', 10);
      o.level = val('level'); o.lang = val('lang');
    }
    return { options: o, bots: bots };
  }


  /**
   * Open the settings window. opts: { title, okLabel, solo } ; onOk(options, bots) runs when confirmed.
   * The <dialog> is created on first use, so any page can call this.
   */
  var dialog = null, pending = null;
  function open(game, opts, onOk) {
    opts = opts || {};
    if (!dialog) {
      dialog = h('dialog', { id: 'options-dialog' });
      var form = h('form', { method: 'dialog' });
      dialog.titleEl = h('h2');
      dialog.fields = h('div');
      dialog.okBtn = h('button', { class: 'btn primary', value: 'ok', id: 'options-ok' });
      form.appendChild(dialog.titleEl);
      form.appendChild(dialog.fields);
      form.appendChild(h('div', { class: 'row end' }, [h('button', { class: 'btn ghost', value: 'cancel' }, t('cancel')), dialog.okBtn]));
      dialog.appendChild(form);
      document.body.appendChild(dialog);
      dialog.addEventListener('close', function () {
        if (dialog.returnValue !== 'ok' || !pending) return;
        box = dialog.fields;
        var c = collect(pending.game, pending.solo);
        var cb = pending.cb;
        pending = null;
        cb(c.options, c.bots);
      });
    }
    box = dialog.fields;
    buildFields(game, !!opts.solo);
    if (!box.firstChild && !opts.always) { onOk(collect(game, !!opts.solo).options, collect(game, !!opts.solo).bots); return; }
    dialog.titleEl.textContent = opts.title || t('g_' + game);
    dialog.okBtn.textContent = opts.okLabel || t('create');
    pending = { game: game, solo: !!opts.solo, cb: onOk };
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else { var c = collect(game, !!opts.solo); onOk(c.options, c.bots); }
  }

  GP.gameOptions = { open: open };
})();
