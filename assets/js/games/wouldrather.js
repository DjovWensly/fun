/* Tu préfères… ? — two choices, everyone votes in secret, then we see who picked what. */
(function () {
  'use strict';
  var h = GP.h, t = GP.t;

  function name(ctx, s) { var p = ctx.player(s); return p ? p.name : '?'; }
  function optB(g) { return g.card.b !== null && g.card.b !== undefined ? g.card.b : t('no'); }

  GP.games.wouldrather = GP.pollGame({
    cardClass: 'wyr-card',

    renderQuestion: function () {
      return h('p', { class: 'poll-question small-q' }, t('wyr_title'));
    },

    renderChoices: function (g, ctx, vote) {
      var you = ctx.you();
      var make = function (key, text) {
        return h('button', {
          type: 'button', class: 'wyr-opt opt-' + key + (g.mine === key ? ' chosen' : ''), disabled: you === null,
          onclick: function () { vote(key); }
        }, text);
      };
      return h('div', { class: 'wyr-choices' }, [make('a', g.card.a), h('span', { class: 'wyr-or' }, t('or')), make('b', optB(g))]);
    },

    renderReveal: function (g, ctx) {
      var votes = g.votes || {};
      var seats = Object.keys(votes).map(Number);
      var side = { a: [], b: [] };
      seats.forEach(function (s) { side[votes[s]].push(s); });
      var box = h('div', { class: 'reveal-box' });
      var verdict;
      if (seats.length === 2) verdict = h('div', { class: 'verdict ' + (g.agree ? 'yes' : 'no') }, g.agree ? t('same_choice') : t('diff_choice'));
      else verdict = h('div', { class: 'verdict ' + (g.agree ? 'yes' : 'no') }, g.agree ? t('same_choice') : t('group_split', { a: side.a.length, b: side.b.length }));
      box.appendChild(verdict);
      var cols = h('div', { class: 'wyr-results' });
      [['a', g.card.a], ['b', optB(g)]].forEach(function (o) {
        var n = side[o[0]].length;
        var pct = seats.length ? Math.round(100 * n / seats.length) : 0;
        cols.appendChild(h('div', { class: 'wyr-col opt-' + o[0] }, [
          h('div', { class: 'wyr-col-text' }, o[1]),
          h('div', { class: 'wyr-pct' }, pct + ' %'),
          h('div', { class: 'wyr-who' }, side[o[0]].map(function (s) { return h('span', { 'data-seat': s, class: 'tag' }, name(ctx, s)); }))
        ]));
      });
      box.appendChild(cols);
      return box;
    },

    renderScore: function (g, ctx, done) {
      return GP.pollMeter(t('agree_rate'), g.agreed, done);
    }
  });
})();
