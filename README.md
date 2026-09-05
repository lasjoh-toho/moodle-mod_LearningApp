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
  Lehrkräfte je Aktivität „App-Daten lokal in Moodle speichern“ wählen,
  und im Player selbst über den Button „Lokal speichern“ jederzeit eine
  frische Kopie anstoßen (Capability `mod/learningapp:storelocally`,
  `store_locally.php`). Moodle lädt dann einen Schnappschuss der App
  herunter und bettet dabei Bilder, Audio, Video und Schriftarten direkt
  als Base64-Data-URIs ein; Skripte und Stylesheets werden inline
  übernommen. Das Ergebnis ist eine einzelne, eigenständige
  `snapshot.html`-Datei im Moodle-Dateisystem
  (`classes/local/storage_manager.php`, ausgeliefert über `pluginfile.php`).
  Die tatsächliche Größe der eingebetteten Kopie wird direkt am
  „Lokal speichern“- und „Herunterladen“-Button angezeigt. Die maximale
  Gesamtgröße der eingebetteten Medien pro Aktivität lässt sich unter
  „Maximale Größe eingebetteter Medien (MB)“ einstellen (Standard: 15 MB)
  — größere oder zusätzliche Ressourcen werden übersprungen und bleiben
  als externer Link bestehen.

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

  Manche CDN-Ressourcen (insbesondere Schriftarten) lehnen direkte,
  referrer-lose Anfragen mit einer HTML-Fehlerseite statt eines echten
  HTTP-Fehlercodes ab. Der Storage-Manager sendet deshalb einen
  Referer/User-Agent-Header mit und prüft für jede eingebettete
  Ressource zusätzlich, ob HTTP-Status und Content-Type überhaupt zum
  erwarteten Ressourcentyp passen (`response_is_usable()`) — eine
  fälschlich als Bild/Schriftart „erfolgreiche“ Fehlerseite wird verworfen
  statt eingebettet, statt das umgebende CSS/HTML unbemerkt zu zerschießen.

  **Deduplizierung:** Da mehrere Aktivitäten (auch über Kurse hinweg)
  dieselbe LearningApps-App einbinden können, wird zusätzlich zur
  aktivitätseigenen Kopie eine mit der LearningApps-App-ID getaggte
  Kopie in einem geteilten, systemweiten Cache abgelegt. Automatische
  Erzeugung (Aktivität speichern, HTML-Download bei Bedarf) nutzt diesen
  Cache, statt dieselbe App erneut vollständig herunterzuladen und
  einzubetten. Der manuelle „Lokal speichern“-Button erzwingt dagegen
  immer einen frischen Abruf und aktualisiert diesen geteilten Cache.

  **Laufzeit-Übungsdaten:** Die eigentlichen Übungsinhalte (Bilder,
  Reihenfolge, Texte) sind bei vielen App-Typen nicht Teil des
  statischen HTML, sondern werden beim Start per synchronem
  `XMLHttpRequest` von `learningapps.org/data?id=…` nachgeladen — ohne
  Netzwerkzugriff bliebe die App sonst dauerhaft bei ihrem Lade-Spinner
  hängen. Der Storage-Manager lädt diese Antwort deshalb serverseitig
  mit und injiziert einen kleinen `XMLHttpRequest`-Shim in das
  verschachtelte Dokument, der genau diese eine Anfrage abfängt und die
  mitgelieferten Daten zurückgibt (`inject_data_shim()`), sowohl für
  synchrone als auch asynchrone Anfragen.

  > **Hinweis:** learningapps.org bietet keine offizielle Export-Schnittstelle.
  > Der lokale Schnappschuss ist ein Best-Effort-Mirror: Bilder/Medien/CSS/JS
  > sowie der eine bekannte Laufzeit-Datenendpunkt werden eingebettet. Andere,
  > bisher nicht beobachtete dynamische Nachladevorgänge einzelner App-Typen
  > lassen sich damit weiterhin nicht erfassen — in diesem Fall bindet das
  > Modul automatisch wieder die externe Quelle ein.
  > lassen sich damit nicht vollständig offline darstellen — in diesem Fall
  > bindet das Modul automatisch wieder die externe Quelle ein.
- **Player mit Vollbild**: `view.php` rendert die App in einem responsiven
  iFrame mit echtem Vollbildmodus (`Fullscreen API`, `amd/src/player.js`).
- **HTML-Download (optional, admin-freigebbar)**: Unter *Website-Administration
  → Plugins → Aktivitäten → LearningApp* kann der Administrator/die
  Administratorin „HTML-Download erlauben“ aktivieren. Ist das aktiv, sehen
  Lehrkräfte (Capability `mod/learningapp:downloadhtml`) im Player einen
  „Als HTML herunterladen“-Button (mit Größenangabe), der die Aktivität als
  eigenständige `.html`-Datei zum Speichern anbietet (`download.php`).
  Existiert noch kein lokaler Schnappschuss, wird beim ersten Download
  automatisch einer erzeugt (derselbe Mechanismus wie bei „Lokale
  Wiederverwendung“, inkl. Dedup-Cache, siehe deren Einschränkungen oben).
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
├── amd/src/player.js        Fullscreen- und AJAX-Abgabe-Logik (inkl. AppSolved-Auto-Erkennung)
├── classes/event/           Moodle-Events
├── classes/local/           Storage-Manager: Einbettung, Dedup-Cache, Validierung
├── classes/privacy/         DSGVO-Privacy-Provider
├── db/access.php            Capabilities
├── db/install.xml           Datenbankschema
├── db/upgrade.php           Upgrade-Schritte
├── lang/de, lang/en         Sprachdateien
├── lib.php                  Kernfunktionen, URL-Transformation, Gradebook
├── mod_form.php             Aktivitätsformular inkl. URL-Validierung
├── settings.php             Admin-Einstellungen (lokale Speicherung, HTML-Download, Größenlimit)
├── view.php                 Player-Ansicht (Vollbild, Lokal speichern, Herunterladen)
├── store_locally.php        Manueller Trigger für die lokale Speicherung
├── download.php             HTML-Download-Endpoint
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
