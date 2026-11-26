<?php
/* r91 deploy: PhonePe PG buttons + return handling + footer line. Run from public_html. Aborts on any anchor mismatch. */
$K = '_hv_export_c3cafe373629b93c/backup-r91'; @mkdir($K, 0755, true);
$plan = [];
function want($f, $a, $b) { global $plan; $plan[$f][] = [$a, $b]; }

/* 1. ₹49 unlock response tells the sheet whether PhonePe is on, and the base price */
want('ix-core/public.php', "'url' => ix_base() . ix_share_url(\$p['slug'], \$token), 'token' => \$token, 'claim' => ix_claim_key(\$token)]]);",
  "'url' => ix_base() . ix_share_url(\$p['slug'], \$token), 'token' => \$token, 'claim' => ix_claim_key(\$token), 'price' => (int)\$amt, 'pg' => ix_pg_on(\$pdo)]]);");
want('ix-core/public.php', "function ix_api_unlock_status(PDO \$pdo, array \$in){\n",
  "/* r91: PhonePe payment gateway (Night Out TTS Pvt Ltd) available? */\nfunction ix_pg_on(PDO \$pdo){ \$f = IX_ROOT . '/ix-core/phonepe.php'; if (!is_file(\$f)) return false; try { require_once \$f; return pp_on(\$pdo); } catch (Throwable \$e) { return false; } }\nfunction ix_api_unlock_status(PDO \$pdo, array \$in){\n");

/* 2. pay sheet: PhonePe button on top; return from PhonePe opens the greeting */
want('assets/interactive/ix.js', "        if (h2) h2.insertAdjacentElement('afterend', box);\n",
<<<'A'
        if (h2) h2.insertAdjacentElement('afterend', box);
        /* r91: PhonePe gateway first (UPI, cards, net banking); paying by UPI ID stays below as the other way */
        if (u.pg) {
          if (h2) h2.textContent = 'Pay ₹' + (u.price || 49) + ' for this surprise';
          var pgw = d.createElement('div'); pgw.style.margin = '6px 0 4px';
          pgw.innerHTML = '<button type="button" class="ix-btn ix-btn-primary ix-btn-big" data-pg style="width:100%">Pay ₹' + (u.price || 49) + ' with PhonePe · UPI · Card</button>' +
            '<p class="ix-us-apps" style="text-align:center;margin-top:6px">Secure checkout by PhonePe, for Night Out TTS Pvt Ltd. Your surprise opens the moment it is paid.</p><p class="ix-us-err" data-pg-err role="alert"></p>' +
            '<p style="text-align:center;margin:12px 0 0;font-weight:700;color:#6b6480">or pay by UPI ID</p>';
          box.parentNode.insertBefore(pgw, box);
          var pgBtn = $('[data-pg]', pgw), pgErr = $('[data-pg-err]', pgw);
          pgBtn.addEventListener('click', function () {
            pgBtn.disabled = true; pgBtn.textContent = 'Opening PhonePe…'; pgErr.textContent = ''; track('pg_checkout', { d: design });
            var back = location.pathname + location.search.replace(/([?&])(ixpaid|ixt|pp)=[^&]*/g, '$1').replace(/[?&]+$/, '');
            fetch('/api/phonepe?a=start', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ code: u.code, t: u.t, back: back }) })
              .then(function (r) { return r.json(); }).then(function (j) { if (j.url) { location.href = j.url; return; } if (j.paid) return; throw new Error(j.error || 'Could not open PhonePe.'); })
              .catch(function (x) { pgErr.textContent = x.message + ' You can pay by UPI ID below.'; pgBtn.disabled = false; pgBtn.textContent = 'Pay ₹' + (u.price || 49) + ' with PhonePe · UPI · Card'; });
          });
        }

A);
want('assets/interactive/ix.js', "    /* back from the UPI app after a reload: carry on where they left off */\n",
<<<'A'
    /* r91: back from PhonePe checkout (?ixpaid=CODE&ixt=TOKEN): open the paid surprise here */
    try { var pq = new URLSearchParams(location.search); if (pq.get('ixpaid') && pq.get('ixt')) { var pCode = pq.get('ixpaid'), pTok = pq.get('ixt'); try { localStorage.removeItem('ix_unl'); } catch (x) {} history.replaceState(null, '', location.pathname);
      (function chk(n) { api('unlock_status', { code: pCode, t: pTok }).then(function (j) { if (j && j.ok && j.url) { done(j); toast('Payment received! Your surprise is ready to send 🎉'); } else if (n < 24) setTimeout(function () { chk(n + 1); }, 2500); else toast('We are still confirming your payment. Your saved link opens as soon as it is confirmed.'); }).catch(function () { if (n < 24) setTimeout(function () { chk(n + 1); }, 4000); }); })(0); } } catch (x) {}
    /* back from the UPI app after a reload: carry on where they left off */

A);

/* 3. plan payment page: PhonePe button */
want('ac-core/public.php', "    <section class=\"acx-card acx-pay\">\n",
  "    <section class=\"acx-card acx-pay\">\n<?php if (in_array(\$u['status'], ['awaiting_payment', 'verification_pending'], true) && is_file(dirname(__DIR__) . '/ix-core/phonepe.php') && (require_once dirname(__DIR__) . '/ix-core/phonepe.php') && pp_on(\$pdo)): ?>\n      <a class=\"acx-btn acx-btn-primary\" href=\"/api/phonepe?a=plan&amp;code=<?=e(rawurlencode(\$u['code']))?>&amp;back=<?=e(rawurlencode(strtok((string)(\$_SERVER['REQUEST_URI'] ?? '/account/plan/'), '?')))?>\" style=\"display:block;text-align:center;margin:0 0 6px;padding:14px;border-radius:14px;font-weight:700\">Pay <?=e(ac_money(\$u['amount']))?> with PhonePe · UPI · Card</a>\n      <p style=\"text-align:center;margin:0 0 14px;color:#6b6480;font-size:13px\">Secure checkout by PhonePe, for Night Out TTS Pvt Ltd. Your plan starts the moment it is paid. Or pay by UPI ID below.</p>\n<?php endif ?>\n");

/* apply */
foreach ($plan as $f => $R) { $s = file_get_contents($f); foreach ($R as $r) { $c = substr_count($s, $r[0]); if ($c !== 1) { echo "ABORT $f count $c: " . substr($r[0], 0, 80) . "\n"; exit(1); } } }
foreach ($plan as $f => $R) { $s = file_get_contents($f); copy($f, "$K/" . str_replace('/', '-', $f)); foreach ($R as $r) $s = str_replace($r[0], $r[1], $s); file_put_contents($f, $s); echo "ok $f\n"; }

/* 4. routing for /api/phonepe */
$h = file_get_contents('.htaccess'); copy('.htaccess', "$K/htaccess");
if (strpos($h, 'api/phonepe-ep') === false) { $h = str_replace("RewriteRule ^api/pay-relay/?$ api/pay-relay-ep/index.php [L]\n", "RewriteRule ^api/pay-relay/?$ api/pay-relay-ep/index.php [L]\nRewriteRule ^api/phonepe/?$ api/phonepe-ep/index.php [L,QSA]\n", $h, $n); if ($n !== 1) { echo "ABORT htaccess\n"; exit(1); } file_put_contents('.htaccess', $h); echo "ok .htaccess\n"; }
echo "done\n";
