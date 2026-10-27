# LLU 27 Supporter Zone – Installation und Betrieb

## Verhalten

Die Supporter Zone nutzt eigene Konten, Session-Cookies und widerrufbare Remember-Tokens. Eine öffentliche Registrierung erzeugt niemals einen Eintrag in `users`. Läufer-Login, OAuth und Performance-Berechnungen bleiben bestehen. Die Challenge-Seite und Supporter-Auswertung verwenden dieselbe Funktion `challengeScore()`.

Das Wettportal liegt im eigenen Reiter `/supporter/bets.php`. Wetten auf den Wochensieger sind bis Mittwoch 23:59:59 Berliner Zeit offen. Missionswetten gibt es nur auf den Abschluss, nie auf die Annahme. Richtige Tipps teilen den Pool proportional und erhalten zusätzlich 20 Prozent des Einsatzes als Bonus; Bruchteile werden auf ganze Coins abgerundet. Co-Sieger zählen jeweils als richtig. Abgerechnete Wettgewinne erscheinen im Supporter-Ranking.

Kilometer und Läufe sind Cache-Summen ab persönlichem Trainingsstart, kein Langzeitarchiv. Ein Loop ist die Summe der je Aktivität abgerundeten vollständigen 6.706 Meter. Fortschrittsbalken vergleichen verfügbare Kilometer im Team und sind keine medizinische oder Zielprognose. Challenge-Historie kommt aus `challenge_weekly`.

Persönliche Wochenziele und das gemeinsame Teamziel verwenden dieselbe UTC-Kalenderwoche wie die Challenge. Jeder Läufer kann sein eigenes Ziel und das gemeinsame Teamziel auf der Navigationsseite `Wochenziele` konfigurieren; das Dashboard zeigt nur die aktuellen Fortschritte. Erreichte persönliche Ziele werden dauerhaft markiert, damit aktuelle und beste Zielserien trotz des siebentägigen Aktivitäts-Caches erhalten bleiben. Der reguläre Sync aktualisiert den Stand der aktuellen und vorherigen Woche automatisch.

Supporter-Missionen befinden sich ausschließlich auf den Unterseiten `/supporter/missions.php` und `/missions.php`; auf den Dashboards erscheinen keine Missionsinhalte. Supporter legen Titel, optionale Beschreibung, Kilometer-, Laufzeit- oder Loop-Ziel, 1–5 Missionspunkte und eine Frist in Berliner Ortszeit fest. Bei exklusiven Missionen sperrt die erste Annahme die Aufgabe für alle anderen Läufer. Offene Missionen können alle drei Läufer unabhängig annehmen und jeweils abschließen. Die Teilnahme ist freiwillig und kann nach der Annahme nicht zurückgegeben werden.

Für den Fortschritt zählen nur synchronisierte Laufaktivitäten, die nach der individuellen Annahme beginnen und anhand ihrer Startzeit plus Bewegungszeit bis zur Frist abgeschlossen sind. Ein persistentes Missions-Ledger bewahrt bereits erkannte Beiträge über den siebentägigen Aktivitäts-Cache hinaus. Der reguläre Strava-Sync aktualisiert offene Missionen; ein Seitenaufruf prüft zusätzlich den aktuellen Cache. Missionspunkte werden nach Erreichen des Ziels dauerhaft in der separaten Missionsrangliste gewertet und verändern die bestehende Backyard-Challenge-Wertung nicht.

Jeder Supporter erhält pro Berliner Kalendermonat genau einen Joker auf der Unterseite `/supporter/jokers.php`. Er kann das aktuelle persönliche Wochenziel eines Läufers um 20 Prozent erhöhen, innerhalb von 30 Stunden einen Lauf über mindestens 75 Prozent der bisherigen Durchschnittsdistanz verlangen oder den nächsten Lauf auf mindestens 150 Prozent der bisherigen Durchschnittsdistanz setzen. Die Berechnungsgrundlage wird beim Einsatz festgeschrieben. Ein Erfolg schützt nur die vorhandenen Missionspunkte; ein Fehlschlag zieht dauerhaft drei Punkte ab. Ungenutzte Monats-Joker werden nicht angespart und jede laufende Joker-Frist endet spätestens mit dem Berliner Monatswechsel. Wochenziele mit aktivem Joker können für die betroffene Woche nicht geändert oder gelöscht werden.

Supporter bekommen bei der Registrierung einmalig 100 LLU Coins und für den ersten authentifizierten Seitenaufruf pro Berliner Kalendertag 10 Coins. Der Kontostand und ein Buchungsverlauf stehen unter „Konto“. Pools werden durch den ersten Tipp geöffnet und anhand der Challenge- beziehungsweise Missionsdaten automatisch geschlossen und ausgewertet. Ohne Gewinner werden die Einsätze erstattet. Offene Einsätze verhindern die Löschung eines Kontos.

Im Wettportal sehen eingeloggte Supporter die Einsätze und Poolanteile der anderen in einer Grafik, die alle 15 Sekunden aktualisiert wird. Supporter können außerdem automatische Laufziel-Pools eröffnen, zum Beispiel „Tim läuft bis Freitag mindestens 100 km“. Dafür muss der Läufer seine Supporter-Laufdaten freigegeben haben. Ziele werden nach Kilometern oder vollständigen Loops ausgewertet, laufen höchstens sieben Tage und werden bei Widerruf der Freigabe abgebrochen; Einsätze werden dann zurückgezahlt. Die Migration `20261026_supporter_custom_bets.sql` speichert dafür nur die nötigen Aktivitätssummen bis zur Auswertung, auch wenn der normale Aktivitäts-Cache abläuft.

## Hostinger vorbereiten (manuell; kein Deployment wurde ausgeführt)

1. Datenbank sichern. Auf Hostinger PHP **8.2 oder neuer** mit PDO MySQL, mbstring, curl und OpenSSL aktivieren. HTTPS ist erforderlich; Cookies sind ausschließlich `Secure`.
2. Die benötigten Migrationen in derselben Datenbank wie `users` anwenden: `20261024_supporter.sql`, `20261026_heart_rate_sharing.sql`, `20261026_performance_cache.sql`, `20261026_weekly_goals.sql`, `20261026_supporter_missions.sql`, `20261026_supporter_jokers.sql`, `20261026_supporter_coins.sql`, `20261026_supporter_daily_bonus.sql` und `20261026_supporter_custom_bets.sql`. Bereits angewendete Migrationen musst du nicht erneut ausführen; die neuen Herzfreigabe-, Performance-Cache- und Wettzielmigrationen sind wiederholbar. Coin- und Tagesbonusmigrationen setzen bestehendes Guthaben nicht zurück; das Startguthaben wird pro Supporter nur einmal vergeben. Führe alle noch fehlenden Migrationen vor dem neuen Anwendungscode aus. Sie löschen keine Trainingsdaten und setzen das bestehende `users`-Schema voraus. Auf einer bestehenden Produktionsdatenbank `schema.sql` nicht erneut importieren.
   Für den Läufer-Login außerdem `remember_tokens.sql` anwenden. Diese wiederholbare Migration wird sowohl für „30 Tage eingeloggt bleiben“ als auch für Passwortänderungen benötigt. Fehlt die Tabelle `remember_tokens`, schlägt die dauerhafte Anmeldung fehl und Passwortänderungen werden vollständig zurückgerollt. Im GitHub-Workflow „LLU27 SQL-Migration ausführen“ dafür `sql_files: remember_tokens.sql` und `confirmation: APPLY` verwenden. Das Übertragen von PHP-Dateien allein führt die Migration nicht aus.
3. Private Konfiguration in `domains/zjung-partyband.de/backyard_private/.env` ergänzen (siehe unten). Keine Secrets in öffentliche Verzeichnisse, Workflow-Inputs oder Repository schreiben.
4. Composer-Pakete installieren: im lokalen/private Arbeitsverzeichnis `composer install --no-dev --prefer-dist --optimize-autoloader`. GitHub Actions installiert und überträgt `vendor/` jetzt in das **private** Verzeichnis. SQL wird weiterhin nicht automatisch ausgeführt. Das bestehende manuelle Deployment hat weiterhin `dry_run: true` als Standard. Erst selbst Dry-Run prüfen, anschließend ein gewünschtes Deployment gesondert auslösen.
5. `APP_URL` muss die tatsächliche öffentliche Basis einschließlich Unterverzeichnis sein: `https://zjung-partyband.de/backyard`. Öffentliche Dateien liegen unter `public_html/backyard/`, private Dateien daneben unter `backyard_private/`. Nicht `backyard_private` unter `public_html` ablegen.
6. Nach Installation meldet sich jeder Läufer wie bisher an und aktiviert im Dashboard bei „Deine Supporter feuern dich an“ die ausdrückliche Freigabe seiner Trainingssummen. Ohne Freigabe werden keine Trainingssummen angezeigt. Das Team-Scoreboard benötigt alle drei Freigaben. Widerruf ist im selben Formular möglich.
7. Supporter registrieren sich unter `<APP_URL>/supporter/register.php` und melden sich unter `/supporter/login.php` an. Der öffentliche Läufer-Login verlinkt beide Seiten.
8. Hostinger-Cronjob **jede Minute**: `/usr/bin/php /home/u489447694/domains/zjung-partyband.de/backyard_private/bin/supporter_push.php`. Den tatsächlichen PHP-CLI-Pfad in hPanel prüfen. Bestehende Sync- und Challenge-Cronjobs beibehalten. Push ist verzögert bis zum nächsten Cronlauf.

Ohne Push-Schlüssel funktionieren Registrierung, Login, Dashboard, Nachrichten und Coin-Pools weiterhin. Ohne die SQL-Migrationen ist die neue Zone nicht benutzbar; das Läufer-Dashboard zeigt einen Hinweis im optionalen Supporter-Modul.

## Private `.env`-Variablen

Bestehende `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_URL` und `TOKEN_KEY` bleiben erforderlich. `TOKEN_KEY` ist der bestehende geheime Base64-Schlüssel mit 32 Bytes und verschlüsselt auch Push-Abonnements.

| Variable | Zweck |
|---|---|
| `SUPPORTER_RATE_KEY` | Separater zufälliger Schlüssel für gehashte Rate-Limit-Kennungen; mindestens 32 zufällige Bytes. Fallback ist der vorhandene `TOKEN_KEY`. |
| `SUPPORTER_DAILY_COIN_REWARD` | LLU-Coins pro Berliner Kalendertag; optional, Standardwert `10`. |
| `SUPPORTER_PRIVACY_URL` | Optional: HTTPS-Link zum bereitgestellten Datenschutztext. |
| `SUPPORTER_TERMS_URL` | Optional: HTTPS-Link zu bereitgestellten Nutzungsbedingungen. Sobald einer der Links konfiguriert ist, muss das Registrierungsformular die Zustimmung erhalten. |
| `SUPPORTER_ADMIN_RUNNER_IDS` | Kommagetrennte IDs bestehender Läuferkonten, die Anfeuerungen moderieren dürfen. Leer bedeutet keine Moderatoren. Keine Supporter-ID verwenden. |
| `VAPID_PUBLIC_KEY` | Öffentlicher VAPID-Schlüssel; darf dem Browser übergeben werden. |
| `VAPID_PRIVATE_KEY` | Geheimer VAPID-Schlüssel; ausschließlich private `.env`. |
| `VAPID_SUBJECT` | Kontakt, beispielsweise `mailto:betreiber@example.org`. |

Zufallswert für `SUPPORTER_RATE_KEY`: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. Keine echten Schlüssel in Dokumentation eintragen.

## Composer und VAPID

Die private `composer.json` verwendet `minishlink/web-push:^9.0`, passend zu PHP 8.2+. Eine `composer.lock` konnte hier ohne ausführbares PHP/Composer nicht erzeugt werden. Vor dem ersten Produktivdeployment einmal `composer update --no-dev` in einer vertrauenswürdigen PHP-8.2+-Umgebung ausführen und die erzeugte `backyard_private/composer.lock` versionieren; ohne Lock löst Composer Versionen beim Installieren neu auf. Bei lokaler Installation `composer check-platform-reqs` ausführen. Pakete gehören ausschließlich nach `backyard_private/vendor/`.

Einmalig im privaten Composer-Verzeichnis ausführen:

```sh
php -r 'require "vendor/autoload.php"; $keys = Minishlink\WebPush\VAPID::createVapidKeys(); echo "VAPID_PUBLIC_KEY=".$keys["publicKey"].PHP_EOL."VAPID_PRIVATE_KEY=".$keys["privateKey"].PHP_EOL;'
```

Die Ausgabe direkt in der privaten `.env` speichern. Nicht über GitHub Actions generieren oder in Logs kopieren. Schlüssel nicht regelmäßig wechseln; nach Wechsel müssen Geräte erneut abonnieren. Technische Grundlage: [PHP-Web-Push-Bibliothek](https://github.com/web-push-libs/web-push-php/tree/v9.0.0).

## Push und Datenschutz

Erst ein Klick auf „Push aktivieren“ fragt die Browser-Erlaubnis ab. Auf iPhone/iPad ab iOS 16.4 muss die Seite über Safari zum Home-Bildschirm hinzugefügt und von dort geöffnet werden ([Apple-Dokumentation](https://developer.apple.com/documentation/usernotifications/sending-web-push-notifications-in-web-apps-and-browsers)). Beide Manifeste und der Service Worker berücksichtigen die Installation unter `/backyard/`. Der Worker speichert keine authentifizierten HTML-Seiten.

Abonnements sind pro Gerät gespeichert und mit `TOKEN_KEY` verschlüsselt. Ein Browser-Abonnement gehört jeweils genau einem Konto/einer Rolle; Aktivierung mit einem anderen Konto ordnet es neu zu. Läufer aktivieren Push im eigenen Dashboard, Supporter im Profil. Supporter haben derzeit keine auslösenden Ereignisse: Die angeforderte Hauptfunktion sendet Anfeuerungen ausschließlich an den gewählten Läufer. Ausschalten ist je Gerät und auf allen Geräten möglich. Abmelden widerruft Sitzungen; Push muss bei Bedarf separat deaktiviert werden.

Unterstützte Gateways: Google FCM, Mozilla, Apple und Windows Notify; fremde URLs, Ports und lokale Ziele werden abgelehnt. Der Versandjob entfernt HTTP-404/410-Abonnements über die Bibliothek. Temporäre Fehler werden bis zu fünfmal versucht; erfolgreich versandte Geräte werden bei regulären Wiederholungen übersprungen. Bei Prozessabbruch direkt nach erfolgreichem Versand kann eine Nachricht doppelt zugestellt werden; ein stabiler Notification-Tag begrenzt sichtbare Duplikate. Logs enthalten nur Job-/Subscription-ID, Status bzw. Fehlerklasse, keine URLs, Tokens oder Nachrichten. hPanel-Logs überwachen. Abgelaufene Rate-Limits und Remember-Tokens werden durch den Job bereinigt, auch ohne VAPID-Konfiguration. Nicht versandte Jobs verfallen nach einem Tag.

Anfeuerungen und Anzeigenamen sind für angemeldete Supporter sichtbar, der Highscore zeigt Anzeigenamen. Kontolöschung entfernt eigene Anfeuerungen, Tipps, Tokens und Abonnements. Alle angemeldeten Läufer können `/supporter/admin.php` öffnen und Supporter-Konten verwalten; die Seite ist auch im Läufer-Dashboard verlinkt. Beim Löschen bleiben Teammissionen und Joker als anonymisierter Verlauf erhalten. Anfeuerungen moderieren: `/supporter/moderation.php`; dafür sind die konfigurierten Läufer-Admin-IDs nötig und die letzten 200 Nachrichten sind löschbar. Die Admin-IDs lassen sich mit `SELECT id, username FROM users;` ermitteln. Datenschutz-/Nutzungstexte werden nicht erfunden und müssen vom Betreiber bereitgestellt werden.

## Tests und Abnahme

### SQL-Migration über GitHub Actions

Der Workflow **LLU27 SQL-Migration ausführen** kann eine geordnete Liste versionierter Dateien aus `backyard_private/sql/` auf der Produktionsdatenbank anwenden. Unter **Actions → LLU27 SQL-Migration ausführen → Run workflow** muss `main` gewählt, die exakten Dateinamen kommasepariert oder je Zeile eingetragen und mit `APPLY` bestätigt werden. Der Workflow verwendet dieselben Hostinger-SSH-Secrets wie das Deployment, liest die Datenbankwerte erst auf dem Server aus der privaten `.env` und erstellt vor jeder einzelnen Datei ein Backup unter `backyard_private/backups/`. Bei einem Fehler stoppt der Lauf; bereits erfolgreich angewendete Dateien bleiben angewendet. Freier SQL-Text und Pfade außerhalb des SQL-Verzeichnisses werden abgelehnt.

Automatisierter Regeltest: `php backyard_private/tests/supporter_test.php` (mbstring erforderlich). PHP-Syntaxprüfung: alle PHP-Dateien in `backyard/` und `backyard_private/`, ohne `vendor/`, mit `php -l` prüfen. Der Deployment-Workflow prüft ebenfalls alle öffentlichen PHP-Dateien und private App-/Bin-Dateien.

Staging-Abnahme mit eigener Datenbank und HTTPS:

- Migration zweimal importieren; unveränderte Trainingsdaten und referentielle Löschregeln prüfen.
- Registrieren; doppelte Namen, ungültige Namen, kurze Passwörter, fehlenden CSRF und wiederholte Anfragen ablehnen.
- Anmeldung mit richtigem/falschem Passwort; Remember-Cookie über Browser-Neustart, Rotation und Ablauf prüfen. Supporter- und Läufer-Login im selben Browser parallel prüfen; Supporter dürfen Läufer-Endpunkte nicht öffnen.
- Anzeigename ändern, Passwort ändern, alte Sitzungen auf anderem Gerät ablehnen, Konto löschen; abhängige Daten prüfen.
- Drei Freigaben einzeln setzen/widerrufen; nur freigegebene Summen anzeigen. Dashboard, Challenge und Performance der Läufer prüfen.
- Nachricht senden, Empfänger und Cooldown prüfen; XSS-Text muss als Text erscheinen. Läuferansicht und Moderation prüfen.
- Wochensieger-Wette vor/nach Mittwoch 23:59:59 Berliner Zeit prüfen; finale Wochen mit Gleichstand, Null-Loops und fehlendem Läufer prüfen. Poolanteil, 20-%-Bonus, tägliche Anmeldung und Supporter-Gewinnerübersicht prüfen.
- Supporter-Mission mit 1–5 Punkten und Berliner Frist erstellen. Exklusive Mission gleichzeitig mit zwei Läufern annehmen und die atomare Sperre prüfen; offene Mission mit allen Läufern annehmen. Aktivitäten vor der Annahme und nach der Frist dürfen nicht zählen. Kilometer, Laufzeit und vollständige Loops sowie dauerhafte Punkte nach Cache-Ablauf prüfen. Erfüllte Missionen dürfen nicht mehr abgesagt werden.
- Push auf echtem Desktop und iPhone-PWA aktivieren. Cronjob ausführen, richtigen Empfänger und mehrere Geräte prüfen. Verweigerte Erlaubnis, Abmelden, Gerätewechsel, Ausschalten sowie abgelaufene Abonnements prüfen.
- Responsive Navigation bei 360, 768 und 1280 Pixeln; Overlay, Escape, Tastaturfokus und Sidebar prüfen.

Live-Abnahme benötigt MySQL-Zugang, konfigurierte private `.env`, VAPID-Schlüssel und echte Browsergeräte. Diese Daten sind im Workspace nicht vorhanden; entsprechende Integrations-/Browserprüfungen dürfen daher nicht als bestanden gewertet werden. Konkrete lokale Ergebnisse stehen im Abschlussbericht.

## Dateien und Commit-Zusammenfassung

Neu ist außerdem `.github/workflows/validate-changes.yml` für Syntax-, Regel- und isolierte MySQL-/HTTP-Integrationstests. Der Workflow führt kein Deployment aus und wurde in dieser lokalen Sitzung nicht ausgelöst.

Geändert: `.gitignore`, `.github/workflows/deploy.yml`, `backyard/index.php`, `backyard/dashboard.php`, `backyard/challenge.php`, `backyard/manifest.webmanifest`.

Neu: diese Anleitung; `backyard/supporter/{index,register,login,logout,profile,push,moderation}.php`, `backyard/supporter/{supporter.css,supporter.js,manifest.webmanifest}`, `backyard/assets/supporter-push.js`, `backyard/sw.js`, `backyard/partials/{supporter-runner,supporter-push}.php`, `backyard_private/app/{supporter,push,challenge_score}.php`, `backyard_private/bin/supporter_push.php`, `backyard_private/sql/20261024_supporter.sql`, `backyard_private/composer.json`, `backyard_private/tests/supporter_test.php` und `backyard_private/tests/supporter_integration.php`.

Für die spätere Missionserweiterung kamen `backyard/missions.php`, `backyard/supporter/missions.php`, `backyard/partials/mission-card.php`, `backyard/assets/missions.css`, `backyard_private/app/missions.php` und `backyard_private/sql/20261026_supporter_missions.sql` hinzu.

Für die Monats-Joker kamen `backyard/jokers.php`, `backyard/supporter/jokers.php`, `backyard/partials/joker-card.php`, `backyard_private/app/jokers.php` und `backyard_private/sql/20261026_supporter_jokers.sql` hinzu.

Vorgeschlagener Commit: `feat: add secure supporter zone with cheers, weekly predictions and web push`.

## Lokales Prüfergebnis (24.10.2026)

Korrektur nach dem ersten CI-Lauf: Der HTTP-Integrationstest bildet jetzt die Hostinger-Struktur `public_html/backyard/` neben `backyard_private/` in einem temporären Verzeichnis ab. Die öffentliche Basis-URL enthält auch im Test `/backyard`. Falsche Include-Tiefen in den beiden Supporter-Partials wurden berichtigt. Verbindungsfehler und HTTP 500 geben im Test das PHP-Serverlog aus; die Profilseite einschließlich Push-Partial wird zusätzlich geprüft. Ein erneuter CI-Lauf ist zur Bestätigung erforderlich.

Repository und Änderungen wurden statisch geprüft; git diff --check meldet keine Whitespace-Fehler. JSON-Manifeste und Composer-Konfiguration werden lokal mit PowerShell geparst. PHP und Composer sind nicht vorinstalliert. Eine offizielle PHP-8.3-Testlaufzeit wurde nach SHA256-Prüfung temporär geladen, ihre Ausführung jedoch vom Windows-Anwendungsschutz blockiert. Daher sind weder PHP-Lint noch Regel-/Integrationstests lokal ausgeführt; auch der neue CI-Workflow wurde nicht gestartet. MySQL, VAPID und echte Browsergeräte fehlen. Vor Freigabe müssen CI und Staging-Abnahme erfolgreich abgeschlossen werden. Kein Deployment, keine produktiven Datenänderungen.

## Laufdiagramme in der Performance-Ansicht

Die Performance-Ansicht zeigt Pace und Puls über die Laufstrecke sowie Kilometer-Splits mit HF-Zonenfarben. Dafür werden Zeit-, Distanz-, HF-, Höhen-, Steigungs-, Watt-, Cadence-, Bewegungs- und Geschwindigkeitsstreams in Zeitfenster ab 30 Sekunden verdichtet (höchstens etwa 600 Punkte). Die Verlaufspace verwendet bevorzugt geglättete Geschwindigkeit in Bewegung; ohne Geschwindigkeitsstream wird sie aus Distanz und verstrichener Zeit berechnet. Vollständig als Pause markierte Fenster haben keinen Pace-Wert. Kilometer-Splits enthalten weiterhin Pausen. Optionale Messwerte werden zeitgewichtet verdichtet; fehlende Werte bleiben null, gemessene Nullen bleiben erhalten. Cadence wird unverändert in Strava-Einheiten dargestellt, ohne pauschale Verdoppelung. Höhenprofil/Steigung, Leistung und Schrittfrequenz erhalten eigene Diagramme. Messlücken über 120 Sekunden werden im Verlauf unterbrochen. Für die private Laufkarte wird zusätzlich der latlng-Stream angefordert. Pro Lauf werden höchstens etwa 2.001 GPS-Punkte auf fünf Nachkommastellen gerundet gespeichert; fehlende Koordinaten und Zeitlücken unterbrechen die Strecke.

Die kompakte Messreihe liegt im bestehenden privaten Sieben-Tage-Cache in activity_performance.detail_json. Eine zusätzliche SQL-Migration ist nicht erforderlich. Analysen ohne analysis_version 4 werden durch den regulären Detail-Sync innerhalb des bisherigen Budgets schrittweise neu geladen; bis dahin zeigt der Verlauf einen Hinweis. Neue Analysen werden bevorzugt. Die Detaildaten bleiben ausschließlich für den jeweiligen Läufer sichtbar.

Die Laufkarte nutzt Leaflet 1.9.4 und OpenStreetMap-Kartenkacheln mit Quellenhinweis. HF-Farben entsprechen den vorhandenen HF-Zonen; Pace-Farben werden relativ zu den Werten des gewählten Laufs skaliert. Karte und Verlaufskurve sind über die Messzeit verknüpft. Ohne GPS bleibt ein Hinweis sichtbar. Der bestehende Zugriff auf die eigenen Laufdaten schützt auch die Route; Supporter erhalten keine GPS-Daten. Kartenkacheln lädt der Browser von tile.openstreetmap.org (IP-Adresse und angezeigter Kartenausschnitt sind dabei für den Anbieter erkennbar); die HF-/Pace-Werte und die Streckenlinie zeichnet der Browser lokal. Die Performance-Seite wird mit Cache-Control private, no-store ausgeliefert. GPS-Daten unterliegen derselben Cache-Löschung wie andere Performance-Details. Keine zusätzliche Datenbankmigration nötig.

### Detail-Sync-Budget

Der reguläre Sync verarbeitet standardmäßig bis zu fünf Detail-Läufe je Cronlauf, mit einem lokalen Budget von 30 pro Stunde und 180 pro UTC-Tag. Private .env-Optionen: PERFORMANCE_BATCH_SIZE (1–20, Standard 5), PERFORMANCE_MAX_PER_HOUR (1–200, Standard 30), PERFORMANCE_MAX_PER_DAY (1–1000, Standard 180). Bei leerer Warteschlange, ausgeschöpftem Budget oder einer aktiven HTTP-429-Pause endet die Verarbeitung vorzeitig. Der bestehende Sync-Lock verhindert parallele Cronläufe. Diese Werte sind lokale Abrufgrenzen; Stravas tatsächliche App-Limits gelten zusätzlich. Budgetzählung basiert weiterhin auf den gespeicherten Erfolgs-/Fehlerdatensätzen.
