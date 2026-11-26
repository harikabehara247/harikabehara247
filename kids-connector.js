
/* ===================================================================
 * r88 (26 Nov 2026): kids' premium birthday designs, packaged pages in
 * /assets/ixpack/birthday--<key>/index.html shown in a same-site iframe.
 * zoo3d = live 3D Toy Zoo Party (three.js); kid* = photo-real party themes.
 * =================================================================== */
(function () {
  'use strict';
  var X = window.IXScenes; if (!X || !X.define) return;
  ['zoo3d', 'kidroyal', 'kidsparkler', 'kidlantern', 'kidpopwall'].forEach(function (key) {
    X.define('birthday:' + key, function (kit) {
      var el = kit.stage.el, d = kit.data || {}, fr = null, done = false, onMsg = null;
      function build() {
        if (fr) fr.remove();
        var ph = d.photoUrl || d.photo || '';
        var hv = { to: d.to || 'Ananya', from: d.from || '', msg: d.msg || '', age: d.age || '', photos: ph ? [ph] : [], autoplay: kit.mode !== 'recipient', muted: kit.mode !== 'recipient' };
        fr = document.createElement('iframe');
        fr.className = 'ix-pack-frame'; fr.title = 'Interactive birthday surprise'; fr.setAttribute('allow', 'autoplay; vibrate');
        fr.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0;z-index:4;background:#bfe6ff;display:block';
        fr.src = '/assets/ixpack/birthday--' + key + '/index.html#' + encodeURIComponent(JSON.stringify(hv));
        el.appendChild(fr);
      }
      return {
        resize: function () {},
        start: function () {
          done = false; build(); kit.hint('');
          onMsg = function (e) {
            if (!fr || e.source !== fr.contentWindow || !e.data || typeof e.data !== 'object') return;
            if (e.data.hv === 'step') kit.ev && kit.ev('step', { x: String(e.data.name || '').slice(0, 40) });
            if (e.data.hv === 'done' && !done) { done = true; setTimeout(function () { kit.reveal(); }, 6000); }
          };
          window.addEventListener('message', onMsg);
        },
        update: function () {},
        draw: function () { var g = kit.stage.ctx; if (g) { g.fillStyle = '#1a1220'; g.fillRect(0, 0, kit.W(), kit.H()); } },
        destroy: function () { if (onMsg) window.removeEventListener('message', onMsg); if (fr) fr.remove(); fr = null; }
      };
    });
  });
})();
