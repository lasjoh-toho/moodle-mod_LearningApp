# moodle-mod_LearningApp

Moodle-Aktivitätsmodul zur Einbindung von Übungen aus [learningapps.org](https://learningapps.org)
mit automatischer Link-Konvertierung, optionaler lokaler Zwischenspeicherung,
Vollbild-/Zoom-Player und Anbindung an das Moodle-Notenbuch.

## Funktionen

- **Automatische URL-Konvertierung**: `display?v=`, `show?v=` und `viewXXXXX`-Links
  werden serverseitig zuverlässig in das Format `watch?v=XXXXX` umgewandelt.
  Ungültige Links werden im Aktivitätsformular abgelehnt (`mod_form.php` +
  `learningapp_transform_url()` in `lib.php`).
- **Lokale Wiederverwendung (optional)**: Administratoren können in
  *Website-Administration → Plugins → Aktivitäten → LearningApp* die Option
  „Lokale Wiederverwendung erlauben“ aktivieren. Ist sie aktiv, können
  Lehrkräfte je Aktivität „App-Daten lokal in Moodle speichern“ wählen.
  Moodle lädt dann einen Schnappschuss der App herunter und bettet dabei
  Bilder, Audio, Video und Schriftarten direkt als Base64-Data-URIs ein;
  Skripte und Stylesheets werden inline übernommen. Das Ergebnis ist eine
  einzelne, eigenständige `snapshot.html`-Datei im Moodle-Dateisystem
  (`classes/local/storage_manager.php`, ausgeliefert über `pluginfile.php`).
  Die maximale Gesamtgröße der eingebetteten Medien pro Aktivität lässt
  sich unter „Maximale Größe eingebetteter Medien (MB)“ einstellen
  (Standard: 15 MB) — größere oder zusätzliche Ressourcen werden
  übersprungen und bleiben als externer Link bestehen.

  LearningApps liefert die abgerufene Seite als zweistufigen Wrapper aus:
  ein äußeres Dokument mit einem zunächst leeren `<iframe id="frame">`,
  dessen echte Quelle (die eigentliche Übung, `show.php?id=…`) erst zur
  Laufzeit per JavaScript gesetzt wird. Der Storage-Manager erkennt dieses
  Muster, lädt das verschachtelte Dokument mit, bettet dessen eigene
  Bilder/CSS/JS ebenfalls ein und trägt das Ergebnis direkt und statisch
  als `data:`-URI in das `<iframe>` ein — eine Verschachtelungsebene tief.
  Das ursprüngliche Bootstrap-Skript (das die Quelle sonst laufend anhand
  der Netzwerkverbindung neu setzen würde) wird entfernt und durch einen
  schlanken `postMessage`-Relay ersetzt, siehe Notenbuch-Anbindung unten.

  > **Hinweis:** learningapps.org bietet keine offizielle Export-Schnittstelle.
  > Der lokale Schnappschuss ist ein Best-Effort-Mirror: Bilder/Medien/CSS/JS,
  > die direkt im HTML referenziert werden (inkl. einer Verschachtelungsebene),
  > werden eingebettet. Stark dynamische Apps, die Inhalte erst zur Laufzeit
  > per JavaScript vom Server nachladen (z. B. Übungsdaten per API-Aufruf),
  > lassen sich damit nicht vollständig offline darstellen — in diesem Fall
  > bindet das Modul automatisch wieder die externe Quelle ein.
- **Player mit Vollbild & Zoom**: `view.php` rendert die App in einem
  responsiven iFrame mit echten Vollbild- (`Fullscreen API`) sowie
  Zoom+/Zoom-/Zoom-Reset-Buttons (CSS `transform: scale(...)`,
  `amd/src/player.js`).
- **HTML-Download (optional, admin-freigebbar)**: Unter *Website-Administration
  → Plugins → Aktivitäten → LearningApp* kann der Administrator/die
  Administratorin „HTML-Download erlauben“ aktivieren. Ist das aktiv, sehen
  Lehrkräfte (Capability `mod/learningapp:downloadhtml`) im Player einen
  „Als HTML herunterladen“-Button, der die Aktivität als eigenständige
  `.html`-Datei zum Speichern anbietet (`download.php`). Existiert noch kein
  lokaler Schnappschuss, wird beim ersten Download automatisch einer erzeugt
  (derselbe Mechanismus wie bei „Lokale Wiederverwendung“, siehe deren
  Einschränkungen oben).
- **Notenbuch-Anbindung**: Lehrkräfte legen eine maximale Punktzahl
  (`grademax`) fest. Lernende geben ihre Bearbeitung über den Button
  „Als Erledigt / Bestanden abgeben“ ab; die Punktzahl wird per AJAX
  (`ajax_submit.php`) übermittelt, im Notenbuch eingetragen und die
  Aktivität auf „Abgeschlossen“ gesetzt.

  **Automatische Erfolgserkennung:** LearningApps meldet eine gelöste
  Übung an die einbettende Seite per `postMessage` im Format
  `"AppSolved|<id>"` (siehe [learningapps.org/api](https://learningapps.org/api)).
  `amd/src/player.js` hört auf diese Nachricht und löst die Abgabe
  automatisch aus — der manuelle Button bleibt als Rückfallebene bestehen
  (nicht jeder App-Typ meldet einen eindeutigen „gelöst“-Zustand, und bei
  einem lokalen Schnappschuss kann die automatische Erkennung entfallen,
  wenn die verschachtelte Übung wegen des Größenlimits nicht eingebettet
  werden konnte).
- **Vollständig lokalisiert** (`lang/de`, `lang/en`).

## Voraussetzungen

- Moodle 4.0 oder neuer (siehe `version.php`, `$plugin->requires`)

## Installation

1. Repository nach `mod/learningapp` im Moodle-Codebase entpacken/klonen.
2. In der Website-Administration die Plugin-Installation abschließen.
3. Optional: „Lokale Wiederverwendung erlauben“ unter den Plugin-Einstellungen
   aktivieren.

## Verzeichnisstruktur

```
mod/learningapp/
├── amd/src/player.js        Fullscreen-, Zoom- und AJAX-Abgabe-Logik
├── classes/event/           Moodle-Events
├── classes/local/           Storage-Manager für die lokale Zwischenspeicherung
├── classes/privacy/         DSGVO-Privacy-Provider
├── db/access.php            Capabilities
├── db/install.xml           Datenbankschema
├── db/upgrade.php           Upgrade-Schritte
├── lang/de, lang/en         Sprachdateien
├── lib.php                  Kernfunktionen, URL-Transformation, Gradebook
├── mod_form.php             Aktivitätsformular inkl. URL-Validierung
├── settings.php             Admin-Einstellung „Lokale Wiederverwendung“
├── view.php                 Player-Ansicht
├── ajax_submit.php          Abgabe-Endpoint (Gradebook + Completion)
└── index.php                Kursweite Aktivitätsübersicht
```

## Entwicklung

Für einen produktionsreifen, minifizierten AMD-Build:

```bash
npm install
grunt amd
```

## Lizenz

GNU GPL v3 or later — siehe [LICENSE](LICENSE).
