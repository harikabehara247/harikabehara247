/* =====================================================================
 * H View Signature engine (r89, 26 Nov 2026)
 * Photo-real art (OpenAI) + live animation and gestures (this file).
 * Designs: goldengates · curtainpalace · templebells (wedding),
 *          grihapravesh (housewarming), kids3dboy · kids3dgirl · rooftop (birthday)
 * Data: window.HV from the URL hash (to, from, msg, age, date, time, venue,
 * address, dress, events, rsvp, photos, autoplay, muted, design).
 * Events out: {hv:'step',name} {hv:'rsvp',data} {hv:'done'}; in: {hv:'rsvp-ok',message}
 * ===================================================================== */
(function () {
'use strict';
var HV = {}; try { HV = JSON.parse(decodeURIComponent(location.hash.slice(1))) || {}; } catch (e) {}
if (window.HV_DATA) HV = Object.assign({}, window.HV_DATA, HV);
var KEY = String(HV.design || window.SIG_DESIGN || 'goldengates');
var ART = (window.SIG_ART || './art/');
var AUTO = HV.autoplay === true, REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;
var POST = function (m) { try { parent.postMessage(m, '*'); } catch (e) {} };
var step = function (n) { POST({ hv: 'step', name: n }); };
function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
function $(s, r) { return (r || document).querySelector(s); }
function mk(tag, cls, html, par) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; if (par) par.appendChild(e); return e; }
function wait(ms) { return new Promise(function (r) { setTimeout(r, AUTO ? ms * .85 : ms); }); }
var E = { out: function (t) { return 1 - Math.pow(1 - t, 3); }, io: function (t) { return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }, back: function (t) { var c = 1.70158; return 1 + (c + 1) * Math.pow(t - 1, 3) + c * Math.pow(t - 1, 2); }, lin: function (t) { return t; } };
function tween(ms, fn, ease) { ease = ease || E.out; return new Promise(function (res) { var t0 = performance.now(); (function f(now) { var p = Math.min(1, (now - t0) / ms); fn(ease(p), p); if (p < 1) requestAnimationFrame(f); else res(); })(t0); }); }

/* ---------------------------------------------------------------- data */
var D = {
  to: String(HV.to || '').trim(), from: String(HV.from || '').trim(), msg: String(HV.msg || '').trim(),
  age: parseInt(HV.age, 10) > 0 ? Math.min(99, parseInt(HV.age, 10)) : 0,
  date: String(HV.date || '').trim(), time: String(HV.time || '').trim(), venue: String(HV.venue || '').trim(),
  address: String(HV.address || '').trim(), dress: String(HV.dress || '').trim(),
  rsvp: !(HV.rsvp === false || HV.rsvp === '0' || HV.rsvp === 0 || HV.rsvp === 'no'),
  photos: (Array.isArray(HV.photos) ? HV.photos : (HV.photo ? [HV.photo] : [])).filter(function (p) { return p && !/^placeholder/.test(p); }).slice(0, 3)
};
var refY = new Date().getFullYear();
function pDate(t) {
  t = String(t).trim(); var m;
  if ((m = t.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/))) return new Date(+m[1], +m[2] - 1, +m[3]);
  if ((m = t.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})$/))) return new Date(+m[3] < 100 ? 2000 + +m[3] : +m[3], +m[2] - 1, +m[1]);
  var mo = 'jan feb mar apr may jun jul aug sep oct nov dec'.split(' ');
  if ((m = t.toLowerCase().match(/^(\d{1,2})\s*(?:st|nd|rd|th)?\s+([a-z]{3})[a-z]*\.?,?\s*(\d{4})?$/)) && mo.indexOf(m[2]) >= 0) return new Date(m[3] ? +m[3] : refY, mo.indexOf(m[2]), +m[1]);
  if ((m = t.toLowerCase().match(/^([a-z]{3})[a-z]*\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s*(\d{4})?$/)) && mo.indexOf(m[1]) >= 0) return new Date(m[3] ? +m[3] : refY, mo.indexOf(m[1]), +m[2]);
  return null;
}
function pTime(t) { var m = String(t).trim().toLowerCase().match(/^(\d{1,2})(?:[:.](\d{2}))?\s*(am|pm)?$/); if (!m || (!m[2] && !m[3])) return null; var h = +m[1], mi = +(m[2] || 0); if (m[3] === 'pm' && h < 12) h += 12; if (m[3] === 'am' && h === 12) h = 0; if (h > 23 || mi > 59) return null; return [h, mi]; }
function mkEv(name, dt, tm, venue, address, dress) { var d = dt instanceof Date ? dt : pDate(dt || ''); var t = Array.isArray(tm) ? tm : pTime(tm || ''); var at = d ? new Date(d.getFullYear(), d.getMonth(), d.getDate(), t ? t[0] : 10, t ? t[1] : 0) : null; return { name: name, at: at, hasTime: !!t, venue: venue || '', address: address || '', dress: dress || '' }; }
var EVENTS = [];
String(HV.events || '').split(/\n+/).forEach(function (line) {
  line = line.trim(); if (!line) return;
  var tk = (line.indexOf('|') >= 0 ? line.split('|') : line.split(/\s+[–—-]\s+/)).map(function (x) { return x.trim(); }).filter(Boolean); if (!tk.length) return;
  var e = { name: tk[0].slice(0, 40), dt: null, tm: null, venue: '', dress: '' };
  for (var i = 1; i < tk.length; i++) { var d = !e.dt && pDate(tk[i]), tm = !e.tm && pTime(tk[i]); if (d || tm) { if (d) e.dt = d; if (tm) e.tm = tm; continue; } if (!e.venue) e.venue = tk[i].slice(0, 90); else if (!e.dress) e.dress = tk[i].slice(0, 50); }
  EVENTS.push(mkEv(e.name, e.dt, e.tm, e.venue, '', e.dress));
});
var MAIN = null;

/* ---------------------------------------------------------------- formatting */
function fmtDate(d) { return d ? d.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
function fmtTime(d) { return d ? d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }) : ''; }
function mapUrl(v, a) { var q = [v, a].filter(Boolean).join(', '); return q ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(q) : ''; }
function gcal(e, title) { if (!e.at) return ''; var s = e.at, en = new Date(s.getTime() + 3 * 3600e3); var f = function (d) { return d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, ''); }; return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(title) + '&dates=' + f(s) + '/' + f(en) + '&location=' + encodeURIComponent([e.venue, e.address].filter(Boolean).join(', ')); }
function ics(list, title) {
  var f = function (d) { return d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, ''); };
  var L = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//H View//Signature//EN'];
  list.forEach(function (e, i) { if (!e.at) return; L.push('BEGIN:VEVENT', 'UID:hv-' + Date.now() + '-' + i + '@hview.in', 'DTSTAMP:' + f(new Date()), 'DTSTART:' + f(e.at), 'DTEND:' + f(new Date(e.at.getTime() + 3 * 3600e3)), 'SUMMARY:' + (e.name + ' · ' + title).replace(/[,;]/g, ' '), 'LOCATION:' + [e.venue, e.address].filter(Boolean).join(' ').replace(/[,;]/g, ' '), 'END:VEVENT'); });
  L.push('END:VCALENDAR');
  try { var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([L.join('\r\n')], { type: 'text/calendar' })); a.download = 'invitation.ics'; document.body.appendChild(a); a.click(); setTimeout(function () { a.remove(); }, 500); } catch (e) {}
}
function splitNames(s) { var m = String(s).split(/\s*(?:&|\+| and | weds | ❤ | ♥ )\s*/i).filter(Boolean); return m.length >= 2 ? [m[0], m.slice(1).join(' & ')] : [s]; }
var INDIC = /[ऀ-෿]/;

/* ---------------------------------------------------------------- stage */
document.title = 'A surprise for you · H View';
var root = mk('div', '', null, document.body); root.id = 'sig';
var bd = mk('div', 'bd', null, root);
var st = mk('div', '', null, root); st.id = 'st';
var vig = mk('div', 'vig', null, st);
var cv = mk('canvas', '', null, st); cv.id = 'fx'; var g = cv.getContext('2d');
var flashEl = mk('div', 'flash', null, st);
var ui = mk('div', 'ui', null, st);
var capEl = mk('div', 'cap off', '<div class="k"></div><div class="t"></div><div class="s"></div>', ui);
var hintEl = mk('div', 'hint off', '', ui);
var finger = mk('div', 'finger', null, st);
var sndBtn = mk('button', 'snd', '♪', st); sndBtn.setAttribute('aria-label', 'Music on or off');
var loadEl = mk('div', 'load', 'Preparing your invitation…', st);
var W = 0, H = 0, DPR = Math.min(2, devicePixelRatio || 1);
function size() { W = st.clientWidth; H = st.clientHeight; cv.width = W * DPR; cv.height = H * DPR; g.setTransform(DPR, 0, 0, DPR, 0, 0); layers.forEach(fitFrame); }
var layers = [];
function fitFrame(L) { var s = Math.max(W / 1080, H / 1920); L.F.style.width = 1080 * s + 'px'; L.F.style.height = 1920 * s + 'px'; L.s = s; if (!L.kb) L.F.style.transform = 'translate(-50%,-50%) scale(' + (L.z || 1) + ')'; }
/* point in image % -> stage px */
function P(L, x, y) { var s = L.s, fw = 1080 * s, fh = 1920 * s; return [(W - fw) / 2 + x / 100 * fw, (H - fh) / 2 + y / 100 * fh]; }

function scene(img, o) {
  o = o || {};
  var L = { el: mk('div', 'L'), z: 1, kb: o.kb !== false && !REDUCED }; L.wantKb = L.kb;
  L.F = mk('div', 'F' + (L.kb ? ' kb' : ''), null, L.el); L.im = mk('div', 'im', null, L.F); L.im.style.backgroundImage = 'url(' + ART + img + '.webp)'; L.src = img;
  if (o.filter) L.im.style.filter = o.filter;
  st.insertBefore(L.el, vig); layers.push(L); fitFrame(L);
  bd.style.backgroundImage = 'url(' + ART + img + '.webp)';
  return L;
}
function show(L, how, ms) {
  ms = ms || 1200; var old = layers.filter(function (x) { return x !== L && x.el.classList.contains('on'); });
  L.el.style.transition = 'opacity ' + ms + 'ms ease';
  if (how === 'push') { L.F.classList.remove('kb'); L.kb = false; L.z = 1.22; fitFrame(L); requestAnimationFrame(function () { L.el.classList.add('on'); tween(ms * 1.6, function (k) { if (L.frozen) return; L.z = 1.22 - .18 * k; fitFrame(L); }, E.out).then(function () { if (L.wantKb && !L.frozen) { L.F.style.transform = ''; L.kb = true; L.F.classList.add('kb'); } }); }); }
  else requestAnimationFrame(function () { L.el.classList.add('on'); });
  old.forEach(function (x) { setTimeout(function () { x.el.classList.remove('on'); setTimeout(function () { drop(x); }, ms + 50); }, how === 'cut' ? 0 : ms * .6); });
  return wait(ms);
}
function drop(L) { var i = layers.indexOf(L); if (i >= 0) layers.splice(i, 1); L.el.remove(); }
function put(L) { L.el.style.transition = 'none'; L.el.classList.add('on'); }
function freeze(L) { /* stop Ken Burns so overlays stay aligned */ L.frozen = true; if (!L.kb) { L.zFix = L.z || 1; return; } var tr = getComputedStyle(L.F).transform; L.F.classList.remove('kb'); L.F.style.transform = tr; L.kb = false; var m = tr.match(/matrix\(([^,]+),/); L.zFix = m ? parseFloat(m[1]) : 1; }
function sprite(L, img, x, y, w, o) { o = o || {}; var e = mk('div', 'sp', null, L.F); e.style.backgroundImage = 'url(' + ART + img + '.webp)'; e.style.left = x + '%'; e.style.top = y + '%'; e.style.width = w + '%'; e.style.aspectRatio = o.ar || '1'; if (o.h) { e.style.height = o.h + '%'; e.style.aspectRatio = 'auto'; } e.style.transform = 'translate(-50%,-50%)' + (o.tr || ''); if (o.z) e.style.zIndex = o.z; if (o.origin) e.style.transformOrigin = o.origin; return e; }
function flash(ms, peak) { peak = peak == null ? 1 : peak; return tween(ms || 900, function (k) { flashEl.style.opacity = String(Math.sin(k * Math.PI) * peak); }, E.lin); }
function cap(k, t, s) { capEl.classList.add('off'); return wait(350).then(function () { $('.k', capEl).textContent = k || ''; $('.t', capEl).textContent = t || ''; $('.t', capEl).style.fontSize = (t && t.length > 18) ? 'clamp(24px,7.2vw,34px)' : ''; $('.s', capEl).textContent = s || ''; if (k || t || s) capEl.classList.remove('off'); }); }
function hint(t) { if (!t) { hintEl.classList.add('off'); return; } hintEl.textContent = t; hintEl.classList.remove('off'); }
function capTop(pct) { capEl.style.top = 'calc(' + pct + '% + env(safe-area-inset-top))'; }

/* ---------------------------------------------------------------- particles */
var P_ = [];
var PAL = { petal: ['#ffffff', '#ffe4ec', '#ffc2d4', '#f7a1b8'], marigold: ['#ff9f1c', '#ffb703', '#e85d04', '#fff3e0', '#d62839'], gold: ['#fff3c4', '#ffd77a', '#ffc04d', '#fffbe6'], confetti: ['#ff5fa8', '#ffd23f', '#33b6ff', '#8a6cff', '#5ee0c0', '#ff8a5c', '#ffffff'] };
var emit = { kind: null, rate: 0, acc: 0 };
function rain(kind, rate) { emit.kind = kind; emit.rate = REDUCED ? rate * .3 : rate; }
function add(kind, x, y, vx, vy, o) { if (P_.length > 900) return; var c = PAL[o && o.pal || (kind === 'petal' ? 'petal' : kind === 'marigold' ? 'marigold' : kind === 'confetti' ? 'confetti' : 'gold')]; P_.push({ k: kind, x: x, y: y, vx: vx, vy: vy, r: Math.random() * 6.28, vr: (Math.random() - .5) * 4, s: (o && o.s) || (kind === 'spark' ? 1 + Math.random() * 2 : 3 + Math.random() * 5), c: c[(Math.random() * c.length) | 0], life: 0, max: (o && o.max) || 7, g: (o && o.g != null) ? o.g : 30 }); }
function burst(kind, x, y, n, sp, o) { n = REDUCED ? n * .4 : n; for (var i = 0; i < n; i++) { var a = Math.random() * 6.283, v = (sp || 300) * (.3 + Math.random() * .7); add(kind, x, y, Math.cos(a) * v, Math.sin(a) * v - (sp || 300) * .3, Object.assign({ max: 2.5 + Math.random() * 2, g: kind === 'spark' ? 220 : 160 }, o || {})); } }
function firework(x, y, quiet) { var c = PAL.confetti[(Math.random() * 7) | 0]; for (var i = 0; i < (REDUCED ? 25 : 70); i++) { var a = i / 70 * 6.283, v = 140 + Math.random() * 120; P_.push({ k: 'fw', x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, r: 0, vr: 0, s: 2.2, c: Math.random() < .5 ? c : '#fff3c4', life: 0, max: 1.6 + Math.random() * .6, g: 60 }); } if (!quiet) SND.boom(); }
function smoke(x, y, n) { for (var i = 0; i < n; i++) P_.push({ k: 'smoke', x: x + (Math.random() - .5) * 6, y: y, vx: (Math.random() - .5) * 14, vy: -30 - Math.random() * 30, r: 0, vr: 0, s: 5 + Math.random() * 5, c: 'rgba(235,230,240,', life: -i * .05, max: 2.6, g: 0 }); }
var lastT = performance.now();
function frame(now) {
  var dt = Math.min(.05, (now - lastT) / 1000); lastT = now; g.clearRect(0, 0, W, H);
  if (emit.kind && emit.rate) { emit.acc += dt * emit.rate; while (emit.acc > 1) { emit.acc--; var k = emit.kind; if (k === 'snowgold') add('spark', Math.random() * W, H + 10, (Math.random() - .5) * 20, -40 - Math.random() * 50, { g: -6, max: 9, s: 1.5 + Math.random() * 2 }); else add(k, Math.random() * W, -20, (Math.random() - .5) * 30, 30 + Math.random() * 40, { g: 8, max: 12 }); } }
  for (var i = P_.length - 1; i >= 0; i--) {
    var p = P_[i]; p.life += dt; if (p.life < 0) continue; if (p.life > p.max || p.y > H + 40) { P_.splice(i, 1); continue; }
    p.vy += p.g * dt; if (p.k === 'petal' || p.k === 'marigold' || p.k === 'confetti') { p.vx += Math.sin(p.life * 2 + i) * 18 * dt; p.vx *= .99; } p.x += p.vx * dt; p.y += p.vy * dt; p.r += p.vr * dt;
    var a = Math.min(1, (p.max - p.life) / .8);
    g.save(); g.translate(p.x, p.y); g.globalAlpha = Math.max(0, a);
    if (p.k === 'petal' || p.k === 'marigold') { g.rotate(p.r); g.scale(1, Math.abs(Math.sin(p.life * 3 + i)) * .7 + .3); g.fillStyle = p.c; g.beginPath(); g.ellipse(0, 0, p.s * 1.3, p.s * .8, 0, 0, 6.283); g.fill(); g.fillStyle = 'rgba(255,255,255,.35)'; g.beginPath(); g.ellipse(-p.s * .3, -p.s * .2, p.s * .5, p.s * .25, 0, 0, 6.283); g.fill(); }
    else if (p.k === 'confetti') { g.rotate(p.r); g.scale(1, Math.sin(p.life * 6 + i)); g.fillStyle = p.c; g.fillRect(-p.s * .6, -p.s, p.s * 1.2, p.s * 2); }
    else if (p.k === 'smoke') { var t = p.life / p.max; g.fillStyle = p.c + (.35 * (1 - t)) + ')'; g.beginPath(); g.arc(0, 0, p.s * (1 + t * 3), 0, 6.283); g.fill(); }
    else if (p.k === 'bubble') { g.strokeStyle = 'rgba(255,255,255,.85)'; g.lineWidth = 1.5; g.beginPath(); g.arc(0, 0, p.s, 0, 6.283); g.stroke(); }
    else { g.globalCompositeOperation = 'lighter'; var rr = p.s * (p.k === 'fw' ? 1.6 : 2.4); var gr = g.createRadialGradient(0, 0, 0, 0, 0, rr * 2); gr.addColorStop(0, p.c); gr.addColorStop(1, 'rgba(255,200,100,0)'); g.fillStyle = gr; g.beginPath(); g.arc(0, 0, rr * 2, 0, 6.283); g.fill(); }
    g.restore();
  }
  requestAnimationFrame(frame);
}

/* ---------------------------------------------------------------- sound (synthesised, nothing to license) */
var AC = null, master = null, muted = HV.muted === true || HV.music === false, started = false;
function ac() { if (!AC) { try { AC = new (window.AudioContext || window.webkitAudioContext)(); master = AC.createGain(); master.gain.value = muted ? 0 : .9; master.connect(AC.destination); } catch (e) {} } if (AC && AC.state === 'suspended') AC.resume(); return AC; }
function note(f, t, d, type, v, att) { var a = ac(); if (!a) return; var o = a.createOscillator(), gn = a.createGain(), at = a.currentTime + (t || 0); o.type = type || 'sine'; o.frequency.value = f; gn.gain.setValueAtTime(0, at); gn.gain.linearRampToValueAtTime(v || .08, at + (att || .02)); gn.gain.exponentialRampToValueAtTime(.0005, at + d); o.connect(gn); gn.connect(master); o.start(at); o.stop(at + d + .05); return o; }
function flute(f, t, d, v) { var a = ac(); if (!a) return; var o = note(f, t, d, 'sine', v || .06, .12); var lfo = a.createOscillator(), lg = a.createGain(); lfo.frequency.value = 5.2; lg.gain.value = f * .012; lfo.connect(lg); lg.connect(o.frequency); lfo.start(a.currentTime + t); lfo.stop(a.currentTime + t + d); note(f * 2, t, d * .8, 'sine', (v || .06) * .18, .15); }
function noise(d, f, v, t) { var a = ac(); if (!a) return; var b = a.createBuffer(1, a.sampleRate * d, a.sampleRate), c = b.getChannelData(0); for (var i = 0; i < c.length; i++) c[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / c.length, 2); var n = a.createBufferSource(), fl = a.createBiquadFilter(), gn = a.createGain(); fl.type = 'bandpass'; fl.frequency.value = f; gn.gain.value = v; n.buffer = b; n.connect(fl); fl.connect(gn); gn.connect(master); n.start(a.currentTime + (t || 0)); }
var SND = {
  bell: function (f) { f = f || 520; [1, 2.76, 5.4, 8.93].forEach(function (m, i) { note(f * m, 0, 3.2 - i * .6, 'sine', [.12, .05, .03, .015][i], .005); }); },
  chime: function () { [1319, 1568, 2093, 2637].forEach(function (f, i) { note(f, i * .09, 1.4, 'sine', .04); }); },
  pop: function () { noise(.12, 1800, .6); note(700, 0, .12, 'triangle', .05); },
  whoosh: function () { noise(.8, 700, .25); },
  boom: function () { noise(.9, 220, .5); noise(.5, 1600, .12, .12); },
  creak: function () { var a = ac(); if (!a) return; var o = a.createOscillator(), gn = a.createGain(), fl = a.createBiquadFilter(); o.type = 'sawtooth'; o.frequency.setValueAtTime(70, a.currentTime); o.frequency.linearRampToValueAtTime(48, a.currentTime + 1.4); fl.type = 'lowpass'; fl.frequency.value = 500; gn.gain.setValueAtTime(.05, a.currentTime); gn.gain.exponentialRampToValueAtTime(.0005, a.currentTime + 1.5); o.connect(fl); fl.connect(gn); gn.connect(master); o.start(); o.stop(a.currentTime + 1.6); },
  giggle: function () { [0, .08, .16, .24, .32].forEach(function (t, i) { note(900 + (i % 2) * 260, t, .09, 'triangle', .05); }); },
  boing: function () { var a = ac(); if (!a) return; var o = a.createOscillator(), gn = a.createGain(); o.frequency.setValueAtTime(220, a.currentTime); o.frequency.exponentialRampToValueAtTime(560, a.currentTime + .25); gn.gain.setValueAtTime(.08, a.currentTime); gn.gain.exponentialRampToValueAtTime(.0005, a.currentTime + .3); o.connect(gn); gn.connect(master); o.start(); o.stop(a.currentTime + .35); },
  blow: function () { noise(.9, 500, .35); },
  fizz: function () { noise(.25, 4000, .08); },
  bubble: function () { note(400 + Math.random() * 300, 0, .08, 'sine', .04); },
  drum: function () { note(110, 0, .25, 'sine', .2); noise(.1, 300, .2); }
};
/* background music loops (scheduler) */
var MUS = null;
var SONGS = {
  /* raag Yaman-ish flute over tanpura (Royal) */
  yaman: { bpm: 66, drone: [146.8, 220], seq: [[0, 2], [2, 1], [4, 1], [6, 2], [7, 1], [9, 1], [11, 2], [12, 2], [11, 1], [9, 1], [7, 2], [6, 1], [4, 1], [2, 2], [4, 1], [0, 3]], base: 293.7, scale: [0, 2, 4, 6, 7, 9, 11, 12], inst: 'flute' },
  waltz: { bpm: 96, drone: null, seq: [[0, 1], [4, 1], [7, 1], [12, 3], [11, 1], [9, 1], [7, 1], [9, 3], [5, 1], [9, 1], [12, 1], [14, 3], [12, 1], [11, 1], [9, 1], [7, 3]], base: 261.6, inst: 'pluck', chords: [[0, 4, 7], [5, 9, 12], [7, 11, 14], [0, 4, 7]] },
  mohanam: { bpm: 84, drone: [130.8, 196], seq: [[0, 1], [2, 1], [4, 1], [7, 1], [9, 2], [7, 1], [4, 1], [2, 2], [4, 1], [7, 1], [9, 1], [12, 2], [9, 1], [7, 1], [4, 1], [2, 1], [0, 3]], base: 261.6, inst: 'reed', bells: true },
  bhairav: { bpm: 60, drone: [138.6, 207.7], seq: [[0, 2], [1, 1], [4, 1], [5, 2], [7, 1], [8, 1], [11, 2], [12, 2], [11, 1], [8, 1], [7, 2], [5, 1], [4, 1], [1, 2], [0, 3]], base: 277.2, inst: 'flute' },
  musicbox: { bpm: 120, drone: null, seq: [[7, .75], [7, .25], [9, 1], [7, 1], [12, 1], [11, 2], [7, .75], [7, .25], [9, 1], [7, 1], [14, 1], [12, 2], [7, .75], [7, .25], [19, 1], [16, 1], [12, 1], [11, 1], [9, 2], [17, .75], [17, .25], [16, 1], [12, 1], [14, 1], [12, 3]], base: 392 / 1.4983, inst: 'box' },
  lounge: { bpm: 78, drone: null, seq: [[12, 1], [14, 1], [16, 2], [19, 1], [16, 1], [14, 2], [12, 1], [11, 1], [9, 2], [11, 1], [12, 3]], base: 220, inst: 'epiano', chords: [[0, 4, 7, 11], [5, 9, 12, 16], [2, 5, 9, 12], [7, 11, 14, 17]] }
};
function music(name) {
  if (MUS || muted) return; var S = SONGS[name]; if (!S || !ac()) return;
  var beat = 60 / S.bpm, i = 0, bar = 0, t = AC.currentTime + .1, alive = true;
  MUS = { stop: function () { alive = false; } };
  if (S.drone) S.drone.forEach(function (f) { (function dr() { if (!alive) return; note(f, 0, 6, 'sawtooth', .006, 1.2); note(f * 2, .3, 6, 'sine', .01, 1); setTimeout(dr, 5200); })(); });
  (function sch() {
    if (!alive) return;
    while (t < AC.currentTime + .6) {
      var s = S.seq[i % S.seq.length], f = S.base * Math.pow(2, s[0] / 12), d = s[1] * beat, at = t - AC.currentTime;
      if (S.inst === 'flute') flute(f, at, d * 1.1, .05);
      else if (S.inst === 'reed') { note(f, at, d, 'sawtooth', .02, .05); note(f * 2, at, d, 'sine', .015, .05); }
      else if (S.inst === 'box') { note(f * 2, at, d * 1.6, 'sine', .05, .005); note(f * 4, at, d * .6, 'sine', .012, .005); }
      else if (S.inst === 'pluck') note(f, at, d * 1.4, 'triangle', .05, .01);
      else if (S.inst === 'epiano') { note(f, at, d * 1.5, 'sine', .045, .01); note(f * 2.01, at, d * .7, 'sine', .01, .01); }
      if (S.chords && i % 4 === 0) { var ch = S.chords[bar % S.chords.length]; ch.forEach(function (c) { note(S.base / 2 * Math.pow(2, c / 12), at, beat * 4, S.inst === 'epiano' ? 'sine' : 'triangle', .018, .04); }); bar++; }
      if (S.bells && i % 8 === 0) setTimeout(function () { SND.bell(1046); }, at * 1000);
      t += d; i++;
    }
    setTimeout(sch, 200);
  })();
}
sndBtn.onclick = function (e) { e.stopPropagation(); muted = !muted; ac(); if (master) master.gain.value = muted ? 0 : .9; sndBtn.textContent = muted ? '✕' : '♪'; if (!muted && !MUS && CFG) music(CFG.song); };
function firstTouch() { if (started) return; started = true; if (!muted && !AUTO) { ac(); music(CFG.song); } }
st.addEventListener('pointerdown', firstTouch, { capture: true });
function vib(ms) { try { if (!muted && navigator.vibrate) navigator.vibrate(ms); } catch (e) {} }

/* ---------------------------------------------------------------- ghost finger (autoplay) */
function ghost(x, y, ms) { finger.style.opacity = '1'; return tween(ms || 600, (function () { var x0 = parseFloat(finger.style.left) || W / 2, y0 = parseFloat(finger.style.top) || H * .8; return function (k) { finger.style.left = x0 + (x - x0) * k + 'px'; finger.style.top = y0 + (y - y0) * k + 'px'; }; })(), E.io); }
function ghostTap(x, y) { return ghost(x, y).then(function () { finger.classList.add('dn'); return wait(160); }).then(function () { finger.classList.remove('dn'); }); }
function ghostHide() { finger.style.opacity = '0'; }

/* ---------------------------------------------------------------- gestures */
function rel(e) { var r = st.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; }
function tapAny(label) {
  hint(label); return new Promise(function (res) {
    var done = false; function go() { if (done) return; done = true; st.removeEventListener('pointerup', h); hint(''); res(); }
    function h(e) { if (e.target.closest && e.target.closest('.snd,.card,a,button')) return; go(); }
    setTimeout(function () { st.addEventListener('pointerup', h); }, 250);
    if (AUTO) wait(1400).then(function () { return ghostTap(W / 2, H * .6); }).then(function () { ghostHide(); go(); });
  });
}
/* tap each hotspot (pts in stage px getters) */
function tapSpots(pts, label, onTap) {
  hint(label); var left = pts.length, els = [];
  return new Promise(function (res) {
    pts.forEach(function (fnp, i) {
      var e = mk('div', 'hs', null, ui); var p = fnp(); e.style.left = p[0] + 'px'; e.style.top = p[1] + 'px'; els.push(e);
      e.onpointerdown = function (ev) { ev.stopPropagation(); hit(i); };
    });
    function hit(i) { var e = els[i]; if (!e || e.classList.contains('done')) return; e.classList.add('done'); vib(15); onTap(i, fnpPos(i)); if (--left === 0) { hint(''); setTimeout(function () { els.forEach(function (x) { x.remove(); }); res(); }, 500); } }
    function fnpPos(i) { return pts[i](); }
    if (AUTO) (async function () { await wait(900); for (var i = 0; i < pts.length; i++) { var p = pts[i](); await ghostTap(p[0], p[1]); hit(i); await wait(450); } ghostHide(); })();
  });
}
function hold(label, ms, onProg) {
  hint(''); var b = mk('button', 'hold', '<svg viewBox="0 0 116 116"><circle cx="58" cy="58" r="55"/></svg><span>' + esc(label) + '</span>', ui);
  var ring = $('circle', b), p = 0, on = false, last = performance.now(), done = false;
  b.onpointerdown = function (e) { e.preventDefault(); on = true; try { b.setPointerCapture(e.pointerId); } catch (x) {} firstTouch(); };
  b.onpointerup = b.onpointercancel = function () { on = false; }; b.oncontextmenu = function (e) { e.preventDefault(); };
  b.onkeydown = function (e) { if (e.key === ' ' || e.key === 'Enter') on = true; }; b.onkeyup = function () { on = false; };
  if (AUTO) { var r = b.getBoundingClientRect(), s = st.getBoundingClientRect(); wait(900).then(function () { return ghost(r.left - s.left + 48, r.top - s.top + 48); }).then(function () { finger.classList.add('dn'); on = true; }); }
  return new Promise(function (res) {
    (function f(now) { var dt = Math.min(.05, (now - last) / 1000); last = now; p = on ? Math.min(1, p + dt / (ms / 1000)) : Math.max(0, p - dt * .5); ring.style.strokeDashoffset = String(345 - 345 * p); onProg && onProg(p, on);
      if (p >= 1 && !done) { done = true; finger.classList.remove('dn'); ghostHide(); b.style.transition = 'opacity .4s,transform .4s'; b.style.opacity = '0'; b.style.transform = 'scale(.6)'; setTimeout(function () { b.remove(); }, 450); res(); return; }
      requestAnimationFrame(f); })(last);
  });
}
/* two knobs dragged apart horizontally; tapping a knob also counts */
function dragApart(pa, pb, label, onProg) {
  hint(label); var ka = mk('div', 'knob', null, ui), kb = mk('div', 'knob', null, ui); ka.dataset.a = '‹'; kb.dataset.a = '›';
  var A = pa(), B = pb(), da = 0, db = 0, done = false, span = W * .26;
  function place() { ka.style.left = A[0] - da + 'px'; ka.style.top = A[1] + 'px'; kb.style.left = B[0] + db + 'px'; kb.style.top = B[1] + 'px'; }
  place();
  return new Promise(function (res) {
    function prog() { var p = Math.min(1, (da + db) / (2 * span)); onProg && onProg(p, da / span, db / span); if (p >= 1 && !done) { done = true; hint(''); ka.remove(); kb.remove(); res(); } }
    [[ka, -1], [kb, 1]].forEach(function (pr) { var k = pr[0], dir = pr[1], sx = 0, base = 0, moved = false;
      k.onpointerdown = function (e) { e.stopPropagation(); firstTouch(); k.setPointerCapture(e.pointerId); sx = rel(e)[0]; base = dir < 0 ? da : db; moved = false; };
      k.onpointermove = function (e) { if (!k.hasPointerCapture(e.pointerId)) return; var d = Math.max(0, Math.min(span, base + (rel(e)[0] - sx) * dir)); if (Math.abs(d - base) > 4) moved = true; if (dir < 0) da = d; else db = d; place(); prog(); };
      k.onpointerup = function () { if (!moved) { if (dir < 0) da = Math.min(span, da + span * .55); else db = Math.min(span, db + span * .55); place(); prog(); } };
    });
    if (AUTO) (async function () { await wait(900); await ghost(A[0], A[1]); finger.classList.add('dn'); await tween(700, function (k) { da = span * k; place(); prog(); }, E.io); finger.classList.remove('dn'); await ghost(B[0], B[1], 400); finger.classList.add('dn'); await tween(700, function (k) { db = span * k; place(); prog(); }, E.io); finger.classList.remove('dn'); ghostHide(); })();
  });
}
function swipe(dir, label, onProg) {
  hint(label); var p = 0, done = false, s0 = null;
  return new Promise(function (res) {
    function fin() { if (done) return; done = true; hint(''); st.removeEventListener('pointerdown', dn); st.removeEventListener('pointermove', mv); st.removeEventListener('pointerup', up); res(); }
    function dn(e) { if (e.target.closest && e.target.closest('.snd,.card,a,button')) return; s0 = rel(e); }
    function mv(e) { if (!s0) return; var r = rel(e), d = dir === 'up' ? s0[1] - r[1] : dir === 'left' ? s0[0] - r[0] : r[0] - s0[0]; p = Math.max(0, Math.min(1, d / (dir === 'up' ? H * .25 : W * .4))); onProg && onProg(p); if (p >= 1) fin(); }
    function up() { if (!s0) return; s0 = null; if (p < .15) { p = 1; onProg && onProg(1); fin(); } else if (p < 1) { tween(300, function (k) { onProg && onProg(p * (1 - k)); }); p = 0; } }
    st.addEventListener('pointerdown', dn); st.addEventListener('pointermove', mv); st.addEventListener('pointerup', up);
    if (AUTO) (async function () { await wait(900); var x = W / 2, y = H * .7; await ghost(x, y); finger.classList.add('dn'); await tween(800, function (k) { var dx = dir === 'left' ? -W * .4 * k : dir === 'right' ? W * .4 * k : 0, dy = dir === 'up' ? -H * .25 * k : 0; finger.style.left = x + dx + 'px'; finger.style.top = y + dy + 'px'; onProg && onProg(k); }, E.io); finger.classList.remove('dn'); ghostHide(); fin(); })();
  });
}

/* ---------------------------------------------------------------- cards */
function card(html, pos, cls) { var c = mk('div', 'card ' + (cls || ''), html, ui); if (pos.top != null) c.style.top = pos.top; if (pos.bottom != null) c.style.bottom = pos.bottom; requestAnimationFrame(function () { requestAnimationFrame(function () { c.classList.add('on'); }); }); return c; }
function unCard(c) { if (!c) return wait(10); c.classList.remove('on'); return wait(500).then(function () { c.remove(); }); }
function cont(c, label) { var b = mk('button', 'btn', esc(label || 'Continue') + ' ›', mk('div', 'row', null, c)); return new Promise(function (res) { b.onclick = function () { res(); }; if (AUTO) { wait(2600).then(function () { var r = b.getBoundingClientRect(), s = st.getBoundingClientRect(); return ghostTap(r.left - s.left + r.width / 2, r.top - s.top + r.height / 2); }).then(function () { ghostHide(); res(); }); } }); }
function evHtml(e, title, main) {
  var m = mapUrl(e.venue, e.address), gc = gcal(e, title);
  return '<div class="ck">' + esc(main ? 'Save the date' : 'Celebration') + '</div><h3>' + esc(e.name) + '</h3><span class="orn"></span>' +
    (e.at ? '<div class="meta"><b>' + esc(fmtDate(e.at)) + '</b>' + (e.hasTime ? '<br>' + esc(fmtTime(e.at)) : '') + '</div>' : '') +
    (e.venue ? '<div class="meta">' + esc(e.venue) + (e.address ? '<br><i>' + esc(e.address) + '</i>' : '') + '</div>' : '') +
    (e.dress ? '<div class="meta">Dress code · ' + esc(e.dress) + '</div>' : '') +
    '<div class="row">' + (m ? '<a class="btn g" target="_blank" rel="noopener" href="' + esc(m) + '">📍 Open map</a>' : '') + (gc ? '<a class="btn g" target="_blank" rel="noopener" href="' + esc(gc) + '">📅 Add to calendar</a>' : '') + '</div>';
}
function countdown(el, target) { function f() { var ms = target - Date.now(); if (ms <= 0) { el.innerHTML = '<p>Thank you for celebrating with us 🙏</p>'; return; } var d = Math.floor(ms / 864e5), h = Math.floor(ms % 864e5 / 36e5), mi = Math.floor(ms % 36e5 / 6e4), s = Math.floor(ms % 6e4 / 1e3); el.innerHTML = '<div class="cd"><div><b>' + d + '</b><i>days</i></div><div><b>' + h + '</b><i>hours</i></div><div><b>' + mi + '</b><i>mins</i></div><div><b>' + s + '</b><i>secs</i></div></div>'; setTimeout(f, 1000); } f(); }
function rsvpCard(pos, title) {
  var c = card('<div class="ck">Kindly reply</div><h3>Will you join us?</h3><form class="rs"><label>Your name</label><input name="n" maxlength="60" required autocomplete="name"><label>Attending?</label><div class="yn"><label class="on"><input type="radio" name="r" value="yes" checked>Yes, with joy</label><label><input type="radio" name="r" value="maybe">Maybe</label><label><input type="radio" name="r" value="no">Sorry, no</label></div><label>Guests (including you)</label><input name="g" type="number" min="1" max="20" value="2"><label>A note for the family (optional)</label><textarea name="note" rows="2" maxlength="300"></textarea><div class="row"><button class="btn" type="submit">Send reply</button></div><div class="rsm"></div></form>', pos);
  var f = $('form', c), m = $('.rsm', c);
  $('.yn', f).onclick = function (e) { var l = e.target.closest('label'); if (!l) return; [].forEach.call($('.yn', f).children, function (x) { x.classList.toggle('on', x === l); }); $('input', l).checked = true; };
  var sent = new Promise(function (res) {
    f.onsubmit = function (e) { e.preventDefault(); if (AUTO) return; var data = { response: (f.querySelector('input[name=r]:checked') || {}).value || 'yes', name: f.n.value.trim(), guests: f.g.value, note: f.note.value.trim(), attending: ((f.querySelector('input[name=r]:checked') || {}).value || 'yes') !== 'no', message: f.note.value.trim() };
      if (!data.name) { m.textContent = 'Please add your name.'; return; } m.textContent = 'Sending…'; POST({ hv: 'rsvp', data: data }); step('rsvp');
      var to = setTimeout(function () { m.textContent = 'Thank you! Your reply is noted.'; res(); }, 4000);
      window.addEventListener('message', function on(ev) { if (ev.data && ev.data.hv === 'rsvp-ok') { clearTimeout(to); window.removeEventListener('message', on); m.textContent = ev.data.message || 'Thank you! Your reply is sent.'; if (data.response !== 'no') burst('confetti', W / 2, H * .6, 120, 420, { pal: 'gold' }); res(); } });
    };
  });
  var skip = mk('button', 'btn g sm', 'Skip ›', $('.row', c)); skip.type = 'button';
  return new Promise(function (res) {
    skip.onclick = function () { res(c); }; sent.then(function () { setTimeout(function () { res(c); }, 1800); });
    if (AUTO) (async function () { await wait(1200); f.n.value = 'Your guest'; await wait(1600); m.textContent = 'Preview: guests reply on your shared link.'; await wait(1600); res(c); })();
  });
}

/* ---------------------------------------------------------------- shared story parts */
function namesBlock(top, names, kid) {
  var wrap = mk('div', 'names', null, ui); wrap.style.top = top;
  var html = ''; names.forEach(function (n, i) { if (i) html += '<span class="amp">&amp;</span>'; html += '<span class="n">' + (INDIC.test(n) ? '<span class="ch">' + esc(n) + '</span>' : n.split('').map(function (c) { return '<span class="ch">' + (c === ' ' ? '&nbsp;' : esc(c)) + '</span>'; }).join('')) + '</span>'; });
  wrap.innerHTML = html; if (INDIC.test(names.join(''))) [].forEach.call(wrap.querySelectorAll('.n'), function (n) { n.style.fontFamily = "'Noto Serif','Cormorant Garamond',serif"; n.style.fontSize = 'clamp(36px,11vw,58px)'; });
  var chs = wrap.querySelectorAll('.ch'); [].forEach.call(chs, function (c, i) { setTimeout(function () { c.classList.add('in'); if (i % 3 === 0) SND.fizz(); }, 200 + i * 90); });
  return wrap;
}
function lead(def) { return D.from ? D.from : def; }
async function eventsCarousel(top, title) {
  var list = [MAIN].concat(EVENTS.filter(function (e) { return !MAIN || e.name.toLowerCase() !== MAIN.name.toLowerCase(); })).filter(Boolean);
  if (!list.length) return;
  var wrap = mk('div', 'evs', null, ui); wrap.style.top = top; list.forEach(function (e, i) { mk('div', 'card on', evHtml(e, title, i === 0), wrap); });
  var dots = mk('div', 'dots', list.map(function (_, i) { return '<i' + (i ? '' : ' class="on"') + '></i>'; }).join(''), ui); dots.style.top = 'calc(' + top + ' - 18px)';
  wrap.onscroll = function () { var i = Math.round(wrap.scrollLeft / (wrap.clientWidth * .84 + 14)); [].forEach.call(dots.children, function (d, k) { d.classList.toggle('on', k === i); }); };
  wrap.style.opacity = '0'; wrap.style.transition = 'opacity .8s'; requestAnimationFrame(function () { wrap.style.opacity = '1'; });
  hint(list.length > 1 ? 'Swipe for every event ›' : '');
  var nx = mk('button', 'btn', 'Continue ›', null); var nr = mk('div', '', null, ui); nr.style.cssText = 'position:absolute;left:0;right:0;text-align:center;bottom:calc(84px + env(safe-area-inset-bottom));z-index:9'; nr.appendChild(nx);
  await new Promise(function (res) { nx.onclick = res; if (AUTO) (async function () { for (var i = 1; i < list.length; i++) { await wait(1700); wrap.scrollTo({ left: i * (wrap.clientWidth * .84 + 14), behavior: 'smooth' }); } await wait(1800); res(); })(); });
  hint(''); wrap.style.opacity = '0'; dots.remove(); nr.remove(); await wait(600); wrap.remove();
}
async function countdownCard(pos, kicker, title) {
  var tgt = (MAIN && MAIN.at) || (EVENTS[0] && EVENTS[0].at); if (!tgt) return;
  var c = card('<div class="ck">' + esc(kicker) + '</div><h3>' + esc(title) + '</h3><div class="cdw"></div><div class="row"><button class="btn g ical" type="button">📅 Add all to calendar</button></div>', pos);
  countdown($('.cdw', c), tgt.getTime()); $('.ical', c).onclick = function () { ics([MAIN].concat(EVENTS).filter(Boolean), D.to || 'Celebration'); };
  await cont(c, 'Continue'); await unCard(c);
}
function finish() { step('final'); POST({ hv: 'done' }); }

/* ---------------------------------------------------------------- door / gate helpers */
/* split a scene's image into two leaves over rect [x0,y0,x1,y1] (image %) */
function leaves(L, r) {
  var fw = 100 / (r[2] - r[0]) * 100, fh = 100 / (r[3] - r[1]) * 100, mid = (r[0] + r[2]) / 2;
  L.F.style.perspective = '1400px';
  var glow = mk('div', '', null, L.F); glow.style.cssText = 'position:absolute;left:' + r[0] + '%;top:' + r[1] + '%;width:' + (r[2] - r[0]) + '%;height:' + (r[3] - r[1]) + '%;background:radial-gradient(ellipse at 50% 55%,#fff6da,#ffd27a 35%,#c2741c 75%,#5a2a08);opacity:0';
  function leaf(side) { var x0 = side < 0 ? r[0] : mid, w = (r[2] - r[0]) / 2; var e = mk('div', 'leaf', null, L.F); e.style.left = x0 + '%'; e.style.top = r[1] + '%'; e.style.width = w + '%'; e.style.height = (r[3] - r[1]) + '%'; e.style.backgroundImage = 'url(' + ART + L.src + '.webp)'; e.style.backgroundSize = (100 / w * 100) + '% ' + fh + '%'; var hh = r[3] - r[1]; e.style.backgroundPosition = (w >= 100 ? 0 : x0 / (100 - w) * 100) + '% ' + (hh >= 100 ? 0 : r[1] / (100 - hh) * 100) + '%'; e.style.transformOrigin = side < 0 ? '0% 50%' : '100% 50%'; e.style.boxShadow = '0 0 30px rgba(0,0,0,.0)'; return e; }
  return { l: leaf(-1), r: leaf(1), glow: glow, set: function (k, deg) { deg = deg || 100; this.l.style.transform = 'rotateY(' + (-deg * k) + 'deg)'; this.r.style.transform = 'rotateY(' + (deg * k) + 'deg)'; glow.style.opacity = String(Math.min(1, k * 2.2)); this.l.style.filter = this.r.style.filter = 'brightness(' + (1 - k * .45) + ')'; } };
}
async function pushInto(L, cx, cy, ms) { freeze(L); var z0 = L.zFix || 1; L.F.style.transformOrigin = cx + '% ' + cy + '%'; await tween(ms || 1600, function (k) { L.F.style.transform = 'translate(-50%,-50%) scale(' + (z0 + k * 1.6) + ')'; }, E.io); }

/* =====================================================================
 * DESIGNS
 * ===================================================================== */
var CFG = null;
var DESIGNS = {
/* ---------------------------------------------------------------- Golden Gates (wedding) */
goldengates: { song: 'yaman', type: 'wedding', run: async function () {
  var names = splitNames(D.to || 'Ananya & Arjun'); var title = names.join(' & ') + ' wedding';
  var A = scene('gates/s2-gates-open', { kb: false }); put(A);
  var C = scene('gates/s1-gates-closed', { kb: false }); put(C); await wait(600);
  var lv = leaves(C, [0, 0, 100, 100]); lv.glow.remove(); C.im.style.opacity = '0'; C.F.style.perspective = '1100px';
  var bl = sprite(C, 'gates/bow-l', 41, 63, 22, { ar: '458/401', z: 3 }), br = sprite(C, 'gates/bow-r', 59, 63, 22, { ar: '449/403', z: 3 });
  rain('petal', 2);
  await cap('You are cordially invited', lead('With the blessings of our families'), 'Untie the ribbon to open the palace gates');
  step('gates');
  await dragApart(function () { return P(C, 42, 64); }, function () { return P(C, 58, 64); }, 'Pull the ribbon apart', function (p, a, b) { bl.style.transform = 'translate(calc(-50% - ' + a * 120 + '%),-50%) rotate(' + (-a * 25) + 'deg)'; br.style.transform = 'translate(calc(-50% + ' + b * 120 + '%),-50%) rotate(' + (b * 25) + 'deg)'; bl.style.opacity = br.style.opacity = String(1 - p * .7); lv.set(p * .06); });
  SND.creak(); SND.chime(); vib(30); cap('', '', '');
  tween(500, function (k) { bl.style.opacity = br.style.opacity = String(.3 * (1 - k)); });
  await tween(2200, function (k) { lv.set(.06 + k * .94, 104); }, E.io);
  flash(900, .7); rain('petal', 6);
  C.el.style.transition = 'opacity .9s'; C.el.classList.remove('on'); setTimeout(function () { drop(C); }, 1000);
  A.F.style.transformOrigin = '50% 55%'; await tween(1800, function (k) { A.F.style.transform = 'translate(-50%,-50%) scale(' + (1 + k * .25) + ')'; }, E.io);
  /* palace by the lake: names */
  var R = scene('gates/s3-palace-reflection'); await show(R, 'push', 1300); step('names');
  capTop(6); await cap(D.from ? 'Together with their families' : 'The wedding of', '', ''); var nb = namesBlock('13%', names);
  for (var i = 0; i < 3; i++) setTimeout(function () { burst('spark', W * (.3 + Math.random() * .4), H * .25, 30, 220); SND.fizz(); }, 1400 + i * 700);
  await wait(3200);
  var mc = card('<p>' + esc(D.msg || 'We joyfully invite you to celebrate our wedding and bless us with your presence.') + '</p>' + (MAIN && MAIN.at ? '<div class="meta"><b>' + esc(fmtDate(MAIN.at)) + '</b>' + (MAIN.venue ? '<br>' + esc(MAIN.venue) : '') + '</div>' : ''), { bottom: '15%' });
  await cont(mc, 'See the celebrations'); await unCard(mc); nb.style.transition = 'opacity .6s'; nb.style.opacity = '0'; setTimeout(function () { nb.remove(); }, 700); cap('', '', '');
  /* mandap: events + countdown + rsvp */
  var M = scene('gates/s4-mandap'); await show(M, 'push', 1300); step('events');
  capTop(5); await cap('The celebrations', names.join(' & '), '');
  await eventsCarousel('30%', title);
  await countdownCard({ top: '30%' }, 'Counting down to', 'The big day');
  cap('', '', '');
  if (D.rsvp) { step('rsvp-form'); var rc = await rsvpCard({ top: '16%' }, title); await unCard(rc); }
  /* finale */
  var Fz = scene('gates/s2-gates-open'); await show(Fz, 'push', 1400); rain('petal', 9);
  sprite(Fz, 'gates/lantern', 14, 22, 15, { ar: '354/865' }).style.animation = 'none'; sprite(Fz, 'gates/lantern', 86, 22, 15, { ar: '354/865' });
  capTop(6); await cap('Please join us', '', ''); namesBlock('12%', names);
  card('<div class="ck">' + esc(MAIN && MAIN.at ? fmtDate(MAIN.at) : 'With love') + '</div>' + (MAIN && MAIN.venue ? '<h3>' + esc(MAIN.venue) + '</h3>' : '') + '<p>' + esc(D.from ? 'With warm regards, ' + D.from : 'Your presence is our blessing') + '</p>', { bottom: '9%' });
  finish();
} },

/* ---------------------------------------------------------------- Curtain Palace (wedding) */
curtainpalace: { song: 'waltz', type: 'wedding', run: async function () {
  var names = splitNames(D.to || 'Meera & Kabir'); var title = names.join(' & ') + ' wedding';
  var B = scene('curtain/s2-palace-reveal', { kb: false }); put(B);
  var C = scene('curtain/s1-curtains-closed', { kb: false }); put(C); await wait(500);
  var lv = { l: mk('div', 'leaf', null, C.F), r: mk('div', 'leaf', null, C.F) };
  [['l', 0], ['r', 50]].forEach(function (a) { var e = lv[a[0]]; e.style.cssText = 'left:' + a[1] + '%;top:0;width:50%;height:100%;background-image:url(' + ART + 'curtain/s1-curtains-closed.webp);background-size:200% 100%;background-position:' + (a[1] ? '100%' : '0%') + ' 0;transform-origin:' + (a[1] ? '100%' : '0%') + ' 50%'; });
  C.im.style.opacity = '0';
  function curtains(k) { var sx = 1 - k * .72, tx = k * 6; lv.l.style.transform = 'translateX(' + (-tx) + '%) scaleX(' + sx + ') skewY(' + (k * 2) + 'deg)'; lv.r.style.transform = 'translateX(' + tx + '%) scaleX(' + sx + ') skewY(' + (-k * 2) + 'deg)'; lv.l.style.filter = lv.r.style.filter = 'brightness(' + (1 - k * .35) + ')'; }
  await cap('An evening at the palace', lead('Our families invite you'), 'Hold the button to draw the curtains');
  step('curtains'); var tick = 0;
  await hold('Hold to\nopen', 2600, function (p, on) { curtains(p * .35); if (on && ++tick % 12 === 0) noise(.25, 900, .05); });
  cap('', '', ''); SND.whoosh(); vib(40);
  await tween(1800, function (k) { curtains(.35 + k * .65); }, E.io);
  C.el.classList.remove('on'); setTimeout(function () { drop(C); }, 1100);
  var ch = sprite(B, 'curtain/chandelier', 50, -14, 40, { ar: '518/838', origin: '50% 0%', z: 3 });
  tween(2000, function (k) { ch.style.top = (-14 - k * 30) + '%'; ch.style.opacity = String(1 - k); }, E.io);
  flash(900, .5); for (var i = 0; i < 4; i++) setTimeout(function () { firework(W * (.2 + Math.random() * .6), H * (.16 + Math.random() * .12)); }, 600 + i * 650);
  capTop(16); await cap('The wedding of', '', ''); step('names'); var nb = namesBlock('21%', names); rain('snowgold', 6);
  await wait(3400);
  var mc = card('<p>' + esc(D.msg || 'Request the honour of your presence as we begin our forever.') + '</p>', { bottom: '13%' }); await cont(mc, 'Open the windows'); await unCard(mc);
  nb.style.transition = 'opacity .6s'; nb.style.opacity = '0'; setTimeout(function () { nb.remove(); }, 700); cap('', '', '');
  /* jharokha: one card per event */
  var J = scene('curtain/s3-jharokha-diyas'); await show(J, 'push', 1300); step('events');
  var list = [MAIN].concat(EVENTS.filter(function (e) { return !MAIN || e.name.toLowerCase() !== MAIN.name.toLowerCase(); })).filter(Boolean);
  capTop(5); await cap('The celebrations', '', '');
  for (var j = 0; j < list.length; j++) { SND.chime(); burst('spark', W / 2, H * .45, 40, 260); var ec = card(evHtml(list[j], title, j === 0), { top: '24%' }); await cont(ec, j < list.length - 1 ? 'Next window' : 'Continue'); await unCard(ec); }
  var Hh = scene('curtain/s4-reception-hall'); await show(Hh, 'push', 1300);
  await countdownCard({ top: '26%' }, 'The countdown', names.join(' & '));
  cap('', '', '');
  if (D.rsvp) { step('rsvp-form'); var rc = await rsvpCard({ top: '14%' }, title); await unCard(rc); }
  var Fz = scene('curtain/s2-palace-reveal'); await show(Fz, 'push', 1400);
  setInterval(function () { if (!document.hidden) firework(W * (.15 + Math.random() * .7), H * (.12 + Math.random() * .16), true); }, 1400);
  capTop(16); await cap('Please join us', '', ''); namesBlock('21%', names);
  card('<div class="ck">' + esc(MAIN && MAIN.at ? fmtDate(MAIN.at) : 'With love') + '</div>' + (MAIN && MAIN.venue ? '<h3>' + esc(MAIN.venue) + '</h3>' : '') + '<p>' + esc(D.from ? 'With warm regards, ' + D.from : 'Your blessings mean the world to us') + '</p>', { bottom: '9%' });
  finish();
} },

/* ---------------------------------------------------------------- Temple Bells (South Indian wedding) */
templebells: { song: 'mohanam', type: 'wedding', run: async function () {
  var names = splitNames(D.to || 'Lakshmi & Karthik'); var title = names.join(' & ') + ' wedding';
  var mu = EVENTS.filter(function (e) { return /muhurt|wedding|kalyan|vivah|marriage|vivaha|subha/i.test(e.name); })[0] || MAIN;
  var T = scene('temple/s1-temple-doors-closed', { kb: false }); put(T); await wait(500);
  var bells = [[20, 0], [50, -2], [80, 0]].map(function (b) { var e = sprite(T, 'temple/bell', b[0], 0, 14, { ar: '344/948', origin: '50% 0%', z: 4 }); e.style.top = b[1] + '%'; e.style.transform = 'translate(-50%,0)'; e.dataset.a = 0; return e; });
  bells.forEach(function (e, i) { (function sw() { var a = +e.dataset.a; e.style.transform = 'translate(-50%,0) rotate(' + (Math.sin(performance.now() / 260 + i) * a) + 'deg)'; e.dataset.a = Math.max(0, a * .985); requestAnimationFrame(sw); })(); });
  var lv = leaves(T, [30.5, 43.5, 71.5, 78]);
  capTop(24); await cap(lead('With the blessings of the Almighty'), 'Shubha Vivaham', 'Ring the three temple bells');
  step('bells');
  await tapSpots(bells.map(function (e, i) { return function () { return P(T, [20, 50, 80][i], 16); }; }), 'Tap each bell 🔔', function (i, p) { bells[i].dataset.a = 16; SND.bell([523, 659, 784][i]); burst('spark', p[0], p[1], 26, 180); });
  cap('', '', ''); await wait(600); SND.creak();
  await tween(2000, function (k) { lv.set(k, 100); }, E.io);
  flash(800, .6); rain('marigold', 6); await pushInto(T, 51, 60, 1400);
  var M = scene('temple/s2-mandap-reveal'); await show(M, 'cut', 900); drop(T); step('names');
  capTop(5); await cap('The auspicious wedding of', '', ''); var nb = namesBlock('12%', names); await wait(3200);
  var mc = card('<p>' + esc(D.msg || 'We seek your gracious presence and blessings on this sacred occasion.') + '</p>', { bottom: '13%' }); await cont(mc, 'Muhurtham'); await unCard(mc);
  nb.style.transition = 'opacity .6s'; nb.style.opacity = '0'; setTimeout(function () { nb.remove(); }, 700); cap('', '', '');
  /* lamp: muhurtham + countdown */
  var Lp = scene('temple/s3-kuthuvilakku'); await show(Lp, 'push', 1300); step('muhurtham'); rain('snowgold', 4);
  if (mu) { var c1 = card(evHtml(mu, title, true).replace('Save the date', 'Muhurtham') + '<div class="cdw"></div>', { top: '20%' }); if (mu.at) countdown($('.cdw', c1), mu.at.getTime()); await cont(c1, 'Continue'); await unCard(c1); }
  var rest = EVENTS.filter(function (e) { return e !== mu; });
  if (rest.length || D.rsvp) { var Fh = scene('temple/s4-feast-hall'); await show(Fh, 'push', 1300); }
  if (rest.length) { var keep = MAIN; MAIN = null; var ev2 = EVENTS; EVENTS = rest; await eventsCarousel('26%', title); EVENTS = ev2; MAIN = keep; }
  if (D.rsvp) { step('rsvp-form'); var rc = await rsvpCard({ top: '14%' }, title); await unCard(rc); }
  var Fz = scene('temple/s2-mandap-reveal'); await show(Fz, 'push', 1400); rain('marigold', 8);
  sprite(Fz, 'temple/jasmine', 6, 20, 8, { ar: '218/1225' }); sprite(Fz, 'temple/jasmine', 94, 20, 8, { ar: '218/1225' });
  capTop(5); await cap('Blessed be the couple', '', ''); namesBlock('12%', names); SND.bell(523); setTimeout(function () { SND.bell(659); }, 600);
  card('<div class="ck">' + esc(mu && mu.at ? fmtDate(mu.at) : 'With love') + '</div>' + (mu && mu.venue ? '<h3>' + esc(mu.venue) + '</h3>' : '') + '<p>' + esc(D.from ? 'With respects, ' + D.from : 'Your blessings are our greatest gift') + '</p>', { bottom: '9%' });
  finish();
} },

/* ---------------------------------------------------------------- Griha Pravesh (housewarming) */
grihapravesh: { song: 'bhairav', type: 'housewarming', run: async function () {
  var fam = D.to || 'The Sharma Family'; var title = 'Griha Pravesh · ' + fam;
  var S = scene('house/s1-home-door-closed', { kb: false }); put(S); await wait(500);
  var dp = [[18, 83], [33, 85.5], [50, 86.5], [67, 85.5], [82, 83]];
  var diyas = dp.map(function (p) { var e = sprite(S, 'house/diya', p[0], p[1], 11, { ar: '364/314', z: 3 }); e.style.filter = 'brightness(.5) saturate(.6)'; e.style.clipPath = 'inset(42% 0 0 0)'; e.style.transition = 'filter .6s'; return e; });
  var lv = leaves(S, [31.5, 44, 69.5, 77]);
  await cap('Griha Pravesh', 'Welcome to our new home', 'Light the five diyas to open the door');
  step('diyas');
  await tapSpots(dp.map(function (p) { return function () { return P(S, p[0], p[1] - 2); }; }), 'Tap each diya 🪔', function (i, p) { var e = diyas[i]; e.style.clipPath = 'none'; e.style.filter = 'brightness(1.1) drop-shadow(0 0 14px rgba(255,190,80,.95))'; e.style.animation = 'none'; note(523 * Math.pow(2, [0, 2, 4, 7, 9][i] / 12), 0, 1.5, 'sine', .06); burst('spark', p[0], p[1] - 20, 22, 150); });
  cap('', '', ''); SND.bell(784); await wait(500); SND.creak();
  await tween(1900, function (k) { lv.set(k, 98); }, E.io); flash(800, .6); await pushInto(S, 50.5, 60, 1400);
  /* milk boils over */
  var I = scene('house/s2-home-interior'); await show(I, 'cut', 900); drop(S); step('milk'); freeze(I);
  var pot = sprite(I, 'house/milkpot', 50, 74, 52, { ar: '519/589', z: 3 });
  var foam = mk('div', 'foam', null, I.F); foam.style.cssText += ';left:31%;width:38%;top:61%;height:3%;opacity:.0';
  capTop(6); await cap('Ksheera pongal', 'Boil the milk for abundance', '');
  var dripEls = [];
  await hold('Hold to\nboil', 3200, function (p, on) { foam.style.opacity = String(Math.min(1, p * 3)); foam.style.top = (61 - p * 5) + '%'; foam.style.height = (3 + p * 6) + '%'; if (on && Math.random() < .35 + p * .5) { var q = P(I, 38 + Math.random() * 24, 60 - p * 4); add('bubble', q[0], q[1], (Math.random() - .5) * 20, -30 - Math.random() * 40, { s: 2 + Math.random() * 4, g: 0, max: .8 }); if (Math.random() < .2) SND.bubble(); } if (on && Math.random() < .15) smoke(P(I, 50, 55)[0] + (Math.random() - .5) * 60, P(I, 50, 55)[1], 1); });
  [33, 41, 50, 59, 67].forEach(function (x, i) { var d = mk('div', 'drip', null, I.F); d.style.left = x + '%'; d.style.top = '62%'; d.style.height = '0%'; dripEls.push(d); tween(1400 + i * 120, function (k) { d.style.height = (k * (8 + (i % 3) * 3)) + '%'; }, E.out); });
  vib(60); SND.chime(); burst('marigold', W / 2, H * .55, 90, 380);
  await cap('May our home overflow with joy', '', ''); await wait(2600); cap('', '', '');
  /* kalash: details */
  var K = scene('house/s3-kalash'); await show(K, 'push', 1300); step('details'); rain('marigold', 4);
  var tor = sprite(K, 'house/toran', 50, 4, 100, { ar: '989/418', origin: '50% 0%', z: 4 }); (function sw() { tor.style.transform = 'translate(-50%,-50%) rotate(' + Math.sin(performance.now() / 900) * 1.2 + 'deg)'; requestAnimationFrame(sw); })();
  capTop(17); await cap(lead('With joy, we invite you to'), fam, 'Griha Pravesh');
  var ev = MAIN; if (ev) { ev.name = ev.name === 'Celebration' ? 'Griha Pravesh Puja' : ev.name; var c1 = card(evHtml(ev, title, true) + '<div class="cdw"></div><p>' + esc(D.msg || 'Please join us for the puja and lunch, and bless our new home.') + '</p>', { top: '30%' }); if (ev.at) countdown($('.cdw', c1), ev.at.getTime()); await cont(c1, 'Continue'); await unCard(c1); }
  if (EVENTS.length) await eventsCarousel('32%', title);
  if (D.rsvp) { step('rsvp-form'); var rc = await rsvpCard({ top: '14%' }, title); await unCard(rc); }
  capTop(17); await cap('Shubh Griha Pravesh', fam, D.from ? 'With love, ' + D.from : 'Your blessings make a house a home');
  finish();
} },

/* ---------------------------------------------------------------- Kids 3D party (boy / girl) */
kids3d: { song: 'musicbox', type: 'kids', run: async function () {
  document.body.classList.add('kid');
  var girl = CFG.girl, name = (D.to || (girl ? 'Ananya' : 'Aarav')).slice(0, 14), age = D.age;
  var pre = girl ? 'kids/girl-' : 'kids/boy-';
  var P1 = scene(pre + 's1-peeking'); put(P1);
  capTop(6); await cap('Psst… 🤫', 'Someone is hiding!', 'There is a surprise for ' + name);
  step('peek');
  await tapAny('Tap to say hi 👋'); SND.giggle(); vib(20);
  var bub = mk('div', 'bubble', 'Wait, wait! 🎈', ui); bub.style.left = '14%'; bub.style.top = '38%'; requestAnimationFrame(function () { bub.classList.add('on'); });
  await wait(1700); bub.classList.remove('on'); setTimeout(function () { bub.remove(); }, 500);
  /* balloons: one per letter */
  var letters = INDIC.test(name) ? [name] : name.replace(/\s+/g, '').split('').slice(0, 10);
  await cap('Pop the balloons!', '', 'Each one hides a letter'); step('balloons');
  var row = mk('div', 'lrow', letters.map(function (c, i) { return '<span style="color:' + ['#ff5fa8', '#ffb020', '#33b6ff', '#8a6cff', '#3fcf9a', '#ff7a4d'][i % 6] + '">' + esc(c) + '</span>'; }).join(''), ui); row.style.top = '19%';
  var cols = ['pink', 'yellow', 'blue', 'purple', 'green', 'orange'], left = letters.length, bals = [];
  await new Promise(function (res) {
    letters.forEach(function (c, i) {
      var b = mk('div', 'bal', '<b>' + esc(c) + '</b>', ui); b.style.backgroundImage = 'url(' + ART + 'kids/bal-' + cols[i % 6] + '.webp)';
      var n = letters.length, perRow = Math.min(5, n), r = Math.floor(i / perRow), k = i % perRow, rowN = Math.min(perRow, n - r * perRow);
      var bx = (100 / (rowN + 1)) * (k + 1), by = 46 + r * 22; b.style.left = 'calc(' + bx + '% - 11%)'; b.style.top = '110%';
      if (letters.length === 1) { b.style.width = '34%'; b.style.left = '33%'; }
      tween(1200 + i * 120, function (q) { b.style.top = (110 - (110 - by) * q) + '%'; }, E.back).then(function () { b.style.animation = 'hb ' + (1.6 + i * .1) + 's ease-in-out infinite'; });
      function pop() { if (b.dataset.p) return; b.dataset.p = 1; var r2 = b.getBoundingClientRect(), s = st.getBoundingClientRect(); SND.pop(); vib(20); burst('confetti', r2.left - s.left + r2.width / 2, r2.top - s.top + r2.height * .35, 40, 320); b.style.transition = 'transform .15s,opacity .15s'; b.style.transform = 'scale(1.3)'; b.style.opacity = '0'; setTimeout(function () { b.remove(); }, 200); row.children[i].classList.add('in'); SND.chime(); if (--left === 0) res(); }
      b.onpointerdown = function (e) { e.stopPropagation(); pop(); }; bals.push([b, pop]);
    });
    hint('Tap a balloon 🎈');
    if (AUTO) (async function () { await wait(1800); for (var i = 0; i < bals.length; i++) { var r2 = bals[i][0].getBoundingClientRect(), s = st.getBoundingClientRect(); await ghostTap(r2.left - s.left + r2.width / 2, r2.top - s.top + r2.height * .35); bals[i][1](); await wait(300); } ghostHide(); })();
  });
  hint(''); await wait(700);
  /* running in */
  var R = scene(pre + 's2-running'); await show(R, 'push', 1200); step('hello'); SND.boing();
  burst('confetti', W * .2, H * .3, 60, 380); burst('confetti', W * .8, H * .3, 60, 380);
  await cap('', 'Happy Birthday!', ''); row.style.transition = 'top .8s'; row.style.top = '15%';
  /* gifts */
  await wait(900); await cap('', 'Happy Birthday!', 'Open your presents 🎁'); step('gifts');
  var gifts = ['pink', 'green', 'purple'].map(function (c, i) { var e = mk('div', 'sp', null, ui); e.style.cssText = 'position:absolute;z-index:9;width:26%;aspect-ratio:284/321;left:' + (8 + i * 31) + '%;bottom:calc(16% + env(safe-area-inset-bottom));background-image:url(' + ART + 'kids/gift-' + c + '.webp);background-size:contain;background-repeat:no-repeat;transform:scale(0);transition:transform .6s cubic-bezier(.2,1.6,.4,1)'; setTimeout(function () { e.style.transform = 'scale(1)'; }, 200 + i * 180); return e; });
  await wait(800);
  await tapSpots(gifts.map(function (e) { return function () { var r2 = e.getBoundingClientRect(), s = st.getBoundingClientRect(); return [r2.left - s.left + r2.width / 2, r2.top - s.top + r2.height / 2]; }; }), 'Tap each present', function (i, p) {
    var e = gifts[i]; SND.pop(); SND.giggle(); e.style.transform = 'scale(1.2) rotate(8deg)'; setTimeout(function () { e.style.transform = 'scale(0)'; }, 260); burst('confetti', p[0], p[1], 50, 360);
    var pop = mk('div', i === 1 && D.photos[0] ? 'photo' : 'sp', null, ui); pop.style.position = 'absolute'; pop.style.zIndex = 9;
    if (i === 1 && D.photos[0]) { pop.style.backgroundImage = 'url("' + String(D.photos[0]).replace(/"/g, '%22') + '")'; pop.style.width = '34%'; pop.style.aspectRatio = '1'; pop.style.left = '33%'; pop.style.top = '34%'; requestAnimationFrame(function () { pop.classList.add('on'); }); }
    else { pop.style.backgroundImage = 'url(' + ART + (i === 0 ? 'kids/teddy' : girl ? 'kids/bal-pink' : 'kids/boy-jump') + '.webp)'; pop.style.backgroundSize = 'contain'; pop.style.backgroundRepeat = 'no-repeat'; pop.style.width = i === 0 ? '30%' : '26%'; pop.style.aspectRatio = i === 0 ? '291/362' : girl ? '181/297' : '427/766'; pop.style.left = (8 + i * 31) + '%'; pop.style.bottom = '18%'; pop.style.transform = 'translateY(40%) scale(.2)'; pop.style.transition = 'transform .7s cubic-bezier(.2,1.6,.4,1)'; requestAnimationFrame(function () { requestAnimationFrame(function () { pop.style.transform = 'none'; }); }); }
    setTimeout(function () { pop.style.transition = 'opacity .6s'; pop.style.opacity = '0'; setTimeout(function () { pop.remove(); }, 700); }, 2600);
  });
  gifts.forEach(function (e) { e.remove(); }); await wait(1200);
  /* parade with the name on the banner (the parade art has the boy, so the girl's story goes straight to the cake) */
  if (!girl) {
  var Pa = scene('kids/s5-animal-parade', { kb: false }); await show(Pa, 'push', 1200); step('parade'); freeze(Pa);
  var ban = mk('div', 'banner', esc('Happy Birthday ' + name + '!'), Pa.F); ban.style.left = '14%'; ban.style.right = '10%'; ban.style.top = '17%';
  row.style.opacity = '0'; await cap('', '', 'The animals are coming to your party!');
  await swipe('left', 'Swipe to start the parade ›', function (p) { Pa.F.style.transform = 'translate(calc(-50% - ' + p * 4 + '%),-50%) scale(' + (Pa.zFix || 1) + ')'; });
  var t0 = performance.now(), dr = setInterval(SND.drum, 430); rain('confetti', 14);
  await tween(3800, function (k) { var b = Math.abs(Math.sin((performance.now() - t0) / 215)) * 1.2; Pa.F.style.transform = 'translate(calc(-50% - ' + (4 - k * 6) + '%),calc(-50% - ' + b + '%)) scale(' + ((Pa.zFix || 1) + k * .05) + ')'; }, E.lin);
  clearInterval(dr); rain(null, 0); cap('', '', '');
  } else { row.style.opacity = '0'; }
  /* cake */
  var boyMap = { 1: '01', 2: '02', 3: '09', 4: '08', 5: '05', 6: '07', 7: '03', 9: '04' };
  var lit = girl ? 'kids/girl-s3-cake-variation' : 'kids/boycake-' + (boyMap[age] || '05'), out = girl ? 'kids/girl-s3-cake' : 'kids/boy-s4-candles-blown-out';
  var Ck = scene(lit); await show(Ck, 'push', 1200); step('cake');
  capTop(5); await cap('', 'Make a wish, ' + name + '!', 'Close your eyes… then blow!');
  var glow = mk('div', '', null, Ck.F); glow.style.cssText = 'position:absolute;left:20%;right:20%;top:52%;height:14%;background:radial-gradient(ellipse,rgba(255,200,110,.55),rgba(255,200,110,0) 70%);mix-blend-mode:screen;animation:hb 1.1s ease-in-out infinite';
  await hold('Hold to\nblow 🌬️', 1800, function (p, on) { if (on && Math.random() < .3) SND.blow(); glow.style.opacity = String(1 - p); });
  vib(50); var ps = P(Ck, 50, 58); smoke(ps[0] - 40, ps[1], 6); smoke(ps[0], ps[1], 6); smoke(ps[0] + 40, ps[1], 6);
  var O = scene(out); await show(O, 'cut', 700);
  SND.chime(); burst('confetti', W / 2, H * .5, 200, 520); setTimeout(function () { burst('confetti', W * .2, H * .4, 90, 420); burst('confetti', W * .8, H * .4, 90, 420); }, 400);
  await cap('', 'Yaaay! 🎉', age ? name + ' is ' + age + ' years old today!' : 'Happy birthday, ' + name + '!');
  rain('confetti', 6); await wait(2200);
  /* party invite (if date given) */
  if (MAIN && MAIN.at) { step('invite'); if (MAIN.name === 'Celebration') MAIN.name = name + "'s Birthday Party"; var c1 = card(evHtml(MAIN, name + "'s birthday", true).replace('Save the date', "You're invited!") + '<div class="cdw"></div>', { top: '24%' }); countdown($('.cdw', c1), MAIN.at.getTime()); await cont(c1, 'Continue'); await unCard(c1); if (D.rsvp) { var rc = await rsvpCard({ top: '12%' }, name + "'s birthday"); await unCard(rc); } }
  /* final card */
  card('<div class="ck">' + esc(age ? 'Happy ' + age + ord(age) + ' birthday' : 'Happy birthday') + '</div><h3>' + esc(name) + ' 🎂</h3><p>' + esc(D.msg || 'You make every day brighter with your giggles and hugs. Have the most magical birthday ever!') + '</p>' + (D.from ? '<div class="meta"><b>With love, ' + esc(D.from) + '</b></div>' : ''), { bottom: '8%' });
  finish();
} },

/* ---------------------------------------------------------------- Rooftop Soirée (adult birthday) */
rooftop: { song: 'lounge', type: 'birthday', run: async function () {
  var name = (D.to || 'Riya').slice(0, 20), age = D.age;
  var R = scene('roof/s1-rooftop', { filter: 'brightness(.22) saturate(.6)' }); put(R);
  R.im.style.transition = 'filter 2.4s ease';
  capTop(8); await cap('Tonight is all about', name, 'The city is waiting for you');
  step('lights');
  await tapAny('Tap to light up the night ✨');
  R.im.style.filter = 'brightness(1) saturate(1)'; SND.chime();
  for (var i = 0; i < 14; i++) (function (i) { setTimeout(function () { var p = P(R, 6 + i * 6.5, 3 + Math.sin(i * .9) * 2.5); burst('spark', p[0], p[1], 10, 90); SND.fizz(); }, i * 120); })(i);
  rain('snowgold', 5); await wait(2600);
  /* gift: swipe up */
  await cap('A little something', 'for ' + name, ''); step('gift');
  var box = mk('div', 'sp', null, ui); box.style.cssText = 'position:absolute;z-index:9;left:22%;width:56%;aspect-ratio:717/568;bottom:calc(18% + env(safe-area-inset-bottom));background-image:url(' + ART + 'roof/box-closed.webp);background-size:contain;background-repeat:no-repeat;transform:scale(0);transition:transform .8s cubic-bezier(.2,1.5,.4,1)';
  requestAnimationFrame(function () { requestAnimationFrame(function () { box.style.transform = 'scale(1)'; }); }); await wait(900);
  var shake = setInterval(function () { box.style.transform = 'rotate(' + (Math.random() * 6 - 3) + 'deg)'; setTimeout(function () { box.style.transform = 'none'; }, 120); }, 1600);
  await swipe('up', 'Swipe up to open ↑', function (p) { box.style.transform = 'translateY(' + (-p * 12) + '%) scale(' + (1 + p * .05) + ')'; });
  clearInterval(shake); flash(700, .6); SND.whoosh(); vib(40);
  box.style.transition = 'none'; box.style.backgroundImage = 'url(' + ART + 'roof/box-open.webp)'; box.style.aspectRatio = '699/656'; box.style.transform = 'none';
  var f = mk('div', 'foil', esc(age ? String(age) : name.charAt(0).toUpperCase()), ui); f.style.top = '24%';
  requestAnimationFrame(function () { f.classList.add('in'); }); for (var j = 0; j < 3; j++) setTimeout(function () { burst('spark', W / 2, H * .55, 50, 320); SND.fizz(); }, j * 300);
  await cap('', '', age ? 'Cheers to ' + age + ' fabulous years' : 'Cheers to you!'); await wait(2800);
  f.style.opacity = '0'; box.style.transition = 'opacity .6s'; box.style.opacity = '0'; setTimeout(function () { f.remove(); box.remove(); }, 700); cap('', '', '');
  /* cake + sparklers */
  var C = scene('roof/s2-cake'); await show(C, 'push', 1300); step('cake');
  capTop(6); await cap('Make a wish', name, 'Hold to light the sparklers');
  var sp = [[44, 46], [50, 44], [56, 46]];
  await hold('Hold to\nwish ✨', 2200, function (p, on) { if (on && Math.random() < .6) { var q = P(C, sp[(Math.random() * 3) | 0][0], sp[0][1] - 3); burst('spark', q[0], q[1], 3 + p * 6, 120 + p * 160); if (Math.random() < .2) SND.fizz(); } });
  for (var k = 0; k < 6; k++) setTimeout(function () { firework(W * (.15 + Math.random() * .7), H * (.08 + Math.random() * .2)); }, k * 420);
  await cap('', 'Happy Birthday, ' + name + '!', ''); await wait(2600);
  /* message card */
  var G = scene('roof/s3-gift-box'); await show(G, 'push', 1300); step('message'); cap('', '', '');
  var mc = card('<div class="ck">' + esc(age ? 'Happy ' + age + ord(age) + ' birthday' : 'Happy birthday') + '</div><h3>' + esc(name) + '</h3><span class="orn"></span><p>' + esc(D.msg || 'Here is to more laughter, bigger dreams and nights as bright as this city. You deserve every bit of it.') + '</p>' + (D.from ? '<div class="meta"><b>With love, ' + esc(D.from) + '</b></div>' : ''), { top: '22%' });
  if (MAIN && MAIN.at) { await cont(mc, 'Party details'); await unCard(mc); if (MAIN.name === 'Celebration') MAIN.name = name + "'s Birthday Dinner"; var c1 = card(evHtml(MAIN, name + "'s birthday", true).replace('Save the date', "You're invited") + '<div class="cdw"></div>', { top: '20%' }); countdown($('.cdw', c1), MAIN.at.getTime()); await cont(c1, 'Continue'); await unCard(c1); if (D.rsvp) { var rc = await rsvpCard({ top: '12%' }, name + "'s birthday"); await unCard(rc); } card('<h3>See you there, ✨</h3><p>' + esc(fmtDate(MAIN.at)) + '</p>', { bottom: '10%' }); }
  setInterval(function () { if (!document.hidden) firework(W * (.15 + Math.random() * .7), H * (.06 + Math.random() * .14), true); }, 1800);
  finish();
} }
};
DESIGNS.kids3dboy = Object.assign({}, DESIGNS.kids3d, { girl: false });
DESIGNS.kids3dgirl = Object.assign({}, DESIGNS.kids3d, { girl: true });
function ord(n) { return (n % 100 > 10 && n % 100 < 14) ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' })[n % 10] || 'th'; }

/* ---------------------------------------------------------------- boot */
CFG = DESIGNS[KEY] || DESIGNS.goldengates;
if (D.date || D.venue) MAIN = mkEv(CFG.type === 'wedding' ? (/nikah/i.test(D.msg + HV.events) ? 'Nikah' : 'Wedding') : CFG.type === 'housewarming' ? 'Griha Pravesh Puja' : 'Celebration', D.date, D.time, D.venue, D.address, D.dress);
if (!MAIN && EVENTS.length) { MAIN = EVENTS.shift(); }
if (!HV.to && !HV.date && CFG.type !== 'kids' && CFG.type !== 'birthday') { var d0 = new Date(); d0.setDate(d0.getDate() + 45); MAIN = mkEv(CFG.type === 'housewarming' ? 'Griha Pravesh Puja' : 'Wedding', d0, '10:30', CFG.type === 'housewarming' ? 'Our new home, Jubilee Hills' : 'The Leela Palace', 'Hyderabad'); if (CFG.type === 'wedding') { var d1 = new Date(d0); d1.setDate(d0.getDate() - 1); EVENTS = [mkEv('Mehendi & Sangeet', d1, '18:00', 'Taj Falaknuma', 'Hyderabad', 'Festive colours'), mkEv('Reception', new Date(d0.getTime() + 864e5), '19:30', 'The Leela Palace', 'Hyderabad')]; } }
var preload = [];
function pre(src) { return new Promise(function (r) { var i = new Image(); i.onload = i.onerror = r; i.src = ART + src + '.webp'; preload.push(i); }); }
var first = { goldengates: ['gates/s1-gates-closed', 'gates/s2-gates-open', 'gates/bow-l', 'gates/bow-r'], curtainpalace: ['curtain/s1-curtains-closed', 'curtain/s2-palace-reveal'], templebells: ['temple/s1-temple-doors-closed', 'temple/bell'], grihapravesh: ['house/s1-home-door-closed', 'house/diya'], kids3dboy: ['kids/boy-s1-peeking'], kids3dgirl: ['kids/girl-s1-peeking'], rooftop: ['roof/s1-rooftop'] }[KEY] || [];
addEventListener('resize', size); size(); requestAnimationFrame(frame);
document.addEventListener('contextmenu', function (e) { e.preventDefault(); });
Promise.race([Promise.all(first.map(pre)), new Promise(function (r) { setTimeout(r, 6000); })]).then(function () {
  loadEl.style.opacity = '0'; setTimeout(function () { loadEl.remove(); }, 700);
  CFG.run().catch(function (e) { console.error(e); finish(); });
});
})();
