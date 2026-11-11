---
target: endschloesen
total_score: 25
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\endschloesen.php"
target_fingerprint: "sha256:5174e6cfe3b5ebd3d1f99c572fd6b7b54d5af258d4ecbc8f5442b13f3af7bc97"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\endschloesen.php"
timestamp: 2026-11-04T00-01-39Z
slug: inc-endschloesen-php
---
Method: dual-agent (A: Design-Review · B: Detektor). Browser-Prüfung entfallen (kein lokales PHP, Prod hinter Login).

## Gesamtnote 25/40 (Akzeptabel)

| # | Heuristik | Note | Hauptbefund |
|---|---|---|---|
| 1 | Systemstatus | 3 | Total live, Chip «Nicht gespeichert»; nach Speichern ist der kassierte Betrag weg |
| 2 | Sprache | 3 | Gast-Kacheln Einzelpreise vs. Kombipreis im Total |
| 3 | Kontrolle | 2 | Definitions-Panel verwirft Spezialpreise bei Esc/Overlay ohne Rückfrage (1268–1272) |
| 4 | Konsistenz | 3 | eigenes Panel statt side_panel.inc.php, lokales esc(), eigener Leerzustand, API getMessage (api:631) |
| 5 | Fehler vermeiden | 2 | «Karte» vorgewählt, Standblatt ohne Speichern, Munition unabhängig von der Waffe |
| 6 | Erkennen statt Erinnern | 2 | bereits gelöste Mitglieder verschwinden aus der Suche (nur im Hilfetext erklärt) |
| 7 | Effizienz | 2 | keine Tastenkürzel, Bearbeiten über ⋯ und Scrollen |
| 8 | Ruhe | 3 | Gast-Hinweis mit 3–4 Preisregeln im Titel |
| 9 | Fehler beheben | 2 | Toasts verschwinden; Sitzung abgelaufen → doppelte Meldung mit «Netzwerkfehler» |
| 10 | Hilfe | 3 | 4 «?»-Hilfen, Kernregeln nur dort |

Detektor: 1 Hinweis, design-system-font-size, inc/endschloesen.php:100 (.total-amount 1.45rem statt zahl 1.35rem), echt.

## Wichtigste Probleme
- [P1] Standblatt nicht an Speichern gekoppelt (795–799, 958–961): Blatt ohne gespeicherte Lösung möglich. Fix: «Speichern & Standblatt», Zustand «gespeichert» bis «Nächste Person».
- [P1] Bereits gelöste Mitglieder aus der Suche ausgeblendet (1433–1437) → Gast-Dubletten. Fix: zeigen mit «bereits gelöst · CHF …», Auswahl lädt Lösung.
- [P1] Beträge gehen nicht auf: Toast nur Stichpreis (958), Gast-Kacheln vs. Kombi, keine Aufschlüsselung; Zahlungsweg nur an Stichen, nur Munition → Seite «karte» (api:620), PDF «bar» (pdf:142); «Karte» vorgewählt (273).
- [P2] Definitions-Panel: Mitglieder-Totale aller Jahre aus aktuellem Preis (api:597–601), Esc verwirft ohne Rückfrage, 7 Anfragen ohne Transaktion.
- [P2] Löschen/Fehler unter Hausstandard: generische Rückfrage, kein msvBackupVorher, Validierung nur Toast, API getMessage statt msvFehler.

## Personas
- Alex: keine Kürzel, ~12 Tab-Schritte, kein Zeilenklick, Tabelle unter 1400 px unten.
- Sam: ⋯ ohne aria-label (1050), Symbol-Spalten ohne Text, Titel als span, Chip ohne Live-Region.
- Kassier: findet zurückkehrendes Mitglied nicht, Bar/Karte-Fehlbuchung, Standblatt ohne Speichern, kein «Bar heute».

## Kleinere Beobachtungen
lokales esc() (510), Zahlungssymbol-Farben, Schatten Total-Leiste (147), Typ-Wechsel/Gastname ohne Schutz vor Verlust (579–599, 882), kein Leerzustand ohne Stiche, Zählung inkl. Passive (api:169), Select2 rc.0 vom CDN im Body, «Suchen...».

## Fragen
1. Formular oder Kasse? 2. Gehören Preise zu einem Jahr? 3. Wenn die Suche alle findet, braucht es ⋯/Scrollen/Hilfesatz noch?
