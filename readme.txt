=== Wir sind im Urlaub ===
Contributors: amarenasoftware
Tags: urlaub, betriebsurlaub, banner, popup, hinweis
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Betriebsurlaub eintragen und als eleganter Balken, Popup oder schwebende Karte anzeigen – mit Schulferien-Vorschlägen und automatischem Ausblenden.

== Description ==

Mit „Wir sind im Urlaub“ informierst du Besucher deiner Webseite stilvoll über deinen Betriebsurlaub:

* **Drei Darstellungsformen:** Balken (oben/unten), zentrales Popup oder schwebende Karte in der Ecke.
* **Fünf Farbwelten** plus frei wählbare eigene Farben.
* **Automatisches Ausblenden:** Nach dem letzten Urlaubstag verschwindet der Hinweis von selbst.
* **Vorankündigung:** Optional schon einige Tage vor Urlaubsbeginn informieren.
* **Schulferien per Klick:** Vorschläge für alle 16 Bundesländer (OpenHolidays API, Fallback ferien-api.de) – ein Klick übernimmt den Zeitraum.
* **Countdown:** „Wieder da in X Tagen“ als dezenter Chip.
* **Live-Vorschau** direkt in den Einstellungen sowie Vorschau auf der echten Webseite (`?wsiu_preview=1`).
* **Shortcode** `[wir_sind_im_urlaub]` für die Einbettung im Seiteninhalt.
* Besucher-Entscheidungen (Popup geschlossen) werden respektiert: einmal pro Besuch, pro Tag oder bei jedem Aufruf.

Platzhalter für die Texte: `{start}`, `{ende}`, `{wieder_da}`.

== Installation ==

1. Plugin-Ordner nach `wp-content/plugins/` hochladen oder ZIP über „Plugins → Installieren“ hochladen.
2. Plugin aktivieren.
3. Unter „Urlaubsmodus“ im Admin-Menü den Zeitraum eintragen (oder Schulferien anklicken), Darstellung wählen, speichern – fertig.

== Frequently Asked Questions ==

= Verschwindet der Hinweis wirklich von allein? =

Ja. Am letzten Urlaubstag bleibt der Hinweis bis zur eingestellten End-Uhrzeit (Standard 23:59 Uhr) sichtbar und wird danach nicht mehr ausgegeben. Für zwischengespeicherte Seiten prüft zusätzlich ein Skript im Browser den Endzeitpunkt.

= Woher kommen die Schulferien-Daten? =

Von der freien OpenHolidays API (openholidaysapi.org); fällt diese aus, wird ferien-api.de befragt. Die Ergebnisse werden 12 Stunden zwischengespeichert.

== Changelog ==

= 0.0.1 =
* Erste Version.
