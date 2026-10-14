---
target: inc/endschrang.php (Absendenbuch-Ablauf, nach Runde 1)
total_score: 24
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\endschrang.php"
target_fingerprint: "sha256:3f013c4f40a896dc137d01d32d80b1b6c8e770f3d02ec225271f785bc18c1ce0"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\endschrang.php"
timestamp: 2026-10-13T07-15-30Z
slug: inc-endschrang-php
closed: true
---
# Critique: Endschiessen Rangliste mit Ablauf Absendenbuch (13.10.2026, nach Runde 1)

Method: dual-agent. Live-Testumgebung (Prod-Kopie, php -S, headless Chrome 1440/390), echte Bücher 2024–2026 als XML. Nicht geprüft: gerendertes Layout, echtes Broschüren-PDF, QZ-Direktdruck. Overlay: CSP blockiert detect.js (nicht umgangen), Ersatz URL-Scan.

| # | Heuristik | Score | vorher | Kernproblem |
|---|---|---|---|---|
| 1 | Status | 2 | 2 | Info-Toast 3 s, danach bis 60 s nur Spinner; kein bleibender Stand des Buchs |
| 2 | Reale Welt | 2 | 2 | 5 Kategorie-Bezeichnungen im Buch; Titelblatt Druckdatum + fest «Roggenacker» |
| 3 | Kontrolle | 2 | 2 | Jeder Klick erstellt neu (Broschüre: Guthaben); kein «diese Datei drucken» |
| 4 | Konsistenz | 3 | 2 | Oberfläche einheitlich; Buchtitel uneinheitlich |
| 5 | Fehlervermeidung | 3 | 1 | Prüfliste deckt Buch ab, Platzhalter-Scan; latent: kein XML-Escaping |
| 6 | Wiedererkennen | 2 | 2 | Hinweise nur im Dialog ohne Link, danach weg |
| 7 | Effizienz | 2 | 3 | A5-PDF nur per URL, Broschüre nicht wiederverwendbar (strenger bewertet) |
| 8 | Ästhetik | 2 | 2 | Seite ruhig; Buch 6 pt, JM-Hälfte ohne Name, Tabellen breiter als Satzspiegel |
| 9 | Fehlerbehebung | 3 | 2 | Klartext je Schritt mit Ausweg; Toast 7 s, Fokus verloren |
| 10 | Hilfe | 3 | 3 | 097 präzise; endschrang.dokumente ohne Word-vs-Broschüre/Dauer |
| **Total** | | **24/40** | 21/40 | Genügend |

Detektor: CLI 0 Befunde; URL-Scan 14/13 (fast alle globale Navigation), echter Seitenbefund h1→h5 (endschrang.php:177/216). Kontraste 5.9–15.8:1, Fokusring sichtbar, kein Überlauf, keine Konsolenfehler.

## Prioritäre Probleme
1. [P1] Prüfliste («3 Punkte offen») und Buch-Dialog («1 Hinweis») zwei Wahrheiten, beide flüchtig (Dialog nur «Verstanden», endschrang.php:497-509). Fix: gemeinsamer Befund mit Links; bleibender Stand des letzten Buchs in «Fürs Absenden» (Zeit, Seiten/Blatt, Hinweise, Öffnen, Diese Datei drucken). → shape, clarify (Runde 3)
2. [P1] Heft schwer lesbar, Titelblatt falsch: JM 6 pt, zweite Hälfte ohne Rang/Name, Tabellen 218–294 mm deklariert bei 133 mm Satzspiegel, Kunst 138 mm fix, Rand links 5 mm ungespiegelt, keine Seitenzahlen, Cup ohne Überschrift, Druckdatum statt Datum des Absendens (JMDefinition «MSV Wilen Absenden» 14.11.2026 Pfäffikon). → typeset, layout (Runde 2)
3. [P2] DOCX latent beschädigbar: kein Output-Escaping (PhpWord-Standard aus) → «&» bricht XML; formatBold() setzt <w:r> in <w:t> (ungültiges OOXML, Podest 1–3); Kopfzellen 850.5 Twips. → harden
4. [P2] Warten und Fokus: Info-Toast 3 s, msvAusgabeLaden disabled → Fokus auf body (verifiziert), Direktdruck-Spinner ohne Text/aria-busy (msv-direktdruck.js:206-208), Fehler-Toast 7 s, Druckanweisung nur Tooltip. → harden, polish
5. [P3] Karte «Dokumente» ohne Hierarchie (13/26 gleich gewichtete Knöpfe), ohne Jahr. → distill, layout

## Personas
- Vorstand am Vorabend: Dauer-Hinweis nach 3 s weg → Drucker → zweite Umwandlung; «1 Hinweis» vs «3 Punkte offen»; 13 Siegerlisten von Hand; Fehler im Heft erst auf Papier.
- Alex: kein Nachdruck ohne Neuerstellung; A5 fehlt in UI; trägt Sieger nach, die das System errechnet.
- Sam: Fokus weg beim Erstellen; Druckanweisung unzugänglich; Direktdruck-Spinner ohne Text; Toasts 3–7 s.

## Kleinere Beobachtungen
h1→h5; Wanderpreise-Link ohne ?year=; Buchteile-Link nur erster Teil; Guard-Reihenfolge generate_absendenbuch_pdf.php:25; #absListe/#absDokHinweis nicht live; Rang-2-Tönung schwach; unvollständige Teilnehmer ohne Kennzeichnung; Handy: Rangliste erst bei ~1460 px; Cup «Unbekannt»; alte Vorlagen in dat/.

## Fragen
1. Warum Siegerlisten von Hand, wenn das System die Sieger errechnet?
2. Welcher Satz bürgt fürs Heft: Prüfliste oder Dialog?
3. Was würde ein PDF-Theme heute verhindern (6 pt, ungültiges XML, Datum)?
