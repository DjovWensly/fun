/* Reactions: quick emoji and phrases players can send during a game. They pop up as a bubble
   over the sender's name (any element with data-seat="N") on every screen at the table. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  var EMOJI = [
    ['laugh', '😂'], ['wow', '😮'], ['cry', '😭'], ['angry', '😡'],
    ['clap', '👏'], ['fire', '🔥'], ['cool', '😎'], ['think', '🤔']
  ];
  var PHRASES = ['gg', 'nice', 'thanks', 'oops', 'hurry', 'again'];
  var EMOJI_MAP = {};
  EMOJI.forEach(function (e) { EMOJI_MAP[e[0]] = e[1]; });

  var opts = null;          // { send(key) -> Promise, name(seat), you() }
  var cursor = null;        // last reaction id seen (null until the first poll)
  var mine = {};            // ids of reactions I sent (already shown locally)
  var muted = GP.store('gp_emotes_muted') === '1';
  var lastSent = 0;
  var els = {};

  function label(key) { return EMOJI_MAP[key] || t('emote_' + key); }

  function build() {
    els.button = h('button', { class: 'emote-fab', type: 'button', 'aria-label': t('reactions'), 'aria-expanded': 'false' },
      [bubbleIcon()]);
    els.panel = h('div', { class: 'emote-panel', hidden: true, role: 'dialog', 'aria-label': t('reactions') });
    els.layer = h('div', { class: 'emote-layer', 'aria-live': 'polite' });
    document.body.appendChild(els.layer);
    document.body.appendChild(els.panel);
    document.body.appendChild(els.button);

    els.button.addEventListener('click', function () { toggle(els.panel.hidden); });
    document.addEventListener('click', function (e) {
      if (!els.panel.hidden && !els.panel.contains(e.target) && !els.button.contains(e.target)) toggle(false);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });
    document.addEventListener('gp:lang', fillPanel);
    fillPanel();
  }

  function bubbleIcon() {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 32 32'); svg.setAttribute('width', '28'); svg.setAttribute('height', '28');
    svg.setAttribute('aria-hidden', 'true');
    var p = document.createElementNS(ns, 'path');
    p.setAttribute('d', 'M6 6h20a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H15l-6 5v-5H6a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3z');
    p.setAttribute('fill', '#fffaf0'); p.setAttribute('stroke', '#1c1915'); p.setAttribute('stroke-width', '2.2');
    p.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(p);
    [11, 16, 21].forEach(function (x) {
      var c = document.createElementNS(ns, 'circle');
      c.setAttribute('cx', x); c.setAttribute('cy', '14.5'); c.setAttribute('r', '1.9'); c.setAttribute('fill', '#1c1915');
      svg.appendChild(c);
    });
    return svg;
  }

  function fillPanel() {
    var p = els.panel;
    p.innerHTML = '';
    var grid = h('div', { class: 'emote-grid' });
    EMOJI.forEach(function (e) {
      grid.appendChild(h('button', { type: 'button', class: 'emote-btn', 'aria-label': t('emote_' + e[0] + '_name'), onclick: function () { send(e[0]); } }, e[1]));
    });
    var phrases = h('div', { class: 'emote-phrases' });
    PHRASES.forEach(function (k) {
      phrases.appendChild(h('button', { type: 'button', class: 'emote-phrase', onclick: function () { send(k); } }, t('emote_' + k)));
    });
    var mute = h('label', { class: 'emote-mute' }, [
      h('input', { type: 'checkbox', checked: muted, onchange: function (e) { muted = e.target.checked; GP.store('gp_emotes_muted', muted ? '1' : '0'); } }),
      h('span', null, t('mute_reactions'))
    ]);
    p.appendChild(grid);
    p.appendChild(phrases);
    p.appendChild(mute);
  }

  function toggle(open) {
    els.panel.hidden = !open;
    els.button.setAttribute('aria-expanded', open ? 'true' : 'false');
    els.button.classList.toggle('open', open);
  }

  function send(key) {
    var now = Date.now();
    if (now - lastSent < 1100) return; // the server accepts about one per second
    lastSent = now;
    toggle(false);
    var you = opts.you();
    show(you, key);
    opts.send(key).then(function (r) { if (r && r.ok) mine[r.id] = true; });
  }

  /** Find where to draw the bubble: the sender's name on screen, if it is visible. */
  function anchorFor(seat) {
    var nodes = document.querySelectorAll('[data-seat="' + seat + '"]');
    for (var i = 0; i < nodes.length; i++) {
      var r = nodes[i].getBoundingClientRect();
      if (r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < window.innerHeight) return r;
    }
    return null;
  }

  function show(seat, key) {
    if (seat === null || seat === undefined) return;
    var isEmoji = !!EMOJI_MAP[key];
    var anchor = anchorFor(seat);
    var bubble = h('div', { class: 'emote-bubble' + (isEmoji ? ' is-emoji' : ' is-text') + (anchor ? '' : ' floating') }, [
      h('span', { class: 'emote-content' }, label(key)),
      anchor ? null : h('span', { class: 'emote-who' }, opts.name(seat))
    ]);
    els.layer.appendChild(bubble);
    if (anchor) {
      var x = anchor.left + anchor.width / 2 + window.scrollX;
      var y = anchor.top + window.scrollY;
      var w = bubble.offsetWidth;
      x = Math.max(8 + w / 2 + window.scrollX, Math.min(x, window.scrollX + document.documentElement.clientWidth - 8 - w / 2));
      bubble.style.left = x + 'px';
      bubble.style.top = y + 'px';
    } else {
      // stack floating bubbles at the top of the screen
      var others = els.layer.querySelectorAll('.emote-bubble.floating').length - 1;
      bubble.style.top = (window.scrollY + 70 + others * 64) + 'px';
    }
    setTimeout(function () { bubble.classList.add('out'); }, 2600);
    setTimeout(function () { if (bubble.parentNode) bubble.parentNode.removeChild(bubble); }, 3100);
  }

  /** Called by room.js with each poll answer. */
  function receive(r) {
    if (r.emoteCursor === undefined) return;
    var first = cursor === null;
    cursor = Math.max(cursor || 0, r.emoteCursor);
    if (first || !r.emotes) return;
    r.emotes.forEach(function (e) {
      if (mine[e.id]) { delete mine[e.id]; return; }
      if (e.seat === opts.you()) return; // sent from another tab of mine
      if (!muted) show(e.seat, e.emote);
    });
  }

  function setVisible(v) {
    if (!els.button) return;
    els.button.hidden = !v;
    if (!v) toggle(false);
  }

  GP.emotes = {
    init: function (o) { opts = o; build(); setVisible(false); },
    cursor: function () { return cursor; },
    receive: receive,
    setVisible: setVisible
  };
})();
