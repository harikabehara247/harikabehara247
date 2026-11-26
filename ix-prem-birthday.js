/* =====================================================================
 * HView Interactive — Cinematic birthday wish (Pro)
 *   birthday:goldbox  "The Gold Gift Box"
 * Photo-real scenes (assets/inv/inv-bday-*.webp, shared with the giftbox
 * invitation). The receiver taps the closed gift, light bursts, the box opens
 * and real balloons rise. A scrolling story follows: "Happy 25th birthday" in
 * gold with their name, the message written like a letter, an age card
 * (years · days · heartbeats), their photo in a real gilded frame, and a
 * finale at the cake: make a wish, hold to blow the sparklers out, smoke, then
 * the room lights up with fireworks and a "send thanks" reply.
 * Fields: to, age, msg, from, photoUrl.
 * ===================================================================== */
(function () {
  'use strict';
  var X = window.IXScenes; if (!X || !X.define) return;
  var Sound = X.Sound || (X.util && X.util.Sound) || { pop: function () {}, chime: function () {}, tone: function () {} };
  var A = '/assets/inv/', FD = '/assets/interactive/fonts/';
  function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ord(n) { var s = ['th', 'st', 'nd', 'rd'], v = n % 100; return n + (s[(v - 20) % 10] || s[v] || s[0]); }
  var D = {
    goldbox: { fx: 'conf', fxc: ['#d4af37', '#f6e3a1', '#fff6d8', '#e8b4b8', '#ffffff', '#c98f94'],
      pal: { ink: '#3a2420', soft: '#7a5c56', acc: '#9a6a3a', gold: '#c99a3d', paper: 'rgba(255,250,246,.95)', glow: '255,226,180' },
      closed: 'inv-bday-01-closed', open: 'inv-bday-02-open', bg: 'inv-bday-04-room', cake: 'inv-bday-05-cake',
      tap: 'Tap to open your gift' }
  };

  function css() {
    if (document.getElementById('ixb-css')) return;
    var s = document.createElement('style'); s.id = 'ixb-css';
    s.textContent =
      "@font-face{font-family:'IXG Script';src:url(" + FD + "great-vibes-latin-400-normal.woff2) format('woff2');font-display:swap}" +
      "@font-face{font-family:'IXG Serif';font-weight:500;src:url(" + FD + "cormorant-garamond-latin-500-normal.woff2) format('woff2');font-display:swap}" +
      "@font-face{font-family:'IXG Serif';font-weight:700;src:url(" + FD + "cormorant-garamond-latin-700-normal.woff2) format('woff2');font-display:swap}" +
      "@font-face{font-family:'IXG Serif';font-weight:500;font-style:italic;src:url(" + FD + "cormorant-garamond-latin-500-italic.woff2) format('woff2');font-display:swap}" +
      ".ixb{position:absolute;inset:0;z-index:3;overflow:hidden;background:#0c0708;color:var(--ink);font-family:'Mukta',system-ui,sans-serif;-webkit-tap-highlight-color:transparent}" +
      ".ixb *{box-sizing:border-box}" +
      ".ixb-scene{position:absolute;inset:0;overflow:hidden}" +
      ".ixb-scene img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:50% 50%;user-select:none;-webkit-user-drag:none}" +
      ".ixb-closed{z-index:2;transition:transform 1.7s cubic-bezier(.6,.02,.3,1),filter 1.7s,opacity 1s .8s;transform-origin:50% 58%;animation:ixb-kb 16s ease-in-out infinite alternate}" +
      "@keyframes ixb-kb{to{transform:scale(1.07)}}" +
      ".ixb.is-open .ixb-closed{animation:none;transform:scale(1.5);filter:brightness(1.8) blur(3px);opacity:0}" +
      ".ixb-openimg{z-index:1;transform:scale(1.2);transition:transform 2.6s cubic-bezier(.2,.7,.2,1) .6s}" +
      ".ixb.is-open .ixb-openimg{transform:scale(1);animation:ixb-kb2 18s ease-in-out 3s infinite alternate}" +
      "@keyframes ixb-kb2{to{transform:scale(1.06) translateY(-1%)}}" +
      ".ixb-burst{position:absolute;inset:-20%;z-index:3;pointer-events:none;opacity:0;background:radial-gradient(circle at 50% 56%,rgba(var(--glow),.95) 0,rgba(var(--glow),.55) 18%,rgba(var(--glow),0) 52%)}" +
      ".ixb.is-open .ixb-burst{animation:ixb-burst 2.3s ease-out forwards}" +
      "@keyframes ixb-burst{0%{opacity:0;transform:scale(.4)}35%{opacity:1}100%{opacity:0;transform:scale(1.6)}}" +
      ".ixb-veil{position:absolute;inset:0;z-index:4;pointer-events:none;background:linear-gradient(180deg,rgba(0,0,0,.55) 0,rgba(0,0,0,0) 30%,rgba(0,0,0,0) 60%,rgba(0,0,0,.6) 100%)}" +
      ".ixb-fx{position:absolute;inset:0;z-index:5;pointer-events:none;overflow:hidden}" +
      ".ixb-fx i{position:absolute;top:-6%;display:block;border-radius:50%;opacity:.9;animation:ixb-fall linear infinite}" +
      ".ixb-fx.petal i{border-radius:60% 10% 60% 10%}.ixb-fx.conf i{border-radius:2px}" +
      ".ixb-fx.glow i{top:auto;bottom:-4%;box-shadow:0 0 10px 3px currentColor;animation-name:ixb-rise}" +
      "@keyframes ixb-fall{0%{transform:translate3d(0,-10vh,0) rotate(0)}100%{transform:translate3d(var(--dx),112vh,0) rotate(540deg)}}" +
      "@keyframes ixb-rise{0%{transform:translate3d(0,0,0);opacity:0}15%{opacity:.95}100%{transform:translate3d(var(--dx),-110vh,0);opacity:0}}" +
      /* gate */
      ".ixb-gate{position:absolute;inset:0;z-index:6;display:flex;flex-direction:column;align-items:center;justify-content:space-between;padding:9vh 6% 12vh;text-align:center;color:#fff;cursor:pointer;transition:opacity .7s}" +
      ".ixb.is-open .ixb-gate{opacity:0;pointer-events:none}" +
      ".ixb-gk{font:600 12px/1.4 'Mukta',sans-serif;letter-spacing:.32em;text-transform:uppercase;color:#f6e3b4;text-shadow:0 1px 8px rgba(0,0,0,.7)}" +
      ".ixb-gt{font:400 clamp(38px,9.5vw,66px)/1.05 'IXG Script',cursive;color:#fff3d6;text-shadow:0 2px 18px rgba(0,0,0,.75);margin-top:10px}" +
      ".ixb-tap{font:700 14px 'Mukta',sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#3a2410;background:linear-gradient(115deg,#b8862b,#f6e3a1 45%,#c99a3d 70%,#f1d68c);border:0;border-radius:999px;padding:13px 26px;box-shadow:0 8px 26px rgba(0,0,0,.45);animation:ixb-pulse 2s infinite;cursor:pointer}" +
      "@keyframes ixb-pulse{50%{box-shadow:0 8px 26px rgba(0,0,0,.45),0 0 0 14px rgba(246,227,161,0)}}" +
      ".ixb-tap:focus-visible,.ixb-btn:focus-visible,.ixb-heartbtn:focus-visible{outline:2px solid #fff;outline-offset:3px}" +
      /* story */
      ".ixb-story{position:absolute;inset:0;z-index:7;overflow-y:auto;overflow-x:hidden;opacity:0;pointer-events:none;transition:opacity 1s 1.3s;scrollbar-width:none;overscroll-behavior:contain}" +
      ".ixb-story::-webkit-scrollbar{display:none}" +
      ".ixb.is-open .ixb-story{opacity:1;pointer-events:auto}" +
      ".ixb-hero{min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:8vh 6% 14vh;text-align:center;color:#fff;position:relative}" +
      ".ixb-hk{font:600 11.5px/1.4 'Mukta',sans-serif;letter-spacing:.3em;text-transform:uppercase;color:#f6e3b4;text-shadow:0 1px 8px rgba(0,0,0,.8)}" +
      ".ixb-yr{font:700 clamp(15px,3.8vw,20px)/1.3 'IXG Serif',Georgia,serif;color:#fff6e6;text-shadow:0 1px 10px rgba(0,0,0,.85);margin-top:12px;letter-spacing:.04em}" +
      ".ixb-yr b{display:block;font-size:clamp(54px,15vw,104px);line-height:1;background:linear-gradient(115deg,#c99a3d 0%,#fff1c4 30%,#d9b45e 50%,#fff6d8 65%,#b8862b 85%);-webkit-background-clip:text;background-clip:text;color:transparent;filter:drop-shadow(0 3px 10px rgba(0,0,0,.6))}" +
      ".ixb-names{font:400 clamp(44px,11.5vw,88px)/1.04 'IXG Script',cursive;margin:8px 0 2px;background:linear-gradient(115deg,#c99a3d 0%,#fff1c4 28%,#d9b45e 48%,#fff6d8 62%,#b8862b 82%,#f6e3a1 100%);-webkit-background-clip:text;background-clip:text;color:transparent;filter:drop-shadow(0 3px 12px rgba(0,0,0,.7));background-size:200% 100%;animation:ixb-shine 6s linear infinite}" +
      "@keyframes ixb-shine{to{background-position:200% 0}}" +
      ".ixb-names em{font-style:normal;font-size:.6em}" +
      ".ixb-hl{font:500 italic clamp(16px,4vw,21px)/1.35 'IXG Serif',Georgia,serif;color:#fff6e6;text-shadow:0 1px 10px rgba(0,0,0,.85);margin-top:8px}" +
      ".ixb-more{position:absolute;left:50%;bottom:4vh;transform:translateX(-50%);font:700 11px 'Mukta',sans-serif;letter-spacing:.22em;text-transform:uppercase;color:#fff;background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:9px 16px;cursor:pointer;animation:ixb-bob 2s ease-in-out infinite}" +
      "@keyframes ixb-bob{50%{transform:translate(-50%,6px)}}" +
      ".ixb-body{position:relative;padding:4vh 5% 6vh;display:flex;flex-direction:column;align-items:center;gap:22px}" +
      ".ixb-body:before{content:'';position:absolute;inset:0;background:var(--bgimg) center/cover no-repeat;z-index:-2}" +
      ".ixb-body:after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,6,4,.3),rgba(10,6,4,.55));z-index:-1}" +
      ".ixb-card{z-index:2;width:100%;max-width:520px;background:var(--paper);border-radius:18px;padding:26px 22px;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.45);position:relative;opacity:0;transform:translateY(34px) scale(.98);transition:opacity .9s,transform .9s cubic-bezier(.2,.8,.2,1)}" +
      ".ixb-card:before{content:'';position:absolute;inset:8px;border:1px solid color-mix(in srgb,var(--gold) 70%,transparent);border-radius:12px;pointer-events:none}" +
      ".ixb-card.in{opacity:1;transform:none}" +
      ".ixb-k{font:700 11px/1.4 'Mukta',sans-serif;letter-spacing:.24em;text-transform:uppercase;color:var(--acc)}" +
      ".ixb-t{font:400 clamp(34px,8vw,46px)/1.1 'IXG Script',cursive;color:var(--acc);margin:4px 0 6px}" +
      ".ixb-p{font:500 18px/1.55 'IXG Serif',Georgia,serif;color:var(--ink);margin:6px auto;max-width:40ch;white-space:pre-line}" +
      ".ixb-p i{color:var(--soft)}" +
      ".ixb-orn{width:120px;height:12px;margin:8px auto;background:radial-gradient(circle,var(--gold) 0 22%,transparent 26%) center/12px 100% repeat-x}" +
      /* together-for counter */
      ".ixb-cnt{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px}" +
      ".ixb-cnt div{padding:10px 2px;border-radius:12px;border:1px solid color-mix(in srgb,var(--gold) 60%,transparent);background:rgba(255,255,255,.72)}" +
      ".ixb-cnt b{display:block;font:700 clamp(20px,5.6vw,28px)/1 'IXG Serif',serif;color:var(--acc);font-variant-numeric:tabular-nums}.ixb-cnt small{font:600 9.5px 'Mukta',sans-serif;letter-spacing:.12em;text-transform:uppercase;color:var(--soft)}" +
      ".ixb-fact{margin-top:12px;font:600 13.5px/1.45 'Mukta',sans-serif;color:var(--soft)}.ixb-fact b{color:var(--acc)}" +
      /* letter */
      ".ixb-letter .ixb-p{text-align:left;font-size:19px;min-height:4.5em}" +
      ".ixb-letter .ixb-p:after{content:'';display:inline-block;width:2px;height:1em;background:var(--acc);margin-left:2px;vertical-align:-2px;animation:ixb-caret 1s steps(1) infinite}" +
      ".ixb-letter.done .ixb-p:after{display:none}@keyframes ixb-caret{50%{opacity:0}}" +
      ".ixb-sign{font:400 30px/1 'IXG Script',cursive;color:var(--acc);text-align:right;margin-top:6px}" +
      /* journey */
      ".ixb-ev{padding:0;overflow:hidden}" +
      ".ixb-ev figure{margin:0;position:relative;aspect-ratio:4/3.3;overflow:hidden;border-radius:18px 18px 0 0}" +
      ".ixb-ev figure img{width:100%;height:100%;object-fit:cover;object-position:50% 40%;transform:scale(1.1);transition:transform 7s ease-out}" +
      ".ixb-ev.in figure img{transform:scale(1)}" +
      ".ixb-ev figure:after{content:'';position:absolute;inset:auto 0 0;height:40%;background:linear-gradient(0deg,var(--paper),transparent)}" +
      ".ixb-ev .ixb-evb{padding:2px 20px 22px}" +
      ".ixb-when{display:inline-block;font:800 12px 'Mukta',sans-serif;letter-spacing:.14em;color:#fff;background:var(--acc);border-radius:999px;padding:4px 12px;margin-bottom:2px}" +
      ".ixb-line{width:2px;height:34px;margin:-10px auto;background:linear-gradient(var(--gold),transparent);z-index:2;position:relative}" +
      /* photo */
      ".ixb-frame{position:relative;width:min(80%,340px);aspect-ratio:4/5;margin:10px auto 4px;padding:14px;border-radius:6px;background:linear-gradient(135deg,#8a6a22,#f6e3a1 25%,#b8862b 50%,#fff1c4 75%,#9a7414);box-shadow:0 18px 34px rgba(0,0,0,.35);transform:rotate(-2deg)}" +
      ".ixb-frame div{width:100%;height:100%;background:#eadfcd center/cover no-repeat;box-shadow:inset 0 0 0 3px rgba(0,0,0,.25),inset 0 0 26px rgba(0,0,0,.35)}" +
      /* finale */
      ".ixb-fin{min-height:92vh;position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;text-align:center;padding:10vh 6%;color:#fff}" +
      ".ixb-fin:before{content:'';position:absolute;inset:0;background:var(--finimg) center 50%/cover no-repeat;z-index:-2}" +
      ".ixb-fin:after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 50%,rgba(0,0,0,.2),rgba(0,0,0,.7));z-index:-1}" +
      ".ixb-fin .ixb-t{color:#fff3d6;text-shadow:0 2px 16px rgba(0,0,0,.8);font-size:clamp(40px,10vw,62px)}" +
      ".ixb-heartbtn{position:relative;width:150px;height:140px;border:0;background:none;cursor:pointer;padding:0;transition:transform .15s}" +
      ".ixb-heartbtn:active{transform:scale(.92)}" +
      ".ixb-heartbtn svg{width:100%;height:100%;overflow:visible;filter:drop-shadow(0 10px 24px rgba(0,0,0,.5))}" +
      ".ixb-heartbtn.beat svg{animation:ixb-beat .9s ease-in-out infinite}@keyframes ixb-beat{15%{transform:scale(1.12)}30%{transform:scale(1)}45%{transform:scale(1.08)}60%{transform:scale(1)}}" +
      ".ixb-hint{font:700 13px 'Mukta',sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#f6e3b4;text-shadow:0 1px 8px rgba(0,0,0,.8)}" +
      ".ixb-done{opacity:0;transform:translateY(16px);transition:opacity .9s,transform .9s;display:flex;flex-direction:column;align-items:center;gap:12px}.ixb-done.on{opacity:1;transform:none}" +
      ".ixb-hl2{font:500 italic 19px/1.4 'IXG Serif',serif;color:#fff6e6;text-shadow:0 1px 10px rgba(0,0,0,.85);max-width:32ch}" +
      ".ixb-btn{font:700 14px 'Mukta',sans-serif;border-radius:999px;padding:11px 20px;cursor:pointer;border:0;background:linear-gradient(115deg,#b8862b,#f6e3a1 45%,#c99a3d 70%,#f1d68c);color:#3a2410;text-decoration:none;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(0,0,0,.4)}" +
      ".ixb-music{position:absolute;right:12px;top:12px;z-index:9;width:40px;height:40px;border-radius:50%;border:1px solid rgba(255,255,255,.4);background:rgba(0,0,0,.4);color:#fff;font:16px/1 sans-serif;cursor:pointer;display:none}" +
      ".ixb.has-music.is-open .ixb-music{display:block}" +
      ".ixb-balloons{position:absolute;left:0;right:0;bottom:-60%;height:60%;z-index:5;pointer-events:none;opacity:0;background:url(" + A + "inv-bday-08-balloons-cut.webp) center/contain no-repeat;filter:drop-shadow(0 18px 30px rgba(0,0,0,.35))}" +
      ".ixb.is-open .ixb-balloons{animation:ixb-up 9s cubic-bezier(.3,.1,.3,1) 1.1s forwards}" +
      ".ixb-balloons.b2{left:-38%;right:38%;height:42%;animation-delay:1.9s!important;animation-duration:11s!important;opacity:0}" +
      ".ixb-balloons.b3{left:40%;right:-40%;height:36%;animation-delay:2.6s!important;animation-duration:12s!important}" +
      "@keyframes ixb-up{0%{opacity:0;transform:translateY(0) rotate(-2deg)}10%{opacity:1}50%{transform:translateY(-95%) rotate(2deg)}100%{opacity:1;transform:translateY(-210%) rotate(-1deg)}}" +
      ".ixb-age{display:flex;justify-content:center;gap:10px;margin-top:12px}" +
      ".ixb-age div{flex:1;max-width:120px;padding:12px 4px;border-radius:12px;border:1px solid color-mix(in srgb,var(--gold) 60%,transparent);background:rgba(255,255,255,.72)}" +
      ".ixb-age b{display:block;font:700 clamp(22px,6vw,30px)/1 'IXG Serif',serif;color:var(--acc);font-variant-numeric:tabular-nums}.ixb-age small{font:600 9.5px 'Mukta',sans-serif;letter-spacing:.12em;text-transform:uppercase;color:var(--soft)}" +
      ".ixb-pf{position:relative;width:min(86%,360px);aspect-ratio:825/1024;margin:6px auto 0}" +
      ".ixb-pf>div{position:absolute;left:23.5%;right:23.2%;top:26.8%;bottom:25.3%;background:#eadfcd center/cover no-repeat}" +
      ".ixb-pf>img{position:absolute;inset:0;width:100%;height:100%;filter:drop-shadow(0 14px 22px rgba(0,0,0,.35))}" +
      ".ixb-cake{min-height:100vh;position:relative;display:flex;flex-direction:column;align-items:center;justify-content:space-between;text-align:center;padding:9vh 6% 9vh;color:#fff;overflow:hidden}" +
      ".ixb-cake>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2;transition:filter 1.4s ease}" +
      ".ixb-cake:after{content:'';position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(0,0,0,.55),rgba(0,0,0,0) 32%,rgba(0,0,0,0) 62%,rgba(0,0,0,.7));pointer-events:none}" +
      ".ixb-cake.blown>img{filter:brightness(.42) saturate(.8)}" +
      ".ixb-cake.lit>img{filter:brightness(1.08) saturate(1.1)}" +
      ".ixb-smoke{position:absolute;left:50%;top:22%;width:2px;height:2px;z-index:1;pointer-events:none}" +
      ".ixb-smoke i{position:absolute;width:26px;height:26px;border-radius:50%;background:radial-gradient(circle,rgba(235,235,240,.55),rgba(235,235,240,0) 70%);opacity:0}" +
      ".ixb-cake.blown .ixb-smoke i{animation:ixb-smoke 2.6s ease-out forwards}" +
      "@keyframes ixb-smoke{0%{opacity:0;transform:translate(0,0) scale(.4)}20%{opacity:.9}100%{opacity:0;transform:translate(var(--dx),-170px) scale(3)}}" +
      ".ixb-blow{position:relative;width:96px;height:96px;border-radius:50%;border:0;cursor:pointer;background:rgba(0,0,0,.35);color:#fff3d6;font:700 12px/1.15 'Mukta',sans-serif;letter-spacing:.12em;text-transform:uppercase;touch-action:none;-webkit-user-select:none;user-select:none;box-shadow:0 10px 30px rgba(0,0,0,.45)}" +
      ".ixb-blow svg{position:absolute;inset:-6px;width:108px;height:108px;transform:rotate(-90deg)}" +
      ".ixb-blow circle{fill:none;stroke-width:5}.ixb-blow .bg{stroke:rgba(255,255,255,.22)}.ixb-blow .fg{stroke:#f6e3a1;stroke-linecap:round;stroke-dasharray:320;stroke-dashoffset:320}" +
      ".ixb-blow.go{animation:ixb-pulse 2s infinite}" +
      ".ixb-wish{font:500 italic clamp(19px,4.8vw,24px)/1.35 'IXG Serif',Georgia,serif;color:#fff6e6;text-shadow:0 2px 12px rgba(0,0,0,.9);max-width:30ch;transition:opacity .6s}" +
      ".ixb-final{display:flex;flex-direction:column;align-items:center;gap:12px;opacity:0;transform:translateY(16px);transition:opacity 1s,transform 1s;pointer-events:none}.ixb-final.on{opacity:1;transform:none;pointer-events:auto}" +
      ".ixb-final .ixb-names{font-size:clamp(48px,13vw,92px)}" +
      ".ixb-final{position:relative;padding:26px 18px 6px}.ixb-final:before{content:'';position:absolute;inset:-30px -40px -10px;z-index:-1;background:radial-gradient(ellipse at 50% 55%,rgba(12,7,5,.82),rgba(12,7,5,.55) 45%,rgba(12,7,5,0) 72%);pointer-events:none}" +
      ".ixb-hero:before{content:'';position:absolute;left:-10%;right:-10%;top:0;height:62%;z-index:-1;background:radial-gradient(ellipse at 50% 30%,rgba(20,10,6,.62),rgba(20,10,6,.3) 45%,rgba(20,10,6,0) 70%);pointer-events:none}.ixb-hero{isolation:isolate}" +
      ".ixb-hero .ixb-names{filter:drop-shadow(0 2px 3px rgba(0,0,0,.9)) drop-shadow(0 6px 18px rgba(0,0,0,.7))}" +
      "@media (min-aspect-ratio:1/1){.ixb-hero{justify-content:center;padding-top:5vh}}" +
      "@media (prefers-reduced-motion:reduce){.ixb *{animation:none!important;transition-duration:.01s!important;transition-delay:0s!important}.ixb-fx{display:none}}";
    document.head.appendChild(s);
  }

  /* ------------------------------------------------------------- helpers */
  function first(to) { var t = String(to || 'Ananya').trim(); return t || 'You'; }
  function age(d) { var a = parseInt(d.age, 10); return a > 0 && a < 120 ? a : 0; }

  /* ------------------------------------------------------------- the scene factory */
  function make(cfg) {
    return function (kit) {
      css();
      var el = kit.stage.el, d = kit.data || {}, preview = kit.mode !== 'recipient', P = cfg.pal;
      var layer = null, story = null, opened = false, timers = [], io = null, T = 0, demo = preview, demoY = 0, audio = null;
      var yrs = 0, typed = false, counted = false, blown = false, holdT = 0, holding = false, finished = false;
      function later(fn, ms) { var id = setTimeout(fn, kit.reduced ? Math.min(ms, 150) : ms); timers.push(id); return id; }
      function src(n) { return A + n + '.webp'; }

      function html() {
        var h = '', who = esc(first(d.to));
        h += '<div class="ixb-scene"><img class="ixb-openimg" alt="" src="' + src(cfg.open) + '" decoding="async"><img class="ixb-closed" alt="" src="' + src(cfg.closed) + '" decoding="async"><div class="ixb-burst"></div><div class="ixb-veil"></div><div class="ixb-balloons"></div><div class="ixb-balloons b2"></div><div class="ixb-balloons b3"></div><div class="ixb-fx ' + cfg.fx + '"></div></div>';
        h += '<div class="ixb-gate" role="button" tabindex="0" aria-label="' + esc(cfg.tap) + '"><div><div class="ixb-gk">A birthday surprise for</div><div class="ixb-gt">' + who + '</div></div><button type="button" class="ixb-tap">' + esc(cfg.tap) + '</button></div>';
        h += '<button type="button" class="ixb-music" aria-label="Play or pause music">♫</button>';
        h += '<div class="ixb-story" tabindex="-1">';
        h += '<section class="ixb-hero"><div class="ixb-hk">Today is all about you</div>' +
          (yrs ? '<div class="ixb-yr">Happy<b>' + ord(yrs) + '</b>Birthday</div>' : '<div class="ixb-yr">Happy <b style="font-size:clamp(40px,11vw,74px)">Birthday</b></div>') +
          '<div class="ixb-names">' + who + '</div>' + (d.from ? '<div class="ixb-hl">with love, ' + esc(d.from) + '</div>' : '') +
          '<button type="button" class="ixb-more">Scroll for your surprise ↓</button></section>';
        h += '<div class="ixb-body" style="--bgimg:url(' + src(cfg.bg) + ')">';
        h += '<article class="ixb-card ixb-letter" data-ixb-letter><div class="ixb-k">A note for you</div><div class="ixb-orn"></div><p class="ixb-p" data-ixb-text></p>' + (d.from ? '<div class="ixb-sign">— ' + esc(d.from) + '</div>' : '') + '</article>';
        if (yrs) h += '<article class="ixb-card" data-ixb-count><div class="ixb-k">' + esc(first(d.to)) + ', in numbers</div><div class="ixb-orn"></div><div class="ixb-age"><div><b data-c="y">0</b><small>Years</small></div><div><b data-c="d">0</b><small>Days</small></div><div><b data-c="s">0</b><small>Sunsets</small></div></div><p class="ixb-fact">And every single one made the world a little brighter.</p></article>';
        if (d.photoUrl) h += '<article class="ixb-card"><div class="ixb-k">My favourite picture</div><div class="ixb-pf"><div style="background-image:url(&quot;' + esc(String(d.photoUrl).replace(/"/g, '%22')) + '&quot;)"></div><img alt="" src="' + src('inv-bday-09-frame-cut') + '" loading="lazy"></div></article>';
        h += '</div>';
        h += '<section class="ixb-cake" data-ixb-cake><img alt="" loading="lazy" src="' + src(cfg.cake) + '"><div class="ixb-smoke">' + [-30, -10, 8, 26, 0].map(function (x, i) { return '<i style="--dx:' + x + 'px;left:' + (x - 13) + 'px;animation-delay:' + (i * .18).toFixed(2) + 's"></i>'; }).join('') + '</div>' +
          '<div class="ixb-wish" data-ixb-wish>Close your eyes and make a wish…</div>' +
          '<div class="ixb-final" data-ixb-final><div class="ixb-hk">Wish made</div><div class="ixb-names">Happy Birthday, ' + who + '</div><div class="ixb-hl2">' + (yrs ? 'Here’s to ' + esc(ord(yrs)) + ' and the best year yet.' : 'Here’s to the best year yet.') + '</div><a class="ixb-btn" data-ixb-reply target="_blank" rel="noopener" href="#">💌 Send a thank-you</a></div>' +
          '<button type="button" class="ixb-blow go" data-ixb-blow aria-label="Press and hold to blow out the candles"><svg viewBox="0 0 108 108"><circle class="bg" cx="54" cy="54" r="50"/><circle class="fg" data-ixb-ring cx="54" cy="54" r="50"/></svg>Hold<br>to blow</button></section>';
        h += '</div>';
        return h;
      }

      function fxFill() {
        var box = layer.querySelector('.ixb-fx'), n = kit.reduced ? 0 : (kit.low ? 12 : 26), out = '';
        for (var i = 0; i < n; i++) {
          var c = cfg.fxc[(Math.random() * cfg.fxc.length) | 0], sz = 5 + Math.random() * 6;
          out += '<i style="left:' + (Math.random() * 100).toFixed(1) + '%;width:' + sz.toFixed(1) + 'px;height:' + (sz * .45).toFixed(1) + 'px;background:' + c + ';--dx:' + ((Math.random() - .5) * 120).toFixed(0) + 'px;animation-duration:' + (8 + Math.random() * 7).toFixed(1) + 's;animation-delay:-' + (Math.random() * 12).toFixed(1) + 's"></i>';
        }
        box.innerHTML = out;
      }

      function build() {
        if (layer) layer.remove();
        layer = document.createElement('div'); layer.className = 'ixb';
        var st = layer.style; st.setProperty('--ink', P.ink); st.setProperty('--soft', P.soft); st.setProperty('--acc', P.acc); st.setProperty('--gold', P.gold); st.setProperty('--paper', P.paper); st.setProperty('--glow', P.glow);
        layer.innerHTML = html(); el.appendChild(layer); fxFill();
        story = layer.querySelector('.ixb-story');
        var gate = layer.querySelector('.ixb-gate');
        gate.addEventListener('click', function (e) { e.stopPropagation(); open(); });
        gate.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
        layer.querySelector('.ixb-more').addEventListener('click', function (e) { e.stopPropagation(); stopDemo(); var b = layer.querySelector('.ixb-body'); story.scrollTo({ top: b.offsetTop - 10, behavior: 'smooth' }); });
        ['wheel', 'touchstart', 'pointerdown', 'keydown'].forEach(function (t) { story.addEventListener(t, stopDemo, { passive: true }); });
        var blow = layer.querySelector('[data-ixb-blow]');
        var down = function (e) { e.preventDefault(); e.stopPropagation(); stopDemo(); if (blown) return; holding = true; blow.classList.remove('go'); try { blow.setPointerCapture(e.pointerId); } catch (x) {} };
        var up = function (e) { if (e) e.stopPropagation(); holding = false; if (!blown && holdT < 1) blow.classList.add('go'); };
        blow.addEventListener('pointerdown', down); blow.addEventListener('pointerup', up); blow.addEventListener('pointercancel', up); blow.addEventListener('pointerleave', up);
        blow.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && !blown) { e.preventDefault(); holding = true; } });
        blow.addEventListener('keyup', function () { up(); });
        var reply = layer.querySelector('[data-ixb-reply]');
        reply.href = 'https://wa.me/?text=' + encodeURIComponent('🎂 I opened my birthday surprise' + (d.from ? ', ' + d.from : '') + '! Thank you so much, it made my day ❤️');
        reply.addEventListener('click', function (e) { e.stopPropagation(); if (preview) { e.preventDefault(); reply.textContent = 'They can reply from the shared link 💌'; } kit.ev('thanks_back'); });
        var cards = layer.querySelectorAll('.ixb-card');
        if ('IntersectionObserver' in window && !kit.reduced) {
          io = new IntersectionObserver(function (es) {
            es.forEach(function (x) { if (!x.isIntersecting) return; x.target.classList.add('in'); io.unobserve(x.target); if (x.target.hasAttribute('data-ixb-letter')) typeLetter(); if (x.target.hasAttribute('data-ixb-count')) countUp(); });
          }, { root: story, threshold: .2 });
          cards.forEach(function (c) { io.observe(c); });
        } else { cards.forEach(function (c) { c.classList.add('in'); }); typeLetter(); countUp(); }
        var mb = layer.querySelector('.ixb-music');
        try {
          audio = new Audio(); audio.preload = 'none'; audio.loop = true; audio.volume = .5; audio.src = A + 'inv-bday-music.mp3';
          audio.addEventListener('canplay', function () { layer && layer.classList.add('has-music'); });
          audio.addEventListener('error', function () { audio = null; });
          mb.addEventListener('click', function (e) { e.stopPropagation(); if (!audio) return; if (audio.paused) { audio.play().catch(function () {}); mb.textContent = '❚❚'; } else { audio.pause(); mb.textContent = '♫'; } });
        } catch (x) { audio = null; }
      }

      function typeLetter() {
        if (typed || !layer) return; typed = true;
        var box = layer.querySelector('[data-ixb-text]'), card = layer.querySelector('[data-ixb-letter]');
        var text = String(d.msg || 'Happy birthday! Thank you for every laugh, every late-night talk and every ordinary day you made special. I hope this year gives back all the love you give. Have the most beautiful day.');
        if (kit.reduced) { box.textContent = text; card.classList.add('done'); return; }
        var i = 0, step = Math.max(1, Math.round(text.length / 170));
        (function tick() { if (!layer) return; i = Math.min(text.length, i + step); box.textContent = text.slice(0, i); if (i < text.length) timers.push(setTimeout(tick, 34)); else card.classList.add('done'); })();
      }
      function countUp() {
        if (counted || !layer || !yrs) return; counted = true;
        var v = { y: yrs, d: Math.round(yrs * 365.25), s: Math.round(yrs * 365.25) }, t0 = performance.now(), b = layer.querySelectorAll('[data-c]');
        (function f(t) { if (!layer) return; var p = kit.reduced ? 1 : Math.min(1, (t - t0) / 1700), k = 1 - Math.pow(1 - p, 3);
          b.forEach(function (x) { var c = x.getAttribute('data-c'); x.textContent = Math.round(v[c] * k).toLocaleString('en-IN'); }); if (p < 1) requestAnimationFrame(f); })(t0);
      }
      function doBlow() {
        if (blown || !layer) return; blown = true; holding = false; kit.ev('candles_blown');
        var cake = layer.querySelector('[data-ixb-cake]'), btn = layer.querySelector('[data-ixb-blow]'), wish = layer.querySelector('[data-ixb-wish]');
        btn.style.transition = 'opacity .5s'; btn.style.opacity = '0'; btn.style.pointerEvents = 'none'; wish.style.opacity = '0';
        cake.classList.add('blown'); Sound.tone && Sound.tone(220, .4);
        later(function () {
          if (!layer) return; cake.classList.remove('blown'); cake.classList.add('lit'); Sound.chime && Sound.chime(5);
          var W = kit.W(), H = kit.H();
          [0, 300, 650, 1000, 1400].forEach(function (ms, i) { later(function () { kit.confetti && kit.confetti(W * (.15 + .18 * i), H * (.25 + (i % 2) * .15), kit.reduced ? 20 : 110, 1, 700); }, ms); });
          layer.querySelector('[data-ixb-final]').classList.add('on'); btn.style.display = 'none';
          finished = true; later(function () { kit.reveal(); }, 2200);
        }, kit.reduced ? 100 : 2300);
      }
      function open() {
        if (opened || !layer) return; opened = true; kit.hint(''); kit.ev('open');
        Sound.pop && Sound.pop(); later(function () { Sound.chime && Sound.chime(3); }, 600);
        layer.classList.add('is-open');
        if (audio && !preview) audio.play().then(function () { layer.querySelector('.ixb-music').textContent = '❚❚'; }).catch(function () {});
        later(function () { kit.confetti && kit.confetti(kit.W() / 2, kit.H() * .45, kit.reduced ? 20 : 90, 1, 520); }, 900);
        later(function () { try { story.focus({ preventScroll: true }); } catch (x) {} }, 1500);
      }
      function stopDemo() { demo = false; }

      return {
        resize: function () {},
        start: function () {
          T = 0; opened = false; demo = preview; demoY = 0; typed = false; counted = false; blown = false; holdT = 0; holding = false; finished = false;
          yrs = age(d); build(); kit.hint(cfg.tap);
        },
        update: function (dt) {
          T += dt;
          if (!opened && preview && T > 1.8) open();
          if (opened && demo && story && T > 6.5) {
            demoY += dt * Math.max(60, kit.H() * .15);
            var max = story.scrollHeight - story.clientHeight;
            if (demoY >= max) { demo = false; if (preview && !blown) { holding = true; } } else story.scrollTop = demoY;
          }
          if (layer && !blown) {
            holdT = holding ? Math.min(1, holdT + dt / 1.6) : Math.max(0, holdT - dt * 1.5);
            var ring = layer.querySelector('[data-ixb-ring]'); if (ring) ring.style.strokeDashoffset = String(320 - 320 * holdT);
            if (holding && holdT >= 1) doBlow();
          }
        },
        draw: function () { var g = kit.stage.ctx; g.fillStyle = '#140c0a'; g.fillRect(0, 0, kit.W(), kit.H()); kit.P.draw(g); },
        destroy: function () { timers.forEach(clearTimeout); if (io) io.disconnect(); if (audio) { try { audio.pause(); } catch (x) {} audio = null; } if (layer) layer.remove(); layer = null; }
      };
    };
  }

  X.define('birthday:goldbox', make(D.goldbox));
})();

/* r83 (26 Nov 2026): on Android, a long press on "Hold to blow" landed on the cake photo and opened the phone's
   "Open image / Download image" menu instead of blowing. Photos can no longer be long-pressed, and holding anywhere
   on the cake counts as holding the blow button. */
(function () {
  var s = document.createElement('style');
  s.textContent = '[class^="ixb"] img,[class*=" ixb"] img{pointer-events:none;-webkit-touch-callout:none;-webkit-user-select:none;user-select:none;-webkit-user-drag:none}'
    + '.ixb-cake,.ixb-blow,.ixb-blow *{-webkit-touch-callout:none;-webkit-user-select:none;user-select:none;touch-action:none}';
  document.head.appendChild(s);
  document.addEventListener('contextmenu', function (e) { if (e.target.closest && e.target.closest('[class^="ixb"],[class*=" ixb"]')) e.preventDefault(); }, true);
  document.addEventListener('dragstart', function (e) { if (e.target.closest && e.target.closest('[class^="ixb"],[class*=" ixb"]')) e.preventDefault(); }, true);
  var fwd = null;
  document.addEventListener('pointerdown', function (e) {
    var cake = e.target.closest && e.target.closest('.ixb-cake'); if (!cake || e.target.closest('.ixb-blow')) return;
    var b = cake.querySelector('[data-ixb-blow]') || document.querySelector('[data-ixb-blow]'); if (!b) return;
    fwd = b; e.preventDefault(); b.dispatchEvent(new PointerEvent('pointerdown', { bubbles: false, cancelable: true, pointerId: e.pointerId, pointerType: e.pointerType }));
  }, true);
  ['pointerup', 'pointercancel'].forEach(function (t) { document.addEventListener(t, function (e) { if (!fwd) return; var b = fwd; fwd = null; b.dispatchEvent(new PointerEvent('pointerup', { bubbles: false, pointerId: e.pointerId })); }, true); });
})();
