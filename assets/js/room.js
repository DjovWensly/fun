/* Room page: joins, waiting room, polling, and hands the game state to the game's UI module. */
(function () {
  'use strict';
  var t = GP.t, h = GP.h;
  var root = document.getElementById('room');
  var CODE = root.getAttribute('data-code');
  var GAME = root.getAttribute('data-game');
  var UI = GP.games[GAME];
  var tokenKey = 'gp_tok_' + CODE;
  var token = GP.store(tokenKey) || '';
  var version = 0;
  var snap = null;           // last full snapshot from the server
  var pollTimer = null;
  var failures = 0;
  var mounted = false;
  var stopped = false;

  var el = function (id) { return document.getElementById(id); };
  var shareUrl = location.origin + location.pathname.replace(/room\.php$/, '') + 'index.php?join=' + CODE;

  // ---------- context passed to the game UI ----------
  var ctx = {
    t: t, h: h,
    send: function (move) { return mutate('move', { move: move }); },
    player: function (seat) {
      if (!snap) return null;
      for (var i = 0; i < snap.players.length; i++) if (snap.players[i].seat === seat) return snap.players[i];
      return null;
    },
    name: function (seat) {
      var p = ctx.player(seat);
      if (!p) return '?';
      return p.name + (seat === snap.you ? ' (' + t('you') + ')' : '');
    },
    you: function () { return snap ? snap.you : null; },
    isHost: function () { return snap ? snap.isHost : false; },
    options: function () { return snap ? snap.room.options : {}; }
  };

  // ---------- networking ----------
  function schedule() {
    clearTimeout(pollTimer);
    if (stopped) return;
    var delay = 2000;
    if (document.hidden) delay = 6000;
    else if (snap && snap.game && isBotTurn()) delay = 1100; // keep bot moves flowing
    pollTimer = setTimeout(poll, delay);
  }

  function isBotTurn() {
    var turn = snap.game && (snap.game.turn !== undefined ? snap.game.turn : null);
    var over = snap.game.phase && snap.game.phase !== 'playing' && GAME === 'dominoes';
    if (turn === null || over || (GAME === 'tictactoe' && snap.game.over)) return false;
    var p = ctx.player(turn);
    return !!(p && p.bot);
  }

  function poll() {
    var req = { code: CODE, token: token, v: version };
    if (GP.emotes.cursor() !== null) req.e = GP.emotes.cursor();
    GP.api('state', req).then(function (r) {
      if (!r.ok) {
        if (r.error === 'room_not_found') { stopped = true; showClosed(t('err_room_not_found')); return; }
        failures++;
        el('net-status').hidden = failures < 2;
        schedule();
        return;
      }
      failures = 0;
      el('net-status').hidden = true;
      GP.emotes.receive(r);
      if (r.same) updatePresence(r.presence);
      else apply(r);
      schedule();
    });
  }

  function mutate(action, data) {
    return GP.api(action, Object.assign({ code: CODE, token: token }, data || {})).then(function (r) {
      if (!r.ok) { if (!r.silent) GP.toast(GP.errText(r.error), true); return r; }
      if (r.room) apply(r);
      return r;
    });
  }

  document.addEventListener('visibilitychange', function () { if (!document.hidden) { clearTimeout(pollTimer); poll(); } });

  // ---------- rendering ----------
  function apply(r) {
    snap = r;
    version = r.room.version;
    el('loading').hidden = true;

    // A stored token that the server no longer knows (kicked, or room recreated) is useless.
    if (token && r.you === null) { GP.store(tokenKey, null); token = ''; }

    var member = r.you !== null;
    var status = r.room.status;
    el('leave-btn').hidden = !member;
    GP.emotes.setVisible(member && status !== 'closed');
    el('share-btn').hidden = false;

    if (status === 'closed') { showClosed(t('room_closed')); stopped = true; return; }

    renderJoin(member, r);
    el('lobby-section').hidden = status !== 'lobby';
    el('game-section').hidden = status !== 'playing';
    el('host-menu').hidden = !(status === 'playing' && r.isHost);

    if (status === 'lobby') renderLobby(r);
    if (status === 'playing') {
      if (!mounted) { UI.mount(el('game-section'), ctx); mounted = true; }
      UI.update(r.game, ctx);
      renderPlayers(el('game-players'), r, true);
      el('back-lobby-btn').hidden = !r.isHost;
    }
  }

  function updatePresence(presence) {
    if (!snap || !presence) return;
    var changed = false;
    snap.players.forEach(function (p) {
      var on = !!presence[p.seat];
      if (p.online !== on) { p.online = on; changed = true; }
    });
    if (changed) {
      if (snap.room.status === 'lobby') renderLobby(snap);
      else renderPlayers(el('game-players'), snap, true);
    }
  }

  function showClosed(msg) {
    el('loading').hidden = true;
    ['lobby-section', 'game-section', 'join-section', 'host-menu'].forEach(function (id) { el(id).hidden = true; });
    var box = h('div', { class: 'panel center' }, [
      h('h2', { text: msg }),
      h('a', { class: 'btn primary', href: 'index.php', text: t('choose_game') })
    ]);
    root.appendChild(box);
  }

  function renderJoin(member, r) {
    var sec = el('join-section');
    if (member) { sec.hidden = true; return; }
    sec.hidden = false;
    var meta = r.room.meta;
    var blocked = null;
    if (r.room.status === 'playing' && !meta.joinMidGame) blocked = t('err_game_started') + ' ' + t('spectating');
    else if (r.players.length >= meta.max) blocked = t('err_room_full') + ' ' + t('spectating');
    el('room-join-form').hidden = !!blocked;
    el('join-blocked').hidden = !blocked;
    el('join-blocked').textContent = blocked || '';
    var input = el('room-join-name');
    if (!input.value) input.value = GP.store('gp_name') || '';
  }

  function badge(text, cls) { return h('span', { class: 'tag ' + (cls || '') }, text); }

  function renderPlayers(list, r, inGame) {
    list.innerHTML = '';
    var turn = r.game && r.game.turn !== undefined ? r.game.turn : null;
    r.players.forEach(function (p) {
      var canKick = r.isHost && p.seat !== r.you && (!inGame || !p.bot || r.room.meta.joinMidGame);
      var label = inGame && !r.room.meta.joinMidGame ? t('replace_bot') : t('kick');
      list.appendChild(h('li', { 'data-seat': p.seat, class: (p.online ? '' : 'offline ') + (inGame && turn === p.seat ? 'turn' : '') }, [
        h('span', { class: 'dot ' + (p.online ? 'on' : 'off'), title: p.online ? '' : t('offline') }),
        h('span', { class: 'pname' }, p.name),
        p.seat === r.you ? badge(t('you'), 'me') : null,
        p.host ? badge(t('host'), 'host') : null,
        p.bot ? badge(t('bot'), 'bot') : null,
        !p.online ? badge(t('offline'), 'muted') : null,
        canKick ? h('button', {
          class: 'btn tiny ghost', type: 'button', text: label,
          onclick: function () { if (confirm(t('kick_confirm', { name: p.name }))) mutate('kick', { seat: p.seat }); }
        }) : null
      ]));
    });
  }

  /** One line describing the settings chosen when the table was opened. */
  function modeSummary(o) {
    if (GAME === 'dominoes') {
      return [t('mode_' + (o.mode || 'draw')), o.teams ? t('teams_on') : null,
        o.goal === 'rounds' ? t('target_rounds', { n: o.target }) : t('target_pts', { n: o.target || 100 })]
        .filter(Boolean).join(' · ');
    }
    if (GAME === 'tictactoe') {
      var n = o.size || 3;
      return n === 3 ? t('size_small') : t('size_big', { n: n });
    }
    if (GAME === 'truthdare') return [t('lvl_' + (o.level || 'soft')), t('order_' + (o.order || 'circle'))].join(' · ');
    return '';
  }

  function renderLobby(r) {
    el('mode-summary').textContent = modeSummary(r.room.options || {});
    renderPlayers(el('lobby-players'), r, false);
    var meta = r.room.meta;
    el('host-controls').hidden = !r.isHost;
    el('add-bot-btn').hidden = !meta.bots || r.players.length >= meta.max;
    var need = GAME === 'dominoes' && r.room.options.teams ? 4 : meta.min;
    var enough = GAME === 'dominoes' && r.room.options.teams ? r.players.length === 4 : r.players.length >= meta.min;
    el('start-btn').disabled = !enough;
    el('lobby-hint').textContent = !enough ? (need === 4 && meta.min !== 4 ? t('err_need_four') : t('need_players', { n: meta.min }))
      : (r.isHost ? '' : (r.you !== null ? t('host_will_start') : ''));
  }

  // ---------- buttons ----------
  el('room-join-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var name = el('room-join-name').value.trim();
    if (!name) return;
    GP.store('gp_name', name);
    GP.api('join', { code: CODE, name: name }).then(function (r) {
      if (!r.ok) { GP.toast(GP.errText(r.error), true); return; }
      token = r.token;
      GP.store(tokenKey, token);
      version = 0;
      clearTimeout(pollTimer);
      poll();
    });
  });

  el('start-btn').addEventListener('click', function () { mutate('start'); });
  el('add-bot-btn').addEventListener('click', function () { mutate('add_bot'); });
  el('back-lobby-btn').addEventListener('click', function () { mutate('lobby'); });
  el('leave-btn').addEventListener('click', function () {
    if (!confirm(t('leave_confirm'))) return;
    mutate('leave').then(function (r) {
      if (r.ok) { GP.store(tokenKey, null); location.href = 'index.php'; }
    });
  });

  function copyLink() {
    var done = function () { GP.toast(t('copied')); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(shareUrl).then(done, fallback);
    else fallback();
    function fallback() { window.prompt(t('copy_link'), shareUrl); }
  }
  el('copy-btn').addEventListener('click', copyLink);
  el('code-btn').addEventListener('click', copyLink);
  el('share-btn').addEventListener('click', function () {
    var text = t('invite_text', { game: t('g_' + GAME), code: CODE });
    if (navigator.share) navigator.share({ title: t('g_' + GAME), text: text, url: shareUrl }).catch(function () {});
    else copyLink();
  });

  function updateWhatsApp() {
    var text = t('invite_text', { game: t('g_' + GAME), code: CODE }) + ' ' + shareUrl;
    el('wa-btn').href = 'https://wa.me/?text=' + encodeURIComponent(text);
  }
  updateWhatsApp();

  el('qr-btn').addEventListener('click', function () {
    var box = el('qr');
    box.hidden = !box.hidden;
    if (!box.hidden && !box.firstChild && window.QRCode) {
      new window.QRCode(box, { text: shareUrl, width: 180, height: 180 });
    }
  });

  document.addEventListener('gp:lang', function () {
    updateWhatsApp();
    mounted = false;
    if (snap) { var s = snap; snap = null; apply(s); }
  });

  GP.emotes.init({
    send: function (key) { return GP.api('emote', { code: CODE, token: token, emote: key }); },
    name: function (seat) { var p = ctx.player(seat); return p ? p.name : ''; },
    you: function () { return snap ? snap.you : null; }
  });

  poll();
})();
