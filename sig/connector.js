
/* ===================================================================
 * r89 (26 Nov 2026): H View Signature designs (photo-real art + live
 * gestures), one shared player /assets/ixpack/_sig/play.html in an iframe.
 * RSVP from the guest is passed to the site's normal RSVP handler.
 * =================================================================== */
(function () {
  'use strict';
  var X = window.IXScenes; if (!X || !X.define) return;
  var MAP = { 'wedding-invitation': ['goldengates', 'curtainpalace', 'templebells'], 'housewarming': ['grihapravesh'], 'birthday': ['kids3dboy', 'kids3dgirl', 'rooftop'], 'birthday-invitation': ['kids3dboy', 'kids3dgirl', 'rooftop'] };
  function make(prod, key) {
    return function (kit) {
      var el = kit.stage.el, d = kit.data || {}, fr = null, done = false, onMsg = null;
      function build() {
        if (fr) fr.remove();
        var ph = d.photoUrl || d.photo || '';
        var hv = { design: key, to: d.to || '', from: d.from || '', msg: d.msg || '', age: d.age || '', date: d.date || '', time: d.time || '', venue: d.venue || '', address: d.address || '', dress: d.dress || '', events: d.events || '', rsvp: !(d.rsvp === '0' || d.rsvp === 0 || d.rsvp === false || d.rsvp === 'no'), photos: ph ? [ph] : [], autoplay: kit.mode !== 'recipient', muted: kit.mode !== 'recipient' };
        fr = document.createElement('iframe');
        fr.className = 'ix-pack-frame'; fr.title = 'Interactive invitation'; fr.setAttribute('allow', 'autoplay; vibrate');
        fr.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0;z-index:4;background:#0d0906;display:block';
        fr.src = '/assets/ixpack/_sig/play.html?v=1#' + encodeURIComponent(JSON.stringify(hv));
        el.appendChild(fr);
      }
      function reply(msg) { try { fr && fr.contentWindow.postMessage({ hv: 'rsvp-ok', message: msg }, '*'); } catch (e) {} }
      return {
        resize: function () {},
        start: function () {
          done = false; build(); kit.hint('');
          onMsg = function (e) {
            if (!fr || e.source !== fr.contentWindow || !e.data || typeof e.data !== 'object') return;
            if (e.data.hv === 'step') kit.ev && kit.ev('step', { x: String(e.data.name || '').slice(0, 40) });
            if (e.data.hv === 'rsvp') {
              var r = e.data.data || {};
              if (kit.mode === 'recipient' && kit.opts && kit.opts.onRsvp) {
                kit.opts.onRsvp({ response: r.response || 'yes', name: r.name || '', guests: r.guests || '1', note: r.note || '' }).then(function (j) { reply((j && j.message) || 'Thank you! Your reply is sent.'); }, function () { reply('Sorry, the reply could not be sent. Please try again.'); });
              } else reply('This is a preview. Guests reply on the shared link.');
            }
            if (e.data.hv === 'done' && !done) { done = true; setTimeout(function () { kit.reveal(); }, 7000); }
          };
          window.addEventListener('message', onMsg);
        },
        update: function () {},
        draw: function () { var g = kit.stage.ctx; if (g) { g.fillStyle = '#0d0906'; g.fillRect(0, 0, kit.W(), kit.H()); } },
        destroy: function () { if (onMsg) window.removeEventListener('message', onMsg); if (fr) fr.remove(); fr = null; }
      };
    };
  }
  Object.keys(MAP).forEach(function (p) { MAP[p].forEach(function (k) { X.define(p + ':' + k, make(p, k)); }); });
})();
