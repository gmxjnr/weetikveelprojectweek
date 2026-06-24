# Secure File Transfer System

**"Weet Ik Veel"** — een end-to-end versleuteld bestanden-deelplatform, gebouwd als schoolproject (PHP) tijdens een projectweek.

## Wat is dit?

Een webapplicatie waarmee gebruikers veilig bestanden kunnen uploaden en delen via een unieke link. Het bestand wordt **in de browser versleuteld** voordat het de server bereikt — de server slaat alleen versleutelde data op en heeft op geen moment toegang tot de inhoud of de originele bestandsnaam.

## Belangrijkste features

- **Registratie & login** — wachtwoorden gehasht met bcrypt, sessies via de database, rate limiting bij herhaalde mislukte inlogpogingen
- **End-to-end encryptie** — bestanden worden client-side versleuteld met AES-GCM (256-bit). De server ontvangt alleen ciphertext
- **Sleutel nooit naar de server** — de encryptiesleutel wordt in het URL-fragment (`#k=...`) van de deelbare link gezet, wat betekent dat hij nooit wordt meegestuurd in een HTTP-request en dus niet in serverlogs terechtkomt
- **Bestandsnaam ook versleuteld** — de originele bestandsnaam wordt mee-versleuteld in de payload, zodat de server ook die niet kan zien
- **Integriteitscontrole** — AES-GCM verifieert bij het ontsleutelen automatisch of het bestand niet is gemanipuleerd
- **Logging** — belangrijke acties (inloggen, registreren, uploaden) worden gelogd in de database

## Hoe werkt de beveiliging?

1. **Upload**: de browser genereert een unieke AES-GCM-sleutel, versleutelt het bestand (inclusief bestandsnaam) en stuurt alleen de ciphertext naar `upload.php`. De server slaat dit op en geeft een token terug.
2. **Delen**: de deelbare link bestaat uit het token (naar de server, voor het ophalen van het juiste bestand) én de sleutel in het `#`-fragment (blijft lokaal in de browser).
3. **Download**: de ontvanger haalt via `download.php?raw=1` de versleutelde bytes op, en de browser ontsleutelt deze lokaal met de sleutel uit de link.

Hierdoor geldt: **zelfs als de server gecompromitteerd raakt, is de inhoud van de bestanden niet leesbaar** zonder de sleutel uit de deelbare link.

## Techniek

- **Backend**: PHP, sessies, PDO/MySQL
- **Frontend**: vanilla JavaScript, Web Crypto API (`crypto.subtle`) voor AES-GCM encryptie/decryptie
- **Database**: MySQL — gebruikers, sessies, bestanden (tokens/metadata), logs

## Installatie (lokaal, XAMPP/MAMP)

1. Clone deze repository in de `htdocs`-map van XAMPP (of de equivalente map in MAMP)
2. Maak een MySQL-database aan en importeer het meegeleverde schema (zie `docs/`)
3. Kopieer `.env.example` naar `.env` en vul de databasegegevens in
4. Start Apache en MySQL via XAMPP/MAMP
5. Open `http://localhost/<projectmap>/login.php` in de browser

> `.env` staat in `.gitignore` en wordt nooit gecommit — vraag de databasegegevens op bij het team als je deze niet hebt.

## Documentatie

Alle projectdocumentatie staat in de map [`docs/`](./docs):

- Probleemanalyse
- Requirements
- Testrapport
- Validatierapport
- Technische documentatie

## Team

- Milan R
- Tijn E.
- Ivo I.
- Malek C.

## Status

Dit project is gebouwd in een meerdaagse projectweek volgens een scrum-achtige aanpak (dag 1 t/m 8). Bekende beperkingen en mogelijke verbeterpunten staan beschreven in het validatierapport en het testrapport in `docs/`.
