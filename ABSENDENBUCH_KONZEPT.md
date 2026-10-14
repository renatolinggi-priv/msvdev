# Absendenbuch-Generierung: Befund und Plan

Stand 12.10.2026. Design-Critique (Impeccable, zwei getrennte Prüfungen: Design-Review und Detektor) der
Absendenbuch-Generierung, dazu der Plan für die erste Runde «Verlässlichkeit».

Snapshot der Critique: `.impeccable/critique/2026-10-12T14-28-10Z__inc-endschrang-php.md`

## Umsetzungsstand

| Schritt | Stand |
|---|---|
| 1 Marken in der Vorlage | **erledigt 12.10.2026** (1.68.2): neue Vorlage `Resultatbuch_Template20261012.docx`, alte bleibt als Rückfall |
| 2 Prüfliste prüft das Buch | **erledigt 12.10.2026** (1.68.3, Migration 097 Hilfetext): neue Zeilen «Siegerlisten» und «Weitere Teile des Absendenbuchs» (JM, Heim, Kanti je Kat. A/B, Cup); `absenden_bereit.php` prüft jeden Teil einzeln |
| 3 Keine stillen Lücken | **erledigt 12.10.2026** (1.68.2) |
| 4 Ehrliche Beschriftung | **erledigt 12.10.2026** (1.68.4): Knopf «Absendenbuch (Broschüre)», Tooltip mit Druckanweisung, Start-Hinweis «bis zu einer Minute», Fehlertexte mit nächstem Schritt; Hilfetext «Absenden vorbereiten» in Migration 097. «Bereit fürs Absenden» deckt seit Schritt 2 auch den Buchinhalt ab und bleibt darum so stehen |

Sicherungspunkt vor der Umsetzung: Commit `743370b`.

**Critique 13.10.2026 nach Runde 1: 24/40 (vorher 21/40).** Danach Entscheid: der Reihe nach DOCX absichern →
Ablauf (bleibender Stand des Buchs) → Heft; Word-Vorlage oder PDF-Theme bleibt offen.

| Nachtrag | Stand |
|---|---|
| DOCX absichern | **erledigt 13.10.2026** (1.68.6): `AbsendenbuchVorlage` schaltet `Settings::setOutputEscapingEnabled()` ein (Werte *und* `setComplexBlock`-Tabellen JM/Cup); `formatBold()` liefert `AbsendenFett`, das den Run der Vorlage fett macht statt `<w:r>` in `<w:t>` zu schachteln (312 → 0); Kopfzellen ganzzahlig. Getestet: Buchtext 2024/2026 identisch, Name mit «& <…>» und Anlass mit «& Co» ergeben wohlgeformtes DOCX (vorher kaputt). |
| Ablauf | **erledigt 13.10.2026** (1.68.8, Migration 098): Block «Stand des Absendenbuchs» unter «Fürs Absenden». Stand je Art (word, broschuere) in `settings.absendenbuch_stand_<jahr>` (nicht in dat/), geschrieben von den Generatoren, gelesen über `absenden_bereit.php` (buchstand). Ein Befund: Hinweise beim Erstellen (mit Abschnitt, Link je Abschnitt) plus live offene Stiche/Wanderpreise; seither behobene Hinweise «Inzwischen erledigt, Buch neu erstellen». «Öffnen»/«Drucken» genau dieser Datei (Resolver `absendenbuch` mit `data-druck-datei`). Dialog entfällt; Fortschritt mit Uhr (Uhr aria-hidden), Fokus zurück. Knöpfe wie bisher (Entscheid). |
| Polish | **erledigt 13.10.2026** (1.68.10, Migration 100): Kategorie-Titel h2 statt h5 (optisch identisch), Druckanweisung der Broschüre per `aria-describedby`, Direktdruck-Spinner mit `aria-busy` und Text, Siegerlisten-Texte auf «Aus Ranglisten übernehmen» (Mig. 099 der Sieger-Sitzung) |
| JM-Doppelseite prüfen | **erledigt 13.10.2026** (1.68.11): `generate_absendenbuch_pdf.php` liest das PDF mit `pdftotext` und meldet einen Hinweis, wenn «Jahresmeisterschaft Kat. A/B» auf einer ungeraden (rechten) Seite beginnt; Prüffehler nur ins Log. Nur Broschüre/A5, nicht Word. Behebt die Lage nicht selbst (möglich wäre eine leere Seite davor per pdfjam `{}`) |
| Vorlage Runde 2 | **erledigt 13.10.2026** (1.68.12, Migration 101), Word-Vorlage beibehalten. `Resultatbuch_Template20261013.docx`: JM Kat. A/B als eigene Abschnitte mit Beginn «gerade Seite» (Doppelseite), gespiegelte Ränder (`mirrorMargins`, innen 5 / aussen 10 mm; `bookFoldPrinting` bleibt), `embedTrueTypeFonts` gesetzt (wirkt erst, wenn die Vorlage einmal in Word gespeichert wird), 46 Leerabsätze → «Abstand vor 6 pt», Kunst-Tabelle 7807 → 7568 Twips, «… seit ${<Präfix>AB}», Junioren-Tabelle mit Platzhaltern SJ. Code: Siegerlisten je letzte 30; JM-Tabellen feste Breiten (Rang 400, Name 1700, Total 700, Anlässe gleich breit auf beiden Seiten), festes Layout und Zellabstand direkt an der Tabelle (der benannte Stil «myTable» kam nie in die Vorlage). Junioren: siegerdef «Junioren»/SJ, 29 Einträge 1997–2025 (2016–2023 «Keine Konkurrenz»); Quelle in sieger_ranglisten.inc.php, «Keine Konkurrenz» nur für Jahre im heutigen Teilnehmer-Modell (2024 hat keine JS-Daten). Drei Paare pro Zeile geprüft und verworfen (8 pt, Namen meist 13–14 Zeichen). Offen: gemeinsame Überschriften (JM/Heim/Kanti A+B) nehmen das «seit» der Kat.-A-Liste. |
| Deckblatt und Cup | **erledigt 13.10.2026** (1.68.13): `${Deckblatt}` = Ort und Datum des Absendens (`absendenbuchDeckblatt()`: Anlass mit «Absenden» im Namen, Datum aus JMSchiesstage, Ort aus der Adresse nach der PLZ; Rückfall «Roggenacker» bzw. Druckdatum); Logo «Grafik 4» umfliesst Text nur links (`wrapText="left"`), vorher zerfiel die Zeile. Cup «wie heute, nur ruhiger»: Sieger fett, Ausgeschiedene grau statt durchgestrichen, feine einheitliche Linien, Podest fett im Final/Standcup, leere Abschlusszeile entfällt; Paarungen beachten `ManualWinner` wie inc/cup.php |
| Cup, zweite Fassung | **erledigt 13.10.2026** (1.68.15, nach Word-Vorschau mit dem Benutzer): Überschrift «Vereinscup» (ohne Jahr, das steht auf dem Deckblatt), Rundentitel ohne Doppelbalken mit Abstand (vor 1. Runde 120, sonst 360 Twips). Paarungen in Reihenfolge der Auslosung, **ohne Rahmen**, nur feine Linie unter jeder Begegnung (Dreiergruppe ohne Linie dazwischen), Sieger fett, Zeilen min. 320 Twips, linksbündig, 7100 Twips breit. Final/Standcup 450+2450+650 = 3550 Twips (Punkte unter der ersten Punktespalte), ohne Rahmen, nur Sieger fett. Benutzer: im Buch möglichst keine Tabellenrahmen. JM bleibt wie bisher (Vorschau mit Linien und Zebra verworfen; nur Zebra ohne Linien nicht entschieden). Einheitliche Kategorienamen erledigt (1.68.14). |
| JM-Lesbarkeit | **erledigt 13.10.2026** (1.68.16, nach Word-Vorschau mit drei Varianten): jede zweite Zeile `F2F2F2` (Zebra) in getJMA/getJMB, ohne Linien; Streicher bleiben D3D3D3 + durchgestrichen. Verworfen: Linie alle 5 Zeilen, Streicher als grauer Text, Linien + Zebra. Standcup zeigt den Verein (`cupStandFinal.club`), Titel Final/Standcup linksbündig (1.68.15). |
| JM-Schrift | **erledigt 13.10.2026** (1.68.17): Zeilen 7.5 pt, Anlass-Titel 7 pt (gemessen: «96.25» passt in die 7-mm-Spalte bei 22 Anlässen) |
| Critique Heft 13.10.2026 | 11/28 (Druck, n/a 3/7/9), Snapshot `.impeccable/critique/2026-10-13T17-13-54Z__inc-absenden.md`. P0 JM-Zeilen über die Doppelseite ~2 Zeilen versetzt (Titel nur über JMA1); P1 Ränder aussen 5 mm (Ursache `bookFoldPrinting`), keine Seitenzahlen/Legende, Schrift ausserhalb JM klein; P2 Zahlen/fehlende Werte |
| Vorschau Klarheit | **nur Word-Vorschau, nicht umgesetzt** (`Downloads/Absendenbuch_2026_Vorschlag_Klarheit_3.docx`): Seite 2 «Sieger 2026» (Wettkampf, Sieger, Resultat, Seite per PAGEREF), Seitenzahlen unten Mitte ohne Deckblatt (Deckblatt zählt als 1, Benutzer einverstanden), Glückstich-Köpfe «Bester Schuss»/«Weitere Schüsse», Titel «… Sieger seit», «Endschiessen Total Partner», «WP + KK», nicht geschossen «–» statt 0 (nur Passen-/Stich-Totale), «–» in der JM nicht durchgestrichen, Siegerlisten ohne «0» hinter «Keine Auswertung», Cup «Dreiergruppe» grau. Siegerlisten feste Breiten 650/1950/800/400/650/1950/800 + noWrap (Sturzenegger Robert brach um). Verworfen: Legende (Benutzer), ausgeschriebene Spaltenköpfe (brechen um). Offen: KK = Kranzkarte oder Königskranz (Hilfetext 068 sagt Königskranz); PAGEREF im Umwandlungsdienst ungeprüft |
| Vorschau Layout | **nur Word-Vorschau, nicht umgesetzt** (`Downloads/Absendenbuch_2026_Vorschlag_Layout.docx`, baut auf Klarheit auf): rechte JM-Hälfte mit Leerabsatz gleich dem Titel (pageBreakBefore, NBSP) statt Umbruch-Absatz → Zeile 1 links/rechts 73.9/73.9 mm; JM-Kopfzeile `exact` 3000; `bookFoldPrinting` aus settings.xml entfernt (Word spiegelte sonst links = aussen) → innen 5 / aussen 10 mm gemessen; tblInd −284 → 0 (Schwini, Zabig, Kunst); Titel nach Tabelle 240 Twips Abstand, keepNext auf Titeln/Untertiteln. Skripte im Scratchpad `vorschlag/bauen.php` + `layout.php`. Benutzer testet am 14.10.2026 |
| Umsetzung 14.10.2026 | **committet 2ab77c6 (1.68.18), Upload ausstehend**: Port 22 war aus dem Netz gesperrt (SSH/SFTP «Network is unreachable», HTTPS ging; neue Vorlage auf Prod 404). Vorlage `Resultatbuch_Template20261014.docx` per Skript `vorschlag/vorlage_bauen.php` (Scratchpad) aus 20261013: alles aus den Vorschauen Klarheit + Layout + Schrift (9 pt ausser JM, Spaltenbreiten Rang 450/562, Name 1900, Marke 950, Lücke 120, Zahlen Rest ≤ 900; Kopf über Zahl+Marke rechtsbündig mit Einzug = Markenbreite). Code: `absendenSiegerMerken()`, `absendenStrichWennNull()`, `absendenJmHatResultat()` (JM ohne Zeilen, die ausser Obligatorisch/Feldschiessen nichts haben – Entscheid Benutzer), `absendenbuchSiegerSeiteXml()` + `AbsendenbuchVorlage::absatzDurchXml()`, Paritätsprüfung nur Titel allein auf der Zeile. Getestet mit Prod-Kopie 2026 und 2025 (Word-Render, keine Umbrüche, JM links, Zeilen über den Falz gleich hoch, PAGEREF rechnet Word selbst). Nach dem Upload: Checksummen, dann Broschüre über den echten Weg testen (PAGEREF im Umwandlungsdienst ungeprüft) |
| Live 14.10.2026 | 1.68.18 hochgeladen (nach Port-22-Sperre), Checksummen gleich. Critique 2: **17/28** (vorher 11/28), Snapshot `.impeccable/critique/2026-10-14T07-07-16Z__inc-absenden.md` |
| Polish 14.10.2026 | **live 1.68.19**, Vorlage `Resultatbuch_Template20261014b.docx`: kein Grau auf «–» in der JM, JM-Total rechtsbündig, JM-Spalten mit Dezimalwerten zwei Stellen; «Rang» überall, Rang rechtsbündig, KK/WP links, Blindzeile 3 pt, Lücken zusammen 240 Twips (Kanti A/B gleiches Raster), Siegerlisten 7568 breit, S. 12 «Seitenumbruch oberhalb». Endschiessen-Siegerlisten 1 Stelle; JM-Siegerliste bleibt wie erfasst (Benutzer: keine «.00» bei alten Jahren). Junioren-Tabelle mit Prod-Daten geprüft. Bewusst gelassen: «Passe»/«Passen» (verschiedene Bedeutung), Glückstich ohne Fettdruck |
| JM-Layout 14.10.2026 | **live 1.68.20/1.68.21**: Datenzeilen 284 Twips (5 mm), Anlassnamen 8.5 pt in exakt 4000 Twips hoher Kopfzeile (längster Name 40 Zeichen, ~2 mm Luft; bis ~42 Zeichen einzeilig; bis ~19 Schützen pro Kategorie auf einer Seite). Rangnummer am Falz rechts ausprobiert (1.68.20) und auf Wunsch wieder entfernt (1.68.21) |
| Nächster Schritt | Später `/impeccable delight`: Deckblatt (ein Logo, Datum grösser), Rückseite mit Dank, Jahresmeister auf S. 2 hervorheben | Nebenbefund: `wanderpreise/generate_pdf_jahresreport.php` und `wanderpreise/PDFReports.php` suchen den Termin mit `Bezeichnung = 'Absenden'`; 2026 heisst er «MSV Wilen Absenden» |

Was Schritt 1 und 3 konkret geändert haben:

- **Vorlage:** Blockmarken `${JUNGSCHUETZEN_BLOCK}` und `${PARTNER_BLOCK}`, je in einem eigenen weissen
  Absatz. Tippfehler korrigiert.
- **Blöcke auflösen:** über die Unterklasse `AbsendenbuchVorlage::blockAufloesen()` (String-Suche).
  `cloneBlock()` von PhpWord fand fehlende Marken nicht, meldete aber keinen Fehler.
- **Abschnitte einzeln:** Jeder Abschnitt läuft für sich. Fehler, leere Tabellen und Siegerlisten ohne
  Buchjahr kommen als `hinweise` in die JSON-Antwort. Platzhalter-Reste werden am Schluss geleert und gemeldet.
- **Siegerlisten:** nur noch Jahre bis zum Buchjahr.
- **Endpunkte:**
  - Jahr wird geprüft.
  - `leer` ohne Endstich-Resultate.
  - try/catch mit Klartext je Schritt (füllen / umwandeln / ausschiessen).
  - `success`/`message` statt `error`.
  - Temporäre Dateien werden auch beim A5-PDF aufgeräumt.
- **Oberfläche:**
  - `msvAusgabe` kennt `erfolg: { dialog }`. Mit Hinweisen erscheint ein Dialog statt eines Toasts.
  - Der Direktdruck fragt bei Hinweisen nach, liest `message || error` und zeigt Servermeldungen ohne
    «Druckfehler:».

**Korrektur zu Befund 1** (Vorher/Nachher-Test mit der Prod-Kopie):
- Die Endmarke `${/Partner_BLOCK}` ist in der alten Vorlage weiss formatiert und im Druck unsichtbar.
- Sichtbar war dagegen etwas Schlimmeres: In jedem Jahr **ohne Jungschützen** standen rohe Platzhalter
  `${JRang} ${JName} ${JE} …` im Heft, bei 2024 zusätzlich `${PRang} …`, weil der leere Abschnitt nie
  entfernt wurde.

## Was geprüft wurde

| Teil | Dateien |
|---|---|
| Bedienung | `inc/endschrang.php` (Karte «Absenden vorbereiten», Gruppe «Fürs Absenden») |
| Prüfliste | `inc/endschrang/absenden_bereit.php` |
| Generatoren | `inc/absenden/generate_absendenbuch.php` (Word), `generate_absendenbuch_pdf.php` (Broschüre), `absendenbuch_docx.inc.php`, `functions.inc.php` |
| Vorlage | `inc/absenden/dat/Resultatbuch_Template20251015.docx` |
| Echtes Ergebnis | `Resultatbuechlein_2026-10-12_15-46-09.docx` von Prod (nur gelesen, als XML ausgewertet, **nicht gerendert**) |

Nicht geprüft: Seitenumbruch und Ausschiessen im gerenderten PDF (lokal weder Word-Automation noch
LibreOffice, auf Prod keine Broschüren-PDF in `dat/`). Keine Browser-Prüfung, weil die Seite Login und
Prod-DB braucht und die Generatoren Dateien schreiben und die kostenpflichtige Umwandlung aufrufen.

## Wertung: 21/40 (genügend, deutliche Verbesserungen nötig)

| # | Heuristik | Score | Kernproblem |
|---|---|---|---|
| 1 | Sichtbarkeit des Status | 2 | Broschüre 30–90 s nur Spinner im Knopf, keine Angabe zur Dauer |
| 2 | Bezug zur realen Welt | 2 | Kategorie dreifach benannt («Kat. A/B», «Standardgewehr/Ordonanzwaffen», «Standardgewehr und freie Waffen…»); KK, WP, TS ohne Legende |
| 3 | Kontrolle und Freiheit | 2 | Direktdruck ohne Vorschau und Rückfrage; Erstellung nicht abbrechbar |
| 4 | Konsistenz | 2 | Knopf «Broschüre» statt Dokumentname; «KK + WP» neben «WP + KK», «Junioren» neben «Jungschützen» |
| 5 | Fehlervermeidung | 1 | Prüfliste deckt 3 von gut 10 Buchquellen ab; Abschnitte fallen still weg; Platzhalter im Druck |
| 6 | Wiedererkennen statt Erinnern | 2 | Siegerlisten von Hand nachführen, nirgends erinnert; Inhalt erst nach dem Erstellen sichtbar |
| 7 | Flexibilität und Effizienz | 3 | Direktdruck mit einem Klick, Word als Notausgang; aber jeder Klick erstellt neu |
| 8 | Ästhetik und Reduktion | 2 | Seite ruhig; im Buch JM-Tabellen in 6 pt, durchgestrichene Cup-Zeilen in 8 pt |
| 9 | Fehler erkennen und beheben | 2 | «Bitte nochmals versuchen» nutzlos bei Dienstausfall; Direktdruck meldet «HTTP 500» |
| 10 | Hilfe und Dokumentation | 3 | Konkrete Hilfetexte; nichts dazu, was ins Buch kommt und woher die Siegerlisten stammen |

Detektor: 0 Befunde in allen sechs Dateien. Er prüft Web-Markup; die Mängel liegen im erzeugten DOCX und in
der Logik, also ausserhalb seiner Reichweite. Guards vollständig, kein `title=`, kein Inline-Style, kein
`alert(`, alle Icon-Knöpfe mit `aria-label`.

## Gesamteindruck

Die Seite um das Buch herum ist sorgfältig und für dieses Absenden geschrieben. Das Buch selbst und die
Zusicherung «bereit fürs Absenden» halten nicht, was die Seite verspricht. Der grüne Status ist ein
**falscher Höhepunkt**: Er prüft die Erfassung des Endschiessens, nicht das Buch. Fehler zeigen sich erst
im gedruckten Heft, also im ungünstigsten Moment.

Das Ausgabedokument ist generisch: eine Word-Vorlage aus der Zeit vor der App (Aptos 12 pt, 6-pt-Tabellen,
keine Seitenzahlen, kein Inhaltsverzeichnis, keine Legende, Reihenfolge nach Platz statt nach Bedeutung).
Das sichtbarste Druckstück des Vereins ist der am wenigsten gestaltete Teil des Systems.

## Was funktioniert

1. **Prüfliste** (`endschrang.php:350–408`): Direktlinks zur Behebung, Schutz gegen Wettläufe beim
   Jahreswechsel, Stand in `aria-live`, Zustand als Text statt nur Farbe; warnt, ohne zu blockieren.
2. **Eine Quelle für Word und Broschüre** (`absendenbuch_docx.inc.php`): Ausschiessen auf dem Server mit
   Auffüllen auf ein Vielfaches von 4; Toast nennt Seiten, Blatt und Druckanweisung; Direktdruck mit
   «kurze Kante» voreingestellt.
3. **`msvAusgabe`-Standard**: Doppelklick-Schutz, stehendes Label mit Spinner, Servermeldung ohne Vorspann.

## Befunde

### [P1] Platzhalter-Rest im heute erstellten Buch

- Im Buch vom 12.10.2026 steht wörtlich `${/Partner_BLOCK}` nach der Partner-Tabelle.
- Die Vorlage enthält nur die schliessende Marke, in gemischter Schreibweise. Der Code sucht
  `PARTNER_BLOCK` (`functions.inc.php:939`) und räumt mit `/PARTNER_BLOCK` auf (`:1033`), trifft also nie.
- Für `JUNGSCHUETZEN_BLOCK` (`:749`, `:906`) gibt es in der Vorlage gar keine Marken.
- Folge: Leere Partner- und Jungschützen-Abschnitte lassen sich nie entfernen, Titel und leerer
  Tabellenkopf bleiben im Heft. Beim Direktdruck sieht das vor dem Druck niemand.

### [P1] «Bereit fürs Absenden» bürgt nicht für das Buch

- Geprüft: offene Stiche, Paare Sie und Er, Wanderpreis-Register (`absenden_bereit.php`).
- Nicht geprüft: JM A/B, Heimmeisterschaft, Kantonalstich, Cup und die 12 Tabellen «Sieger seit …».
- **Im Buch für 2026 enden alle Siegerlisten bei 2025.** `getSieger()` (`functions.inc.php:1265 ff.`) liest
  die von Hand geführte Tabelle `sieger`, ohne Bezug zum gewählten Jahr.
- Der Wanderpreis-Check (`wanderpreise_gewinner`) ist von der WP-Markierung im Buch entkoppelt; diese
  berechnet `endschWpVergeben()` (`:528`, `:693`).
- Sie und Er wird geprüft, steht aber nicht im Buch.

### [P1] Abschnitte fehlen still, die Meldung sagt trotzdem «Erfolg»

- Jede `get*`-Funktion loggt einen SQL-Fehler und entfernt den Abschnitt (z.B. `functions.inc.php:88–91`).
  Der Toast meldet trotzdem «heruntergeladen».
- `generate_absendenbuch.php` hat kein try/catch: Eine Exception ergibt einen leeren Fehler 500, der
  Client zeigt dann seinen eigenen Text ohne nächsten Schritt.
- Für ein Jahr ohne Daten entsteht ein leeres Buch.

### [P2] Das Buch ist für Mitglieder schwer lesbar

- JM-Tabellen in 6 pt (`:1633`, `:1838`), auf zwei Hälften verteilt. **Korrektur 13.10.2026:** Dass die zweite
  Hälfte keine Namens- und Rangspalte hat, ist **Absicht** – aufgeklappt liegen beide Hälften als Doppelseite
  nebeneinander, die Namen links gelten für beide (Hinweis des Benutzers). Kein Mangel.
  **Echtes Risiko dabei:** Das klappt nur, wenn die JM-Tabelle (JMA1, ebenso JMB1) auf einer *linken*, also geraden
  Seite beginnt. Die Vorlage hat nur normale Seitenumbrüche, keinen Abschnittsumbruch «gerade Seite»; die Parität
  hängt von allem davor ab (Teilnehmerzahl, jedes Jahr längere Siegerlisten, seit 1.68.2 wegfallende leere
  Abschnitte Jungschützen/Partner). Landet JMA1 rechts, stehen die Hälften auf Vorder- und Rückseite desselben
  Blatts und die rechte Hälfte hat keine Namen. Nicht gerendert, im Druck prüfen.
- Keine Seitenzahlen, kein Inhaltsverzeichnis, keine Legende.
- Tippfehler in der Vorlage: «Schinistich», «Ordonanzwaffen».
- Das Endschiessen-Total, die Hauptsache des Abends, steht erst auf etwa Seite 10. Bei Kunst stehen die
  Resultate nach der Siegerliste, bei Zabig und Glück umgekehrt.
- Seiteneinrichtung: A5, links 5 mm auf allen Seiten, keine gespiegelten Ränder. Nach dem Ausschiessen
  liegen die geraden Seiten 5 mm am Aussenrand und können abgeschnitten werden.
- Schrift Aptos ist nicht eingebettet; der PDF-Dienst ersetzt sie unter Umständen.

### [P2] Ablauf ist nicht «erstellen, prüfen, genau das drucken»

- «Fürs Absenden» hat 5 Knöpfe (Word, Broschüre, Broschüre drucken, Gesamtrangliste, Gesamtrangliste
  drucken). Welcher «das Buch» ist, muss man wissen.
- Jeder Klick erstellt das Buch neu: 30–90 s Wartezeit und Guthaben beim Umwandlungsdienst.
- Der Direktdruck druckt ohne Rückfrage zu Blattzahl oder offenen Punkten.
- Er meldet «Druckfehler: HTTP 500», weil `_holePdf` bei `!r.ok` nur `.message` liest
  (`msv-direktdruck.js:305`), der Endpunkt aber `{error}` liefert (`generate_absendenbuch_pdf.php:111`).
- Auf dem Titelblatt steht das **Erstelldatum** als Datum des Anlasses (`absendenbuch_docx.inc.php:34–37`).

## Personas

- **Vorstandsmitglied am Vorabend (1× pro Jahr):** weiss nicht, ob «Absendenbuch (Word)» oder «Broschüre»
  das richtige Dokument ist; vertraut dem grünen «bereit»; hält das System nach 40 s Spinner für hängig und
  klickt auf den Drucker, was das Buch ein zweites Mal erstellt; entdeckt Fehler erst im Heft; Korrekturen
  von Hand in Word gehen beim nächsten Erstellen verloren.
- **Power-User:** kann die geprüfte Broschüre nicht nochmals drucken, ohne sie neu zu erstellen; A5-PDF
  ohne Ausschiessen für die Druckerei nur über die URL (`booklet=0`); der seltenste Knopf (Word) steht zuoberst.
- **Tastatur und Screenreader:** Erklärung zur Broschüre nur im `data-tooltip` (`aria-hidden`, kein
  `aria-describedby`); während der Erstellung ist der Knopf `disabled`, der Fokus fällt weg, keine
  Fortschrittsmeldung über `aria-live` (live ist nur `#absStand`).

## Kleinere Beobachtungen

- Fehlertexte `endschrang.php:318`, `:333`, `:474` ohne Punkt und ohne nächsten Schritt; der PDF-Fehler sagt
  «Dokument» statt «Absendenbuch».
- `msvFehler` lässt durch: «Absendenbuch-Vorlage nicht gefunden: Resultatbuch_Template20251015.docx»,
  «DOCX-Datei nicht gefunden: <Temp-Pfad>» (`convertapi_helper.php:74`), «iLoveAPI: HTTP 4xx».
- PDF-Fehler doppelt geloggt (`generate_absendenbuch_pdf.php:109–111`: `error_log` und `msvFehler`).
- Die Generatoren validieren das Jahr nicht (nur `intval`); die Siegerlisten haben keinen Jahresfilter, ein
  Buch für ein früheres Jahr enthält spätere Sieger.
- Fehlt ein Name im Cup, druckt das Buch «Unbekannt» (`functions.inc.php:1499`, `:1506`).
- Jungschützen: `SUM()` über drei LEFT JOINs (`:790–797`) kann doppelt zählen, Zahlen prüfen.
- JM-Tabellenraster (`tblGrid`) hat 2 Spalten für rund 10 Zellen; Kopfzelle 1,5 cm, Datenzelle 2 cm
  (`:1690` gegen `:1849`). Andere Programme als Word können die Spalten verschieben.
- Karte «Dokumente» zeigt das Jahr nicht.
- Alte Vorlagen V1–V3 liegen weiter in `dat/` und auf Prod; der Vorlagenname mit Datum ist fest im Code.
- Differenzler und Sie & Er fehlen im Buch.

## Offene Grundsatzfragen

1. Wofür genau bürgt «bereit fürs Absenden»: für die Erfassung oder für das Heft, das die Mitglieder in der
   Hand halten?
2. Warum wählt ein Ehrenamtlicher zwischen Word, Broschüre und Drucker? Wäre «erstellen, Zusammenfassung
   ansehen, genau diese Datei drucken» nicht der einzig richtige Weg, mit Word nur als Notausgang?
3. **Word-Vorlage oder eigenes PDF-Theme?** (Entscheid 12.10.2026: später entscheiden.) Im Code wäre das
   Layout prüfbar, die Vereinsidentität austauschbar und das Platzhalter-Risiko weg; Word bliebe höchstens
   als Export.

---

## Plan Runde 1: Verlässlichkeit (nur die drei P1)

Entscheid 12.10.2026: zuerst die Verlässlichkeit, Vorlage-Frage offen, vorerst nur planen. Alle Schritte
gelten für beide Varianten (Word oder PDF-Theme); die Prüfungen in Schritt 2 und 3 braucht es in beiden.

### Empfohlene Befehle

Jeder Befehl setzt um, also **ändert Dateien, und die sind sofort auf Prod**. Darum einzeln ausführen und
diese Datei als Auftrag mitgeben, damit der Befehl dem Plan folgt und nicht nur seiner allgemeinen Checkliste.

| # | Befehl | Deckt ab |
|---|---|---|
| 1 | `/impeccable harden inc/absenden/ gemäss ABSENDENBUCH_KONZEPT.md Schritt 1 und 3` | Marken in der Vorlage, Platzhalter-Scan, Hinweise statt stiller Lücken, try/catch, `leer`, `_holePdf` |
| 2 | `/impeccable harden inc/endschrang/absenden_bereit.php gemäss ABSENDENBUCH_KONZEPT.md Schritt 2` | Prüfliste prüft Siegerlisten, JM, Heim, Kanti, Cup |
| 3 | `/impeccable clarify inc/endschrang.php gemäss ABSENDENBUCH_KONZEPT.md Schritt 4` | ehrlicher Status, Fehlertexte, Hilfetext-Migration |
| 4 | `/impeccable polish inc/endschrang.php` | Schlussdurchgang; liest den Critique-Snapshot als Pendenzenliste |

Für die späteren Runden: Runde 2 (Heft) `/impeccable typeset` und `/impeccable layout`, Runde 3 (Ablauf)
`/impeccable distill` und `/impeccable clarify` auf `inc/endschrang.php`. Danach `/impeccable critique`
erneut laufen lassen, um die Wertung mit 21/40 zu vergleichen.

### Schritt 1: Marken in der Vorlage reparieren

- Vorlage per Skript im Scratchpad bearbeiten (ZipArchive, `word/document.xml`, nicht PhpWord, sonst
  gehen Formatierungen verloren): `${PARTNER_BLOCK}` vor den Partner-Titel, `${/PARTNER_BLOCK}` nach die
  Tabelle, je in einem eigenen Absatz; dasselbe für `JUNGSCHUETZEN_BLOCK`.
- Tippfehler «Schinistich» und «Ordonanzwaffen» im selben Durchgang korrigieren.
- **Neuer Dateiname** mit Datum, `ABSENDENBUCH_TEMPLATE` darauf umstellen; die alte Vorlage bleibt als
  Rückfallweg.

### Schritt 2: Die Prüfliste prüft das Buch

`absenden_bereit.php` liefert heute nur die Wanderpreise. Neu, jeweils mit Direktlink zur Behebung:

| Prüfung | Quelle | Link |
|---|---|---|
| Siegerlisten enthalten das Jahr («9 von 12 Listen ohne 2026») | `sieger` × `siegerdef` (wie `getSieger`) | Sieger-Seite |
| JM Kat. A und B haben Resultate | Abfrage wie `getJMA` / `getJMB` | JM erfassen |
| Heimmeisterschaft, Kantonalstich, Cup vorhanden | Abfragen wie die `get*`-Funktionen | jeweilige Seite |

- Sie und Er bleibt (gehört zur Erfassung), mit Hinweis «nicht im Buch».
- Später, eigener Schritt: Sieger, die das System schon kennt (z.B. Endstich), vorschlagen statt von Hand
  nachführen lassen.

### Schritt 3: Keine stillen Lücken mehr

- `absendenbuchTemplateFuellen()` sammelt `$hinweise`. Jede `get*`-Funktion, die heute loggt und den
  Abschnitt entfernt, trägt Klartext ein (z.B. «Kantonalstich: keine Resultate, Abschnitt weggelassen»).
- Nach dem Füllen alle XML-Teile nach `${…}` durchsuchen; jeder Rest wird ein Hinweis mit dem Markennamen.
- Beide Endpunkte liefern `hinweise` mit; `msvAusgabe` zeigt sie als Warnung statt «heruntergeladen».
- `generate_absendenbuch.php`: try/catch mit `msvFehler()`, `success`-Schlüssel, `charset`.
- Ohne Endstich-Daten antworten beide Endpunkte mit `leer` (`msvAusgabeLeer`) statt einem leeren Buch.
- Doppeltes Logging in `generate_absendenbuch_pdf.php` entfernen.
- `_holePdf` (`msv-direktdruck.js:305`) liest auch `.error`; bei Hinweisen fragt der Druck einmal nach.

### Schritt 4: Ehrliche Beschriftung

- Grün heisst nur noch, was geprüft ist: «Erfassung und Buchinhalt vollständig».
- Fehlertexte `endschrang.php:318`, `:333`, `:474` mit nächstem Schritt.
- Hilfetext `endschrang.absenden` per Migration (`INSERT … ON DUPLICATE KEY UPDATE`) ergänzen: was die
  Prüfliste prüft, woher die Siegerlisten kommen.

### Test

- Lokal mit Prod-Kopie in der lokalen MariaDB; Vorher/Nachher-Vergleich des DOCX-XML für 2025 und 2026.
- Prüfen: kein `${` mehr, leerer Partner-Abschnitt verschwindet, Jahr ohne Daten liefert `leer`, Hinweise
  erscheinen bei absichtlich fehlenden Siegern.
- Neue Vorlage einmal in Word öffnen und von Auge prüfen; eine Broschüre probedrucken.

### Deploy

Reihenfolge (Helfer vor Aufrufern, wegen des Watchers):

1. Vorlage mit neuem Namen
2. `functions.inc.php`
3. `absendenbuch_docx.inc.php`
4. Endpunkte (`generate_absendenbuch*.php`, `absenden_bereit.php`)
5. JS (`endschrang.php`, `msv-direktdruck.js`)
6. Migration Hilfetext

Danach Checksummen mit Prod vergleichen, Changelog-Eintrag `admin_only`, Commit.

Aufwand: etwa ein halber Tag. Am meisten Risiko hat Schritt 1 (Vorlage), darum neuer Name und alte als
Rückfall.

### Spätere Runden

- **Runde 2, Heft:** mindestens 8 pt, JM-Doppelseite absichern (beginnt sicher auf einer linken Seite, z.B.
  Abschnittsumbruch «gerade Seite» oder Prüfung der Seitenzahl im Broschüren-PDF per `pdftotext`), Seitenzahlen, Legende,
  einheitliche Kategorienamen, Reihenfolge nach Bedeutung, gespiegelte Ränder. Hängt am Entscheid
  Word-Vorlage oder PDF-Theme.
- **Runde 3, Ablauf:** ein Knopf «Absendenbuch erstellen» mit Dauerangabe, Zusammenfassung danach
  («24 Seiten, 6 Blatt · Siegerlisten bis 2026 ✓ · 0 Hinweise»), «Öffnen» und «Diese Datei drucken» mit
  derselben Datei, Word nur als Notausgang, Datum aus dem Termin des Absendens.
