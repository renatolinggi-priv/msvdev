---
target: die absendenbuch generierung
total_score: 21
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\endschrang.php"
target_fingerprint: "sha256:a7f8316144d617947033ad87764e529b7b64998cde6cf0d8d71f8defa86d04c1"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\endschrang.php"
timestamp: 2026-10-12T14-28-10Z
slug: inc-endschrang-php
---
# Critique: Absendenbuch-Generierung (inc/endschrang.php + inc/absenden/*)

Method: dual-agent. Echtes Buch vom 12.10.2026 15:46 (Prod, nur gelesen) als XML ausgewertet, nicht gerendert. Detektor: 0 Befunde (prüft Web-Markup, nicht das DOCX). Browser: übersprungen (Login + Prod-DB).

| # | Heuristik | Score | Kernproblem |
|---|---|---|---|
| 1 | Status | 2 | Broschüre 30–90 s nur Spinner im Knopf |
| 2 | Reale Welt | 2 | Kategorie dreifach benannt; KK/WP/TS ohne Legende |
| 3 | Kontrolle | 2 | Direktdruck ohne Vorschau/Rückfrage; kein Abbrechen |
| 4 | Konsistenz | 2 | «Broschüre» statt Dokumentname; KK + WP / WP + KK |
| 5 | Fehlervermeidung | 1 | Prüfliste deckt 3 von ~10 Quellen ab; Platzhalter im Druck |
| 6 | Wiedererkennen | 2 | Siegerlisten von Hand, keine Erinnerung |
| 7 | Effizienz | 3 | Direktdruck 1 Klick; aber jeder Klick erstellt neu |
| 8 | Ästhetik | 2 | Seite ruhig; Buch 6-pt-Tabellen |
| 9 | Fehlerbehebung | 2 | «nochmals versuchen» nutzlos bei Dienstausfall; «HTTP 500» |
| 10 | Hilfe | 3 | Konkrete Hilfe; nichts zum Buchinhalt/Siegerlisten |
| **Total** | | **21/40** | Genügend |

## Spezifität
Bedienung spezifisch (Prüfliste mit Uhrzeit/Direktlinks, kurze Kante, Hilfetext «Abend vor dem Absenden"). Ausgabedokument generisch aus Alt-Word-Vorlage (Aptos 12, 6-pt-Tabellen, keine Seitenzahlen/Legende, Reihenfolge nach Platz).

## Prioritäre Probleme
1. [P1] Platzhalter-Rest `${/Partner_BLOCK}` im Buch; Vorlage hat nur schliessende Marke in falscher Schreibweise, JUNGSCHUETZEN_BLOCK fehlt ganz (functions.inc.php:939/1033). Fix: Vorlage korrigieren + Platzhalter-Scan vor saveAs. → harden
2. [P1] «Bereit fürs Absenden» prüft nur Stiche/Sie&Er/WP-Register; Siegerlisten im 2026er Buch enden bei 2025 (Tabelle sieger, manuell). Fix: absenden_bereit.php um Buchinhalt erweitern. → harden, clarify
3. [P1] Abschnitte fallen still weg (get* loggt + entfernt), generate_absendenbuch.php ohne try/catch, leeres Jahr = leeres Buch. Fix: $hinweise[] im JSON, msvAusgabeLeer, msvFehler. → harden
4. [P2] Buch schwer lesbar: JM 6 pt, zweite Hälfte ohne Name/Rang (functions.inc.php:1694-1701), keine Seitenzahlen/Legende, Tippfehler Schinistich/Ordonanzwaffen, Total erst ~S. 10, Rand links 5 mm ohne Spiegelung. → typeset, layout
5. [P2] Ablauf nicht erstellen→prüfen→genau das drucken: 5 Knöpfe, jeder Klick neu (30–90 s, API-Guthaben), Direktdruck ohne Rückfrage, «HTTP 500» (msv-direktdruck.js:305 liest nur .message, Endpunkt liefert error), Titeldatum = Erstelldatum (absendenbuch_docx.inc.php:34-37). → clarify, distill

## Personas
- Vorstand am Vorabend: Word vs Broschüre unklar; vertraut Grün; klickt nach 40 s nochmals; Fehler erst im Heft; Word-Korrekturen gehen verloren.
- Alex: kein Nachdruck ohne Neuerstellung; A5 ohne Ausschiessen nur per URL; Word-Knopf zuoberst.
- Sam: Broschüre-Erklärung nur im aria-hidden Tooltip; disabled-Knopf verliert Fokus, keine live-Fortschrittsmeldung.

## Kleinere Beobachtungen
Fehlertexte endschrang.php:318/333/474 ohne Punkt/nächsten Schritt; msvFehler lässt Vorlagenname/Temp-Pfad/«iLoveAPI: HTTP 4xx» durch; doppeltes Logging generate_absendenbuch_pdf.php:109-111; Generatoren validieren Jahr nicht, Siegerlisten ohne Jahresfilter; Cup «Unbekannt»; Jungschützen SUM über 3 LEFT JOINs; Karte «Dokumente» ohne Jahr; alte Vorlagen V1–V3 in dat/.

## Fragen
1. Wofür bürgt «bereit fürs Absenden»: Erfassung oder Heft?
2. Warum Word vs Broschüre vs Drucker statt erstellen→Zusammenfassung→diese Datei drucken?
3. Layout des wichtigsten Druckstücks in Word-Vorlage oder im eigenen PDF-Theme?
