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
    call: function (action, data) { return GP.api(action, Object.assign({ code: CODE, token: token }, data || {})); },
    player: function (seat) {
      if (!snap) return null;
      var all = snap.players.concat(snap.spectators || []);
      for (var i = 0; i < all.length; i++) if (all[i].seat === seat) return all[i];
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
    var base = GP.config.pollMs || 2000;   // set in admin.php > Réglages
    var delay = base;
    if (document.hidden) delay = base * 3;
    else if (snap && snap.game && isBotTurn()) delay = 1100; // keep bot moves flowing
    else if (snap && snap.game && UI.fastPoll && UI.fastPoll(snap.game)) delay = Math.max(900, Math.round(base / 2));
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
    // Games can ask for extra data with each poll (the drawing in Dessine et devine).
    if (snap && snap.room.status === 'playing' && UI.pollParams) Object.assign(req, UI.pollParams());
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
      if (UI.onPoll && mounted) UI.onPoll(r, ctx);
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
    // The host switched the table to another game: reload to get that game's screen.
    if (r.room.game !== GAME) { stopped = true; location.reload(); return; }
    var prev = snap;
    snap = r;
    version = r.room.version;
    el('loading').hidden = true;

    // A stored token that the server no longer knows (kicked, or room recreated) is useless.
    if (token && r.you === null && r.watching === null) { GP.store(tokenKey, null); token = ''; }

    var member = r.you !== null;
    var watching = r.watching !== null && r.watching !== undefined;
    var status = r.room.status;
    el('leave-btn').hidden = !member && !watching;
    GP.emotes.setVisible((member || watching) && status !== 'closed');
    el('share-btn').hidden = false;

    if (status === 'closed') { showClosed(t('room_closed')); stopped = true; return; }

    renderJoin(member || watching, r);
    renderSpectatorBar(watching, r);
    el('lobby-section').hidden = status !== 'lobby';
    el('game-section').hidden = status !== 'playing';
    el('host-menu').hidden = !(status === 'playing' && r.isHost);

    if (status === 'lobby') renderLobby(r);
    if (status === 'playing') {
      if (!mounted) { UI.mount(el('game-section'), ctx); mounted = true; }
      UI.update(r.game, ctx);
      renderPlayers(el('game-players'), r, true);
      renderSpectators(el('game-spectators'), r);
      el('back-lobby-btn').hidden = !r.isHost;
    }
    renderHistory(r, prev);
    soundCues(r, prev);
  }

  /** Sounds that every game shares: your turn, and the end of a game. */
  function soundCues(r, prev) {
    if (!GP.sound) return;
    var g = r.game, you = r.you;
    var mine = function (s) {
      var x = s && s.game;
      if (!x || you === null || x.turn === undefined || x.turn !== you || x.over) return false;
      return !x.phase || x.phase === 'playing' || x.phase === 'choose' || x.phase === 'vote';
    };
    if (prev && mine(r) && !mine(prev)) { GP.sound.play('turn'); GP.sound.vibrate(120); }
    var top = r.history && r.history[0];
    var before = prev && prev.history && prev.history[0];
    if (prev && top && (!before || top.id !== before.id)) {
      var me = r.you !== null && ctx.player(r.you) ? ctx.player(r.you).name : null;
      GP.sound.play(me && top.winners.indexOf(me) >= 0 ? 'win' : 'end');
    }
  }

  // ---------- spectators ----------
  function renderSpectatorBar(watching, r) {
    el('spectator-bar').hidden = !watching;
    if (!watching) return;
    var meta = r.room.meta;
    var canSit = (r.room.status === 'lobby' || meta.joinMidGame) && r.players.length < meta.max;
    el('sit-btn').hidden = !canSit;
  }

  function renderSpectators(box, r) {
    var list = r.spectators || [];
    box.hidden = !list.length;
    box.innerHTML = '';
    if (!list.length) return;
    box.appendChild(h('span', { class: 'label' }, t('spectators') + ' (' + list.length + ')'));
    var row = h('div', { class: 'spectator-list' });
    list.forEach(function (sp) {
      row.appendChild(h('span', { class: 'tag' + (sp.online ? '' : ' muted'), 'data-seat': sp.seat }, [
        sp.name + (sp.seat === r.watching ? ' (' + t('you') + ')' : ''),
        r.isHost ? h('button', { type: 'button', class: 'tag-x', 'aria-label': t('kick'), onclick: function () { if (confirm(t('kick_confirm', { name: sp.name }))) mutate('kick', { seat: sp.seat }); } }, '×') : null
      ]));
    });
    box.appendChild(row);
  }

  // ---------- evening history ----------
  function renderHistory(r) {
    var list = r.history || [];
    el('history').hidden = !list.length;
    if (!list.length) return;
    el('history-count').textContent = '(' + list.length + ')';
    // wins per person
    var wins = {};
    list.forEach(function (e) { (e.winners || []).forEach(function (n) { wins[n] = (wins[n] || 0) + 1; }); });
    var tally = el('history-tally');
    tally.innerHTML = '';
    Object.keys(wins).sort(function (a, b) { return wins[b] - wins[a]; }).forEach(function (n, i) {
      tally.appendChild(h('span', { class: 'tally-item' + (i === 0 ? ' top' : '') }, [h('strong', null, n), ' ' + t('wins_n', { n: wins[n] })]));
    });
    var ul = el('history-list');
    ul.innerHTML = '';
    list.forEach(function (e) {
      var d = new Date(e.at * 1000);
      var time = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
      var what = e.draw ? t('hist_draw') : (e.winners.length > 1 ? t('hist_won_many', { names: e.winners.join(' & ') }) : t('hist_won', { names: e.winners.join('') }));
      var score = (e.ranking || []).slice(0, 4).map(function (r) { return r[1]; }).join(' – ');
      ul.appendChild(h('li', null, [
        h('span', { class: 'muted' }, time), ' ', h('strong', null, t('g_' + e.game)), ' · ', what,
        score ? h('span', { class: 'muted' }, ' (' + score + ')') : null
      ]));
    });
  }

  // ---------- switch game (host) ----------
  function chooseGame() {
    var games = (GP.config.games || []);
    var dlg = h('dialog', { class: 'game-chooser' });
    var form = h('form', { method: 'dialog' }, [h('h2', null, t('pick_game'))]);
    var grid = h('div', { class: 'chooser-grid' });
    games.forEach(function (g) {
      grid.appendChild(h('button', { class: 'btn' + (g === GAME ? ' primary' : ''), value: g }, t('g_' + g)));
    });
    form.appendChild(grid);
    form.appendChild(h('div', { class: 'row end' }, [h('button', { class: 'btn ghost', value: '' }, t('cancel'))]));
    dlg.appendChild(form);
    document.body.appendChild(dlg);
    dlg.addEventListener('close', function () {
      var game = dlg.returnValue;
      dlg.remove();
      if (!game) return;
      GP.gameOptions.open(game, { always: true, okLabel: t('switch_go') }, function (options) {
        mutate('switch_game', { game: game, options: options });
      });
    });
    dlg.showModal();
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

  function renderJoin(inside, r) {
    var sec = el('join-section');
    if (inside) { sec.hidden = true; return; }
    sec.hidden = false;
    var meta = r.room.meta;
    var blocked = null;
    if (r.room.status === 'playing' && !meta.joinMidGame) blocked = t('err_game_started') + ' ' + t('can_watch');
    else if (r.players.length >= meta.max) blocked = t('err_room_full') + ' ' + t('can_watch');
    // You can always watch; you can play only when there is room.
    el('join-play-btn').hidden = !!blocked;
    el('join-watch-btn').classList.toggle('primary', !!blocked);
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
    if (GAME === 'whoofus' || GAME === 'wouldrather') return t('lvl_' + (o.level || 'soft'));
    if (GAME === 'mystery') return [o.length === 'mix' ? t('mw_mix') : t('mw_letters', { n: o.length || 5 }), o.first ? t('mw_first_yes') : null].filter(Boolean).join(' · ');
    if (GAME === 'drawguess') return [(o.rounds === 1 ? t('round_one') : t('rounds_n', { n: o.rounds || 2 })), t('secs', { n: o.time || 80 }), o.level === 'party' ? t('words_hard') : t('words_easy')].join(' · ');
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
    renderSpectators(el('lobby-spectators'), r);
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

  el('join-watch-btn').addEventListener('click', function () {
    var name = el('room-join-name').value.trim();
    if (!name) { el('room-join-name').focus(); GP.toast(t('err_name_required'), true); return; }
    GP.store('gp_name', name);
    GP.api('watch', { code: CODE, name: name }).then(function (r) {
      if (!r.ok) { GP.toast(GP.errText(r.error), true); return; }
      token = r.token;
      GP.store(tokenKey, token);
      version = 0;
      clearTimeout(pollTimer);
      poll();
    });
  });
  el('sit-btn').addEventListener('click', function () { mutate('sit'); });
  el('look-btn').addEventListener('click', function () { GP.look.open(); });
  el('switch-btn-lobby').addEventListener('click', chooseGame);
  el('switch-btn-game').addEventListener('click', chooseGame);
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
    you: function () { return snap ? (snap.you !== null ? snap.you : snap.watching) : null; }
  });

  poll();
})();
