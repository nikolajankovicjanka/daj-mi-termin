# Termin

Marketplace za zakazivanje usluga. Projekat ima Vue 3 + TypeScript frontend, Laravel 12 API, Filament 3 administraciju, MySQL i Capacitor 7 projekte za Android i iOS. Lokalni razvoj je prilagođen Docker Desktopu i WSL2.

## Prvo pokretanje

Raspakujte projekat unutar WSL datotečnog sistema i otvorite terminal u folderu koji sadrži `compose.yaml`. Potrebni su Docker Compose, Bash i OpenSSL. Skripta generiše lokalne ključeve samo pri prvom pokretanju, gradi aplikaciju i pokreće migracije.

```bash
bash scripts/setup.sh --demo
docker compose exec -u www-data app php artisan platform:admin admin@vasafirma.ba
```

Druga komanda traži ime i lozinku administratora. Opcija `--demo` učitava izmišljene firme, usluge, rasporede i ocjene u Bijeljini. Za praznu platformu pokrenite skriptu bez te opcije.

| Gdje | Adresa | Namjena |
|---|---|---|
| Javni sajt | http://localhost:8080 | Kategorije, pretraga, mapa i rezervacije |
| Vlasnik | http://localhost:8080/salon | Kalendar, usluge, zaposleni, postavke firme, Premium poruke |
| Platform admin | http://localhost:8080/admin | Otvaranje firme, vlasnički nalog, paket i članarina |
| Mailpit | http://localhost:8025 | Pregled lokalnih potvrda, podsjetnika i ostalih mailova |

Za prvi vlasnički nalog u Filamentu otvorite **Saloni → Novi salon** ili uredite demo salon. Unesite adresu, grad, koordinate, email i lozinku vlasnika, paket i period pristupa. Email vlasnika služi za prijavu, zvanični email za kontakt s klijentima, a email za pretplatu za mjesečni pregled. U vlasničkom interfejsu dodajte uslugu, cijenu, trajanje i zaposlenog sa smjenama. Zatim otvorite javni profil salona i rezervišite termin. Vlasnik u kalendaru može unijeti telefonsku ili ličnu rezervaciju, otkazati termin ili označiti završenu posjetu.

```bash
docker compose ps
docker compose logs -f app scheduler
docker compose down
```

Podaci i učitane slike se čuvaju u imenovanim Docker volumenima. Kod je kopiran u slike pri izgradnji. Nakon izmjene pokrenite `docker compose up -d --build`. Za Vite hot reload ostavite Compose pokrenut, pa u drugom terminalu otvorite `frontend` i pokrenite `npm ci && npm run dev` (Node 22.12+). Vite prosljeđuje API i Filament na port 8080. Za promjenu porta uskladite `WEB_PORT`, `APP_URL`, `FRONTEND_URL` i razvojni proxy.

## Pretraga i raspoloživost

Izbor kategorije otvara rezultate i mapu. Nakon dozvole uređaja rezultati se ograničavaju na registrovane salone u krugu od 50 km. Grad se može upisati ručno. Za Google mapu uključite **Maps JavaScript API**, podesite ključ za dozvoljeni domen i upišite `VITE_GOOGLE_MAPS_KEY` u korijenski `.env`. Ponovo izgradite web servis. Za lokalni Vite razvoj upišite vrijednost i u `frontend/.env.local`. Udaljenost je vazdušnom linijom.

Termini se prikazuju po vremenu Europe/Sarajevo. Počeci su u razmacima od 15 minuta, do 120 dana unaprijed. Cijela usluga mora stati u smjenu. U ovoj verziji svi zaposleni salona mogu pružati sve njegove usluge. Rezervacija zaključava termin za konkretnog zaposlenog, a istovremeni zahtjev koji se preklapa dobija poruku da izabere drugi termin.

## Paketi i komunikacija

| Paket | Prikaz i funkcije |
|---|---|
| Osnovni | Standardna kartica, profilna fotografija, boja, usluge i rezervacije |
| Business | Naslovna fotografija, okvir i viša pozicija u preporučenom poretku kategorije |
| Premium | Prednost u preporučenom poretku, isticanje na početnoj i chat sa salonom |

Kada korisnik izabere sortiranje po ocjeni, početnoj cijeni ili broju rezervacija, odabrani kriterijum ima prednost. Paket rješava izjednačen rezultat. Slobodni datum i sat filtriraju termine prema smjenama, trajanju usluge i zauzeću.

Platform admin određuje mjesečnu cijenu i godišnji popust za svaku firmu. Može odobriti jedan, dva ili tri besplatna mjeseca. Obnova članstva se evidentira ručno. Integracija za kartično plaćanje nije uključena.

Scheduler obrađuje potvrde, podsjetnike i pozive za ocjenu. Podsjetnik se obrađuje u posljednjih 30 minuta prije početka. Kada vlasnik označi da je posjeta završena i istekne vrijeme usluge, klijent dobija jednokratni link za ocjenu. Mjesečni pregled navodi cijenu, godišnji popust, rok i pogodnosti paketa. Platform admin dobija email i Filament notifikaciju tokom posljednjih sedam dana prije isteka.

## Mobilna aplikacija

U folderu `frontend` se nalaze nativni Android i iOS projekti. Aplikacija koristi isti javni API. Za test na telefonu potreban je HTTPS URL servera koji telefon može otvoriti. Na računaru podesite `frontend/.env.mobile` prema primjeru, sa stvarnim `VITE_API_URL`, `VITE_PUBLIC_SITE_URL` i Maps ključem. CORS podrazumijevano dozvoljava Capacitor origin na iOS-u i Androidu.

```bash
cd frontend
npm ci
cp .env.mobile.example .env.mobile
# Unesite stvarne adrese u .env.mobile prije izgradnje.
npm run mobile:sync
npx cap open android
# Na macOS-u sa Xcode i CocoaPods:
npx cap open ios
```

Za Android release instalirajte Android SDK 35 i JDK 21. Postavite `TERMIN_KEYSTORE` (apsolutna putanja), `TERMIN_KEY_ALIAS`, `TERMIN_STORE_PASSWORD` i `TERMIN_KEY_PASSWORD` u okruženju. Iz korijena pokrenite:

```bash
bash scripts/android-release.sh
```

Skripta potpisuje APK i kopira ga u `downloads/termin.apk`. Postavite `VITE_ANDROID_DOWNLOAD_URL=/downloads/termin.apk` u korijenski `.env` i ponovo izgradite web. Za iPhone pripremite build u Xcodeu i u `VITE_IOS_DOWNLOAD_URL` upišite TestFlight ili App Store link iz svog Apple Developer naloga. Linkovi za preuzimanje prikazuju se nakon podešavanja.

## Povezivanje produkcije

Postavite vlastiti domen s HTTPS reverse proxyjem ispred web servisa, produkcijski `APP_URL` i `FRONTEND_URL`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` i trajni `APP_KEY`. U `.env` zamijenite Mailpit postavke podacima SMTP servisa (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`). Scheduler mora ostati aktivan za vremenski zavisne poruke. Lokalni Mailpit služi za pregled poruka poslanih tokom razvoja.
