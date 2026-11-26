set -e
R=$1; B=_hv_export_c3cafe373629b93c/backup-r84; mkdir -p $B assets/ixpack/birthday--royal w/sreevidya-4-98bcd7
cp assets/interactive/ix-prem-birthday.js $B/
test "$(md5sum assets/interactive/ix-prem-birthday.js | cut -c1-12)" = "2500037f9c0d" || { echo "ix-prem-birthday.js changed on server, not patched"; exit 1; }
curl -sSL -o /tmp/ixpb.js $R/ix-prem-birthday.js && cp /tmp/ixpb.js assets/interactive/ix-prem-birthday.js
curl -sSL -o assets/ixpack/birthday--royal/index.html $R/party.html
curl -sSL -o w/sreevidya-4-98bcd7/index.html $R/sreevidya.html
md5sum assets/interactive/ix-prem-birthday.js assets/ixpack/birthday--royal/index.html w/sreevidya-4-98bcd7/index.html | cut -c1-12; echo DEPLOYED
