/* Action ou Vérité · à distance (duo): thermometer, Bonus/Trap wheel, own dares, chat. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  var WHEEL = ['truth', 'dare', 'double', 'mirror', 'reverse', 'secret', 'guess', 'gift'];
  var WHEEL_COLORS = ['#1f4592', '#c4381d', '#e3a21a', '#2f7d4f', '#7a3b8f', '#1c1915', '#d9668a', '#5d8fc9'];
  var IDEAS = {
    fr: [
      ['Envoie-moi un vocal où tu dis mon prénom de ta voix la plus douce.', 'Montre-moi ta pièce en vidéo, en commentant comme un agent immobilier.', 'Envoie la photo la plus drôle de ta galerie.', 'Écris-moi un poème de 4 lignes sur mes yeux.'],
      ['Envoie un selfie avec ton regard le plus séducteur.', 'Murmure-moi un secret en vocal.', 'Change de haut et montre-moi le nouveau.', 'Décris ce que tu ferais si j’étais à côté de toi, en 3 phrases.'],
      ['Retire un vêtement de ton choix devant la caméra.', 'Envoie un vocal de 15 secondes à bout de souffle, et dis-moi pourquoi.', 'Montre-moi où tu voudrais que je t’embrasse.', 'Écris le message le plus chaud que tu oses, sans un mot vulgaire.']
    ],
    en: [
      ['Send me a voice note saying my name in your softest voice.', 'Give me a video tour of your room like an estate agent.', 'Send the funniest photo in your gallery.', 'Write me a 4-line poem about my eyes.'],
      ['Send a selfie with your most seductive look.', 'Whisper me a secret in a voice note.', 'Change your top and show me the new one.', 'Describe what you’d do if I were next to you, in 3 sentences.'],
      ['Take off a piece of clothing of your choice on camera.', 'Send a breathless 15-second voice note, and tell me why.', 'Show me where you’d like me to kiss you.', 'Write the hottest message you dare, without a vulgar word.']
    ]
  };

  var ctx = null, els = {}, busy = false, lastSig = '', lastChat = -1, wheelSeen = null, spinUntil = 0, clockOffset = 0, g = null, timerIv = null;

  function send(move) {
    if (busy) return Promise.resolve({ ok: false });
    busy = true;
    return ctx.send(move).then(function (r) { busy = false; return r; });
  }
  function other(gs, seat) { return gs.order[0] === seat ? gs.order[1] : gs.order[0]; }
  function heatName(n) { return t('duo_heat_' + n); }
  function btn(label, cls, fn) { return h('button', { type: 'button', class: 'btn ' + (cls || ''), onclick: fn }, label); }

  function mount(root, c) {
    ctx = c;
    root.innerHTML = '';

    els.thermo = h('div', { class: 'duo-thermo', role: 'img' });
    els.heatLabel = h('strong', { class: 'duo-heat-label' });
    els.down = btn(t('duo_softer'), 'small', function () { send({ type: 'heat_down' }); });
    els.up = btn(t('duo_hotter'), 'small duo-hot', function () { send({ type: 'heat_up' }); });
    els.heatNote = h('p', { class: 'duo-heat-note small' });
    els.scores = h('div', { class: 'duo-scores' });
    els.stage = h('div', { class: 'duo-stage' });
    els.safety = h('p', { class: 'duo-safety small' }, t('duo_safety'));

    // Writing a dare for the partner: outside the re-rendered stage, so polling never erases typing.
    els.chText = h('textarea', { rows: 2, maxlength: 200 });
    els.challenge = h('form', { class: 'panel duo-challenge' }, [
      h('h3', null, t('duo_challenge_title')),
      els.chText,
      h('div', { class: 'row wrap-row' }, [
        btn(t('duo_suggest'), 'ghost', function () {
          var lang = (g && g.opts.lang) === 'en' ? 'en' : 'fr';
          var list = IDEAS[lang][(g ? g.heat : 1) - 1];
          els.chText.value = list[Math.floor(Math.random() * list.length)];
        }),
        h('button', { class: 'btn primary', type: 'submit' }, t('duo_send'))
      ])
    ]);
    els.challenge.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = els.chText.value.trim();
      if (!v) return;
      send({ type: 'challenge', text: v }).then(function (r) { if (r.ok) els.chText.value = ''; });
    });

    // Chat
    els.chatList = h('ol', { class: 'duo-chat-list' });
    els.chatInput = h('input', { maxlength: 240, placeholder: t('duo_chat_ph') });
    var chatForm = h('form', { class: 'row duo-chat-form' }, [els.chatInput, h('button', { class: 'btn', type: 'submit' }, t('duo_send'))]);
    chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = els.chatInput.value.trim();
      if (!v) return;
      els.chatInput.value = '';
      ctx.send({ type: 'say', text: v });
    });

    // Secret settings
    els.limit = h('div', { class: 'choices', role: 'radiogroup' });
    [1, 2, 3].forEach(function (n) {
      var input = h('input', { type: 'radio', name: 'duo-limit', value: String(n) });
      input.addEventListener('change', function () { send({ type: 'limit', value: n }); });
      els.limit.appendChild(h('label', null, [input, h('span', null, heatName(n))]));
    });
    els.limitHelp = h('p', { class: 'muted small' });
    els.adult = h('input', { type: 'checkbox' });
    els.adult.addEventListener('change', function () { send({ type: 'adult', value: els.adult.checked }); });
    els.buy = btn('', 'small', function () { send({ type: 'buy_joker' }); });
    els.settings = h('details', { class: 'panel duo-settings' }, [
      h('summary', null, t('duo_settings')),
      h('div', { class: 'field' }, [h('span', null, t('duo_limit')), els.limit]),
      els.limitHelp,
      h('label', { class: 'duo-check' }, [els.adult, h('span', null, t('duo_adult'))]),
      h('p', { class: 'muted small' }, t('duo_adult_help')),
      els.buy
    ]);

    root.appendChild(h('div', { class: 'duo' }, [
      h('div', { class: 'panel duo-top' }, [
        h('div', { class: 'duo-heat' }, [els.thermo, els.heatLabel, h('div', { class: 'row duo-heat-btns' }, [els.down, els.up])]),
        els.heatNote,
        els.scores
      ]),
      h('div', { class: 'duo-main' }, [
        h('div', null, [els.stage, els.challenge, els.safety]),
        h('div', { class: 'duo-side' }, [
          h('div', { class: 'panel duo-chat' }, [h('h3', null, t('duo_chat')), els.chatList, chatForm]),
          els.settings,
          h('p', { class: 'muted small' }, t('duo_tip_call'))
        ])
      ])
    ]));

    if (!timerIv) timerIv = setInterval(tickClock, 500);
  }

  function tickClock() {
    var el = document.getElementById('duo-clock');
    if (!el || !g || !g.endsAt) return;
    var left = Math.max(0, g.endsAt - Math.round(Date.now() / 1000 + clockOffset));
    el.textContent = Math.floor(left / 60) + ':' + ('0' + (left % 60)).slice(-2);
    el.classList.toggle('low', left <= 15);
  }

  // ---------- the wheel ----------
  function wheelEl() {
    var seg = 360 / WHEEL.length;
    var stops = WHEEL.map(function (k, i) { return WHEEL_COLORS[i] + ' ' + (i * seg) + 'deg ' + ((i + 1) * seg) + 'deg'; }).join(',');
    var disc = h('div', { class: 'duo-wheel-disc', style: 'background: conic-gradient(' + stops + ')' });
    WHEEL.forEach(function (k, i) {
      disc.appendChild(h('span', { class: 'duo-wheel-lbl', style: 'transform: rotate(' + (i * seg + seg / 2) + 'deg) translateY(calc(var(--r) * -0.64))' }, t('duo_w_' + k)));
    });
    return { box: h('div', { class: 'duo-wheel' }, [h('div', { class: 'duo-wheel-pin' }), disc]), disc: disc, seg: seg };
  }

  function spin(result) {
    var w = wheelEl();
    els.stage.innerHTML = '';
    els.stage.appendChild(h('div', { class: 'duo-card spin' }, [w.box, h('p', { class: 'duo-wheel-res' }, '')]));
    var i = WHEEL.indexOf(result);
    // the pin is at the top: bring the middle of segment i under it
    var target = 360 * 5 + (360 - (i * w.seg + w.seg / 2)) + (Math.random() * 0.5 - 0.25) * w.seg;
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { w.disc.style.transform = 'rotate(' + target + 'deg)'; });
    });
    if (GP.sound) GP.sound.play('pop');
    setTimeout(function () {
      var res = els.stage.querySelector('.duo-wheel-res');
      if (res) res.textContent = t('duo_w_' + result);
    }, 2300);
    spinUntil = Date.now() + 3200;
    setTimeout(function () { lastSig = ''; if (g) render(g); }, 3250);
  }

  // ---------- stage ----------
  function wait(text) { return h('p', { class: 'duo-wait' }, text); }

  function stage(gs) {
    var you = ctx.you(), card = gs.card, isActor = gs.turn === you, partner = other(gs, you), pName = ctx.name(partner);
    var box = h('div', { class: 'duo-card ' + (card ? card.kind : 'choose') });
    var actions = h('div', { class: 'row wrap-row center-row duo-actions' });
    var myJokers = gs.jokers[you] || 0;
    var jokerBtn = function () { return btn(t('duo_joker', { n: myJokers }), 'ghost', function () { send({ type: 'joker' }); }); };
    var pauseBtn = btn(t('duo_pause'), 'ghost duo-pause', function () { send({ type: 'pause' }); });
    pauseBtn.title = t('duo_pause_help');

    box.appendChild(h('div', { class: 'duo-who' }, isActor ? t('duo_your_turn') : t('duo_their_turn', { name: ctx.name(gs.turn) })));

    if (gs.phase === 'choose') {
      if (isActor) {
        box.appendChild(h('h2', null, t('duo_choose')));
        actions.appendChild(btn(t('truth'), 'td-truth', function () { send({ type: 'choose', kind: 'truth' }); }));
        actions.appendChild(btn(t('dare'), 'td-dare', function () { send({ type: 'choose', kind: 'dare' }); }));
        actions.appendChild(btn(t('duo_wheel'), 'duo-wheel-btn', function () { send({ type: 'choose', kind: 'wheel' }); }));
      } else {
        box.appendChild(wait(t('duo_their_turn', { name: pName }) + '…'));
      }
      box.appendChild(actions);
      return box;
    }

    if (gs.phase === 'offer' || gs.phase === 'counter') {
      var o = gs.offer;
      if (gs.phase === 'offer' && isActor) {
        box.appendChild(h('div', { class: 'duo-label' }, t('duo_offer_title', { name: pName })));
        box.appendChild(h('p', { class: 'duo-text' }, o.text));
        box.appendChild(h('p', { class: 'duo-mods' }, [h('span', null, t('duo_mod_bonus'))]));
        actions.appendChild(btn(t('duo_accept'), 'primary', function () { send({ type: 'offer_accept' }); }));
        if (myJokers > 0) actions.appendChild(jokerBtn());
        box.appendChild(actions);
        var ct = h('textarea', { rows: 2, maxlength: 200, placeholder: t('duo_counter_ph') });
        var f = h('form', { class: 'duo-counter' }, [ct, h('button', { class: 'btn', type: 'submit' }, t('duo_counter'))]);
        f.addEventListener('submit', function (e) { e.preventDefault(); if (ct.value.trim()) send({ type: 'offer_counter', text: ct.value.trim() }); });
        box.appendChild(f);
      } else if (gs.phase === 'offer') {
        box.appendChild(h('p', { class: 'duo-text muted' }, o.text));
        box.appendChild(wait(t('duo_offer_wait', { name: pName })));
      } else if (!isActor) {
        box.appendChild(h('div', { class: 'duo-label' }, t('duo_counter_title', { name: pName })));
        box.appendChild(h('p', { class: 'duo-text' }, o.counter));
        box.appendChild(h('p', { class: 'muted small' }, '« ' + o.text + ' »'));
        actions.appendChild(btn(t('duo_counter_ok'), 'primary', function () { send({ type: 'counter_reply', ok: true }); }));
        actions.appendChild(btn(t('duo_counter_no'), '', function () { send({ type: 'counter_reply', ok: false }); }));
        box.appendChild(actions);
      } else {
        box.appendChild(h('p', { class: 'duo-text muted' }, o.counter));
        box.appendChild(wait(t('duo_counter_wait', { name: pName })));
        if (myJokers > 0) { actions.appendChild(jokerBtn()); box.appendChild(actions); }
      }
      box.appendChild(h('div', { class: 'row center-row' }, [pauseBtn]));
      return box;
    }

    // a card is on the table
    var doers = card.doers, isDoer = doers.indexOf(you) >= 0, mods = {};
    // copy only real flags: an empty list from PHP must not expose Array.prototype.reverse etc.
    ['double', 'mirror', 'reverse', 'secret', 'bonus'].forEach(function (k) {
      if (card.mods && Object.prototype.hasOwnProperty.call(card.mods, k) && card.mods[k] === true) mods[k] = true;
    });
    var kindLabel = card.kind === 'truth' ? t('truth') : card.kind === 'dare' ? t('dare') : t('duo_guess_title');
    box.appendChild(h('span', { class: 'td-corner', 'aria-hidden': 'true' }, kindLabel));
    box.appendChild(h('div', { class: 'duo-label duo-lvl' }, heatName(card.heat)));
    var tags = [];
    if (mods.double) tags.push(t('duo_mod_double'));
    if (mods.mirror) tags.push(t('duo_mod_mirror'));
    if (mods.reverse) tags.push(t('duo_mod_reverse', { name: ctx.name(doers[0]) }));
    if (mods.secret) tags.push(t('duo_mod_secret'));
    if (mods.bonus) tags.push(t('duo_mod_bonus'));
    if (tags.length) box.appendChild(h('p', { class: 'duo-mods' }, tags.map(function (x) { return h('span', null, x); })));
    if (card.text !== null) box.appendChild(h('p', { class: 'duo-text' }, card.text));
    else box.appendChild(h('p', { class: 'duo-text duo-hidden' }, '? ? ?'));
    if (gs.endsAt && gs.phase === 'card') box.appendChild(h('div', { class: 'duo-clock', id: 'duo-clock' }));

    var doneMap = (card.done && !Array.isArray(card.done)) ? card.done : {};
    var doerName = ctx.name(doers[0]);

    if (gs.phase === 'card' && card.kind === 'guess') {
      var mine = isDoer ? 'answer' : 'guess', given = card[mine] !== null && card[mine] !== undefined;
      if (!given) {
        box.appendChild(h('p', { class: 'duo-label' }, isDoer ? t('duo_guess_doer') : t('duo_guess_other', { name: doerName })));
        var gi = h('input', { maxlength: 200, placeholder: t('duo_guess_ph') });
        var gf = h('form', { class: 'row duo-input' }, [gi, h('button', { class: 'btn primary', type: 'submit' }, t('duo_send'))]);
        gf.addEventListener('submit', function (e) { e.preventDefault(); if (gi.value.trim()) send({ type: 'answer', text: gi.value.trim() }); });
        box.appendChild(gf);
      } else {
        box.appendChild(wait(t('duo_guess_sent', { name: pName })));
      }
    } else if (gs.phase === 'card') {
      if (!isDoer) {
        box.appendChild(wait(mods.secret ? t('duo_secret_hidden', { name: doerName }) : t('duo_doing', { name: doerName })));
      } else if (doneMap[you] !== undefined) {
        box.appendChild(wait(t('duo_mirror_wait', { name: pName })));
      } else {
        if (mods.secret) box.appendChild(h('p', { class: 'muted small' }, t('duo_secret_mine', { name: pName })));
        var note = h('input', { maxlength: 240, placeholder: t('duo_proof_ph') });
        box.appendChild(h('div', { class: 'duo-input' }, [note]));
        actions.appendChild(btn(t('duo_done'), 'primary', function () { send({ type: 'done', note: note.value.trim() }); }));
        if (myJokers > 0) actions.appendChild(jokerBtn());
        box.appendChild(actions);
        if (card.kind === 'dare') box.appendChild(h('p', { class: 'muted small' }, t('duo_proof_tip')));
      }
    } else if (gs.phase === 'judge') {
      var proof = doneMap[doers[0]];
      if (!isDoer) {
        box.appendChild(h('p', { class: 'duo-label' }, t('duo_judge_q', { name: doerName })));
        if (proof) box.appendChild(h('blockquote', { class: 'duo-proof' }, proof));
        actions.appendChild(btn(t('duo_ok'), 'primary', function () { send({ type: 'judge', ok: true }); }));
        actions.appendChild(btn(t('duo_no'), '', function () { send({ type: 'judge', ok: false }); }));
        box.appendChild(actions);
      } else {
        box.appendChild(wait(t('duo_wait_judge', { name: pName })));
      }
    } else if (gs.phase === 'secret_guess') {
      if (!isDoer) {
        box.appendChild(h('p', { class: 'duo-label' }, t('duo_secret_guess_title', { name: doerName })));
        var si = h('input', { maxlength: 200, placeholder: t('duo_guess_ph') });
        var sf = h('form', { class: 'row duo-input' }, [si, h('button', { class: 'btn primary', type: 'submit' }, t('duo_send'))]);
        sf.addEventListener('submit', function (e) { e.preventDefault(); if (si.value.trim()) send({ type: 'secret_guess', text: si.value.trim() }); });
        box.appendChild(sf);
      } else {
        box.appendChild(wait(t('duo_secret_wait', { name: pName })));
      }
    } else if (gs.phase === 'secret_check') {
      if (isDoer) {
        box.appendChild(h('p', { class: 'duo-label' }, t('duo_secret_check', { name: pName, g: card.guess })));
        actions.appendChild(btn(t('duo_yes'), 'primary', function () { send({ type: 'secret_check', ok: true }); }));
        actions.appendChild(btn(t('duo_nope'), '', function () { send({ type: 'secret_check', ok: false }); }));
        box.appendChild(actions);
      } else {
        box.appendChild(wait(t('duo_secret_check_wait', { name: pName })));
      }
    } else if (gs.phase === 'guess_check') {
      if (isDoer) {
        box.appendChild(h('p', { class: 'duo-label' }, t('duo_guess_check', { a: card.answer, name: pName, g: card.guess })));
        actions.appendChild(btn(t('duo_yes'), 'primary', function () { send({ type: 'guess_check', ok: true }); }));
        actions.appendChild(btn(t('duo_nope'), '', function () { send({ type: 'guess_check', ok: false }); }));
        box.appendChild(actions);
      } else {
        box.appendChild(wait(t('duo_guess_check_wait', { g: card.guess, name: pName })));
      }
    }
    box.appendChild(h('div', { class: 'row center-row' }, [pauseBtn]));
    return box;
  }

  function sysText(m) {
    var v = m.vars || {}, vars = {};
    Object.keys(v).forEach(function (k) { vars[k] = v[k]; });
    if (v.seat !== undefined) vars.name = ctx.name(v.seat);
    if (m.key === 'duo_heat_up' || m.key === 'duo_heat_down') vars.heat = heatName(v.n);
    return t(m.key, vars);
  }

  function renderChat(gs) {
    var you = ctx.you();
    var key = gs.chat.length + ':' + JSON.stringify(gs.chat[gs.chat.length - 1] || '');
    if (key === els.chatKey) return;
    var fresh = els.chatKey !== undefined;
    els.chatKey = key;
    els.chatList.innerHTML = '';
    gs.chat.forEach(function (m) {
      if (m.k === 'sys') { els.chatList.appendChild(h('li', { class: 'sys' }, sysText(m))); return; }
      els.chatList.appendChild(h('li', { class: (m.s === you ? 'me ' : 'them ') + m.k }, [
        h('b', null, m.s === you ? t('duo_me') : ctx.name(m.s)), ' ', m.k === 'proof' ? h('em', null, m.t) : m.t
      ]));
    });
    els.chatList.scrollTop = els.chatList.scrollHeight;
    var last = gs.chat[gs.chat.length - 1];
    if (fresh && last && last.s !== you && GP.sound) GP.sound.play('pop');
  }

  function render(gs) {
    var you = ctx.you();
    if (you === null) {
      els.stage.innerHTML = '';
      els.stage.appendChild(h('div', { class: 'duo-card' }, [wait(t('duo_their_turn', { name: ctx.name(gs.turn) }))]));
      els.challenge.hidden = true;
      return;
    }
    var partner = other(gs, you), pName = ctx.name(partner);

    // thermometer
    els.thermo.innerHTML = '';
    [1, 2, 3].forEach(function (n) { els.thermo.appendChild(h('span', { class: 'lvl l' + n + (n <= gs.heat ? ' on' : '') }, heatName(n))); });
    els.thermo.setAttribute('aria-label', heatName(gs.heat));
    els.heatLabel.textContent = heatName(gs.heat);
    els.up.disabled = gs.heat >= 3 || gs.iWantUp;
    els.down.disabled = gs.heat <= 1;
    els.heatNote.textContent = gs.iWantUp ? t('duo_wait_up', { name: pName }) : (gs.partnerWantsUp ? t('duo_partner_up', { name: pName }) : '');
    els.heatNote.classList.toggle('hot', !!gs.partnerWantsUp);
    els.safety.hidden = gs.heat < 3;
    document.querySelector('.duo').setAttribute('data-heat', gs.heat);

    // scores
    els.scores.innerHTML = '';
    gs.order.forEach(function (s) {
      els.scores.appendChild(h('div', { class: 'duo-score' + (s === gs.turn ? ' turn' : '') }, [
        h('span', { class: 'pname' }, s === you ? t('duo_me') : ctx.name(s)),
        h('strong', null, t('duo_score', { n: gs.points[s] || 0 })),
        h('span', { class: 'muted small' }, t('duo_jokers_n', { n: gs.jokers[s] || 0 }))
      ]));
    });

    // settings (only your own values are sent by the server)
    els.limit.querySelectorAll('input').forEach(function (i) { i.checked = Number(i.value) === gs.myLimit; });
    els.limitHelp.textContent = t('duo_limit_help', { name: pName });
    els.adult.checked = !!gs.myAdult;
    els.buy.textContent = t('duo_buy', { n: gs.buyJoker });
    els.buy.disabled = (gs.points[you] || 0) < gs.buyJoker;

    els.challenge.hidden = !(gs.phase === 'choose' && gs.turn !== you);
    els.chText.placeholder = t('duo_challenge_ph', { name: pName });

    renderChat(gs);

    // a new spin of the wheel: animate first, the card shows afterwards
    if (gs.wheel && wheelSeen !== null && gs.wheel.n !== wheelSeen) {
      wheelSeen = gs.wheel.n;
      spin(gs.wheel.result);
      return;
    }
    if (gs.wheel) wheelSeen = gs.wheel.n; else if (wheelSeen === null) wheelSeen = 0;
    if (Date.now() < spinUntil) return;

    var c = gs.card;
    var sig = JSON.stringify([gs.phase, gs.round, gs.turn, gs.offer, c && [c.text, c.done, c.answer, c.guess], gs.endsAt, gs.jokers[you], ctx.name(partner)]);
    if (sig === lastSig) return;
    lastSig = sig;
    els.stage.innerHTML = '';
    els.stage.appendChild(stage(gs));
    tickClock();
  }

  function update(gs, c) {
    ctx = c;
    g = gs;
    if (gs.now) clockOffset = gs.now - Date.now() / 1000;
    render(gs);
  }

  GP.games.duo = { mount: mount, update: update, fastPoll: function () { return true; } };
  document.addEventListener('gp:lang', function () { lastSig = ''; els.chatKey = undefined; });
})();
