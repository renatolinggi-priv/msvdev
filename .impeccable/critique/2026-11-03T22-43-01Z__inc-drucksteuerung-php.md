---
target: critique drucksteuerung
total_score: 20
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\drucksteuerung.php"
target_fingerprint: "sha256:89d7b860c484787c54f856e69ed0a9e792d358b51b3ef5013375782e0149e220"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\drucksteuerung.php"
timestamp: 2026-11-03T22-43-01Z
slug: inc-drucksteuerung-php
---
Method: dual-agent (A: Design-Review aus Code, B: Detektor + Greps); Browser-Prüfung ausgelassen (kein lokales PHP, Login nötig, QZ-Zertifikatsabfragen)

## Gesamtwertung 20/40 (Acceptable)
| # | Heuristik | Punkte | Hauptbefund |
|---|---|---|---|
| 1 | Systemstatus | 2 | kein Ungespeichert-Hinweis, Fehlerursache nur im Hover |
| 2 | Sprache | 2 | Systemname, Arbeitsplatz-ID, Portrait/Landscape, Duplex Lange Seite |
| 3 | Kontrolle | 2 | Übertragen ohne Rückgängig, Verlassen verliert Änderungen |
| 4 | Konsistenz | 2 | title= statt data-tooltip, ASCII-Umlaute, grauer Test-Knopf, Karte in Karte, eigenes max-width, Modal statt Panel |
| 5 | Fehlervermeidung | 1 | Profile mit inaktivem Drucker werden beim Speichern gelöscht |
| 6 | Erkennen | 2 | Shift-Trick nur im title, Reihenfolge QZ→Drucker→Profil nicht gezeigt |
| 7 | Effizienz | 3 | Übertragen, ein Speichern, Testdruck ungespeichert |
| 8 | Reduziert | 2 | 7 Spalten × 19 Zeilen, Anzahl doppelt, Typ Etiketten/Bon |
| 9 | Fehlerbehebung | 1 | rohe QZ-Exception, Serverpfad im Dialog, kein Installationsweg |
| 10 | Hilfe | 3 | drei Hilfetexte, Installation fehlt |

## Design-Spezifität
Inhalt vereinsspezifisch (Dokumentkatalog, Saison-Gruppen), Rahmen generische Geräteverwaltung aus EWS. Detektor: 0 Befunde (sieht JS-generierte Tabellen nicht). Greps: 6× title=, 8 Knöpfe ohne type, 15 Icons ohne aria-hidden, 11 onclick (2 nicht fokussierbare div/tr), 19 Zeilen ASCII-Umlaute, 2 Knöpfe ohne Namen, lokale esc().

## Prioritäre Probleme
- [P1-A] Erster Besuch Sackgasse: Fehlerdialog bei jedem Aufruf (js:131), alle Gruppen zu (js:220), Drucker-Karte zu (php:184), leere Druckerauswahl ohne Hinweis. Fix: Hinweis in QZ-Leiste mit Download-Alternative und Einrichtungslink, Leerzustand «Zuerst Drucker übernehmen», Gruppen beim ersten Besuch offen, Test/Auswahl ohne Verbindung gesperrt. → onboard
- [P1-B] Stiller Profilverlust: inaktive Drucker fehlen in Optionen (js:524) → Profil leer → beim Speichern gelöscht (js:293–295); Drucker-Löschfrage nennt Profile nicht (js:631). Fix: inaktiven Drucker als gesperrte Option behalten, betroffene Profile in Löschfrage, Rückfrage vor Entfernen. → harden
- [P1-C] Tastatur/Screenreader: div onclick ohne role/tabindex/aria-expanded (js:222, php:177/211), Selects ohne Label, 2 Knöpfe ohne Namen. Fix: echte Buttons, aria-label je Feld, Fehlertext sichtbar. → audit, harden
- [P2-D] Ungespeichert unsichtbar, Speichern schwach. Fix: msvUngespeichert, gefüllter Hauptknopf auch unten, beforeunload. → polish
- [P2-E] Technik-/Entwicklersprache, «Duplex: Aus» beim Absendenbuch irreführend. Fix: Fehler als nächster Schritt, «Gilt für diesen Browser», A4 hoch/quer, «Beidseitig: automatisch (kurze Seite)», Umlaute. → clarify

## Personas
- Vorstand einmal pro Saison: kein QZ-Wissen, Fehlerdialog, Direktdruck auf anderen Seiten unsichtbar, neue Arbeitsplatz-ID = Profile scheinbar weg.
- Alex: Shift-Trick versteckt, kein Rückgängig, Protokoll 20 Einträge ohne Filter, zweiter Rechner = alles neu.
- Sam: Gruppen/Karten unerreichbar, div-Raster ohne Semantik, kein aria-live, Fehler nur in title.

## Kleinere Beobachtungen
Kein content-width-*, max-width 1100px, Karte in Karte; Test grau statt türkis, Verbinden blau, gefülltes Badge; Modal statt Slide-Panel, Systemname leer beim Bearbeiten ohne QZ; Status «gedruckt» nie geschrieben; «...» statt «…», gerade Anführungszeichen; Leerzustand in Entwicklersprache.

## Fragen
1. Profil beim ersten Druck-Klick anlegen statt Voraussetzungsseite?
2. Pro Zeile nur Drucker + «Abweichend einstellen»?
3. Profile beim Benutzer statt pro Browser-ID?
