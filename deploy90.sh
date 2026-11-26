set -e; B=https://raw.githubusercontent.com/harikabehara247/harikabehara247/c66d6c49285dc1964bfe0d33685dfcbf5cec7dd6; K=_hv_export_c3cafe373629b93c/backup-r90; mkdir -p $K
for f in ix-prem-birthday.js ix-prem-wedding-invitation.js scene-map.json; do cp assets/interactive/$f $K/$f; done; cp ix-core/registry.php $K/registry.php
curl -fsSL $B/zoo3d/index.html -o assets/ixpack/birthday--zoo3d/index.html
sed 's#<script type="importmap">#<script>window.HV_DATA={"to":"Sreevidya","age":4};</script><script type="importmap">#' assets/ixpack/birthday--zoo3d/index.html > w/sreevidya-4-98bcd7/zoo/index.html
S=assets/ixpack/_sig; mkdir -p $S/art/gates $S/art/curtain $S/art/temple $S/art/house $S/art/roof $S/art/kids
for f in play.html sig.js sig.css; do curl -fsSL $B/sig/$f -o $S/$f; done
curl -fsSL $B/sig/art/curtain/chandelier.webp -o $S/art/curtain/chandelier.webp
curl -fsSL $B/sig/art/curtain/curtain-l.webp -o $S/art/curtain/curtain-l.webp
curl -fsSL $B/sig/art/curtain/curtain-r.webp -o $S/art/curtain/curtain-r.webp
curl -fsSL $B/sig/art/curtain/diya.webp -o $S/art/curtain/diya.webp
curl -fsSL $B/sig/art/curtain/s1-curtains-closed.webp -o $S/art/curtain/s1-curtains-closed.webp
curl -fsSL $B/sig/art/curtain/s2-palace-reveal.webp -o $S/art/curtain/s2-palace-reveal.webp
curl -fsSL $B/sig/art/curtain/s3-jharokha-diyas.webp -o $S/art/curtain/s3-jharokha-diyas.webp
curl -fsSL $B/sig/art/curtain/s4-reception-hall.webp -o $S/art/curtain/s4-reception-hall.webp
curl -fsSL $B/sig/art/gates/bow-l.webp -o $S/art/gates/bow-l.webp
curl -fsSL $B/sig/art/gates/bow-r.webp -o $S/art/gates/bow-r.webp
curl -fsSL $B/sig/art/gates/lantern.webp -o $S/art/gates/lantern.webp
curl -fsSL $B/sig/art/gates/petal.webp -o $S/art/gates/petal.webp
curl -fsSL $B/sig/art/gates/s1-gates-closed.webp -o $S/art/gates/s1-gates-closed.webp
curl -fsSL $B/sig/art/gates/s2-gates-open.webp -o $S/art/gates/s2-gates-open.webp
curl -fsSL $B/sig/art/gates/s3-palace-reflection.webp -o $S/art/gates/s3-palace-reflection.webp
curl -fsSL $B/sig/art/gates/s4-mandap.webp -o $S/art/gates/s4-mandap.webp
curl -fsSL $B/sig/art/house/diya.webp -o $S/art/house/diya.webp
curl -fsSL $B/sig/art/house/kalash.webp -o $S/art/house/kalash.webp
curl -fsSL $B/sig/art/house/milkpot.webp -o $S/art/house/milkpot.webp
curl -fsSL $B/sig/art/house/s1-home-door-closed.webp -o $S/art/house/s1-home-door-closed.webp
curl -fsSL $B/sig/art/house/s2-home-interior.webp -o $S/art/house/s2-home-interior.webp
curl -fsSL $B/sig/art/house/s3-kalash.webp -o $S/art/house/s3-kalash.webp
curl -fsSL $B/sig/art/house/toran.webp -o $S/art/house/toran.webp
curl -fsSL $B/sig/art/kids/bal-blue.webp -o $S/art/kids/bal-blue.webp
curl -fsSL $B/sig/art/kids/bal-green.webp -o $S/art/kids/bal-green.webp
curl -fsSL $B/sig/art/kids/bal-orange.webp -o $S/art/kids/bal-orange.webp
curl -fsSL $B/sig/art/kids/bal-pink.webp -o $S/art/kids/bal-pink.webp
curl -fsSL $B/sig/art/kids/bal-purple.webp -o $S/art/kids/bal-purple.webp
curl -fsSL $B/sig/art/kids/bal-yellow.webp -o $S/art/kids/bal-yellow.webp
curl -fsSL $B/sig/art/kids/boy-cake-extra-variation.webp -o $S/art/kids/boy-cake-extra-variation.webp
curl -fsSL $B/sig/art/kids/boy-jump.webp -o $S/art/kids/boy-jump.webp
curl -fsSL $B/sig/art/kids/boy-s1-peeking.webp -o $S/art/kids/boy-s1-peeking.webp
curl -fsSL $B/sig/art/kids/boy-s2-running.webp -o $S/art/kids/boy-s2-running.webp
curl -fsSL $B/sig/art/kids/boy-s4-candles-blown-out.webp -o $S/art/kids/boy-s4-candles-blown-out.webp
curl -fsSL $B/sig/art/kids/boy-wave.webp -o $S/art/kids/boy-wave.webp
curl -fsSL $B/sig/art/kids/boycake-01.webp -o $S/art/kids/boycake-01.webp
curl -fsSL $B/sig/art/kids/boycake-02.webp -o $S/art/kids/boycake-02.webp
curl -fsSL $B/sig/art/kids/boycake-03.webp -o $S/art/kids/boycake-03.webp
curl -fsSL $B/sig/art/kids/boycake-04.webp -o $S/art/kids/boycake-04.webp
curl -fsSL $B/sig/art/kids/boycake-05.webp -o $S/art/kids/boycake-05.webp
curl -fsSL $B/sig/art/kids/boycake-06.webp -o $S/art/kids/boycake-06.webp
curl -fsSL $B/sig/art/kids/boycake-07.webp -o $S/art/kids/boycake-07.webp
curl -fsSL $B/sig/art/kids/boycake-08.webp -o $S/art/kids/boycake-08.webp
curl -fsSL $B/sig/art/kids/boycake-09.webp -o $S/art/kids/boycake-09.webp
curl -fsSL $B/sig/art/kids/boycake-10.webp -o $S/art/kids/boycake-10.webp
curl -fsSL $B/sig/art/kids/early-child-opening-variation.webp -o $S/art/kids/early-child-opening-variation.webp
curl -fsSL $B/sig/art/kids/early-child-running-variation.webp -o $S/art/kids/early-child-running-variation.webp
curl -fsSL $B/sig/art/kids/gift-green.webp -o $S/art/kids/gift-green.webp
curl -fsSL $B/sig/art/kids/gift-pink.webp -o $S/art/kids/gift-pink.webp
curl -fsSL $B/sig/art/kids/gift-purple.webp -o $S/art/kids/gift-purple.webp
curl -fsSL $B/sig/art/kids/girl-s1-peeking.webp -o $S/art/kids/girl-s1-peeking.webp
curl -fsSL $B/sig/art/kids/girl-s2-running.webp -o $S/art/kids/girl-s2-running.webp
curl -fsSL $B/sig/art/kids/girl-s3-cake-variation.webp -o $S/art/kids/girl-s3-cake-variation.webp
curl -fsSL $B/sig/art/kids/girl-s3-cake.webp -o $S/art/kids/girl-s3-cake.webp
curl -fsSL $B/sig/art/kids/s5-animal-parade.webp -o $S/art/kids/s5-animal-parade.webp
curl -fsSL $B/sig/art/kids/teddy.webp -o $S/art/kids/teddy.webp
curl -fsSL $B/sig/art/roof/box-closed.webp -o $S/art/roof/box-closed.webp
curl -fsSL $B/sig/art/roof/box-open.webp -o $S/art/roof/box-open.webp
curl -fsSL $B/sig/art/roof/s1-rooftop.webp -o $S/art/roof/s1-rooftop.webp
curl -fsSL $B/sig/art/roof/s2-cake.webp -o $S/art/roof/s2-cake.webp
curl -fsSL $B/sig/art/roof/s3-gift-box.webp -o $S/art/roof/s3-gift-box.webp
curl -fsSL $B/sig/art/roof/sparkler.webp -o $S/art/roof/sparkler.webp
curl -fsSL $B/sig/art/temple/bell.webp -o $S/art/temple/bell.webp
curl -fsSL $B/sig/art/temple/jasmine.webp -o $S/art/temple/jasmine.webp
curl -fsSL $B/sig/art/temple/lamp.webp -o $S/art/temple/lamp.webp
curl -fsSL $B/sig/art/temple/s1-temple-doors-closed.webp -o $S/art/temple/s1-temple-doors-closed.webp
curl -fsSL $B/sig/art/temple/s2-mandap-reveal.webp -o $S/art/temple/s2-mandap-reveal.webp
curl -fsSL $B/sig/art/temple/s3-kuthuvilakku.webp -o $S/art/temple/s3-kuthuvilakku.webp
curl -fsSL $B/sig/art/temple/s4-feast-hall.webp -o $S/art/temple/s4-feast-hall.webp
curl -fsSL $B/sig/covers/birthday--kids3dboy.webp -o assets/interactive/covers/birthday--kids3dboy.webp
curl -fsSL $B/sig/covers/birthday--kids3dgirl.webp -o assets/interactive/covers/birthday--kids3dgirl.webp
curl -fsSL $B/sig/covers/birthday--rooftop.webp -o assets/interactive/covers/birthday--rooftop.webp
curl -fsSL $B/sig/covers/housewarming--grihapravesh.webp -o assets/interactive/covers/housewarming--grihapravesh.webp
curl -fsSL $B/sig/covers/wedding-invitation--curtainpalace.webp -o assets/interactive/covers/wedding-invitation--curtainpalace.webp
curl -fsSL $B/sig/covers/wedding-invitation--goldengates.webp -o assets/interactive/covers/wedding-invitation--goldengates.webp
curl -fsSL $B/sig/covers/wedding-invitation--templebells.webp -o assets/interactive/covers/wedding-invitation--templebells.webp
printf '%s\n' 'Options -Indexes' > $S/.htaccess
curl -fsSL $B/sig/connector.js -o /tmp/sigconn.js
for p in birthday wedding-invitation housewarming birthday-invitation; do f=assets/interactive/ix-prem-$p.js; [ -f $f ] || printf '/* H View premium designs for %s */\n' $p > $f; cat /tmp/sigconn.js >> $f; done
curl -fsSL $B/sig/registry.php -o /tmp/sigreg.php
php -r '$f="ix-core/registry.php"; $s=file_get_contents($f); $p=strrpos($s,"return \$R;"); file_put_contents($f, substr($s,0,$p).file_get_contents("/tmp/sigreg.php")."\n".substr($s,$p)); echo "reg ok\n";'
php -l ix-core/registry.php | head -1
php -r '$f="assets/interactive/scene-map.json"; $m=json_decode(file_get_contents($f),true); foreach(["housewarming","birthday-invitation"] as $k){ $js="ix-prem-$k.js"; if(!in_array($js,$m[$k]??[])) $m[$k][]=$js; } file_put_contents($f,json_encode($m,JSON_UNESCAPED_SLASHES)); echo "map ok\n";'
ls $S/art/*/ | wc -l; ls assets/interactive/covers | grep -c 'goldengates\|curtainpalace\|templebells\|grihapravesh\|kids3d\|rooftop'
