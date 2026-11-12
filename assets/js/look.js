/* Appearance panel: sound, vibration, domino style, table colour, Morpion paper.
   Choices are kept on this device only (localStorage) and applied as classes on <body>. */
(function () {
  'use strict';
  var t = GP.t, h = GP.h;

  var OPTIONS = {
    dom: { label: 'look_dominoes', values: [['classic', 'look_dom_classic'], ['colors', 'look_dom_colors'], ['night', 'look_dom_night'], ['red', 'look_dom_red']] },
    table: { label: 'look_table', values: [['green', 'look_table_green'], ['blue', 'look_table_blue'], ['wine', 'look_table_wine'], ['black', 'look_table_black'], ['wood', 'look_table_wood']] },
    ttt: { label: 'look_ttt', values: [['paper', 'look_ttt_paper'], ['yellow', 'look_ttt_yellow'], ['chalk', 'look_ttt_chalk']] }
  };

  var look = { dom: 'classic', table: 'green', ttt: 'paper' };
  try { Object.assign(look, JSON.parse(GP.store('gp_look') || '{}')); } catch (e) { /* keep defaults */ }

  function apply() {
    var b = document.body;
    Object.keys(OPTIONS).forEach(function (k) {
      OPTIONS[k].values.forEach(function (v) { b.classList.remove(k + '-' + v[0]); });
      b.classList.add(k + '-' + look[k]);
    });
  }

  function save() {
    GP.store('gp_look', JSON.stringify(look));
    apply();
  }

  /** Small previews drawn in the panel next to each choice. */
  function swatch(kind, value) {
    return h('span', { class: 'swatch swatch-' + kind + ' ' + kind + '-' + value });
  }

  var dialog = null;
  function open() {
    if (dialog) dialog.remove();
    dialog = h('dialog', { class: 'look-dialog' });
    var form = h('form', { method: 'dialog' }, [h('h2', null, t('look_title'))]);

    // sound + vibration
    var sound = h('label', { class: 'check' }, [h('input', { type: 'checkbox', checked: GP.sound && GP.sound.enabled() }), h('span', null, t('look_sound'))]);
    sound.querySelector('input').addEventListener('change', function (e) { if (GP.sound) GP.sound.set(e.target.checked); });
    var vib = h('label', { class: 'check' }, [h('input', { type: 'checkbox', checked: GP.sound && GP.sound.vibration() }), h('span', null, t('look_vibrate'))]);
    vib.querySelector('input').addEventListener('change', function (e) { if (GP.sound) GP.sound.setVibration(e.target.checked); });
    form.appendChild(h('div', { class: 'look-row' }, [sound, vib]));

    Object.keys(OPTIONS).forEach(function (k) {
      var group = h('div', { class: 'choices look-choices', role: 'radiogroup' });
      OPTIONS[k].values.forEach(function (v) {
        var input = h('input', { type: 'radio', name: 'look-' + k, value: v[0], checked: look[k] === v[0] });
        input.addEventListener('change', function () { look[k] = v[0]; save(); });
        group.appendChild(h('label', null, [input, h('span', null, [swatch(k, v[0]), t(v[1])])]));
      });
      form.appendChild(h('div', { class: 'field' }, [h('span', null, t(OPTIONS[k].label)), group]));
    });
    form.appendChild(h('p', { class: 'muted small' }, t('look_note')));
    form.appendChild(h('div', { class: 'row end' }, [h('button', { class: 'btn primary', value: 'ok' }, t('look_done'))]));
    dialog.appendChild(form);
    document.body.appendChild(dialog);
    dialog.showModal();
  }

  apply();
  document.addEventListener('DOMContentLoaded', apply);
  GP.look = { open: open, get: function () { return look; } };
})();
