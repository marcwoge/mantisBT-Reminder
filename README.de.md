# MantisBT – Open Ticket Reminder

[English](README.md) · **Deutsch**

Ein MantisBT-Plugin, das Benutzer an ihre **offenen Tickets** erinnert.

* **Wöchentliche Übersicht** über alle offenen Punkte eines Benutzers –
  standardmäßig **1× pro Woche, montags um 09:00 Uhr** (Wochentag und Uhrzeit
  frei einstellbar).
* **Optionale Einzel-Erinnerungen** pro Ticket in festem Intervall
  (z. B. „alle X Tage erinnere mich an dieses offene Ticket“).
* **Pro Benutzer einstellbar** – jeder kann die globalen Vorgaben des
  Administrators für sich überschreiben oder die Erinnerungen abschalten.
* **Projekt-Filter** – ganze Projekte, die keine Erinnerung benötigen,
  ausschließen – global und zusätzlich pro Benutzer.
* **Ansprechende HTML-Mails** (mit reinem Text als Fallback) oder wahlweise
  reine Text-Mails.
* **Mehrsprachig** – Englisch, Deutsch, Französisch, Spanisch und Italienisch,
  automatisch passend zur MantisBT-Sprache des Empfängers (Fallback Englisch).

---

## Funktionen im Detail

### 1. Wöchentliche Übersicht (Digest)
Eine E-Mail mit allen offenen Tickets des Benutzers. Konfigurierbar:

| Option | Standard | Beschreibung |
| --- | --- | --- |
| Wochentag | Montag | An welchem Tag die Übersicht verschickt wird |
| Uhrzeit | 09:00 | Stunde des Versands (0–23) |
| Zugewiesene Tickets | ein | Tickets, die mir zugewiesen sind |
| Gemeldete Tickets | aus | Tickets, die ich gemeldet habe |
| Beobachtete Tickets | aus | Tickets, die ich beobachte (Monitor) |
| Leere Übersicht überspringen | ein | Keine Mail senden, wenn nichts offen ist |

### 2. Einzel-Erinnerungen pro Ticket
Erinnert in festem Intervall an einzelne offene Tickets. Konfigurierbar:

| Option | Standard | Beschreibung |
| --- | --- | --- |
| Aktiviert | aus | Einzel-Erinnerungen einschalten |
| Intervall | 7 Tage | Frühestens alle N Tage pro Ticket erinnern |
| Nur wenn unverändert | 0 | Nur Tickets ohne Aktivität seit N Tagen (0 = immer) |

### 3. Weitere sinnvolle Features (bereits enthalten)

* **Überfällig-Markierung** – Tickets mit überschrittenem Fälligkeitsdatum
  (`due_date`) werden in der Mail mit `[ÜBERFÄLLIG]` gekennzeichnet.
* **Priorisierte Sortierung** – höchste Priorität zuerst, danach die am
  längsten nicht aktualisierten Tickets oben.
* **„Zuletzt aktualisiert vor X Tagen“** pro Ticket, damit liegengebliebene
  Punkte sofort auffallen.
* **Anti-Spam-Logik** – ein Log-Table merkt sich, was bereits verschickt
  wurde; ein stündlicher Cron erzeugt daher keine Doppel-Mails.
* **Pro-Benutzer-Overrides** – globale Admin-Vorgaben plus persönliche
  Einstellungen unter *Mein Konto → Erinnerungs-Einstellungen*.
* **Sprache pro Empfänger** – die Mail nutzt die Sprache des Empfängers.
* **Zwei Cron-Wege** – CLI-Skript *oder* tokengeschützter Web-Endpunkt für
  Hosts ohne Shell-Zugriff.

Mögliche spätere Erweiterungen sind in [CHANGELOG.md](CHANGELOG.md) notiert.

---

## Installation

1. Plugin beziehen – entweder das aktuelle Release-Archiv von der
   [Releases-Seite](https://github.com/marcwoge/mantisBT-Reminder/releases)
   herunterladen oder den Ordner **`Reminder/`** aus diesem Repository kopieren.

2. Den Ordner in das Plugin-Verzeichnis deiner MantisBT-Installation legen:

   ```
   <mantisbt>/plugins/Reminder/
   ```

   > Wichtig: Der Plugin-Ordner muss exakt `Reminder` heißen (nicht
   > `mantisBT-Reminder`), sonst findet MantisBT die Plugin-Klasse nicht.

3. In MantisBT als Administrator anmelden →
   **Verwaltung → Verwalte Plugins** → *Open Ticket Reminder* installieren.

4. Unter **Verwaltung → Verwalte Plugins → Open Ticket Reminder** die globalen
   Vorgaben setzen (Wochentag, Uhrzeit, Intervalle …).

5. Den Versand per Cron einrichten (siehe unten). **Ohne Cron werden keine
   Erinnerungen verschickt** – MantisBT bringt keinen eigenen Scheduler mit.

---

## Cron einrichten

Das Plugin entscheidet selbst, *wann* welche Mail fällig ist. Du musst den
Dispatcher daher nur **stündlich** auslösen – am besten zur vollen Stunde.

### Variante A – Kommandozeile (empfohlen)

**Linux / crontab** (`crontab -e`):

```cron
0 * * * * php /pfad/zu/mantisbt/plugins/Reminder/cron/reminder_cron.php >/dev/null 2>&1
```

**Windows – Aufgabenplanung (Task Scheduler):**

* Programm/Skript: `C:\xampp\php\php.exe` (Pfad zu deiner `php.exe`)
* Argumente: `D:\mantisbt\plugins\Reminder\cron\reminder_cron.php`
* Trigger: täglich, alle 1 Stunde wiederholen

Test-Versand der Übersicht sofort (ignoriert Wochentag/Uhrzeit):

```bash
php plugins/Reminder/cron/reminder_cron.php force-digest
```

### Variante B – Web-Endpunkt (für Hosts ohne Shell)

1. Auf der Plugin-Konfigurationsseite ein **Web-Cron-Token** (geheime
   Zeichenkette) setzen.
2. Einen externen Scheduler / Uptime-Dienst stündlich diese URL aufrufen
   lassen:

   ```
   https://dein-mantis/plugin.php?page=Reminder/cron&token=DEIN_TOKEN
   ```

Ohne gültiges Token antwortet der Endpunkt mit `403 Forbidden`.

---

## Per-Benutzer-Einstellungen

Jeder Benutzer findet unter **Mein Konto → Erinnerungs-Einstellungen** seine
persönlichen Optionen. Werte, die hier gesetzt werden, überschreiben die
globalen Admin-Vorgaben; alles andere wird weiterhin von den globalen Vorgaben
geerbt.

## Projekt-Filter

Manche Projekte benötigen keine Erinnerungen. Du kannst sie ausschließen:

* **Global (Admin)** – auf der Plugin-Konfigurationsseite die auszuschließenden
  Projekte auswählen. Das ist die **Standard-Vorauswahl** für jeden Benutzer.
* **Pro Benutzer** – unter *Mein Konto → Erinnerungs-Einstellungen* kann jeder
  Benutzer seine Auswahl frei anpassen (Projekte hinzufügen oder entfernen) und
  ein global ausgeschlossenes Projekt für sich **wieder aktivieren**.

Solange ein Benutzer nichts angepasst hat, gilt die globale Vorauswahl. Sobald
er eine eigene Auswahl speichert, wird diese persönliche Auswahl verwendet.

---

## E-Mail-Format (HTML)

Standardmäßig werden **HTML-Mails** mit einer kleinen Übersichtstabelle
verschickt (überfällige Tickets rot markiert), inklusive Klartext-Variante als
Fallback. Umstellbar global und pro Benutzer über die Option *E-Mail-Format*
(HTML / Reiner Text).

> Hinweis: HTML-Mails werden direkt über die SMTP-/Mail-Einstellungen von
> MantisBT versendet (PHPMailer). Schlägt das fehl oder ist PHPMailer nicht
> verfügbar, fällt das Plugin automatisch auf die normale Text-Mail-Warteschlange
> von MantisBT zurück.

---

## Sprachen

Das Plugin bringt Übersetzungen für **Englisch, Deutsch, Französisch, Spanisch
und Italienisch** mit. MantisBT verwendet automatisch die eingestellte Sprache
des jeweiligen Empfängers und fällt für Fehlendes auf Englisch zurück.

Eine weitere Sprache hinzufügen ist einfach: `Reminder/lang/strings_english.txt`
nach `strings_<sprache>.txt` kopieren (passend zum MantisBT-Sprachnamen) und die
Werte übersetzen. Pull Requests mit neuen oder verbesserten Übersetzungen sind
herzlich willkommen.

---

## Tests

Die Kernlogik (Zeitplan-Entscheidung, Intervall-/Stale-Prüfung, E-Mail-Rendering)
ist durch Unit-Tests abgedeckt, die **ohne MantisBT, ohne Datenbank und ohne
Webserver** laufen – die nötigen MantisBT-Funktionen werden durch Stubs ersetzt
([tests/stubs.php](tests/stubs.php)).

**Du musst MantisBT dafür nicht installieren.** Zwei Wege:

1. **Automatisch via GitHub Actions** – bei jedem Push laufen die Tests auf
   PHP 7.4–8.3 (siehe [.github/workflows/tests.yml](.github/workflows/tests.yml)).
   So brauchst du lokal gar nichts.

2. **Lokal** – nur eine PHP-CLI nötig (kein MantisBT):

   ```bash
   php tests/run_tests.php
   ```

   Ohne lokales PHP geht es auch per Docker:

   ```bash
   docker run --rm -v "$PWD":/app -w /app php:8.2-cli php tests/run_tests.php
   ```

Der Runner gibt am Ende `Passed: N  Failed: 0` aus und liefert bei Fehlern den
Exit-Code 1 (CI-tauglich).

---

## Ausprobieren mit Docker (ohne eigene MantisBT-Installation)

Im Ordner [`docker/`](docker/) liegt eine komplett lauffähige Testumgebung
(MantisBT + MariaDB + Mailpit als Mail-Catcher). Das Plugin ist dort bereits
eingehängt.

```bash
docker compose up -d --build
```

* MantisBT: http://localhost:8989 (`administrator` / `root`)
* Mailpit (zeigt alle E-Mails): http://localhost:8025

Versand zum Testen auslösen:

```bash
docker compose exec mantis php plugins/Reminder/cron/reminder_cron.php force-digest
```

Die vollständige Schritt-für-Schritt-Anleitung steht in
[docker/README.md](docker/README.md).

---

## Kompatibilität

* MantisBT **2.0.0** oder neuer
* PHP 5.6+

## Lizenz

[GNU General Public License v2.0](LICENSE) – wie MantisBT selbst.
