/* Sounds, made on the fly with the Web Audio API (no audio files to upload).
   Phones only allow sound after the first tap on the page, so nothing plays before that.
   The player can switch sound and vibration off in the Appearance panel (stored on the device). */
(function () {
  'use strict';
  var ac = null;
  var on = GP.store('gp_sound') !== 'off';
  var vib = GP.store('gp_vibrate') !== 'off';

  function unlock() {
    if (ac) { if (ac.state === 'suspended') ac.resume(); return; }
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    try { ac = new AC(); } catch (e) { ac = null; }
  }
  ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
    document.addEventListener(ev, unlock, { passive: true });
  });

  /** One tone: frequency (Hz, or [from, to] for a slide), start offset, duration, wave, volume. */
  function tone(freq, at, dur, type, vol) {
    var t0 = ac.currentTime + (at || 0);
    var o = ac.createOscillator(), g = ac.createGain();
    o.type = type || 'sine';
    if (Array.isArray(freq)) { o.frequency.setValueAtTime(freq[0], t0); o.frequency.exponentialRampToValueAtTime(freq[1], t0 + dur); }
    else o.frequency.setValueAtTime(freq, t0);
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(vol || 0.2, t0 + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    o.connect(g); g.connect(ac.destination);
    o.start(t0); o.stop(t0 + dur + 0.02);
  }

  /** A short burst of filtered noise: the "clac" of a tile on the table. */
  function knock(at, freq, vol) {
    var t0 = ac.currentTime + (at || 0);
    var len = Math.floor(ac.sampleRate * 0.06);
    var buf = ac.createBuffer(1, len, ac.sampleRate);
    var d = buf.getChannelData(0);
    for (var i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 4);
    var src = ac.createBufferSource(); src.buffer = buf;
    var f = ac.createBiquadFilter(); f.type = 'bandpass'; f.frequency.value = freq || 1800; f.Q.value = 1.2;
    var g = ac.createGain(); g.gain.value = vol || 0.9;
    src.connect(f); f.connect(g); g.connect(ac.destination);
    src.start(t0);
  }

  var SOUNDS = {
    clack: function () { knock(0, 1900, 1); tone([180, 90], 0, 0.07, 'sine', 0.25); },
    turn: function () { tone(660, 0, 0.18, 'sine', 0.18); tone(880, 0.12, 0.25, 'sine', 0.18); },
    pop: function () { tone([900, 500], 0, 0.09, 'sine', 0.15); },
    win: function () { [523, 659, 784, 1047].forEach(function (f, i) { tone(f, i * 0.11, 0.3, 'triangle', 0.18); }); },
    end: function () { tone(523, 0, 0.2, 'triangle', 0.14); tone(392, 0.15, 0.35, 'triangle', 0.14); },
    ding: function () { tone(1320, 0, 0.25, 'sine', 0.16); tone(1760, 0.08, 0.3, 'sine', 0.1); },
    tick: function () { knock(0, 3200, 0.35); },
    wrong: function () { tone([220, 160], 0, 0.16, 'square', 0.06); }
  };

  GP.sound = {
    play: function (name) {
      if (!on || !ac || !SOUNDS[name]) return;
      if (ac.state === 'suspended') ac.resume();
      try { SOUNDS[name](); } catch (e) { /* never break the game for a sound */ }
    },
    vibrate: function (ms) {
      if (vib && navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) { } }
    },
    enabled: function () { return on; },
    set: function (v) { on = !!v; GP.store('gp_sound', on ? 'on' : 'off'); if (on) { unlock(); GP.sound.play('pop'); } },
    vibration: function () { return vib; },
    setVibration: function (v) { vib = !!v; GP.store('gp_vibrate', vib ? 'on' : 'off'); if (vib) GP.sound.vibrate(60); }
  };
})();
