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

  // ----- settings window (assets/js/options.js) -----
  function open(game, solo) {
    var name = needName();
    if (!name) return;
    GP.gameOptions.open(game, { solo: solo, always: true, okLabel: solo ? t('play') : t('create') }, function (options, bots) {
      send(game, name, options, bots, solo);
    });
  }

  function send(game, name, options, bots, solo) {
    GP.api('create', { game: game, name: name, options: options, bots: bots, solo: !!solo }).then(function (r) {
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
