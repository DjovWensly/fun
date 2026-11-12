/* Qui de nous deux ? — everyone picks a player in secret, then the answers are revealed. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  // The start of the question follows the language of the cards, so it matches the rest of the sentence.
  function prefix(g) { var l = (g.opts && g.opts.lang) || GP.lang(); return GP.tIn(l, g.order.length === 2 ? 'who_two' : 'who_group'); }
  function name(ctx, s) { var p = ctx.player(s); return p ? p.name : '?'; }

  GP.games.whoofus = GP.pollGame({
    cardClass: 'who-card',

    renderQuestion: function (g) {
      // "Qui de nous deux" + "cuisine le mieux ?"
      return h('p', { class: 'poll-question' }, [h('span', { class: 'q-prefix' }, prefix(g) + ' '), g.card.text]);
    },

    renderChoices: function (g, ctx, vote) {
      var box = h('div', { class: 'who-choices' + (g.order.length > 4 ? ' many' : '') });
      var you = ctx.you();
      if (you === null) return box;
      box.appendChild(h('p', { class: 'label center' }, t('pick_secret')));
      g.order.forEach(function (s) {
        box.appendChild(h('button', {
          class: 'btn who-btn' + (g.mine === s ? ' chosen' : ''), type: 'button',
          onclick: function () { vote(s); }
        }, s === you ? name(ctx, s) + ' (' + t('you') + ')' : name(ctx, s)));
      });
      return box;
    },

    renderReveal: function (g, ctx) {
      var box = h('div', { class: 'reveal-box' });
      var votes = g.votes || {};
      var seats = Object.keys(votes).map(Number);
      if (g.order.length === 2 && seats.length === 2) {
        box.appendChild(h('div', { class: 'verdict ' + (g.agree ? 'yes' : 'no') }, g.agree ? t('agree') : t('disagree')));
      } else {
        var counts = {};
        seats.forEach(function (s) { counts[votes[s]] = (counts[votes[s]] || 0) + 1; });
        var ranked = Object.keys(counts).sort(function (a, b) { return counts[b] - counts[a]; });
        var tie = ranked.length > 1 && counts[ranked[0]] === counts[ranked[1]];
        box.appendChild(h('div', { class: 'verdict ' + (tie ? 'no' : 'yes') },
          tie ? t('group_tie') : t('group_pick', { name: name(ctx, Number(ranked[0])) })));
      }
      var list = h('ul', { class: 'answers' });
      seats.forEach(function (s) {
        list.appendChild(h('li', { 'data-seat': s }, [
          h('strong', null, name(ctx, s)), h('span', { class: 'muted' }, ' ' + t('chose') + ' '), h('strong', null, name(ctx, votes[s]))
        ]));
      });
      box.appendChild(list);
      return box;
    },

    renderScore: function (g, ctx, done) {
      if (g.order.length === 2) return GP.pollMeter(t('complicity'), g.agreed, done);
      var box = h('div');
      box.appendChild(h('div', { class: 'label' }, t('players')));
      var list = h('ul', { class: 'td-stats' });
      g.order.slice().sort(function (a, b) { return (g.tally[b] || 0) - (g.tally[a] || 0); }).forEach(function (s) {
        list.appendChild(h('li', { 'data-seat': s }, [h('span', { class: 'pname' }, ctx.name(s)), h('span', null, t('picked_n', { n: g.tally[s] || 0 }))]));
      });
      box.appendChild(list);
      return box;
    }
  });
})();
