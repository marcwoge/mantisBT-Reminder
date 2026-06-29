# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/),
die Versionierung an [Semantic Versioning](https://semver.org/).

## [1.2.0] - 2026-06-29

### Hinzugefügt
- **Projekt-Filter**: Projekte lassen sich von den Erinnerungen ausschließen –
  global durch den Administrator und zusätzlich persönlich pro Benutzer. Tickets
  aus ausgeschlossenen Projekten erscheinen weder in der Übersicht noch in den
  Einzel-Erinnerungen. Global ausgeschlossene Projekte sind für alle aus; jeder
  Benutzer kann weitere Projekte für sich ausschließen.
- Neue reine, getestete Filterfunktion `reminder_filter_excluded()`.

## [1.1.0] - 2026-06-22

### Hinzugefügt
- **HTML-E-Mails** mit kleiner Übersichtstabelle und Überfällig-Markierung;
  Klartext als Fallback. Neue Option *E-Mail-Format* (HTML/Text), global und
  pro Benutzer.
- Versand der HTML-Mails direkt über PHPMailer/SMTP von MantisBT, mit
  automatischem Rückfall auf die Text-Mail-Warteschlange bei Fehlern.
- **Mehrsprachigkeit**: zusätzliche Sprachdateien für Französisch, Spanisch und
  Italienisch (zuvor nur Englisch/Deutsch). MantisBT wählt automatisch die
  Sprache des Empfängers, Fallback Englisch.
- Englische README als Standard (`README.md`) plus deutsche Version
  (`README.de.md`) mit Sprach-Umschalter.
- Abhängigkeitsfreie Unit-Tests (`tests/run_tests.php`), die ohne MantisBT,
  Datenbank oder Webserver laufen (MantisBT-Stubs in `tests/stubs.php`).
- GitHub-Actions-Pipeline, die Lint + Tests auf PHP 7.4–8.3 ausführt.
- Komplette lokale Testumgebung unter `docker/` (MantisBT 2.28.3 + MariaDB +
  Mailpit), inkl. eingehängtem Plugin und Schritt-für-Schritt-Anleitung.

### Geändert
- Kernlogik in reine, testbare Funktionen aufgeteilt
  (`reminder_is_digest_due_at()`, `reminder_is_issue_due()`, Render-Funktionen).

### Behoben
- Verwendung der in MantisBT nicht existierenden Konstante `SECONDS_PER_HOUR`
  in der Zeitplanprüfung (führte beim regulären Cron-Lauf zu einem Fehler).
  Beim End-to-End-Test in echtem MantisBT gefunden; die Test-Stubs spiegeln nun
  die real vorhandenen Konstanten wider.

## [1.0.0] - 2026-06-08

### Hinzugefügt
- Wöchentliche Übersicht über offene Tickets pro Benutzer
  (Standard: montags 09:00 Uhr, Wochentag und Uhrzeit einstellbar).
- Optionale Einzel-Erinnerungen pro Ticket in festem Intervall (Standard 7 Tage)
  mit optionalem „nur wenn N Tage unverändert“-Filter.
- Auswahl der berücksichtigten Beziehungen: zugewiesen / gemeldet / beobachtet.
- Globale Administrator-Konfiguration plus persönliche Overrides pro Benutzer
  (Mein Konto → Erinnerungs-Einstellungen).
- Überfällig-Markierung (`due_date`), Prioritäts-Sortierung und Anzeige des
  letzten Aktualisierungsalters je Ticket.
- Log-Tabelle zur Vermeidung doppelter Mails bei stündlichem Cron.
- Zwei Auslöse-Wege: CLI-Skript und tokengeschützter Web-Endpunkt.
- Deutsche und englische Sprachdateien.

## Ideen für künftige Versionen
- HTML-formatierte E-Mails (zusätzlich zum Klartext).
- Eskalation an Vorgesetzte / Projektleiter bei sehr alten Tickets.
- Filter nach Projekt, Schweregrad oder Kategorie.
- Zusammenfassung mehrerer Benutzer in einer Manager-Übersicht.
- Konfigurierbarer Versandkanal (z. B. zusätzlich an eine Sammeladresse).
