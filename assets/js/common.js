/* Shared helpers: translations, API calls, storage, small UI bits. No framework, no build step. */
(function () {
  'use strict';

  var DICT = {
    fr: {
      site_title: 'Soirée Jeux',
      hero_title: 'Ce soir, on joue.',
      footer_text: '© {year} {site} · Des jeux à partager entre amis.',
      tagline: 'Choisis un jeu, envoie le code à tes amis, et c\'est parti.',
      back_home: 'dwens.gamer.gd',
      your_name: 'Ton nom',
      name_ph: 'Wens',
      join_title: 'On t\'a donné un code ?',
      join_hint: 'Tape-le ici pour rejoindre la table.',
      code_ph: 'CODE',
      join: 'Entrer',
      choose_game: 'Les jeux',
      create_room: 'Créer une table',
      solo_bots: 'Jouer seul',
      one_phone: 'Un seul téléphone',
      players_n: '{min} à {max} joueurs',
      players_plus: '{min} joueurs et plus',
      players_exact: '{n} joueurs',
      g_dominoes: 'Dominos',
      g_dominoes_d: 'Double-six, de 2 à 4. Avec pioche, bloqué, all fives, ou en équipes de deux.',
      g_truthdare: 'Action ou Vérité',
      g_truthdare_d: 'Des questions et des défis, du plus sage au plus osé. Le groupe peut écrire ses propres cartes.',
      g_tictactoe: 'Morpion',
      g_tictactoe_d: 'Le petit 3 × 3, ou une grande grille jusqu\'à 25 × 25 où il faut aligner 5.',
      options: 'Réglages',
      opt_target: 'Partie en',
      opt_level: 'Niveau',
      lvl_soft: 'Sage (tout le monde)',
      lvl_party: 'Soirée (plus osé)',
      opt_order: 'Ordre des tours',
      order_circle: 'Chacun son tour',
      order_random: 'Au hasard',
      opt_lang: 'Langue des cartes',
      opt_bots: 'Adversaires',
      opt_mode: 'Variante',
      mode_draw: 'Avec pioche',
      mode_draw_d: 'Si tu ne peux pas jouer, tu pioches.',
      mode_block: 'Bloqué',
      mode_block_d: 'Pas de pioche : si tu ne peux pas jouer, tu passes.',
      mode_fives: 'All fives',
      mode_fives_d: 'Quand les deux bouts font un multiple de 5, tu marques ces points tout de suite.',
      opt_teams: 'Équipes',
      teams_off: 'Chacun pour soi',
      teams_on: '2 contre 2 (4 joueurs)',
      opt_goal: 'Gagner en',
      goal_points: '{n} points',
      goal_rounds: '{n} manches',
      opt_size: 'Grille',
      size_small: '3 × 3, aligner 3',
      size_big: '{n} × {n}, aligner 5',
      n_bots: '{n} bot(s)',
      cancel: 'Annuler',
      create: 'Créer la table',
      play: 'Jouer',
      room: 'Table',
      code: 'Code',
      copy_link: 'Copier le lien',
      copied: 'Lien copié',
      share: 'Partager',
      share_whatsapp: 'WhatsApp',
      show_qr: 'QR code',
      leave: 'Quitter',
      leave_confirm: 'Quitter la partie ?',
      invite_text: 'On joue aux {game} ? Code : {code}',
      waiting_room: 'Salle d\'attente',
      waiting_players: 'Envoie ce code aux autres joueurs.',
      players: 'Joueurs',
      manage_table: 'Gérer la table',
      host: 'hôte',
      bot: 'bot',
      you: 'toi',
      offline: 'absent',
      add_bot: 'Ajouter un bot',
      kick: 'Retirer',
      kick_confirm: 'Retirer {name} ?',
      replace_bot: 'Remplacer par un bot',
      start: 'Commencer',
      need_players: 'Il faut au moins {n} joueurs.',
      host_will_start: 'On attend que l\'hôte commence.',
      back_lobby: 'Retour à la salle d\'attente',
      join_this: 'Rejoindre la table',
      spectating: 'Tu regardes la partie.',
      room_closed: 'Cette table est fermée.',
      loading: 'Chargement',
      connection_lost: 'Connexion perdue, on réessaie.',
      your_turn: 'À toi',
      turn_of: 'Au tour de {name}',
      // dominoes
      boneyard: 'Pioche : {n}',
      sleeping: 'Dormants : {n}',
      ends_count: 'Bouts : {n}',
      draw: 'Piocher',
      pass: 'Passer',
      left: 'À gauche',
      right: 'À droite',
      where_play: 'De quel côté ?',
      round_n: 'Manche {n}',
      target_pts: 'en {n} points',
      target_rounds: 'en {n} manches',
      team_n: 'Équipe {n}',
      tiles_n: '{n} dominos en main',
      domino_win: '{name} a posé son dernier domino.',
      blocked_win: 'Jeu bloqué. {name} a le moins de points en main.',
      blocked_tie: 'Jeu bloqué, égalité. Personne ne marque.',
      points_won: '+{n} points',
      fives_scored: '{name} marque {n}',
      match_win: '{name} gagne la partie',
      next_round: 'Manche suivante',
      new_match: 'Rejouer',
      host_restart: 'L\'hôte peut relancer une partie.',
      pips: '{n} pts',
      log_play: '{name} pose {tile}',
      log_draw: '{name} pioche',
      log_pass: '{name} passe',
      log_round: 'Manche {n}, {name} commence',
      log_end: 'Fin de manche',
      no_move: 'Rien ne passe.',
      // truth or dare
      truth: 'Vérité',
      dare: 'Action',
      random: 'Au hasard',
      choose_td: 'Action ou vérité ?',
      waiting_choice: '{name} choisit',
      done: 'C\'est fait',
      skip: 'Passer',
      another: 'Autre carte',
      custom_card: 'écrite par le groupe',
      add_card: 'Écrire une carte',
      add_card_ph: 'Ta question ou ton défi',
      add: 'Ajouter',
      card_added: 'Carte ajoutée au paquet',
      custom_count: '{n} carte(s) du groupe',
      cards_played: '{n} carte(s) jouée(s)',
      stats: 'V {t} · A {d} · passé {s}',
      last_turn: 'Avant : {name}',
      skipped: 'a passé',
      host_can_skip: 'Tu es l\'hôte : tu peux passer son tour.',
      // local
      local_title: 'Action ou Vérité, sur un seul téléphone',
      local_hint: 'Ajoute les joueurs, puis faites tourner le téléphone.',
      add_player: 'Ajouter',
      player_ph: 'Prénom',
      need_two: 'Ajoute au moins 2 joueurs.',
      pass_phone: 'Donne le téléphone à {name}',
      // tic tac toe
      ttt_win: '{name} gagne',
      ttt_draw: 'Match nul',
      rematch: 'Revanche',
      draws: 'Nuls',
      zoom_in: 'Zoomer',
      zoom_out: 'Dézoomer',
      center_board: 'Recentrer',
      align_n: 'Aligner {n}',
      // errors
      reactions: 'Réactions',
      mute_reactions: 'Couper les réactions des autres',
      emote_gg: 'Bien joué !',
      emote_nice: 'Joli coup !',
      emote_thanks: 'Merci !',
      emote_oops: 'Oups…',
      emote_hurry: 'Allez, joue !',
      emote_again: 'On en refait une ?',
      emote_laugh_name: 'Mort de rire', emote_wow_name: 'Surpris', emote_cry_name: 'En larmes', emote_angry_name: 'Fâché',
      emote_clap_name: 'Bravo', emote_fire_name: 'En feu', emote_cool_name: 'Tranquille', emote_think_name: 'Hmm',
      err_too_fast: 'Doucement !',
      err_game_disabled: 'Ce jeu n\'est pas disponible pour le moment.',
      err_reactions_off: 'Les réactions sont désactivées.',
      g_whoofus: 'Qui de nous deux ?',
      g_whoofus_d: 'Une question, chacun répond en secret, puis on compare. À deux pour tester votre complicité, ou en groupe.',
      g_wouldrather: 'Tu préfères… ?',
      g_wouldrather_d: 'Deux choix, un seul possible. Chacun vote en secret, puis on découvre qui pense comme qui.',
      g_drawguess: 'Dessine et devine',
      g_drawguess_d: 'Chacun son tour dessine un mot, les autres devinent le plus vite possible.',
      who_two: 'Qui de nous deux',
      who_group: 'Qui de nous',
      pick_secret: 'Choisis en secret',
      waiting_for: 'On attend : {names}',
      your_answer_saved: 'Réponse enregistrée.',
      reveal_now: 'Révéler maintenant',
      skip_card: 'Passer cette question',
      next_card: 'Question suivante',
      agree: 'Vous êtes d\'accord !',
      disagree: 'Pas d\'accord…',
      group_pick: 'Le groupe a choisi {name}',
      group_tie: 'Égalité, personne ne marque.',
      complicity: 'Complicité',
      complicity_n: '{a} sur {n}',
      picked_n: '{n} fois',
      chose: 'a choisi',
      wyr_title: 'Tu préfères…',
      or: 'ou',
      no: 'Non',
      same_choice: 'Même choix !',
      diff_choice: 'Choix différents',
      group_split: '{a} contre {b}',
      agree_rate: 'Même avis',
      round_of: 'Manche {n} sur {m}',
      drawing_now: '{name} dessine',
      you_draw: 'À toi de dessiner',
      choose_word: 'Choisis le mot à dessiner',
      choosing_word: '{name} choisit un mot',
      guess_ph: 'Ta réponse',
      send: 'Envoyer',
      found_msg: '{name} a trouvé ! +{n}',
      close_msg: 'Presque ! Tu y es.',
      word_was: 'C\'était : {w}',
      why_time: 'Temps écoulé.',
      why_all: 'Tout le monde a trouvé.',
      why_skip: 'Tour passé.',
      why_left: 'Le dessinateur est parti.',
      final_scores: 'Scores finaux',
      winner_is: '{name} gagne !',
      undo: 'Annuler',
      clear: 'Effacer',
      eraser: 'Gomme',
      skip_turn: 'Passer mon tour',
      you_found: 'Trouvé ! Regarde les autres chercher.',
      letters_n: '{n} lettres',
      opt_rounds: 'Manches',
      rounds_n: '{n} manches',
      round_one: '1 manche',
      opt_time: 'Temps pour dessiner',
      secs: '{n} s',
      words_easy: 'Mots faciles',
      words_hard: 'Faciles et difficiles',
      err_no_spoilers: 'Pas de réponse dans le chat quand tu connais le mot !',
      err_not_drawer: 'Ce n\'est pas à toi de dessiner.',
      err_name_required: 'Écris ton nom d\'abord.',
      err_room_not_found: 'Aucune table avec ce code.',
      err_room_full: 'La table est pleine.',
      err_game_started: 'La partie a déjà commencé.',
      err_room_closed: 'Cette table est fermée.',
      err_not_in_room: 'Tu n\'es pas à cette table.',
      err_host_only: 'Seul l\'hôte peut faire ça.',
      err_not_your_turn: 'Ce n\'est pas ton tour.',
      err_not_enough_players: 'Pas assez de joueurs.',
      err_need_four: 'Le jeu en équipes se joue à 4 exactement.',
      err_tile_does_not_fit: 'Ce domino ne va pas là.',
      err_you_can_play: 'Tu as un domino qui passe.',
      err_cannot_pass: 'Tu ne peux pas passer.',
      err_card_too_short: 'Carte trop courte.',
      err_too_many_cards: 'Le paquet est plein.',
      err_no_cards: 'Aucune carte dans ce paquet.',
      err_bad_response: 'Le serveur a renvoyé une erreur PHP au lieu d\'une réponse du jeu.',
      run_check: 'Ouvrir la vérification (check.php)',
      err_network: 'Problème de connexion.',
      err_server_error: 'Erreur du serveur, réessaie.',
      err_generic: 'Impossible pour le moment.'
    },
    en: {
      site_title: 'Game Night',
      hero_title: 'Tonight, we play.',
      footer_text: '© {year} {site} · Games to share with friends.',
      tagline: 'Pick a game, send the code to your friends, and you\'re in.',
      back_home: 'dwens.gamer.gd',
      your_name: 'Your name',
      name_ph: 'Wens',
      join_title: 'Got a code?',
      join_hint: 'Type it here to sit at the table.',
      code_ph: 'CODE',
      join: 'Join',
      choose_game: 'Games',
      create_room: 'Open a table',
      solo_bots: 'Play solo',
      one_phone: 'One phone',
      players_n: '{min} to {max} players',
      players_plus: '{min} or more players',
      players_exact: '{n} players',
      g_dominoes: 'Dominoes',
      g_dominoes_d: 'Double-six for 2 to 4. Draw game, block game, all fives, or two against two.',
      g_truthdare: 'Truth or Dare',
      g_truthdare_d: 'Questions and dares, from tame to bold. The group can write its own cards.',
      g_tictactoe: 'Tic-Tac-Toe',
      g_tictactoe_d: 'The little 3 × 3, or a big grid up to 25 × 25 where you need five in a row.',
      options: 'Settings',
      opt_target: 'Play to',
      opt_level: 'Level',
      lvl_soft: 'Tame (everyone)',
      lvl_party: 'Party (bolder)',
      opt_order: 'Turn order',
      order_circle: 'Around the table',
      order_random: 'Random',
      opt_lang: 'Card language',
      opt_bots: 'Opponents',
      opt_mode: 'Variant',
      mode_draw: 'Draw game',
      mode_draw_d: 'Can\'t play? Draw from the boneyard.',
      mode_block: 'Block game',
      mode_block_d: 'No drawing: if you can\'t play, you pass.',
      mode_fives: 'All fives',
      mode_fives_d: 'When both ends add up to a multiple of 5, you score that right away.',
      opt_teams: 'Teams',
      teams_off: 'Every player for themselves',
      teams_on: 'Two against two (4 players)',
      opt_goal: 'Win at',
      goal_points: '{n} points',
      goal_rounds: '{n} rounds',
      opt_size: 'Grid',
      size_small: '3 × 3, three in a row',
      size_big: '{n} × {n}, five in a row',
      n_bots: '{n} bot(s)',
      cancel: 'Cancel',
      create: 'Open the table',
      play: 'Play',
      room: 'Table',
      code: 'Code',
      copy_link: 'Copy link',
      copied: 'Link copied',
      share: 'Share',
      share_whatsapp: 'WhatsApp',
      show_qr: 'QR code',
      leave: 'Leave',
      leave_confirm: 'Leave the game?',
      invite_text: 'Up for {game}? Code: {code}',
      waiting_room: 'Waiting room',
      waiting_players: 'Send this code to the other players.',
      players: 'Players',
      manage_table: 'Manage the table',
      host: 'host',
      bot: 'bot',
      you: 'you',
      offline: 'away',
      add_bot: 'Add a bot',
      kick: 'Remove',
      kick_confirm: 'Remove {name}?',
      replace_bot: 'Replace with a bot',
      start: 'Start',
      need_players: 'You need at least {n} players.',
      host_will_start: 'Waiting for the host to start.',
      back_lobby: 'Back to the waiting room',
      join_this: 'Join the table',
      spectating: 'You are watching.',
      room_closed: 'This table is closed.',
      loading: 'Loading',
      connection_lost: 'Connection lost, retrying.',
      your_turn: 'Your turn',
      turn_of: '{name}\'s turn',
      boneyard: 'Boneyard: {n}',
      sleeping: 'Sleeping: {n}',
      ends_count: 'Ends: {n}',
      draw: 'Draw',
      pass: 'Pass',
      left: 'Left end',
      right: 'Right end',
      where_play: 'Which end?',
      round_n: 'Round {n}',
      target_pts: 'to {n} points',
      target_rounds: 'first to {n} rounds',
      team_n: 'Team {n}',
      tiles_n: '{n} tiles in hand',
      domino_win: '{name} played their last tile.',
      blocked_win: 'Blocked. {name} has the fewest pips left.',
      blocked_tie: 'Blocked, and it\'s a tie. Nobody scores.',
      points_won: '+{n} points',
      fives_scored: '{name} scores {n}',
      match_win: '{name} wins the match',
      next_round: 'Next round',
      new_match: 'Play again',
      host_restart: 'The host can start a new match.',
      pips: '{n} pips',
      log_play: '{name} plays {tile}',
      log_draw: '{name} draws',
      log_pass: '{name} passes',
      log_round: 'Round {n}, {name} leads',
      log_end: 'End of round',
      no_move: 'Nothing fits.',
      truth: 'Truth',
      dare: 'Dare',
      random: 'Random',
      choose_td: 'Truth or dare?',
      waiting_choice: '{name} is choosing',
      done: 'Done',
      skip: 'Skip',
      another: 'Another card',
      custom_card: 'written by the group',
      add_card: 'Write a card',
      add_card_ph: 'Your question or dare',
      add: 'Add',
      card_added: 'Card added to the deck',
      custom_count: '{n} card(s) from the group',
      cards_played: '{n} card(s) played',
      stats: 'T {t} · D {d} · skipped {s}',
      last_turn: 'Before: {name}',
      skipped: 'skipped',
      host_can_skip: 'You are the host: you can skip their turn.',
      local_title: 'Truth or Dare on one phone',
      local_hint: 'Add the players, then pass the phone around.',
      add_player: 'Add',
      player_ph: 'First name',
      need_two: 'Add at least 2 players.',
      pass_phone: 'Hand the phone to {name}',
      ttt_win: '{name} wins',
      ttt_draw: 'It\'s a draw',
      rematch: 'Rematch',
      draws: 'Draws',
      zoom_in: 'Zoom in',
      zoom_out: 'Zoom out',
      center_board: 'Recenter',
      align_n: '{n} in a row',
      reactions: 'Reactions',
      mute_reactions: 'Mute other players\' reactions',
      emote_gg: 'Good game!',
      emote_nice: 'Nice move!',
      emote_thanks: 'Thanks!',
      emote_oops: 'Oops…',
      emote_hurry: 'Come on, play!',
      emote_again: 'One more?',
      emote_laugh_name: 'Laughing', emote_wow_name: 'Surprised', emote_cry_name: 'Crying', emote_angry_name: 'Angry',
      emote_clap_name: 'Applause', emote_fire_name: 'On fire', emote_cool_name: 'Cool', emote_think_name: 'Hmm',
      err_too_fast: 'Slow down!',
      err_game_disabled: 'This game is not available right now.',
      err_reactions_off: 'Reactions are turned off.',
      g_whoofus: 'Which of us?',
      g_whoofus_d: 'A question, everyone answers in secret, then you compare. Test how in sync you are as a couple, or play as a group.',
      g_wouldrather: 'Would you rather…?',
      g_wouldrather_d: 'Two choices, only one allowed. Everyone votes in secret, then you see who thinks alike.',
      g_drawguess: 'Draw & guess',
      g_drawguess_d: 'Take turns drawing a word while the others race to guess it.',
      who_two: 'Which of us two',
      who_group: 'Which of us',
      pick_secret: 'Pick in secret',
      waiting_for: 'Waiting for: {names}',
      your_answer_saved: 'Answer saved.',
      reveal_now: 'Reveal now',
      skip_card: 'Skip this question',
      next_card: 'Next question',
      agree: 'You agree!',
      disagree: 'You don\'t agree…',
      group_pick: 'The group picked {name}',
      group_tie: 'A tie, nobody scores.',
      complicity: 'In sync',
      complicity_n: '{a} out of {n}',
      picked_n: '{n} times',
      chose: 'picked',
      wyr_title: 'Would you rather…',
      or: 'or',
      no: 'No',
      same_choice: 'Same choice!',
      diff_choice: 'Different choices',
      group_split: '{a} against {b}',
      agree_rate: 'Same answer',
      round_of: 'Round {n} of {m}',
      drawing_now: '{name} is drawing',
      you_draw: 'Your turn to draw',
      choose_word: 'Pick the word to draw',
      choosing_word: '{name} is picking a word',
      guess_ph: 'Your guess',
      send: 'Send',
      found_msg: '{name} got it! +{n}',
      close_msg: 'So close!',
      word_was: 'It was: {w}',
      why_time: 'Time\'s up.',
      why_all: 'Everybody found it.',
      why_skip: 'Turn skipped.',
      why_left: 'The drawer left.',
      final_scores: 'Final scores',
      winner_is: '{name} wins!',
      undo: 'Undo',
      clear: 'Clear',
      eraser: 'Eraser',
      skip_turn: 'Skip my turn',
      you_found: 'Got it! Watch the others search.',
      letters_n: '{n} letters',
      opt_rounds: 'Rounds',
      rounds_n: '{n} rounds',
      round_one: '1 round',
      opt_time: 'Time to draw',
      secs: '{n} s',
      words_easy: 'Easy words',
      words_hard: 'Easy and hard',
      err_no_spoilers: 'No answers in the chat once you know the word!',
      err_not_drawer: 'It\'s not your turn to draw.',
      err_name_required: 'Write your name first.',
      err_room_not_found: 'No table with that code.',
      err_room_full: 'The table is full.',
      err_game_started: 'The game has already started.',
      err_room_closed: 'This table is closed.',
      err_not_in_room: 'You are not at this table.',
      err_host_only: 'Only the host can do that.',
      err_not_your_turn: 'It\'s not your turn.',
      err_not_enough_players: 'Not enough players.',
      err_need_four: 'Team play needs exactly 4 players.',
      err_tile_does_not_fit: 'That tile doesn\'t go there.',
      err_you_can_play: 'You have a tile that fits.',
      err_cannot_pass: 'You can\'t pass.',
      err_card_too_short: 'Card is too short.',
      err_too_many_cards: 'The deck is full.',
      err_no_cards: 'No cards in this deck.',
      err_bad_response: 'The server sent a PHP error instead of a game response.',
      run_check: 'Open the setup check (check.php)',
      err_network: 'Connection problem.',
      err_server_error: 'Server error, try again.',
      err_generic: 'You can\'t do that right now.'
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

  // Settings chosen in admin.php (printed by the page as window.GP_CONFIG).
  var CONFIG = window.GP_CONFIG || {};
  var CUSTOM = CONFIG.texts || {};
  var defaultLang = CONFIG.lang === 'fr' || CONFIG.lang === 'en' ? CONFIG.lang
    : ((navigator.language || 'fr').slice(0, 2) === 'en' ? 'en' : 'fr');
  var lang = store('gp_lang') || defaultLang;

  /** Text stored under key, only if it is a real string of that object (not e.g. Array.prototype.join). */
  function own(obj, key) {
    return obj && Object.prototype.hasOwnProperty.call(obj, key) && typeof obj[key] === 'string' ? obj[key] : '';
  }

  function t(key, vars) {
    var s = own(CUSTOM[lang], key) || own(DICT[lang], key) || own(DICT.fr, key) || key;
    if (vars) {
      Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(vars[k]); });
    }
    return s;
  }

  function applyI18n(root) {
    root = root || document;
    // {year} and {site} can be used in any text, e.g. the footer
    var common = { year: new Date().getFullYear(), site: t('site_title') };
    root.querySelectorAll('[data-i18n]').forEach(function (el) { el.textContent = t(el.getAttribute('data-i18n'), common); });
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
        var j;
        try { j = JSON.parse(txt); } catch (e) {
          // The server answered with something that is not JSON (usually a PHP error page).
          var plain = txt.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
          console.error('api.php returned non-JSON (HTTP ' + r.status + '):', txt);
          setupBanner(t('err_bad_response'), plain.slice(0, 300));
          return { ok: false, error: 'server_error', silent: true };
        }
        if (j && j.help) {
          console.error('Setup problem:', j.error, j.detail);
          setupBanner(j.help, j.detail);
          j.silent = true;
        }
        return j;
      });
    }).catch(function () { return { ok: false, error: 'network' }; });
  }

  /** A box that stays on screen explaining a server/database setup problem. */
  function setupBanner(help, detail) {
    var box = document.getElementById('setup-banner');
    if (!box) {
      box = document.createElement('div');
      box.id = 'setup-banner';
      box.className = 'setup-banner';
      document.body.insertBefore(box, document.body.firstChild.nextSibling);
    }
    box.innerHTML = '';
    box.appendChild(h('strong', null, help));
    if (detail) box.appendChild(h('div', { class: 'small' }, detail));
    box.appendChild(h('a', { href: 'check.php' }, t('run_check')));
  }

  function errText(code) {
    var k = 'err_' + code;
    var s = t(k);
    return s === k ? t('err_generic') : s;
  }

  var toastTimer;
  function toast(msg, isError) {
    if (document.getElementById('setup-banner') && isError) return; // the banner already explains it
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
    config: CONFIG,
    t: t, api: api, store: store, toast: toast, h: h, errText: errText, applyI18n: applyI18n,
    lang: function () { return lang; },
    games: {} // game UIs register themselves here
  };
})();
