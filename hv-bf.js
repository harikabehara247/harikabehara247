/* H View Black Friday sale (r78, 25 Nov 2026): a slim strip at the top of every page and one well-timed offer card.
 * Loaded by app.php only while the sale runs (setting bf_until) and never for paying members.
 * Card rules: never on arrival; after ~20 s of reading plus some scrolling, or when a desktop visitor heads for the tabs;
 * at most once a day; one tap to close; centred card on desktop, bottom sheet on phones (no full-screen cover).
 * Views, clicks and closes are counted in hv_cta_clicks (placement bf_strip / bf_card) via /whatsapp/cta. */
(function () {
  'use strict';
  var d = document, w = window, path = location.pathname;
  if (w.__hvBf || /^\/(account|admin|business)\//.test(path)) return; w.__hvBf = 1;
  var END = new Date('2026-11-30T23:59:59+05:30').getTime();
  if (Date.now() > END) return;
  var URL = '/account/plan/?from=bf';
  var PLANS = [
    { k: 'bf3', m: 3, p: 199 }, { k: 'bf6', m: 6, p: 299, tag: 'Popular' },
    { k: 'bf8', m: 8, p: 399 }, { k: 'bf12', m: 12, p: 499, tag: 'Best value' }
  ];
  var PERKS = [
    ['✨', 'Every premium wish & invitation', 'No daily limit'],
    ['🎬', 'Photo-real animated surprises', 'Gift box, 3D card, secret message'],
    ['🛠️', 'Premium tools, no daily limits', 'Plagiarism checker Pro and more'],
    ['🚫', 'All free tools, zero ads', 'Nothing renews automatically']
  ];
  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  function sess(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } }
  function count(place, act) { try { var f = new FormData(); f.append('p', place); f.append('a', act); f.append('u', path.slice(0, 190)); navigator.sendBeacon && navigator.sendBeacon('/whatsapp/cta', f); } catch (e) {} }
  function left() {
    var s = Math.max(0, Math.floor((END - Date.now()) / 1000));
    return { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function css() {
    var c = d.createElement('style');
    c.textContent =
      '.hvbf-strip{position:relative;z-index:60;display:flex;align-items:center;justify-content:center;gap:10px 16px;flex-wrap:wrap;padding:8px 44px 8px 14px;background:linear-gradient(90deg,#0b0f1f,#1b2563 55%,#4b2a8f);color:#fff;font:500 14px/1.35 Inter,system-ui,sans-serif;text-align:center}' +
      '.hvbf-strip b{color:#ffd36b;letter-spacing:.04em}.hvbf-strip a{color:#0b0f1f;background:#ffd36b;border-radius:999px;padding:5px 14px;font-weight:700;text-decoration:none;white-space:nowrap}' +
      '.hvbf-strip time{font-variant-numeric:tabular-nums;opacity:.9}.hvbf-x{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:30px;height:30px;border:0;border-radius:50%;background:rgba(255,255,255,.12);color:#fff;font-size:18px;line-height:1;cursor:pointer}' +
      '.hvbf-ov{position:fixed;inset:0;z-index:2147483000;background:rgba(8,10,24,.55);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:18px;opacity:0;transition:opacity .25s}' +
      '.hvbf-ov.is-on{opacity:1}.hvbf-card{position:relative;width:min(560px,100%);max-height:92vh;overflow:auto;border-radius:24px;background:radial-gradient(120% 90% at 100% 0,#3b2a8f 0,#141a3d 45%,#0b0f1f 100%);color:#fff;box-shadow:0 30px 80px rgba(0,0,0,.45);padding:26px 24px 22px;font:15px/1.45 Inter,system-ui,sans-serif;transform:translateY(16px) scale(.98);transition:transform .3s cubic-bezier(.2,.8,.2,1)}' +
      '.hvbf-ov.is-on .hvbf-card{transform:none}.hvbf-card .hvbf-x{top:16px;transform:none;right:14px}' +
      '.hvbf-k{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#0b0f1f;background:#ffd36b;border-radius:999px;padding:4px 10px}' +
      '.hvbf-card h2{text-transform:none!important;letter-spacing:-.01em;margin:12px 0 12px;font:800 26px/1.15 Inter,system-ui,sans-serif;color:#fff}.hvbf-card h2 em{font-style:normal;background:linear-gradient(90deg,#ffd36b,#ff8fc4);-webkit-background-clip:text;background-clip:text;color:transparent}' +
      '.hvbf-sub{margin:0 0 14px;color:#c9cdf0}.hvbf-cd{display:flex;gap:8px;margin:0 0 16px}.hvbf-cd span{flex:1;text-align:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:7px 4px}' +
      '.hvbf-cd b{display:block;font-size:20px;font-variant-numeric:tabular-nums}.hvbf-cd small{font-size:11px;color:#aab0dd;text-transform:uppercase;letter-spacing:.08em}' +
      '.hvbf-perks{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 16px;padding:0;list-style:none}.hvbf-perks li{display:flex;gap:10px;align-items:flex-start;background:rgba(255,255,255,.06);border-radius:14px;padding:10px 12px}' +
      '.hvbf-perks i{font-style:normal;font-size:20px;line-height:1.2}.hvbf-perks b{display:block;font-size:14px}.hvbf-perks span{display:block;font-size:12.5px;color:#b8bde6;line-height:1.35}' +
      '.hvbf-plans{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:0 0 14px}.hvbf-plan{position:relative;border:1.5px solid rgba(255,255,255,.18);background:rgba(255,255,255,.04);color:#fff;border-radius:14px;padding:12px 6px 10px;text-align:center;cursor:pointer;font:inherit}' +
      '.hvbf-plan b{display:block;font-size:20px}.hvbf-plan small{display:block;font-size:12px;color:#b8bde6}.hvbf-plan em{position:absolute;top:-9px;left:50%;transform:translateX(-50%);white-space:nowrap;font-style:normal;font-size:10.5px;font-weight:700;color:#0b0f1f;background:#ffd36b;border-radius:999px;padding:2px 8px}' +
      '.hvbf-plan[aria-pressed=true]{border-color:#ffd36b;background:rgba(255,211,107,.12);box-shadow:0 0 0 3px rgba(255,211,107,.18)}' +
      '.hvbf-go{display:block;text-align:center;text-decoration:none;font-weight:800;font-size:16px;color:#0b0f1f;background:linear-gradient(90deg,#ffd36b,#ffb3d6);border-radius:14px;padding:14px}' +
      '.hvbf-fine{margin:10px 0 0;text-align:center;font-size:12px;color:#9ea4d6}.hvbf-later{background:none;border:0;color:#c9cdf0;text-decoration:underline;cursor:pointer;font:inherit;font-size:13px;display:block;margin:8px auto 0}' +
      '.hvbf-card{width:min(500px,100%)}.hvbf-scratch{position:relative;height:150px;border-radius:18px;overflow:hidden;margin:0 0 12px;background:linear-gradient(135deg,#fff7e0,#ffe3f0);box-shadow:inset 0 0 0 1px rgba(255,255,255,.4)}' +
      '.hvbf-prize{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:10px 16px;color:#1b1440}' +
      '.hvbf-prize small{font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#a0306b}.hvbf-prize b{font:800 23px/1.2 Inter,system-ui,sans-serif;margin:4px 0}.hvbf-prize span{font-size:13px;color:#4a4270}' +
      '.hvbf-scratch canvas{position:absolute;inset:0;width:100%;height:100%;touch-action:none;cursor:grab;transition:opacity .45s}.hvbf-scratch.is-revealed canvas{opacity:0;pointer-events:none}' +
      '.hvbf-tap{position:absolute;right:10px;bottom:8px;border:0;border-radius:999px;background:rgba(27,20,64,.7);color:#fff;font:600 12px Inter,system-ui,sans-serif;padding:5px 11px;cursor:pointer}.hvbf-scratch.is-revealed .hvbf-tap,.hvbf-scratch.is-scratching .hvbf-tap{display:none}' +
      '.hvbf-scratch{transition:height .4s ease}.hvbf-scratch.is-revealed{height:104px}.hvbf-cd.is-small b{font-size:15px}.hvbf-cd.is-small span{padding:4px}.hvbf-cd.is-small small{font-size:10px}' +
      '.hvbf-scratch.is-revealed .hvbf-prize b{animation:hvbfPop .6s cubic-bezier(.2,1.6,.4,1)}@keyframes hvbfPop{0%{transform:scale(.7)}100%{transform:scale(1)}}' +
      '.hvbf-more.is-in{animation:hvbfIn .45s ease both}@keyframes hvbfIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}.hvbf-conf{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}' +
      '.hvbf-perks li{padding:9px 11px}.hvbf-perks b{font-size:13.5px}.hvbf-perks span{font-size:12px}.hvbf-plan{padding:12px 4px 9px}.hvbf-plan b{font-size:19px}.hvbf-plan small{font-size:11.5px}' +
      '@media(max-width:640px){.hvbf-scratch{height:140px}.hvbf-prize b{font-size:20px}.hvbf-strip{font-size:12.5px;padding:7px 40px 7px 10px}.hvbf-strip .hvbf-long{display:none}.hvbf-ov{align-items:flex-end;padding:0}' +
      '.hvbf-card{width:100%;border-radius:22px 22px 0 0;padding:22px 16px 16px;max-height:88vh}.hvbf-card h2{font-size:22px}.hvbf-perks{grid-template-columns:1fr;gap:8px}.hvbf-perks li{padding:8px 10px}' +
      '.hvbf-plans{grid-template-columns:repeat(2,1fr);gap:12px 8px}.hvbf-cd b{font-size:17px}}';
    d.head.appendChild(c);
  }
  function strip() {
    if (sess('hvbf_strip_x')) return;
    var s = d.createElement('div'); s.className = 'hvbf-strip'; s.setAttribute('role', 'region'); s.setAttribute('aria-label', 'Black Friday offer');
    s.innerHTML = '<span><b>BLACK FRIDAY</b> <span class="hvbf-long">· All H View Premium: wishes, invitations &amp; tools, no limits, no ads ·</span> from ₹199</span><time aria-label="Time left"></time><a href="' + URL + '&amp;src=strip" data-hvbf-open>🎁 Reveal my offer</a><button class="hvbf-x" type="button" aria-label="Close">×</button>';
    d.body.insertBefore(s, d.body.firstChild);
    var t = s.querySelector('time');
    function tick() { var l = left(); t.textContent = 'Ends in ' + (l.d ? l.d + 'd ' : '') + pad(l.h) + ':' + pad(l.m) + ':' + pad(l.s); }
    tick(); setInterval(tick, 1000);
    s.querySelector('a').addEventListener('click', function (e) { count('bf_strip', 'click'); e.preventDefault(); card('strip', true); });
    s.querySelector('.hvbf-x').addEventListener('click', function () { s.remove(); sess('hvbf_strip_x', '1'); count('bf_strip', 'close'); });
  }
  var shown = false;
  function confetti(host) {
    var c = d.createElement('canvas'), r = host.getBoundingClientRect(), dpr = Math.min(2, w.devicePixelRatio || 1);
    c.className = 'hvbf-conf'; c.width = r.width * dpr; c.height = r.height * dpr; host.appendChild(c);
    var x = c.getContext('2d'), cols = ['#ffd36b', '#ff8fc4', '#8fb3ff', '#ffffff', '#b69bff'], P = [];
    for (var i = 0; i < 90; i++) P.push({ x: c.width / 2, y: c.height * 0.35, vx: (Math.random() - .5) * 14 * dpr, vy: (-Math.random() * 11 - 3) * dpr, s: (3 + Math.random() * 5) * dpr, r: Math.random() * 6, c: cols[i % cols.length], t: 0 });
    var f = 0; (function step() {
      x.clearRect(0, 0, c.width, c.height); f++;
      P.forEach(function (p) { p.vy += .35 * dpr; p.x += p.vx; p.y += p.vy; p.r += .15; x.save(); x.translate(p.x, p.y); x.rotate(p.r); x.fillStyle = p.c; x.globalAlpha = Math.max(0, 1 - f / 90); x.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); x.restore(); });
      if (f < 90) requestAnimationFrame(step); else c.remove();
    })();
  }
  function card(why, force) {
    if (d.querySelector('.hvbf-ov')) return;
    if (!force) { if (shown) return; shown = true; if (Date.now() - (+store('hvbf_card_at') || 0) < 864e5) return; }
    store('hvbf_card_at', String(Date.now())); count('bf_card', force ? 'open' : 'view');
    var pick = 'bf12', revealed = false;
    var ov = d.createElement('div'); ov.className = 'hvbf-ov'; ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-labelledby', 'hvbf-h');
    ov.innerHTML = '<div class="hvbf-card"><button class="hvbf-x" type="button" aria-label="Close">×</button>' +
      '<span class="hvbf-k">🎁 Black Friday surprise</span><h2 id="hvbf-h">Scratch to reveal your offer</h2>' +
      '<div class="hvbf-scratch"><div class="hvbf-prize"><small>You unlocked</small><b>12 months of Premium for ₹499</b><span>the price of 1 month of our top plan · or from ₹199</span></div>' +
      '<canvas aria-hidden="true"></canvas><button type="button" class="hvbf-tap">Tap to reveal</button></div>' +
      '<div class="hvbf-cd" aria-label="Time left in the sale"><span><b data-u="d">0</b><small>days</small></span><span><b data-u="h">00</b><small>hrs</small></span><span><b data-u="m">00</b><small>mins</small></span><span><b data-u="s">00</b><small>secs</small></span></div>' +
      '<div class="hvbf-more" hidden><ul class="hvbf-perks">' + PERKS.map(function (p) { return '<li><i aria-hidden="true">' + p[0] + '</i><div><b>' + p[1] + '</b><span>' + p[2] + '</span></div></li>'; }).join('') + '</ul>' +
      '<div class="hvbf-plans" role="group" aria-label="Choose how long">' + PLANS.map(function (p) {
        return '<button type="button" class="hvbf-plan" data-k="' + p.k + '" aria-pressed="' + (p.k === pick) + '">' + (p.tag ? '<em>' + p.tag + '</em>' : '') + '<b>₹' + p.p + '</b><small>' + p.m + ' months · ₹' + Math.round(p.p / p.m) + '/mo</small></button>';
      }).join('') + '</div>' +
      '<a class="hvbf-go" href="#">Get 12 months for ₹499 →</a>' +
      '<p class="hvbf-fine">Pay once by UPI · Premium starts right away · Ends Mon 30 Nov, 11:59 PM IST</p></div>' +
      '<button class="hvbf-later" type="button">Maybe later</button></div>';
    d.body.appendChild(ov);
    requestAnimationFrame(function () { ov.classList.add('is-on'); });
    var cardEl = ov.querySelector('.hvbf-card'), go = ov.querySelector('.hvbf-go'), more = ov.querySelector('.hvbf-more');
    function setPick(k) {
      pick = k; var p = PLANS.filter(function (x) { return x.k === k; })[0];
      ov.querySelectorAll('.hvbf-plan').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-k') === k ? 'true' : 'false'); });
      go.textContent = 'Get ' + p.m + ' months for ₹' + p.p + ' →'; go.href = URL + '&src=card&plan=' + k;
    }
    setPick(pick);
    ov.querySelectorAll('.hvbf-plan').forEach(function (b) { b.addEventListener('click', function () { setPick(b.getAttribute('data-k')); }); });
    /* scratch foil */
    var box = ov.querySelector('.hvbf-scratch'), cv = box.querySelector('canvas'), cx = cv.getContext('2d'), dpr = Math.min(2, w.devicePixelRatio || 1), moves = 0, down = false;
    function paint() {
      var r = box.getBoundingClientRect(); cv.width = r.width * dpr; cv.height = r.height * dpr;
      var g = cx.createLinearGradient(0, 0, cv.width, cv.height); g.addColorStop(0, '#b8862b'); g.addColorStop(.35, '#ffe39a'); g.addColorStop(.6, '#d9a441'); g.addColorStop(1, '#fff0c2');
      cx.fillStyle = g; cx.fillRect(0, 0, cv.width, cv.height);
      for (var i = 0; i < 900; i++) { cx.fillStyle = 'rgba(255,255,255,' + Math.random() * .25 + ')'; cx.fillRect(Math.random() * cv.width, Math.random() * cv.height, dpr, dpr); }
      cx.fillStyle = 'rgba(90,55,0,.75)'; cx.font = '700 ' + 18 * dpr + 'px Inter,system-ui,sans-serif'; cx.textAlign = 'center'; cx.textBaseline = 'middle';
      cx.fillText('✨ Scratch here ✨', cv.width / 2, cv.height / 2);
    }
    paint();
    function at(e) { var r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * dpr, (e.clientY - r.top) * dpr]; }
    function scratch(e) {
      if (!down || revealed) return; e.preventDefault(); var p = at(e);
      cx.globalCompositeOperation = 'destination-out'; cx.beginPath(); cx.arc(p[0], p[1], 28 * dpr, 0, Math.PI * 2); cx.fill(); cx.globalCompositeOperation = 'source-over';
      if (++moves % 8 === 0) { var data = cx.getImageData(0, 0, cv.width, cv.height).data, clear = 0, n = 0; for (var i = 3; i < data.length; i += 64) { n++; if (data[i] === 0) clear++; } if (clear / n > .35) reveal('scratch'); }
    }
    cv.addEventListener('pointerdown', function (e) { down = true; try { cv.setPointerCapture(e.pointerId); } catch (x) {} box.classList.add('is-scratching'); scratch(e); });
    cv.addEventListener('pointermove', scratch);
    cv.addEventListener('pointerup', function () { down = false; });
    box.querySelector('.hvbf-tap').addEventListener('click', function () { reveal('tap'); });
    function reveal(how) {
      if (revealed) return; revealed = true; box.classList.add('is-revealed'); count('bf_card', 'reveal_' + how);
      ov.querySelector('#hvbf-h').textContent = 'Your Black Friday offer is unlocked!';
      confetti(cardEl);
      setTimeout(function () { more.hidden = false; more.classList.add('is-in'); ov.querySelector('.hvbf-cd').classList.add('is-small'); go.focus({ preventScroll: true }); setTimeout(function () { try { go.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (x) {} }, 450); }, 380);
    }
    var cd = {}; ov.querySelectorAll('[data-u]').forEach(function (b) { cd[b.getAttribute('data-u')] = b; });
    function tick() { var l = left(); cd.d.textContent = l.d; cd.h.textContent = pad(l.h); cd.m.textContent = pad(l.m); cd.s.textContent = pad(l.s); }
    tick(); var iv = setInterval(tick, 1000);
    function close(how) { clearInterval(iv); ov.classList.remove('is-on'); setTimeout(function () { ov.remove(); }, 250); d.removeEventListener('keydown', esc); count('bf_card', how + (revealed ? '' : '_unscratched')); }
    function esc(e) { if (e.key === 'Escape') close('close'); }
    d.addEventListener('keydown', esc);
    ov.addEventListener('click', function (e) { if (e.target === ov) close('close'); });
    ov.querySelector('.hvbf-x').addEventListener('click', function () { close('close'); });
    ov.querySelector('.hvbf-later').addEventListener('click', function () { close('later'); });
    go.addEventListener('click', function () { count('bf_card', 'click_' + pick.replace('bf', 'm')); });
  }
  function watch() {
    /* card: ~20 s of visible reading plus 30% scroll (or 45 s on short pages), or desktop exit intent after 10 s */
    var seen = 0, deep = false, t0 = Date.now();
    w.addEventListener('scroll', function () { var h = d.documentElement; if ((h.scrollTop + innerHeight) / Math.max(1, h.scrollHeight) > 0.3) deep = true; }, { passive: true });
    var iv = setInterval(function () {
      if (d.visibilityState !== 'visible' || d.querySelector('.ix-sheet.is-open,.ix-hlp.is-open,[aria-modal=true]:not(.hvbf-ov)')) return;
      seen++; if ((seen >= 20 && deep) || seen >= 45) { clearInterval(iv); card('time'); }
    }, 1000);
    if (matchMedia('(pointer:fine)').matches) d.addEventListener('mouseout', function (e) { if (!e.relatedTarget && e.clientY < 8 && Date.now() - t0 > 10000) { clearInterval(iv); card('exit'); } });
  }
  function start() {
    /* never interrupt someone opening a greeting that was sent to them */
    if (w.IX && w.IX.mode === 'recipient') return;
    css(); strip(); watch();
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
})();
