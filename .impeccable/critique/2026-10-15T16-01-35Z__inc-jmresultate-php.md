---
target: jmresultate.php Funktionalitaet Streicher und Spalten
total_score: 23
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 4
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\jmresultate.php"
target_fingerprint: "sha256:5ff23ea9c621c5f930547e53e923995dc77d12e6faa123f2e0d479b2bdb51593"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\jmresultate.php"
timestamp: 2026-10-15T16-01-35Z
slug: inc-jmresultate-php
closed: true
---
Method: dual-agent (A: Design-/Funktions-Review · B: Detektor)

Fokus laut Auftrag: Streicher-Berechnung während des Jahres, Spaltendarstellung.

## Design Health Score

| # | Heuristik | Score | Kernbefund |
|---|---|---|---|
| 1 | Systemstatus | 2 | Bis zum Endstich weder Total noch Rang; «–» trennt «verpasst» nicht von «noch nicht erfasst» |
| 2 | Reale Welt | 2 | «Auswärtige Schiessen» enthält Endstich, Vereinscup, Schlossturm; hochgerechnete Werte ohne Rohwert |
| 3 | Kontrolle | 3 | Ungespeichert-Schutz, Esc ok; Panel-Speichern löscht zwischenzeitlich gemeldete Werte |
| 4 | Konsistenz | 1 | Vier Streicher-Rechnungen mit vier Basen (Screen, PDF, Absendenbuch, Portal) |
| 5 | Fehlervermeidung | 2 | Rangliste schaltet nur nach Datum um; abgesagter Anlass = 0 für alle (nur Screen) |
| 6 | Wiedererkennen | 2 | Nur 6 Spalten, Köpfe auf 78px gekürzt (Muota ×3 identisch), Total nicht nachrechenbar |
| 7 | Effizienz | 3 | Tastatur im Panel, Kopf öffnet Erfassung; Rangliste ohne Sortierung/Alle-Anlässe-Ansicht |
| 8 | Ästhetik | 3 | Ruhig; Tooltip «Gewertet» auf jeder Zelle ist Rauschen |
| 9 | Fehlerbehebung | 3 | Klartext mit nächstem Schritt |
| 10 | Hilfe | 2 | Hilfe sagt «noch keine Streicher» und nennt falsche fehlende Spalten; Anzahl Streicher und 0-Regel stehen nirgends |
| **Total** | | **23/40** | **Acceptable** |

## Priority Issues

[P1] Vier Streicher-Rechnungen, vier Basen. Screen load_jm.php:239-268 (Datum erreicht → 0-Platzhalter), PDF generate_pdf_jm.php:271-297 (Anlass mit ≥1 jmresultate-Eintrag in der Kategorie; Endstich nie), Absendenbuch functions.inc.php:2446ff und Portal inc_jm_stand.php:209ff (global «irgendwer hat Resultat»). hidden-Filter uneinheitlich. Beispiel: S.vo. ohne Endstich → Screen 1779.50 Rang 2, PDF 1686.50 Rang 8. Fix: eine Funktion jmWertung() für alle Ausgaben.

[P1] Unterjährig weiss Admin weniger als Portal und PDF. Screen zeigt bis Endstich kein Total/keine Streicher (load_jm.php:325-340), Portal zeigt Total + «Streicher 3/3», PDF druckt «Rangliste» mit Podest. Am 03.05. (4 von 16) streicht PDF/Portal 3 → nur bestes Resultat zählt. Regel für Zwischenstand (fix 3 / anteilig / nur Verpasstes) ist fachlich nicht entschieden. Fix: Vorstand entscheidet Regel, eine Funktion rechnet, überall «Zwischenstand per … · provisorisch».

[P1] Umschaltung auf Rangliste nur nach Datum (load_jm.php:274-286). Ab 00:00 Endstich-Tag: Endstich = 0 für alle, Podest, Gleichstand-Regel mit 0. Kat. B: zwei Schützen teilen Rang 10 bei 40.00, einer hat definitiv 135.00. Fix: Datum UND Resultate erfasst, besser expliziter Abschluss.

[P1] Nur die letzten 6 Anlässe als Spalten (load_jm.php:474-486), ohne Max-20-Anlässe. 10 von 16 Streicher-Anlässen unsichtbar, gestrichene Werte dort nicht sichtbar, Total nicht nachrechenbar, .jm-th-label max-width:78px (jmresultate.php:80). Fix: alle Streicher-Anlässe in schmalen Spalten, Rang+Name sticky, Pflicht als Summenspalte, «Streich 13/16» + Total, gestrichene 0 durchgestrichen.

[P2] Aufschlüsselung erklärt Streicher nicht: «13 gewertet · 1 gestrichen · 2 verpasst» verschweigt, dass Verpasste Streicher verbrauchen; «Auswärtige Schiessen» falsch; Muota 77 erscheint nur als 96.25. Fix: alle Streicher-Zeilen inkl. 0, «77 → 96.25», Gruppe «Streichresultate».

[P2] Panel-Speichern sendet alle Felder; leer = DELETE (save_anlass.php:104-106) → Portal-Meldung/Import zwischen Öffnen und Speichern geht verloren. Fix: nur geänderte Felder senden.

## Persona Red Flags

Alex: keine Sortierung/Filter, Streicher nur per Aufklappen, zwei PDFs «Rangliste» mit verschiedenen Totalen.
Sam: Streicher nur grau+durchgestrichen + Tooltip; keine caption/scope; Zeile nur per kleinem Chevron per Tastatur.
Vorstand abends: nach Import «Wer führt?» → «offen»; Karten ohne Datum/«durchgeführt, 0 erfasst»; am Endstich-Abend falsche Rangliste.
Riley: eingegebene 0 zählt in Rangliste, nicht auf Karte, Portal = nicht teilgenommen; hidden-Anlass zählt in Screen/PDF; Gleichstand per !== auf Floats (load_jm.php:528).

## Minor

status='entwurf' zählt ungekennzeichnet mit; PDF gibt Mitgliedern ohne Resultat einen Rang; 2 vs. 0 Nachkommastellen; initResponsive hängt bei jedem Laden neuen matchMedia-Listener an; colspan="100%" ungültig (jmresultate.php:791, 824); zwei 60vh-Scrollbereiche; Absendenbuch Pflicht ohne Hochrechnung; checkIfSSMExists sucht fest «SSM 2024».

Detektor: 0 Befunde in jmresultate.php, inc/jmresultate/, load_jm.php (Gegenprobe bestätigt, dass PHP gescannt wird). Browser übersprungen (Vorgabe, Login).

## Fragen

Welche Zwischenstand-Regel gilt? Warum weiss das Portal mehr als der Admin? Soll ein gestrichener Endstich Gleichstand entscheiden? Ist «letzte 6» Entscheid oder Notlösung?
