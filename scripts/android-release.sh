#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
: "${TERMIN_KEYSTORE:?Postavite apsolutnu putanju do release keystore fajla.}"
: "${TERMIN_STORE_PASSWORD:?Nedostaje lozinka keystore fajla.}"
: "${TERMIN_KEY_ALIAS:?Nedostaje alias ključa.}"
: "${TERMIN_KEY_PASSWORD:?Nedostaje lozinka privatnog ključa.}"
[[ -f "$TERMIN_KEYSTORE" && "$TERMIN_KEYSTORE" = /* ]]
cd frontend
node --input-type=module <<'JS'
import { loadEnv } from 'vite'
const env = loadEnv('mobile', process.cwd(), 'VITE_')
const api = new URL(env.VITE_API_URL)
if (api.protocol !== 'https:' || api.hostname === 'termin.example.ba') {
  throw new Error('Postavite stvarni HTTPS API u frontend/.env.mobile.')
}
JS
npm run build -- --mode mobile
npx cap sync android
(cd android && ./gradlew assembleRelease)
install -m 644 android/app/build/outputs/apk/release/app-release.apk ../downloads/termin.apk
