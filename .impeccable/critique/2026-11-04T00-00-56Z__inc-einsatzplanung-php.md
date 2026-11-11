---
target: critique Einsatzplanung
total_score: 24
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\einsatzplanung.php"
target_fingerprint: "sha256:ff4760e70d26e860aa281b2c4fca43f7dabe68a18cc291bb6ac734e361c55c92"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\einsatzplanung.php"
timestamp: 2026-11-04T00-00-56Z
slug: inc-einsatzplanung-php
closed: true
---
Method: dual-agent (A: Design-Review aus Code, B: Detektor + Greps); Browser-Prüfung ausgelassen (kein lokales PHP, Login nötig, QZ-Zertifikatsabfragen)

## Gesamtwertung 24/40 (Acceptable)
| # | Heuristik | Punkte | Hauptbefund |
|---|---|---|---|
| 1 | Systemstatus | 3 | Zähler/Helferstunden live; Panel-Auto-Save ohne Gespeichert-Anzeige |
| 2 | Sprache | 3 | Anfrage-Rolle, Pos. je Termin, Plan #12, Migration 054 ausgeführt? |
| 3 | Kontrolle | 2 | Plan in jedem Status löschbar; Modal-Zeilen gehen beim Schliessen verloren |
| 4 | Konsistenz | 2 | drei Speichermodelle; Vereinsfarben kollidieren mit Bedeutungsfarben |
| 5 | Fehlervermeidung | 2 | Einteilen-Sperre gut; Löschen/Termin-Änderung bei freigegebenen Plänen ungeschützt |
| 6 | Erkennen | 2 | Ablauf-Reihenfolge im Kopf; Umfrage unter Titel/Fusstext versteckt |
| 7 | Effizienz | 3 | Drag/Tausch gut; Strukturänderungen laden ganze Seite |
| 8 | Reduziert | 2 | ~17 gleich gewichtete Knöpfe, lange Hinweistexte |
| 9 | Fehlerbehebung | 2 | teils Entwicklersprache, nacktes «Fehler» |
| 10 | Hilfe | 3 | 3 Hilfetexte, keiner der 11 Modals hat «?» |

## Design-Spezifität
Fachlich hochspezifisch (Obli/Feld/Schlossturm/Chilbi, 3 Vereine, Word-Roundtrip, Personalanfrage, Helferstunden), Oberfläche bewusst SFARL-Admin. Spezifität ist zu Feature-Ansammlung geworden: kein sichtbarer Ablauf.
Detektor: exit 0, 4 Hinweise (font-size .76rem Z. 92/231, 1.05rem Z. 115; radius .55rem Z. 109). Nicht gesehen: inc/helferabrechnung/ansicht_css.inc.php (via Z. 254) mit 14 Hex-Farben. Fehlalarme: btn-primary Z. 276–277 = Umschalter; Hex Z. 916–919 = Dokumentfarben; 18 Icon-Knöpfe aria-label via msv-tooltips.js.

## Stärken
1. Einteilung als Vorschlag (gestrichelt, einzeln/alle übernehmen, Bereitschaft je Verein).
2. Word-Roundtrip mit Rückmeldung-Vorschau bisher/neu.
3. Live-Bilanz eingeteilt/angeboten + Helferstunden je Verein.

## Priority Issues
- [P1] Kein roter Faden (Werkzeugleiste Z. 356–432 nach Objekt statt Ablauf, kein gefüllter Hauptknopf). Fix: Schrittleiste in $page_extra mit Status je Schritt, nächster Schritt = btn-primary, Umfrage in Verfügbarkeiten. → onboard, layout
- [P1] Löschen ungeschützt: plan_delete.php ohne Statusprüfung, ohne Anwesenheits-Sperre, ohne Backup; Bestätigung nennt Folgen nicht. Fix: nur Entwurf ohne Anwesenheit, msvBackupVorher, Folgen im Text; Termin-Löschen analog. → harden
- [P1] Farbkollision: MSV rot = offen = nicht da = Gefahr; Freienbach blau = Vorschlag = Auswahl; Wollerau grün = anwesend. Fix: Vereinsfarbe nur als Punkt/Kürzel, Chipfläche neutral, offen = Bernstein, Vorschlag neutral gestrichelt mit Wort. → colorize
- [P2] Drei Speichermodelle, Modal-Schliessen verwirft still. Fix: ein Speichern pro Modal oder msvUngespeichert-Sperre; Gespeichert-Chip im Panel. → harden, clarify
- [P2] Werkzeugleiste überladen, schrumpft statt zu reduzieren (Z. 90–99). Fix: «Plan einrichten»-Menü. → distill

## Persona Red Flags
Alex: Reload je Strukturänderung, keine Tastenkürzel, 40× ✓ in Verfügbarkeiten.
Sam: Aufklappen (td.ep-fn, #epAlleToggle span, .ep-side-sub, .ep-summary) nicht per Tastatur, Zeilen standardmässig zu; Chip-Name ohne Kontext; .an-btn ohne aria-pressed; Modals ohne aria-labelledby.
Vorstand im Ehrenamt: 17 Knöpfe ohne nächsten Schritt; Kopie vs. Leer+Stammdaten unklar; Umfrage unauffindbar; Final vs. Freigegeben unklar; Einteilen-Sperrgrund nur im Tooltip auf deaktiviertem Knopf.

## Kleinere Beobachtungen
Jahresauswahl in $page_actions; Liste mit .table-wrapper statt .ui-karte; Word-Knopf ausserhalb btn-group, Editor-Dokumente im Dropdown; 48 Inline-Styles, Bootstrap-Badges, --primary-color-Altlasten; Auswertung türkis, Vorschläge übernehmen grün, Position entfernen rot ohne Bestätigung; Freigeben-Dialog generisch, Push vorgehakt, keine Zahlen; kein «?» bei Einteilung/Verfügbarkeiten/Rückmeldung/OK; leere Zeilen ohne msv_empty_row().

## Fragen
1. Editor als geführter Ablauf statt Werkzeugkasten?
2. Vereinsfarbe nur als Punkt statt ganze Chipfläche?
3. Freigegebener Plan strukturell gesperrt bis zum Zurückziehen?
