# Izborni pregled · Srbija 2026

PHP 8.3 aplikacija za nezavisan prikaz parlamentarnih rezultata iz tri izvora:
RIK, Studentska lista i SNS. Svaki izvor ima 250 mesta u 14 centriranih redova
(13 × 18 + 16), popunjenih po kolonama odozgo nadole, od leve ka desnoj, tabelu glasova,
donut grafikon mandata i vremenske preseke.
Početno stanje je **demo**, sa jasno označenim izmišljenim rezultatima.

## Pokretanje jednog Docker kontejnera

Docker Desktop mora biti pokrenut u Linux režimu. Iz direktorijuma projekta:

```powershell
docker build -t elections-tracker-sr:local .
php bin/console.php password
docker compose up -d
```

Komanda `password` traži vašu lozinku (najmanje 12 znakova) i pravi `.env` iz
primera, sa bcrypt hashom. Na ovom WAMP računaru PHP već postoji.
Ako nemate lokalni PHP, drugu komandu zamenite sledećom PowerShell komandom:

```powershell
docker run --rm -it --entrypoint php --mount "type=bind,source=$($PWD.Path),target=/var/www/html" elections-tracker-sr:local bin/console.php password
```

- Rezultati: <http://localhost:8080>
- Administracija: <http://localhost:8080/admin.php>
- Logovi: `docker compose logs -f`
- Zaustavljanje: `docker compose down` (volume sa istorijom ostaje sačuvan).

U jednom kontejneru rade Apache/PHP i pozadinski collector pod `www-data`, uz
`tini` za procese. Collector proverava interval svakog minuta, čak i kada nema
posetilaca. Podrazumevani interval je 10 minuta. Browser proverava sačuvane
rezultate svakih 30 sekundi; ne pokreće scraping. Pri prvom startu nastaju test
preseci za tri dana i sva tri izvora. Podešavanja, sirovi izvori i istorija
ostaju na named volume-u `election-data`.

Docker build koristi Node samo u fazi kompajliranja SCSS-a; završni image je PHP.
Razvojna konfiguracija `compose.override.yaml` automatski povezuje lokalne PHP
fajlove i `assets` sa kontejnerom. Sačuvajte PHP/JS fajl i osvežite browser;
rebuild nije potreban. Za SCSS izmene pokrenite `npm run styles:watch` lokalno,
ili `npm run styles:build` za jednokratno kompajliranje.
Za promene Dockerfile-a ili zavisnosti: `docker compose up -d --build`.
Za pokretanje samo sa fajlovima iz image-a, bez razvojnih mount-ova, koristite
`docker compose -f compose.yaml up -d --build`.
Posle promene `.env`: `docker compose up -d --force-recreate`.
Nemojte koristiti `docker compose down -v` ako želite da sačuvate istoriju.

## Podešavanje stvarnih izvora

U `.env` dodajte `OPENAI_API_KEY` i po potrebi promenite `OPENAI_MODEL`
(početna vrednost `gpt-4.1-mini`). Tajne nisu u Git-u, Docker image-u ili javnom API-ju.
Potom ponovo kreirajte kontejner.

U administraciji:

1. Unesite kompletan registar proglašenih izbornih lista, jedinstvene ID-eve,
   nazive, boje, broj kandidata i status manjinske liste prema rešenju RIK-a.
   Početni registar je demonstracioni, nije zvaničan spisak kandidatura.
2. Za svaki izvor unesite URL stranice sa zbirnim nacionalnim rezultatima.
   Jedan URL po redu, najviše pet, po redosledu prednosti.
3. Izaberite režim „Uživo” i sačuvajte. Kliknite „Proveri izvore sada” za
   prvu proveru bez čekanja intervala. Status i razlozi odbijanja su ispod.

URL-ovi jednog izvora su **rezervni zbirni izveštaji**, a ne sabirci. Prvi ispravan
ima prednost; aplikacija nikada ne sabira preklapajuće nacionalne rezultate.
Tri izvora se obrađuju nezavisno. Greška jednog ne briše prethodni rezultat
ni rezultate ostalih izvora. Stariji izveštaj ne zamenjuje noviji.

HTML/tekst se preuzima cURL-om, čisti DOM parserom i šalje OpenAI Responses API-ju
sa strogom JSON šemom. AI mapira nazive na administratorov registar; ne određuje
boje, manjinski status ili mandate. Izvorni sadržaj, SHA-256, model, URL i vreme
preuzimanja ostaju sačuvani radi provere. Identican sadržaj i registar koriste
keširani odgovor. Ključ nije potreban za demo ili JSON izvore.

Podržani su javni HTTP/HTTPS HTML, tekst i JSON do 2 MB. Privatne adrese i
preusmerenja nisu dozvoljeni; unesite krajnji URL. PDF, slike/OCR, autentifikovani
portali i stranice koje rezultate učitavaju samo JavaScript-om zahtevaju
dodatni adapter. Konkretni URL-ovi za prijavljivanje rezultata nisu unapred
pretpostavljeni. Integraciju sa stvarnim izvorima treba proveriti na njihovim
stvarnim izveštajima pre izbornog dana.

AI odbija nepotpune ili nejasne izveštaje. Server dodatno proverava da su glasovi
celobrojni, da nema nepoznatih/duplih/nedostajućih lista i da zbir glasova i
nevažećih listića odgovara broju birača koji su glasali. Ne procenjuje nedostajuće
glasove iz zaokruženih procenata. Matematička validacija ne dokazuje tačnost
AI prepisivanja; izvorni izveštaji ostaju osnova za proveru.

### JSON format izvora

JSON endpoint može direktno da vrati sledeću strukturu. U `lists` mora biti
tačno jedna stavka za svaki ID iz podešavanja. Primer ispod odgovara registru
sa samo dve liste `a` i `b`.

```json
{
  "reportedAt": "2026-10-25T22:10:00+01:00",
  "ballotsCast": 10000,
  "invalidBallots": 1000,
  "registeredVoters": 20000,
  "processedStations": 8,
  "totalStations": 10,
  "lists": [
    { "id": "a", "votes": 6000 },
    { "id": "b", "votes": 3000 }
  ]
}
```

`ballotsCast` je zakonski broj birača koji su glasali, uključujući nevažeće
listiće. Podaci o upisanim biračima i obrađenim mestima moraju se odnositi na
isti obuhvat izveštaja. `reportedAt` je vreme objave izvora sa UTC pomakom,
a `capturedAt` vreme kada je aplikacija sačuvala stanje.

## Test podaci i istorija

```powershell
docker compose exec --user www-data elections php bin/console.php demo
docker compose exec --user www-data elections php bin/console.php demo 3
docker compose exec --user www-data elections php bin/console.php refresh --force
```

`demo` svaki put nasumično menja glasove unutar tri izmišljena scenarija:
Studentska lista prijavljuje oko 55% za `studenti` i 40% za `sns`, SNS obrnuto,
a RIK približno 47,5% za obe liste. Odstupanje je ±0,3 procentna poena.
Nevažeći listići su oko 0,5%, a ostatak se deli ostalim listama opadajuće po
redosledu registra. Generator prepoznaje vodeće liste po ID-evima `studenti`
i `sns`, nezavisno od njihove pozicije u registru. Broj dana je 1–7.
Generisanje ne menja izabrani režim i nikada ne prepisuje stvarne rezultate.
Isto dugme postoji u administraciji. Za prikaz test podataka odaberite demo režim.

Svaka promena rezultata ili registra stvara nepromenljivi JSON snimak u
`storage/{demo|live}/{rik|studenti|sns}/history/`. `latest.json` je pokazivač
na trenutno stanje. Isti sadržaj ne pravi duplikat. Upis je zaključan i
zamena fajla atomska. Istorija čuva tadašnje boje i pravila registra.

Na javnoj stranici biraju se dan i sačuvani trenutak. Svaki izvor prikazuje
poslednje stanje poznato do tog trenutka; ako ga još nije bilo, ostaje prazan.
Istorijski prikaz se ne vraća automatski na najnovije. Dugme „Najnovije” vraća
aktuelni prikaz. Vremenska zona je `Europe/Belgrade`, uključujući promenu na
zimsko računanje vremena 25. oktobra; vremena u izboru imaju oznaku zone.

## Pravila raspodele mandata

Implementacija je u `app/elections.php` i prikazuje **nezvaničnu projekciju**, ne
odluku RIK-a o dodeli mandata. Na delimičnim rezultatima pretpostavlja isti
odnos glasova u celoj izbornoj jedinici.

- Ustav, član 100: Narodna skupština ima 250 poslanika.
- Zakon, čl. 5 i 128: jedna izborna jedinica, proporcionalni sistem; prag od
  najmanje 3% broja birača koji su glasali. Ako ga nijedna lista ne dostigne,
  sve liste sa glasovima učestvuju.
- Član 129: D’Ontovi količnici (deljenje glasova sa 1–250), veći broj glasova
  ima prednost kod jednakog količnika. Lista ne dobija više mandata nego što
  ima kandidata. Nerešena jednakost količnika i glasova na poslednjem mestu
  označava se za odluku nadležnog organa, bez proizvoljne dodele.
- Član 140: manjinske liste učestvuju i ispod praga. Samo kada su ispod 3%,
  njihovi količnici se uvećavaju za 35%.

Poređenje količnika koristi celobrojno unakrsno množenje. Procenti u tabeli
koriste broj birača koji su glasali kao imenilac; nevažeći listići zato znače
da zbir procenata lista može biti manji od 100%. Donut prikazuje mandate.

Zvanični izvori provereni 16.10.2026:

- [RIK – objašnjenje parlamentarnih izbora](https://www.rik.parlament.gov.rs/tekst/sr/77/o-izborima-za-narodne-poslanike.php)
- [RIK – Zakon o izboru narodnih poslanika, PDF](https://www.rik.parlament.gov.rs/extfile/sr/41907/Zakon%20o%20izboru%20narodnih%20poslanika%20lat.pdf)
- [RIK – registar propisa i izmena](https://www.rik.parlament.gov.rs/tekst/sr/61/zakoni.php)
- [Vlada Srbije – Ustav](https://www.srbija.gov.rs/tekst/330326/ustav.php)
- [OpenAI – Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs)

Pre aktiviranja na izborni dan proverite eventualne nove izmene propisa.

## Razvoj i stil koda

```powershell
npm ci
npm run styles:watch
npm run styles:build
npm run format
npm run format:check
composer install
composer format
composer check
composer test
```

Integracione provere koriste namenski test kontejner na `127.0.0.1:18080` sa
isključivo test lozinkom `Local-test-password-2026`. `npm run test:http` proverava
API, istoriju, privatne fajlove, sesiju i CSRF. `npm run test:browser` koristi
instalirani Chrome/Edge (ili `CHROME_PATH`) i proverava 750 ćelija, grafikone,
istoriju, mobilni prikaz i administratorske izmene. Ovaj test menja podešavanja
i demo podatke, pa ga pokrećite samo na izdvojenom test volume-u. Screenshot-i
se čuvaju u ignorisanom `.qa/` direktorijumu. Drugi URL možete zadati kroz
`TEST_BASE_URL`.

SCSS izvor je `assets/scss/style.scss`, sa tokenima u `_tokens.scss`.
`assets/style.css` je generisan, čitljiv CSS; oba su verzionisana.
Prettier formatira JS/SCSS/CSS sa dva razmaka, jednim navodnikom u JS i
višelinijskim blokovima. PHP koristi četiri razmaka i PSR-12 koji proverava
PHP CodeSniffer (`phpcs.xml`); PHP šabloni su odvojeni od kontrolera.
Runtime nema Composer ili npm zavisnosti.

Bez Dockera, na WAMP-u je javni URL
<http://localhost/elections_tracker_sr/>. Pokrenite `php bin/console.php password`
i `php bin/console.php demo 3`. Za samostalno osvežavanje van Dockera pozivajte
`php bin/console.php refresh` svakog minuta kroz Task Scheduler ili cron.
Apache mora da dozvoli `.htaccess`; ugrađeni `php -S` server nije namenjen
objavljivanju ovog korena jer ne primenjuje Apache zabrane pristupa.

Admin koristi hash lozinke, sesiju, CSRF tokene i ograničenje neuspešnih prijava.
Za javni server postavite HTTPS kroz svoj reverse proxy i sačuvajte backup volume-a.
Privatni JSON fajlovi i sirovi sadržaji blokirani su Apache pravilima.
