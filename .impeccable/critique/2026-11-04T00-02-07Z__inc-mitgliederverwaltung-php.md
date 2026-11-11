---
target: critique mitgliederverwaltung
total_score: 24
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 2
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\mitgliederverwaltung.php"
target_fingerprint: "sha256:8cdd8d91b18c2288da35f14c2f42d31f814a915d4ec4eab2b780dc77583df828"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\mitgliederverwaltung.php"
timestamp: 2026-11-04T00-02-07Z
slug: inc-mitgliederverwaltung-php
closed: true
---
Method: dual-agent (A: Design-Review aus Code, B: Detektor + Greps); Browser-Prüfung ausgelassen (kein lokales PHP, Login nötig)

## Gesamtwertung 24/40 (Acceptable)
| # | Heuristik | Punkte | Hauptbefund |
|---|---|---|---|
| 1 | Systemstatus | 3 | Skeleton/Chip/Zeilen-Flash gut; kein Leerzustand bei Suche ohne Treffer, Import ohne Änderungsvorschau |
| 2 | Sprache | 3 | Import-Dialog: Code-String ID;…;WaffenID, «Semikolon»; Email/Whatsapp |
| 3 | Kontrolle | 2 | Import überschreibt ohne Sicherung/Rückweg |
| 4 | Konsistenz | 2 | Neu-Dialog ≠ Panel, lokale esc/ajaxMsg, Speichern gefüllt, Mobile Bootstrap-Farben |
| 5 | Fehlervermeidung | 2 | leerer Name/Geburtsdatum speicherbar, aktiv+verstorben gleichzeitig |
| 6 | Erkennen | 2 | 4 Status-Punkte nur per Hover, WaffenID/Spaltenfolge im Import |
| 7 | Effizienz | 2 | Tastatur gut; keine Sortierung/Filter/Nächstes-Mitglied |
| 8 | Reduziert | 3 | ruhig; 4 meist graue Punkte pro Zeile, Tastenhinweis doppelt |
| 9 | Fehlerbehebung | 2 | nur Toast, keine Feldmarkierung, SQL-Fehler im Import (import_csv:83/89) |
| 10 | Hilfe | 3 | 4 Hilfen gepflegt, Import-/Neu-Dialog ohne Hilfe |

## Design-Spezifität
Vereinsvokabular auf generischer Verwaltungsliste; Löschen mit Bezugsprüfung stark; Mitgliedschaft (aktiv/ausgetreten/Art/Austrittsjahr) nicht abgebildet. Detektor 0 Befunde (bei .php nur Textregeln). Greps: 28/32 Icons ohne aria-hidden, div onclick 739, lokale esc/ajaxMsg 371-372, Speichern btn-primary 227, msv-phone.js ohne Cache-Busting 364, tote .mv-search 13, csrf_require() ohne true in delete:16, 19 tote Hidden-Inputs pro tr.

## Prioritäre Probleme
- [P0-A] CSV-Import beschädigt Register still: split(';') vs fputcsv-Quotes (Rundreise speichert Anführungszeichen, Telefon danach unspeicherbar), fehlende Spalten → leer/inaktiv, ANSI als UTF-8, unbekannte WaffenID + INNER JOIN → unsichtbare Mitglieder, keine Sicherung/Vorschau. Fix: fgetcsv serverseitig, nur vorhandene Spalten, Kodierung, Waffe prüfen, Prüflauf+Übernehmen mit msvBackupVorher, LEFT JOIN. → harden
- [P1-B] Mitgliedschaftsstatus unlesbar: 4 Punkte ohne Namen, opacity 0.5, kein Filter, Zähler zählt alle, aktiv+verstorben gleichzeitig. Fix: .ui-filter Aktiv|Ausgetreten|Verstorben|Alle, Status als Wort, Chips nur gesetzt, Verstorben schaltet Aktiv aus. → clarify, layout
- [P1-C] Prüfung lückenhaft/falsch gewichtet: nur Telefon (CH-Regex blockiert alles), Name leer erlaubt, nur Toast. Fix: gemeinsame Prüffunktion, msvPruefeFelder/aria-invalid, internationale Nummern, Duplikat-Hinweis. → harden
- [P2-D] Adressliste Excel: Ausgetretene ohne Status, interne Notizen enthalten. Fix: Notizen weg, Status/Filter. → clarify
- [P2-E] Import-/Neu-Dialog: Code-String, keine Hilfe, Fehlerliste in msvConfirm, «...»-Knopf, 15 Felder, neues Mitglied nicht angesprungen. → onboard, polish

## Personas
Alex: keine Sortierung/Filter, Suche ohne Telefon/Waffe, kein Nächstes im Panel, Ctrl+S im Neu-Dialog wirkungslos. Sam: flag-dots namenlos, Zeilenansage ohne Status, #panelId im Tab, Chip ohne Live-Region, div onclick mobil. Vorstand am Abend: Punkte deuten, Verstorbene in Liste, Telefon-Toast für unberührtes Feld, Lizenz-Pflicht bei Neumitglied, neues Mitglied unauffindbar.

## Kleinere Beobachtungen
Tote Hidden-Inputs, th max-width wirkungslos, Panel-Titel nicht live, Vereinsaufnahme unter Status, «...» statt «…», mobile ohne JSK-Leiter, Waffenliste async leer, Dateiname Adressliste_MiFu_, Lizenz-Tippfehler nicht korrigierbar, «Temporaere Zugangsdatei» im Toast möglich.

## Fragen
1. Mitgliedschaft als Art + Ein-/Austrittsjahr statt Ja/Nein?
2. Verstorbene/Ausgetretene als Archiv, nur auf Wunsch?
3. Import ersetzen durch Abgleich mit Verbands-Lizenzliste?
