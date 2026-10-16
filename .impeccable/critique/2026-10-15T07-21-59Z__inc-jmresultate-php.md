---
target: Erfassen+Rangliste vereint
total_score: 25
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\jmresultate.php"
target_fingerprint: "sha256:e2e1c20ce9c67e0d871c8aeaf714a7e5ab9b81889254678b6bd04218686ce87c"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\jmresultate.php"
timestamp: 2026-10-15T07-21-59Z
slug: inc-jmresultate-php
closed: true
---
Method: dual-agent (A: Design-Review · B: Detektor + Browser auf lokaler Prod-Kopie msvtest). Ziel: Erfassen+Rangliste vereint (1.68.29): jmresultate, heimresultate, kantiresultate, endresultate (+?ansicht=rangliste), cup.

## Design Health Score
| # | Heuristik | Wert | Befund |
|---|---|---|---|
| 1 | Systemstatus | 3 | Fortschritt, «n nicht gespeichert», Ladezeilen gut; JM-Rangliste vor Endstich nur «–»; Heim/Kanti-Rang rechnet Ungespeichertes ohne Kennzeichnung |
| 2 | Reale Welt | 3 | Fachsprache sitzt; Titel «… erfassen» bei Rangliste, «Veröffentlichen» je Seite anders |
| 3 | Kontrolle | 3 | Rückfrage bei Ungespeichertem, Esc; «Zur Erfassung» in Prüfliste lädt ganze Seite neu |
| 4 | Konsistenz | 1 | 3 Umschalter-Varianten, 3 Speicher-Orte, 3 Rang-Darstellungen, 3 Gleichstand-Regeln |
| 5 | Fehlervermeidung | 2 | Plausibilität stark; Heim/Kanti-PDF ohne Warnung bei Ungespeichertem (Cup warnt) |
| 6 | Wiedererkennen | 2 | Rangliste fehlt im Menü bei 4/5; JM-Spaltenköpfe klickbar ohne Affordanz; Heim/Kanti-Import nicht verlinkt |
| 7 | Effizienz | 3 | Enter/Pfeile/Ctrl+S, Schnellerfassung, Zeilen-Tastatur; Heim-Namenszelle/JM-Zeilen nur Maus |
| 8 | Ästhetik | 2 | JM-Legende doppelt, Endschiessen 14 Dokumente / 26 Knöpfe, JM-Kopf 5 Aktionen + Jahr |
| 9 | Fehlerbehebung | 3 | Speicherfehler vorbildlich; JM-Rangliste-Lader: «Fehler beim Laden» ohne Schritt, parsed.error ungeescapt (:813), Rohantwort ersetzt Tabelle (:818) |
| 10 | Hilfe | 3 | ?-Buttons richtig platziert; Hilfetexte Mig. 104/105 nennen Menüeinträge, die Mig. 107 gelöscht hat |
| Total | | 25/40 | Akzeptabel |

## Priority Issues
- [P1] Fünf Dialekte des Zusammenlegens, Rangliste aus Menü und Titel verschwunden (Mig. 107 löscht jmrang/heimrang/kantirang/cuprang; Endschiessen behält beide). Ein Muster: Segment «Erfassen | Rangliste» im Kopf neben dem Jahr wie endresultate.php/wanderpreise.php, Titel folgt Ansicht; Menü-Deep-Links per Migration zurück; Hilfetexte 104/105 korrigieren. → layout + clarify
- [P1] Heim/Kanti: Bildschirm-Rang ≠ PDF bei Ungespeichertem (heimresultate.php:785, kantiresultate.php:372). Vor PDF: speichern-dann-PDF-Rückfrage; vorläufigen Rang geänderter Zeilen kennzeichnen; toter Parameter kat:'B'. → harden
- [P1] Gleichstand unsichtbar und je Wettkampf anders (JM Endstich sonst geteilt; Endschiessen Endstich→Geburtsdatum; Heim/Kanti geteilt). Tooltip/Marke an entschiedenen Rängen (load_jm.php:539/544, load_endschresultate.php:144), «3.=» bei geteiltem Rang. → clarify
- [P2] JM-«Rangliste» vor dem Endstich zehn Monate eine alphabetische Strichliste. Statuszeile «Zwischenstand – Rang und Total ab dem Endstich (TT.MM.)», Überschrift «Teilnehmer Kat. A», Legende einmal. → clarify
- [P2] Endschiessen-Dokumentwand (endresultate.php:155–245) und «Veröffentlichen» Heim/Kanti immer sichtbar (nur Changelog-Eintrag). Weitere Ranglisten einklappen; Veröffentlichen nur bei Bedarf mit Zähler oder umbenennen. → distill + clarify
- [P3] A11y/Feinschliff: Cup-Entfernen tabindex=-1 (cup.php:416/963/1223/1307), sichtbares h6 «Standcup Final» nach h1 (cup.php:171), th #6c757d auf #f8fafb 4.48:1, mobile Suchfelder nur Placeholder, Badge 9.6px (jmresultate.php:174), Cup-Mini 10px, .jm-toggle-btn ohne aria-expanded, Rang «A 3.» ohne Spaltenkopf, cup.css Schatten/Pulse, fadeTo ohne reduced-motion, fest verdrahtete Vereinsnamen cup.php:173–185. → harden + polish

## Detektor
Quell-Scan: Exit 0, 4 Hinweise design-system-font-size (jmresultate:117, heimresultate:82, load_jm:93/145 Leerzustand statt msv_empty_row). URL-Scan lokal 13–25 Befunde je Seite, Grossteil global (mobile-submenu transition, verstecktes Panel-h6, Offcanvas) oder falsch-positiv (edge-flush auf td, gewollte Ellipsis). Overlay-Injektion an App-CSP gescheitert (nicht umgangen). 0 Konsolenfehler auf allen 6 Seiten; kein horizontales Scrollen bei 1366/390 frisch geladen.

## Persona-Red-Flags
- Alex: JM-Spaltenköpfe nur letzte 6 Anlässe, keine Zeilentastatur in JM-Rangliste, Prüfliste lädt Daten doppelt, 400-ms-Fade nach jedem Speichern.
- Sam: erster Inhalts-Tab-Stopp = Stopp 57 (Sprunglink vorhanden), Cup-Entfernen nicht fokussierbar, Rang als Text in Namenszelle, Live-Rangänderung nicht angesagt.
- Vorstand abends: «Wo ist die Heim-Rangliste?» (Menü weg, Hilfe verweist darauf); «JM kaputt? Lauter Striche»; PDF zeigt anderen Rang als Bildschirm; 14 Dokumente beim Endschiessen.

## Fragen
- Warum gilt «dieselbe Tabelle» (Mig. 104) gegen einen Umschalter bei der JM, aber nicht bei Heim/Kanti?
- Sollen alle fünf Seiten nach dem Wettkampf heissen statt «… erfassen»?
- Fehlt JM/Heim/Kanti/Cup ein Prüflisten-Moment wie beim Endschiessen?
- Gleichstand-Regeln: Reglement oder gewachsen?
