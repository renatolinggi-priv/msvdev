---
target: Raster-Erfassung Heim/Kanti
total_score: 26
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\heimresultate.php"
target_fingerprint: "sha256:076e67665707f415be8ad39114313a60132a06a0cd29077224035d9013167121"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\heimresultate.php"
timestamp: 2026-10-16T12-11-36Z
slug: inc-heimresultate-php
closed: true
---
Method: dual-agent (A: Design-Review · B: Detektor + Browser), lokale Prod-Kopie 16.10.2026, QZ gesperrt. Ziel: inc/heimresultate.php + inc/kantiresultate.php (Baustein Raster-Erfassung 1.68.37/38).

## Design Health Score
| # | Heuristik | Wert | Befund |
|---|---|---|---|
| 1 | Systemstatus | 3 | Ungespeichert-Punkt/Chip/vorläufig gut; Panel ohne Chip; nach Ctrl+S Fokus auf BODY |
| 2 | Sprache | 3 | Schnellerfassung nicht in Standblatt-Reihenfolge |
| 3 | Kontrolle | 2 | Esc/Blättern im Panel ohne Rückfrage |
| 4 | Konsistenz | 2 | Endschiessen-Panel hat Chip, Fehlerliste, nächstes offenes; Raster-Panel nicht |
| 5 | Fehlervermeidung | 2 | Panel ohne Prüfung; Auto-Weiter+Enter überspringt Passe |
| 6 | Wiedererkennen | 3 | Tastenleiste, ?-Hilfe |
| 7 | Effizienz | 3 | Fokus nach Speichern weg; sendet alle 22 Zeilen |
| 8 | Ästhetik | 3 | 1366px: Kopf bricht um, 8 von 22 Zeilen sichtbar |
| 9 | Fehlerbehebung | 2 | Panel-Fehler nur Toast ohne Ort |
| 10 | Hilfe | 3 | Hilfetexte vorhanden |
| Total | | 26/40 | Akzeptabel |

## Priority Issues
- [P1] Schnellerfassung (Panel) ohne Prüfung, Ungespeichert-Chip, Rückfrage beim Schliessen (msv-raster-erfassen.js:775–801, 702–730). → harden
- [P1] Kanti: ungültiger Wert (150) wird beste Passe (Stern, Gold, aria «beste Passe»), Gold überdeckt Fehlerrot (js:136–146, 474; msv-ui.css Raster-Block). → harden
- [P2] Auto-Weiter + Enter überspringt Passe (js:828–851). → clarify
- [P2] Nach Speichern Fokus/Scroll weg; POST sendet alle Zeilen (Überschreibgefahr) (js:371–386). → harden
- [P2] «Auf Website melden» ohne Rückfrage bei Ungespeichertem (js:446). → harden

## Detektor
PHP: Exit 0. Gerendert: 52 Befunde je Seite, keiner echt aus dem Baustein (Navigation, SweetAlert2, erlaubte Muster). Browser: 0 Konsolenfehler, kein Querscrollen, 0 unbenannte Felder, Fokus überall sichtbar; Kontrast nur Leerwert «–» 1.83 (gewollt). Kleinste Schrift 11.52px.

## Persona-Red-Flags
- Alex: Maus nach Ctrl+S; Speichern & Weiter durch 14 Nichtschützen.
- Sam: Fokus 2× auf BODY; Select2 zuletzt in Tab-Folge; ungültige Kanti-Passe als beste angesagt.
- Vorstand: 8 Zeilen bei 1366px; Start bei Nichtschütze.

## Minor
Leerfeld-Rand 2.83:1; Handy-Fehlerlink auf verborgenes Feld; Toast-Texte uneinheitlich; ungültiges ?year still ersetzt; Daten Kälin Norbert Kanti.
