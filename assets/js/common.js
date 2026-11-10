/* Shared helpers: translations, API calls, storage, small UI bits. No framework, no build step. */
(function () {
  'use strict';

  var DICT = {
    fr: {
      site_title: 'Soirée Jeux',
      tagline: 'Des jeux à partager entre amis — crée une salle, partage le code, jouez !',
      back_home: '← dwens.gamer.gd',
      your_name: 'Ton nom',
      name_ph: 'Ex : Wens',
      join_title: 'Rejoindre une partie',
      join_hint: 'Entre le code que ton ami t\'a donné.',
      code_ph: 'CODE',
      join: 'Rejoindre',
      choose_game: 'Choisis un jeu',
      create_room: 'Créer une salle',
      solo_bots: 'Solo contre bots',
      one_phone: 'Sur un seul téléphone',
      players_n: '{min}–{max} joueurs',
      players_plus: '{min}+ joueurs',
      players_exact: '{n} joueurs',
      g_dominoes: 'Dominos',
      g_dominoes_d: 'Le classique double-six. Pose tes dominos, bloque les autres, finis le premier !',
      g_truthdare: 'Action ou Vérité',
      g_truthdare_d: 'Des questions et des défis pour mettre l\'ambiance. Ajoutez vos propres cartes !',
      g_tictactoe: 'Morpion',
      g_tictactoe_d: 'Tic-tac-toe rapide à deux, avec le score des revanches.',
      options: 'Options',
      opt_target: 'Score à atteindre',
      opt_level: 'Niveau',
      lvl_soft: 'Tranquille (tout public)',
      lvl_party: 'Soirée (plus osé)',
      opt_order: 'Ordre des tours',
      order_circle: 'Chacun son tour',
      order_random: 'Au hasard',
      opt_lang: 'Langue des cartes',
      opt_bots: 'Nombre de bots',
      cancel: 'Annuler',
      create: 'Créer',
      play: 'Jouer',
      room: 'Salle',
      code: 'Code',
      copy_link: 'Copier le lien',
      copied: 'Lien copié !',
      share_whatsapp: 'Partager sur WhatsApp',
      show_qr: 'QR code',
      leave: 'Quitter',
      leave_confirm: 'Quitter la partie ?',
      invite_text: 'Viens jouer à {game} avec moi ! Code : {code}',
      waiting_room: 'Salle d\'attente',
      waiting_players: 'En attente des joueurs… Partage le code ci-dessous.',
      players: 'Joueurs',
      host: 'Hôte',
      bot: 'Bot',
      you: 'toi',
      offline: 'hors ligne',
      add_bot: '+ Ajouter un bot',
      kick: 'Retirer',
      kick_confirm: 'Retirer {name} ?',
      replace_bot: 'Remplacer par un bot',
      start: 'Lancer la partie',
      need_players: 'Il faut au moins {n} joueurs.',
      host_will_start: 'L\'hôte va lancer la partie…',
      back_lobby: 'Retour à la salle d\'attente',
      join_this: 'Rejoindre cette partie',
      spectating: 'Tu regardes la partie.',
      room_closed: 'Cette salle est fermée.',
      loading: 'Chargement…',
      connection_lost: 'Connexion perdue, nouvelle tentative…',
      your_turn: 'À toi de jouer !',
      turn_of: 'Au tour de {name}',
      // dominoes
      boneyard: 'Pioche : {n}',
      draw: 'Piocher',
      pass: 'Passer',
      left: '◀ Gauche',
      right: 'Droite ▶',
      where_play: 'Où poser ce domino ?',
      round_n: 'Manche {n}',
      target_pts: 'Objectif : {n} pts',
      tiles_n: '{n} dominos',
      domino_win: '{name} a fini ses dominos !',
      blocked_win: 'Jeu bloqué — {name} gagne avec le moins de points.',
      blocked_tie: 'Jeu bloqué — égalité, personne ne marque.',
      points_won: '+{n} points',
      match_win: '🏆 {name} gagne la partie !',
      next_round: 'Manche suivante',
      new_match: 'Nouvelle partie',
      host_restart: 'L\'hôte peut relancer une partie.',
      pips: '{n} pts',
      log_play: '{name} pose {tile}',
      log_draw: '{name} pioche',
      log_pass: '{name} passe',
      log_round: 'Manche {n} — {name} commence',
      log_end: 'Fin de manche',
      no_move: 'Aucun domino ne passe.',
      // truth or dare
      truth: 'Vérité',
      dare: 'Action',
      random: 'Au hasard',
      choose_td: 'Action ou vérité ?',
      waiting_choice: '{name} choisit…',
      done: 'Fait ✓',
      skip: 'Passer',
      another: 'Autre carte',
      custom_card: 'carte du groupe',
      add_card: 'Ajouter une carte',
      add_card_ph: 'Écris ta question ou ton défi…',
      add: 'Ajouter',
      card_added: 'Carte ajoutée au paquet !',
      custom_count: '{n} carte(s) ajoutée(s) par le groupe',
      cards_played: 'Cartes jouées : {n}',
      stats: 'V {t} · A {d} · passé {s}',
      last_turn: 'Dernier tour : {name}',
      skipped: 'a passé',
      host_can_skip: 'En tant qu\'hôte tu peux passer son tour.',
      // local
      local_title: 'Action ou Vérité — un seul téléphone',
      local_hint: 'Ajoutez les joueurs, puis passez-vous le téléphone.',
      add_player: 'Ajouter',
      player_ph: 'Nom du joueur',
      need_two: 'Ajoute au moins 2 joueurs.',
      pass_phone: 'Passe le téléphone à {name}',
      // tic tac toe
      ttt_win: '{name} gagne !',
      ttt_draw: 'Match nul !',
      rematch: 'Revanche',
      draws: 'Nuls',
      // errors
      err_name_required: 'Entre ton nom.',
      err_room_not_found: 'Salle introuvable. Vérifie le code.',
      err_room_full: 'La salle est pleine.',
      err_game_started: 'La partie a déjà commencé.',
      err_room_closed: 'Cette salle est fermée.',
      err_not_in_room: 'Tu ne fais pas partie de cette salle.',
      err_host_only: 'Seul l\'hôte peut faire ça.',
      err_not_your_turn: 'Ce n\'est pas ton tour.',
      err_not_enough_players: 'Pas assez de joueurs.',
      err_tile_does_not_fit: 'Ce domino ne passe pas.',
      err_you_can_play: 'Tu as un domino jouable.',
      err_cannot_pass: 'Tu ne peux pas passer.',
      err_card_too_short: 'Carte trop courte.',
      err_too_many_cards: 'Trop de cartes ajoutées.',
      err_no_cards: 'Aucune carte dans ce paquet.',
      err_network: 'Problème de connexion.',
      err_server_error: 'Erreur du serveur, réessaie.',
      err_generic: 'Action impossible.'
    },
    en: {
      site_title: 'Game Party',
      tagline: 'Games to share with friends — create a room, share the code, play!',
      back_home: '← dwens.gamer.gd',
      your_name: 'Your name',
      name_ph: 'e.g. Wens',
      join_title: 'Join a game',
      join_hint: 'Enter the code your friend gave you.',
      code_ph: 'CODE',
      join: 'Join',
      choose_game: 'Pick a game',
      create_room: 'Create a room',
      solo_bots: 'Solo vs bots',
      one_phone: 'On one phone',
      players_n: '{min}–{max} players',
      players_plus: '{min}+ players',
      players_exact: '{n} players',
      g_dominoes: 'Dominoes',
      g_dominoes_d: 'Classic double-six. Lay your tiles, block the others, go out first!',
      g_truthdare: 'Truth or Dare',
      g_truthdare_d: 'Questions and dares to liven up the party. Add your own cards!',
      g_tictactoe: 'Tic-Tac-Toe',
      g_tictactoe_d: 'Quick two-player tic-tac-toe with a running rematch score.',
      options: 'Options',
      opt_target: 'Points to win',
      opt_level: 'Level',
      lvl_soft: 'Chill (everyone)',
      lvl_party: 'Party (bolder)',
      opt_order: 'Turn order',
      order_circle: 'Around the circle',
      order_random: 'Random',
      opt_lang: 'Card language',
      opt_bots: 'Number of bots',
      cancel: 'Cancel',
      create: 'Create',
      play: 'Play',
      room: 'Room',
      code: 'Code',
      copy_link: 'Copy link',
      copied: 'Link copied!',
      share_whatsapp: 'Share on WhatsApp',
      show_qr: 'QR code',
      leave: 'Leave',
      leave_confirm: 'Leave the game?',
      invite_text: 'Come play {game} with me! Code: {code}',
      waiting_room: 'Waiting room',
      waiting_players: 'Waiting for players… Share the code below.',
      players: 'Players',
      host: 'Host',
      bot: 'Bot',
      you: 'you',
      offline: 'offline',
      add_bot: '+ Add a bot',
      kick: 'Remove',
      kick_confirm: 'Remove {name}?',
      replace_bot: 'Replace with a bot',
      start: 'Start the game',
      need_players: 'At least {n} players needed.',
      host_will_start: 'The host will start the game…',
      back_lobby: 'Back to waiting room',
      join_this: 'Join this game',
      spectating: 'You are watching this game.',
      room_closed: 'This room is closed.',
      loading: 'Loading…',
      connection_lost: 'Connection lost, retrying…',
      your_turn: 'Your turn!',
      turn_of: '{name}\'s turn',
      boneyard: 'Boneyard: {n}',
      draw: 'Draw',
      pass: 'Pass',
      left: '◀ Left',
      right: 'Right ▶',
      where_play: 'Where do you want to play this tile?',
      round_n: 'Round {n}',
      target_pts: 'Target: {n} pts',
      tiles_n: '{n} tiles',
      domino_win: '{name} played their last tile!',
      blocked_win: 'Blocked — {name} wins with the fewest pips.',
      blocked_tie: 'Blocked — tie, nobody scores.',
      points_won: '+{n} points',
      match_win: '🏆 {name} wins the match!',
      next_round: 'Next round',
      new_match: 'New match',
      host_restart: 'The host can start a new match.',
      pips: '{n} pips',
      log_play: '{name} plays {tile}',
      log_draw: '{name} draws',
      log_pass: '{name} passes',
      log_round: 'Round {n} — {name} starts',
      log_end: 'End of round',
      no_move: 'No tile fits.',
      truth: 'Truth',
      dare: 'Dare',
      random: 'Random',
      choose_td: 'Truth or dare?',
      waiting_choice: '{name} is choosing…',
      done: 'Done ✓',
      skip: 'Skip',
      another: 'Another card',
      custom_card: 'group card',
      add_card: 'Add a card',
      add_card_ph: 'Write your question or dare…',
      add: 'Add',
      card_added: 'Card added to the deck!',
      custom_count: '{n} card(s) added by the group',
      cards_played: 'Cards played: {n}',
      stats: 'T {t} · D {d} · skipped {s}',
      last_turn: 'Last turn: {name}',
      skipped: 'skipped',
      host_can_skip: 'As host you can skip their turn.',
      local_title: 'Truth or Dare — one phone',
      local_hint: 'Add the players, then pass the phone around.',
      add_player: 'Add',
      player_ph: 'Player name',
      need_two: 'Add at least 2 players.',
      pass_phone: 'Pass the phone to {name}',
      ttt_win: '{name} wins!',
      ttt_draw: 'It\'s a draw!',
      rematch: 'Rematch',
      draws: 'Draws',
      err_name_required: 'Enter your name.',
      err_room_not_found: 'Room not found. Check the code.',
      err_room_full: 'The room is full.',
      err_game_started: 'The game has already started.',
      err_room_closed: 'This room is closed.',
      err_not_in_room: 'You are not in this room.',
      err_host_only: 'Only the host can do that.',
      err_not_your_turn: 'It\'s not your turn.',
      err_not_enough_players: 'Not enough players.',
      err_tile_does_not_fit: 'That tile doesn\'t fit.',
      err_you_can_play: 'You have a playable tile.',
      err_cannot_pass: 'You can\'t pass.',
      err_card_too_short: 'Card is too short.',
      err_too_many_cards: 'Too many cards added.',
      err_no_cards: 'No cards in this deck.',
      err_network: 'Connection problem.',
      err_server_error: 'Server error, try again.',
      err_generic: 'You can\'t do that.'
    }
  };

  function store(key, val) {
    try {
      if (val === undefined) return localStorage.getItem(key);
      if (val === null) localStorage.removeItem(key);
      else localStorage.setItem(key, val);
    } catch (e) { /* private mode: ignore */ }
    return null;
  }

  var lang = store('gp_lang') || ((navigator.language || 'fr').slice(0, 2) === 'en' ? 'en' : 'fr');

  function t(key, vars) {
    var s = (DICT[lang] && DICT[lang][key]) || DICT.fr[key] || key;
    if (vars) {
      Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(vars[k]); });
    }
    return s;
  }

  function applyI18n(root) {
    root = root || document;
    root.querySelectorAll('[data-i18n]').forEach(function (el) { el.textContent = t(el.getAttribute('data-i18n')); });
    root.querySelectorAll('[data-i18n-ph]').forEach(function (el) { el.placeholder = t(el.getAttribute('data-i18n-ph')); });
    document.documentElement.lang = lang;
  }

  function setLang(l) {
    lang = l;
    store('gp_lang', l);
    applyI18n();
    document.dispatchEvent(new CustomEvent('gp:lang'));
  }

  function api(action, data) {
    var body = Object.assign({ action: action }, data || {});
    return fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.text().then(function (txt) {
        try { return JSON.parse(txt); } catch (e) { return { ok: false, error: r.ok ? 'network' : 'server_error' }; }
      });
    }).catch(function () { return { ok: false, error: 'network' }; });
  }

  function errText(code) {
    var k = 'err_' + code;
    var s = t(k);
    return s === k ? t('err_generic') : s;
  }

  var toastTimer;
  function toast(msg, isError) {
    var el = document.getElementById('toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.className = 'show' + (isError ? ' error' : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.className = ''; }, 2600);
  }

  /** Tiny DOM builder: h('div', {class: 'x', onclick: fn}, [children or text]) */
  function h(tag, attrs, children) {
    var el = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), v);
        else if (k === 'class') el.className = v;
        else if (k === 'text') el.textContent = v;
        else el.setAttribute(k, v === true ? '' : v);
      });
    }
    (Array.isArray(children) ? children : children === undefined ? [] : [children]).forEach(function (c) {
      if (c === null || c === undefined || c === false) return;
      el.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
    });
    return el;
  }

  function langSwitch() {
    document.querySelectorAll('[data-lang]').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-lang') === lang);
      b.addEventListener('click', function () {
        setLang(b.getAttribute('data-lang'));
        document.querySelectorAll('[data-lang]').forEach(function (x) {
          x.classList.toggle('active', x.getAttribute('data-lang') === lang);
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () { applyI18n(); langSwitch(); });

  window.GP = {
    t: t, api: api, store: store, toast: toast, h: h, errText: errText, applyI18n: applyI18n,
    lang: function () { return lang; },
    games: {} // game UIs register themselves here
  };
})();
