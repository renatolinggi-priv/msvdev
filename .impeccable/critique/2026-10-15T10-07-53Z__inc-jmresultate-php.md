---
target: Erfassen+Rangliste vereint
total_score: 28
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\TEMP\\msvjm\\msvdev\\inc\\jmresultate.php"
target_fingerprint: "sha256:107fb387e5693e4e52f2708e916ab54dfdccb12e61f4bd904808549d5edd15bd"
target_path: "C:\\TEMP\\msvjm\\msvdev\\inc\\jmresultate.php"
timestamp: 2026-10-15T10-07-53Z
slug: inc-jmresultate-php
---
Method: dual-agent (A: Design-Review · B: Detektor + Browser auf lokaler Kopie msvtest). Ziel: Erfassen+Rangliste vereint, Stand 1.68.30 nach clarify/harden/distill/polish.

## Design Health Score
| # | Heuristik | Wert | vorher | Befund |
|---|---|---|---|---|
| 1 | Systemstatus | 3 | 3 | Zwischenstand, vorläufig, Prüfliste; Prüfliste meldet «bereit» zu früh (Cup jede Paarung), JM-Anlässe-Fehler = endloser Spinner |
| 2 | Reale Welt | 3 | 3 | Endschiessen: Summe der Rohwerte ≠ Total, Formel nur im Tooltip |
| 3 | Kontrolle | 3 | 3 | Drei Wege bei Ungespeichertem; kein Rückgängig |
| 4 | Konsistenz | 2 | 1 | Muster bewusst verschieden; Ausgabe-Schutz (Cup nein), Veröffentlichen/Auf Website melden, Rang-Darstellung, Titelregel |
| 5 | Fehlervermeidung | 3 | 2 | Heim/Kanti fragen vor PDF; Cup druckt Veraltetes; leere Passen still 0 |
| 6 | Wiedererkennen | 2 | 2 | Ranglisten nicht im Menü (Entscheid), Sortierregel/vorläufig nur Tooltip |
| 7 | Effizienz | 3 | 3 | Starke Tastatur; Kürzel je Seite verschieden |
| 8 | Ästhetik | 3 | 2 | Weitere Dokumente eingeklappt (3 statt 14 sichtbar); JM-Kopf 6 Knöpfe, Legende 3 Anweisungen |
| 9 | Fehlerbehebung | 3 | 3 | Ausreisser «Fehler: »-Vorspann jmresultate:423, Handy «Fehler beim Laden» |
| 10 | Hilfe | 3 | 3 | endschrang.wertung widerspricht Tabelle (Rohwerte, «–» = nicht gelöst) |
| Total | | 28/40 | 25/40 | Gut |

## Priority Issues
- [P1] Prüfliste «bereit fürs Absenden» zu früh: absenden_bereit.php:91 zählt jede cupPairs-Zeile; JM zählt unbestätigte «gemeldet». Cup erst mit Finale-Resultat, JM-Entwürfe als offen. → harden
- [P1] Cup gibt veraltete Ranglisten aus: PDF nur Toast (cup.php:1718), Direktdruck ohne Prüfung (1866). hkVorAusgabe als msvVorAusgabe nach msv-toast.js, Cup + Heim/Kanti nutzen. → harden
- [P1] Endschiessen: Hilfe endschrang.wertung (Mig. 093) beschreibt umgerechnete Werte und «Strich = nicht erfasst», Tabelle zeigt Rohwerte und «–» = nicht gelöst; Total ohne sichtbare Rechnung. Mig. 112 neu, Tooltip je td.es-total mit Rechnung. → clarify
- [P2] Ranglisten-Auffindbarkeit/Titel (Folge Entscheid): Titel «… erfassen» bei halber Seite Rangliste; Segment «Name | Rang» sieht aus wie Filter; JM-Ausgaben ohne Gruppenlabel. → clarify
- [P2] A11y-Rest: Cup-Punktefelder alle aria-label «Punkte»; Handy-Kartenköpfe div onclick (jm:766, end:1282, kanti:674, heim:519); endresultate Handy-Suche ohne Label; .ui-suche Placeholder 4.4:1 (msv-ui.css:321); Erstbesuch-Hinweis verdeckt Kopfknöpfe 12 s. → harden

## Detektor
CLI: 2 advisory font-size (jmresultate:118, heimresultate:83). URL: 12–24 je Seite, fast alles global/versteckt/falsch-positiv; echt: Placeholder-Kontrast .ui-suche, transition:width Cup-Fortschritt. 0 Konsolenfehler, 0 namenlose Elemente, kein horizontales Scrollen. Endschiessen Rangliste: 3 sichtbare Dokumente.

## Persona-Red-Flags
- Alex: Kürzel je Seite verschieden; Ctrl+S Reichweite wechselt; «Zur Erfassung» lädt Seite neu.
- Sam: Handy-Kartenköpfe nicht tastaturbedienbar; Cup-Felder ohne Namen; Prüfliste+Dokumente vor Tabelle; «vorläufig» nur kursiv+Tooltip.
- Vorstand abends: Rangliste nicht im Menü; JM «Veröffentlichen 114» klingt nach unsichtbar; Cup druckt Veraltetes; Kanti beste Passe blau hervorgehoben, gewertet wird Summe.

## Fragen
- Titel nach Wettkampf statt Tätigkeit?
- Status «bereit fürs Absenden / N offen» auf jeder der fünf Seiten?
- Rohwerte beim Endschiessen: mehr Sicherheit oder Rechnung zurück im Kopf?
- Braucht es «Auf Website melden» überhaupt, oder meldet das System selbst?
