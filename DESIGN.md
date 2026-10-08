---
name: MSV Wilen – Admin
description: Vereinsverwaltung des Militärschützenvereins Wilen; klassisches, ruhiges Admin aus der SFARL-Familie.
colors:
  grund: "#f5f6fa"
  flaeche: "#ffffff"
  flaeche-2: "#f8fafb"
  hover: "#f1f5f9"
  gewaehlt: "#eef4ff"
  rand: "#e8ecf1"
  linie: "#eef1f5"
  linie-zart: "#f1f4f8"
  feldrand: "#8a94a5"
  text: "#1a2332"
  text-2: "#5a6577"
  text-3: "#667080"
  akzent: "#3b6cce"
  akzent-dunkel: "#2b52a0"
  akzent-tief: "#1e3f80"
  akzent-hell: "#e8f0fe"
  akzent-rand: "#c9d9f7"
  ok-bg: "#ecfdf3"
  ok-fg: "#166534"
  ok-rand: "#a7e9c0"
  warn-bg: "#fef3c7"
  warn-fg: "#92400e"
  warn-zeile: "#fffbeb"
  warn-zeile-hover: "#fef6d8"
  warn-rand: "#f7d98a"
  warn-punkt: "#d97706"
  fehler-bg: "#fef3f2"
  fehler: "#b42318"
  fehler-rand: "#f5c2bd"
  leer: "#b8c0cc"
  feldrand-leer: "#8a94a5"
  rand-stark: "#7d8899"
  gold-bg: "#f6e3a1"
  gold-fg: "#6b4f00"
  silber-bg: "#e2e7ee"
  silber-fg: "#3d4757"
  bronze-bg: "#f0d2b6"
  bronze-fg: "#7a3f12"
  k-gruen: "#1f7a4d"
  k-blau: "#2b52a0"
  k-tuerkis: "#0e6e78"
  k-rot: "#b42318"
  k-grau: "#5a6577"
typography:
  headline:
    fontFamily: "IBM Plex Sans, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.01em"
  title:
    fontFamily: "IBM Plex Sans, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "1rem"
    fontWeight: 700
  title-sm:
    fontFamily: "IBM Plex Sans, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "0.95rem"
    fontWeight: 600
  body:
    fontFamily: "IBM Plex Sans, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "0.9rem"
    fontWeight: 400
    fontFeature: "tnum"
  meta:
    fontFamily: "IBM Plex Sans, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "0.85rem"
    fontWeight: 400
  body-sm:
    fontFamily: "IBM Plex Sans, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "0.8rem"
    fontWeight: 600
  label:
    fontFamily: "IBM Plex Sans, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "0.71875rem"
    fontWeight: 600
    letterSpacing: "0.05em"
  zahl:
    fontFamily: "IBM Plex Sans, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "1.35rem"
    fontWeight: 700
    lineHeight: 1.1
    fontFeature: "tnum"
rounded:
  xs: "5px"
  sm: "6px"
  btn-sm: "7px"
  md: "8px"
  alert: "10px"
  lg: "12px"
  pill: "999px"
spacing:
  pad: "20px"
  kopf-y: "14px"
  karte-y: "16px"
  abstand: "14px"
  mobil: "12px"
components:
  button-primary:
    backgroundColor: "{colors.k-blau}"
    textColor: "{colors.flaeche}"
    rounded: "{rounded.btn-sm}"
    padding: "0.2rem 0.6rem"
    typography: "{typography.body-sm}"
  button-primary-hover:
    backgroundColor: "#22448a"
    textColor: "{colors.flaeche}"
  button-outline-anlegen:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.k-gruen}"
    rounded: "{rounded.btn-sm}"
    padding: "0.2rem 0.6rem"
  button-outline-anlegen-hover:
    backgroundColor: "{colors.k-gruen}"
    textColor: "{colors.flaeche}"
  button-outline-speichern:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.k-blau}"
    rounded: "{rounded.btn-sm}"
    padding: "0.2rem 0.6rem"
  button-outline-speichern-hover:
    backgroundColor: "{colors.k-blau}"
    textColor: "{colors.flaeche}"
  button-outline-export:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.k-tuerkis}"
    rounded: "{rounded.btn-sm}"
    padding: "0.2rem 0.6rem"
  button-outline-export-hover:
    backgroundColor: "{colors.k-tuerkis}"
    textColor: "{colors.flaeche}"
  button-outline-loeschen:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.k-rot}"
    rounded: "{rounded.btn-sm}"
    padding: "0.2rem 0.6rem"
  button-outline-loeschen-hover:
    backgroundColor: "{colors.k-rot}"
    textColor: "{colors.flaeche}"
  button-outline-neutral:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.k-grau}"
    rounded: "{rounded.btn-sm}"
    padding: "0.2rem 0.6rem"
  button-outline-neutral-hover:
    backgroundColor: "{colors.hover}"
    textColor: "{colors.text}"
  kopf-card:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.text}"
    rounded: "{rounded.lg}"
    padding: "14px 20px"
  inhalts-card:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.text}"
    rounded: "{rounded.lg}"
    padding: "16px 20px"
  feld:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.text}"
    rounded: "{rounded.md}"
  suche:
    backgroundColor: "{colors.flaeche-2}"
    textColor: "{colors.text}"
    rounded: "{rounded.md}"
    height: "30px"
    width: "17rem"
  chip:
    backgroundColor: "#f4f6f9"
    textColor: "#3d4757"
    rounded: "{rounded.sm}"
    padding: "2px 9px"
  filter-segment:
    backgroundColor: "{colors.linie-zart}"
    textColor: "{colors.text}"
    rounded: "{rounded.md}"
    padding: "2px"
  filter-segment-aktiv:
    backgroundColor: "{colors.flaeche}"
    textColor: "{colors.text}"
    rounded: "{rounded.sm}"
    height: "26px"
  zeile-offen:
    backgroundColor: "{colors.warn-zeile}"
  zeile-gewaehlt:
    backgroundColor: "{colors.gewaehlt}"
  tabellenkopf:
    backgroundColor: "{colors.flaeche-2}"
    textColor: "{colors.text-2}"
    typography: "{typography.label}"
  slide-panel:
    backgroundColor: "{colors.flaeche}"
    width: "500px"
  tooltip:
    backgroundColor: "#1e293b"
    textColor: "{colors.flaeche}"
    rounded: "{rounded.sm}"
    padding: "6px 12px"
  rang-badge-1:
    backgroundColor: "{colors.gold-bg}"
    textColor: "{colors.gold-fg}"
    rounded: "{rounded.pill}"
    height: "30px"
  rang-badge-2:
    backgroundColor: "{colors.silber-bg}"
    textColor: "{colors.silber-fg}"
    rounded: "{rounded.pill}"
    height: "30px"
  rang-badge-3:
    backgroundColor: "{colors.bronze-bg}"
    textColor: "{colors.bronze-fg}"
    rounded: "{rounded.pill}"
    height: "30px"
  upload-flaeche:
    backgroundColor: "{colors.flaeche-2}"
    textColor: "{colors.text}"
    rounded: "{rounded.lg}"
    padding: "40px 24px"
  upload-flaeche-ziehen:
    backgroundColor: "{colors.gewaehlt}"
    textColor: "{colors.text}"
  leerzustand:
    textColor: "{colors.text-2}"
    padding: "2.5rem 1rem"
  flag-dot-an:
    backgroundColor: "{colors.akzent-dunkel}"
    textColor: "{colors.flaeche}"
    rounded: "{rounded.pill}"
    width: "28px"
    height: "28px"
  flag-dot-aktiv:
    backgroundColor: "{colors.ok-fg}"
    textColor: "{colors.flaeche}"
  flag-dot-aus:
    backgroundColor: "{colors.linie-zart}"
    textColor: "{colors.leer}"
---

# Design System: MSV Wilen – Admin

<!-- Erfasst am 08.10.2026 aus dem ausgelieferten Code (css/msv-ui.css, css/msv-oeffentlich.css, Partials, Musterseiten). Gilt für den Admin-Bereich und die öffentlichen Seiten; das Mitgliederportal ist ausdrücklich ausgenommen (siehe «Noch nicht migriert»). Normativ sind die Tokens oben; die Quelle der Wahrheit im Code ist :root in css/msv-ui.css. -->

## Overview

**Creative North Star: «Das aufgeräumte Vereinsbüro»**

Der Admin-Bereich ist ein Arbeitsplatz für Ehrenamtliche, die am Abend am Laptop Resultate erfassen, Ranglisten erzeugen und Mitglieder pflegen. Er sieht aus wie ein gut geführtes Büro: heller, kühler Grund (`grund`), darauf weisse Flächen mit feinem Rand, links das Menü, oben jeder Seite eine Kopf-Card mit Titel, Jahr und Aktionen, darunter kompakte Tabellen. Bearbeitet wird in einem Slide-Panel, das von rechts einfährt, ohne die Liste zu verlassen. Das System stammt aus derselben Familie wie die Schwesterprojekte SFARL, JSK und EWS.

Die Dichte ist bewusst hoch (Grundschrift 0.9rem, Knöpfe ~28px, Tabellenköpfe in kleinen Versalien), die Stimmung ruhig: keine Verläufe, keine farbigen Rahmen, keine Bewegung, die nicht etwas mitteilt. Farbe trägt Bedeutung, nicht Stimmung: ein dunkles Blau als Akzent und für Speichern, Grün für Anlegen, Türkis für Ausgaben, Rot nur für Gefahr. Das Vereinsrot des Logos bleibt im Logo.

Zahlen stehen im Zentrum der Arbeit (Passen, Stiche, Totale, Ränge). Deshalb rechnet die ganze Oberfläche mit Tabellenziffern, und Totale erhalten eine eigene, grössere Zahlenrolle.

**Key Characteristics:**
- Heller Grund, weisse Karten mit 1px-Rand, flach (keine Schatten auf Karten)
- Eine Kopf-Card pro Seite: Titel, «?»-Hilfe und Jahr links, Aktionen rechts
- Kompakte Tabellen mit Zeilenzuständen (offen, gewählt) statt Symbolen
- Slide-Panel rechts für Bearbeiten und Erfassen
- Knopffarbe = Zweck; alle Knöpfe Outline und klein, genau ein gefüllter Hauptknopf je Fläche
- IBM Plex Sans, lokal ausgeliefert, Tabellenziffern überall

## Colors

Kühle, fast farblose Neutrale tragen die Fläche; ein einziges gedämpftes Blau führt, die übrigen Farben sind reine Bedeutungsträger.

### Primary
- **Ruhiges Signalblau** (`akzent`): Fokus- und Auswahlzustände, Checkboxen, die Randlinie der gewählten Zeile, der Rand blauer Outline-Knöpfe, der Hinweis «ungespeichert» (Punkt neben dem Namen).
- **Tiefes Amtsblau** (`akzent-dunkel`, gleich `k-blau`): Links, Fokusrahmen (`:focus-visible` 2px), Fokusrand der Felder, Text blauer Outline-Knöpfe und Füllung des einen Hauptknopfs. Auch der eingeschaltete Flag-Dot.
- **Nachtblau** (`akzent-tief`): Link-Hover, gedrückter Hauptknopf, Text im Info-Hinweis.
- **Helles Auswahlblau** (`akzent-hell`): Textauswahl, aktive Dropdown-Einträge, Hover der «?»-Hilfe, Jahr-Hinweis «Planung».
- **Auswahlrand** (`akzent-rand`): Rand auf hellblauen Flächen: Info-Hinweis, Seitenhinweis `.info-card`, Jahr-Hinweis «Planung», hervorgehobene Kacheln.

### Secondary (Knopf-Semantik)
- **Tannengrün** (`k-gruen`): Anlegen, Hochladen, Import; im Fragebogen der Absenden-Knopf.
- **Petroltürkis** (`k-tuerkis`): Export, PDF, Drucken, Ranglisten.
- **Signalrot** (`k-rot`, gleich `fehler`): ausschliesslich Löschen und Fehler.
- **Schiefergrau** (`k-grau`): Abbrechen, Schliessen, neutrale Nebenaktionen.

### Tertiary (Zustände)
- **Erledigt-Grün** (`ok-fg` auf `ok-bg`, Rand `ok-rand`): Status «erfasst», Fortschrittsbalken, eindeutige Eingaben, Erfolgshinweise.
- **Bernstein** (`warn-punkt`, Text `warn-fg` auf `warn-bg`, Zeile `warn-zeile`, Hover der Zeile `warn-zeile-hover`, Rand `warn-rand`): offene Zeilen, Status «offen», Jahr-Hinweis «Archiv», «gelöst»-Pillen.
- **Fehlerrosa** (`fehler-bg`, Rand `fehler-rand`): Fehlerhinweise und ungültige Eingaben.

### Podest (Rang 1–3)
Gedämpfte Metalltöne, flach, nie als Verlauf. Die Marke (`.rang-badge.r1–r3`, Rang-Kreis auf Mobile-Karten, Top-Käufer) nimmt Fläche und Schrift voll; Ranglistenzeilen und Kartenköpfe mischen die Fläche zu 40 % mit Weiss (`color-mix(in srgb, var(--ui-gold-bg) 40%, var(--ui-flaeche))`).
- **Gold** (`gold-fg` auf `gold-bg`), **Silber** (`silber-fg` auf `silber-bg`), **Bronze** (`bronze-fg` auf `bronze-bg`). Kontrast der Schrift jeweils über 5:1.

### Neutral
- **Bürogrund** (`grund`): Seitenhintergrund und Hintergrund im Erfassungs-Panel, damit Karten darin stehen.
- **Papierweiss** (`flaeche`): Karten, Kopf-Card, Panel, Outline-Knöpfe.
- **Leicht getönt** (`flaeche-2`): Tabellenköpfe, Hover von Zeilen, Fusszeilen von Dialogen, Suchfeld, Tastenleiste.
- **Hover-Grau** (`hover`): Hover neutraler Knöpfe.
- **Auswahl-Hauch** (`gewaehlt`): gewählte Zeile, fokussiertes Eingabefeld im Raster.
- **Kartenrand** (`rand`), **Trennlinie** (`linie`), **zarte Linie** (`linie-zart`): Rand aller Karten; Linien unter Köpfen; Zeilentrenner in Tabellen.
- **Feldrand** (`feldrand`): Rand von Eingabefeldern, Suchfeld, neutralen Knöpfen, `kbd`, Upload-Fläche. Mindestens 3:1 gegen Weiss (WCAG 1.4.11, Entscheid 08.10.2026): Felder müssen am Abend auf dem Laptop als Felder erkennbar sein.
- **Zahlenfelder im Raster:** leer `feldrand-leer` (gleich `feldrand`; leer zeigt sich über die getönte Fläche), gefüllt `rand-stark` (kräftiger); `rand-stark` ist auch der Hover-Rand anklickbarer Kacheln (Anlässe, Stiche).
- **Leerwert** (`leer`): leere Zellen, Striche und fehlende Werte in Tabellen, ausgeschaltete Flag-Dots. Nur für «nichts da», nie für Text, den man lesen muss (Kontrast unter 3:1).
- **Tinte** (`text`), **Zweittext** (`text-2`), **Dritttext** (`text-3`): Inhalt; Untertitel, Tabellenköpfe, Zähler; Hinweise und Icons.

### Named Rules
**The Vereinsrot-im-Logo Rule.** Das Rot des Vereinslogos kommt in der Oberfläche nicht vor. Rot (`k-rot`) bedeutet ausschliesslich Gefahr: Löschen, Fehler, ungültige Eingabe. Vereinsidentität (Name, Logo, Farbe) bleibt eine austauschbare Schicht.

**The Farbe-ist-Zweck Rule.** Eine Farbe wird nie zur Dekoration eingesetzt. Wer Grün sieht, legt an; wer Türkis sieht, bekommt ein Dokument; wer Rot sieht, löscht.

**The Token-Pflicht Rule.** Farben in `$page_specific_css` und neuem CSS kommen aus den `--ui-*` Variablen, nie als Hex-Wert.

## Typography

**Display Font:** keine (die App hat keine Schaufläche)
**Body Font:** IBM Plex Sans (mit -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif), lokal aus `css/fonts/ibm-plex-sans/`, variabel 400–700, latin und latin-ext
**Label/Mono Font:** keine eigene; `kbd` erbt die Grundschrift. Für Code (Hilfe-Schlüssel, SQL-Regeln, HTML-Quelltext, Link-Pfade, `.code-badge`) gilt der Systemstapel `--ui-mono` (ui-monospace, Cascadia Mono, Consolas, SF Mono), seit 08.10.2026 überall derselbe.

**Character:** Eine einzige sachliche Grotesk mit technischer Herkunft, die in kleinen Grössen klar bleibt. Hierarchie entsteht über Gewicht (400/500/600/700) und Grösse, nicht über eine zweite Familie.

### Hierarchy
- **Headline** (700, 1.25rem, 1.25, −0.01em): Seitentitel in der Kopf-Card (`h1.page-title`, seit 08.10.2026 ein `h1`), einmal pro Seite.
- **Title** (700, 1rem): Titel im Slide-Panel-Kopf. Einzelseiten dürfen ihn für die Schnellerfassung vergrössern (Heimmeisterschaft 1.15rem).
- **Title-sm** (600, 0.95rem): Titel von Tabellen-Cards (`.ui-tab-titel`), Zähler im Fortschritt; 0.9rem für Abschnittstitel in Erfassungskarten (`.shot-section-title`).
- **Body** (400, 0.9rem, Tabellenziffern): Grundschrift der ganzen App.
- **Meta** (400, 0.85rem, `text-2` oder `text-3`): Untertitel, Nebenzeilen unter einem Namen («seit 2019 · Hersteller …»), Suchfeld, Zusatzangaben in Tabellenzellen.
- **Body-sm** (600, 0.8rem): Knöpfe `btn-sm`, Filter, Status, Chips.
- **Label** (600, 0.71875rem, 0.05em, VERSALIEN): Tabellenköpfe, Gruppenzeilen im Raster, Gruppenlabels der Export-Toolbar. Nur für Spalten- und Gruppenbeschriftungen über Daten. Zugleich die Untergrenze: kleiner als 0.72rem wird auf dem Bildschirm nichts gesetzt.
- **Zahl** (700, 1.35rem, 1.1, Tabellenziffern): Totale in Erfassungskarten und Schnellerfassung; Eingabefelder für Passen im Panel 1.35rem/600.

### Named Rules
**The Tabellenziffern Rule.** `font-variant-numeric: tabular-nums` gilt global am `body`. Zahlen stehen untereinander; keine Proportionalziffern in Ranglisten oder Rastern.

**The Eine-Familie Rule.** Keine zweite Schrift, keine Systemschrift als Auszeichnung, keine externen Schriftserver. Schrift wird lokal ausgeliefert (CSP `font-src 'self'`).

## Layout

- **Gerüst:** Navigation links (Sidebar, Breite 280px, per Umschalter auch oben als Topbar), rechts der Inhalt auf `grund`. Unter 992px wird das Menü zum Off-Canvas.
- **Breite:** genau eine Klasse am `.main-content-wrapper`: `content-width-wide` (1500px), `content-width-default` (1200px), `content-width-narrow` (850px), linksbündig. Kein eigenes `max-width` auf Seiten; der äussere Wrapper ist `col-12 ps-0`.
- **Stapel:** Kopf-Card, dann eine oder mehrere Inhalts-Cards (`.content-background` oder `.ui-karte`), je 14px Abstand. Kinder einer Inhalts-Card haben 1.25rem Abstand. Keine Karte in der Karte: Tabellenblöcke in einer Inhalts-Card verlieren Rand und Hintergrund.
- **Innenabstand:** horizontal überall `pad` (20px); Kopf-Card 14px vertikal, Inhalts-Card 16px, Tabellen-Card-Kopf 10px. Unter 768px 12px und Radius 10px.
- **Scrollende Tabellen:** Erfassungsseiten füllen das Fenster, die Tabelle scrollt innen. Den Rahmen liefern drei Klassen aus `msv-ui.css`: `.ui-vollhoehe` am `.main-content-wrapper` (`height: calc(100vh − Nav − 28px)`, mindestens 520px, Kopf-Card fest), `.ui-vollhoehe-karte` an der Tabellen-Card (Flex-Kette `flex: 1 1 auto; min-height: 0` über `.desktop-table-container`) und `.ui-scroll` am Scrollbereich. Unter 768px fliesst die Seite wieder normal. Fixierte Kopfzeile und Randspalten sowie die Z-Index-Stufen liegen in `css/fixes/resultate-unified.css`; dort steht kein Aussehen. Genutzt von Endschiessen, Partnerinnen, Heimmeisterschaft und Kantonalstich.
- **Mobile:** Tabellen werden unter 768px zu Karten (`mobile-cards.css` + `MSVMobileCards`); die Kopf-Card ist auf Mobile ausgeblendet, ausser die Seite setzt `$page_show_mobile` (nötig, sobald sie die Jahresauswahl enthält). Das Panel ist mobil 100vw breit.
- **Ladereihenfolge (bestimmt, wer gewinnt):** Bootstrap 5.3 → `msv-styles.css` (Altbestand) → `fixes/resultate-unified.css` → `mobile-cards.css` → Inline-`<style>` im Header → `msv-ui.css` → `$page_specific_css`.

### Named Rules
**The Eine-Breitenklasse Rule.** Die Breite einer Seite steht in genau einer Klasse am `.main-content-wrapper`, nirgends sonst.

**The Kopf-Card Rule.** Jede Admin-Seite beginnt mit dem Partial `partials/page_header.inc.php`. Seitentitel = Menütext = Browser-Tab.

## Elevation & Depth

Das System ist flach. Karten, Kopf-Card, Tabellen und Export-Toolbars haben keinen Schatten; Tiefe entsteht durch den Ton-Unterschied `grund` gegen `flaeche` und den 1px-Rand `rand`. Schatten gibt es nur für Ebenen, die sich wirklich über die Seite legen.

### Shadow Vocabulary
- **Panel** (`box-shadow: -10px 0 30px rgba(26, 35, 50, .14)`): Slide-Panel, mit Overlay `rgba(26, 35, 50, .28)`.
- **Dialog** (`box-shadow: 0 16px 40px rgba(26, 35, 50, .18)`): Bootstrap-Modals.
- **Menü** (`box-shadow: 0 8px 24px rgba(26, 35, 50, .12)`): Dropdowns.
- **Segment** (`box-shadow: 0 1px 2px rgba(16, 24, 40, .12)`): der aktive Knopf im Segment-Schalter, damit er auf dem grauen Träger steht.
- **Öffentliche Karte** (`box-shadow: 0 1px 2px rgba(26, 35, 50, .04), 0 8px 24px rgba(26, 35, 50, .06)`): nur die eine Karte der Login-, Registrierungs-, Passwort- und Fragebogenseite, die allein auf dem Grund steht.

### Named Rules
**The Flach-bis-es-schwebt Rule.** Ein Schatten zeigt an, dass etwas über der Seite liegt (Panel, Dialog, Menü). Karten in der Seite bekommen nie einen.

**The Kein-Hüpfen Rule.** Hover verändert Farbe, nie Lage: kein `translateY`, kein Anheben, kein Schatten beim Überfahren.

**The Ruhig-laden Rule.** Inhalte erscheinen ohne Einblend-Animation: keine hereingleitenden Tabellen, Karten oder Ablaufschritte, keine Dauer-Animationen wie Pulsieren. Bewegung gibt es nur, wenn sie einen Zustandswechsel mitteilt (Panel fährt ein, Fortschrittsbalken füllt sich, Ladeanzeige läuft).

## Shapes

Weich, aber nicht rund: kleine, abgestufte Radien nach Grösse des Elements. Grosse Flächen (Kopf-Card, Inhalts-Card, `.ui-karte`, `.card`, Modals, Export-Toolbar) `lg` (12px); Felder, Suche, Knöpfe und Segment-Träger `md` (8px), Knöpfe `btn-sm` 7px; Hinweise, Dropdowns, Mobile-Karten und Erfassungskarten 10px; Chips, Badges, Pillen und Rasterfelder `sm` (6px); `kbd` 5px; Fortschrittsbalken 3px; Statuspunkte und «?»-Hilfe kreisrund; der Jahr-Hinweis als Pille (`pill`). Ränder sind immer 1px, ausser der unteren Kante von `kbd` (2px, Tasten-Anmutung) und der 2px-Innenlinie der gewählten Zeile.

## Components

### Kopf-Card (Seitenkopf)
Der feste Einstieg jeder Seite.
- **Aufbau:** Partial `partials/page_header.inc.php`. `$page_title` (Pflicht), `$page_title_after` direkt neben dem Titel («?»-Hilfe, dann Jahresauswahl `.msv-kopf-neben .form-select`, getönt `flaeche-2`, 600), `$page_actions` rechts, `$page_extra` als zweite Zeile über die ganze Breite (getrennt durch `linie`, z.B. Fortschritt und Chips), `$page_subtitle` optional, `$page_show_mobile` für Mobile.
- **Form:** `flaeche`, Rand `rand`, Radius `lg`, Padding 14px 20px, Abstand 10px/16px.
- **Verhalten:** Steht der «?»-Knopf am Anfang von `$page_actions`, verschiebt das Partial ihn neben den Titel.

### Inhalts-Card und Tabellen-Card
- **Inhalts-Card** (`.content-background`): `flaeche`, Rand `rand`, Radius `lg`, kein Schatten, Padding 16px 20px.
- **Tabellen-Card** (`.ui-karte` + `.ui-tab-kopf`): Kopfzeile mit Titel (`.ui-tab-titel`, 0.95rem/600), Segment-Filter und Suche rechts, Linie `linie` darunter; die Tabelle läuft randlos bis zum Kartenrand.
- **Bootstrap `.card`:** gleicher Rand und Radius, Kopf `flaeche-2`, 600.

### Buttons
Ruhig und eindeutig: die Farbe sagt, was passiert.
- **Shape:** Radius `md` (8px), `btn-sm` 7px; 600; Grösse ~33px, `btn-sm` ~28px (Padding 0.2rem 0.6rem, 0.8rem). Grösse steht im Inline-`<style>` des Headers; kleiner ist erlaubt, grösser nie.
- **Outline nach Zweck (immer `btn-sm`):** `btn-outline-success` Anlegen/Hochladen/Import · `btn-outline-primary` Speichern/Bearbeiten · `btn-outline-info` Export/PDF/Drucken · `btn-outline-danger` nur Löschen · `btn-outline-secondary` Abbrechen/Schliessen/neutral. `btn-outline-warning` ist in Bernstein gestaltet, hat aber keinen festgelegten Zweck und gehört nicht zur Semantik.
- **Ruhe und Hover:** Outline-Knöpfe stehen auf `flaeche`, Text und Rand in der Zweckfarbe; beim Hover füllen sie sich mit der Zweckfarbe, Text weiss (neutral: `hover`-Grau, Text `text`). Übergang nur Farbe, 0.15s.
- **Gefüllt (`btn-primary`):** die eine Hauptaktion einer Fläche (Seite, Panel, Dialog), meist «Speichern»; `k-blau`, Hover `#22448a`, aktiv `akzent-tief`. Gefülltes Grün (`btn-success`) und Rot (`btn-danger`) nur als Bestätigung in Dialogen.
- **Icon-Knöpfe:** nur Bootstrap Icons (`bi-*`), immer mit `aria-label` und `data-tooltip`.
- **Fokus:** 2px `akzent-dunkel`, 2px Abstand.

### «?»-Hilfe
- **Stil:** `.btn-help`, kein `.btn`; Kreis 22px, Rand `feldrand`, Icon `text-2`; Hover `akzent-hell` mit `akzent`-Rand.
- **Platz:** neben dem Seitentitel, einem Card- oder Abschnittstitel oder einem erklärungsbedürftigen Feld; nie pro Knopf. Markup `<button type="button" class="btn-help" data-help="seite.thema" aria-label="Hilfe"></button>`; Text aus Tabelle `hilfetexte` via `inc/js/msv-help.js` (Klick = Modal, Hover = Kurzansicht).

### Tooltips
`data-tooltip="…"` an jedem Element, das eine Erklärung braucht, nie `title=`. `inc/js/msv-tooltips.js` zeigt ein dunkles Schild (`#1e293b`, Text weiss, 0.78rem/500, Radius 6px, max. 320px).

### Inputs / Fields
- **Style:** Rand `feldrand`, Radius `md`, Text `text`, Grund `flaeche`.
- **Focus:** Rand `akzent-dunkel` plus 1px-Ring derselben Farbe (kein weicher Glow). Im Raster wird das fokussierte Feld zusätzlich `gewaehlt`.
- **Gefüllt / leer im Raster:** leere Felder getönt (`flaeche-2`, Rand `feldrand-leer`, 400, `text-2`), gefüllte weiss mit Rand `rand-stark`, 600.
- **Fehler:** `aria-invalid="true"` → Rand, Text und Ring `fehler`, Grund `fehler-bg`. Eindeutige Treffer (Stich-Codes) `ok-bg`/`ok-rand`.
- **Suche** (`.ui-suche`): 30px hoch, 17rem breit, `flaeche-2`, Lupe in `text-3`, rechtsbündig im Tabellenkopf; Fokus wie Felder.

### Segment-Schalter (Filter)
- **Style:** `.ui-filter`: Träger `linie-zart`, Radius 8px, 2px Innenabstand; Knöpfe 26px hoch, 0.8rem/600, transparent.
- **State:** aktiv über `aria-pressed="true"` → weiss mit Segment-Schatten; Zähler im Knopf in `text-2`/500. Seitenspezifische Umschalter (z.B. Teilnehmer/Zahlung beim Lösen, `.typ-switch`) folgen genau diesem Muster.

### Chips, Status, Fortschritt
- **Chip** (`.ui-chip`): `#f4f6f9` mit Text `#3d4757`, Radius 6px, 0.8rem; Zahl fett; optional Punkt 7px in `warn-punkt` (offen) oder `akzent` (ungespeichert).
- **Status** (`.ui-status`): Punkt 8px plus Wort, 0.8rem/600; `ok` grün, `offen` bernstein.
- **Fortschritt** (`.ui-fortschritt` + `.ui-balken`): Zahl 0.95rem/700 mit «von N» in `text-2`; Balken 160×6px, Träger `linie`, Füllung `ok-fg`, Breite animiert 0.4s.
- **Leere Zellen** in `leer` (`.cell-empty`); «gelöst»-Pille in Bernstein.

### Tabellen und Zeilenzustände
- **Kopf:** `flaeche-2`, Label-Typografie (Versalien, 0.72rem, `text-2`), Linie `linie`. Zeilen getrennt durch `linie-zart`.
- **Hover:** `flaeche-2`.
- **Offen** (`tr.ui-offen`): ganze Zeile `warn-zeile`, Hover `warn-zeile-hover`. Kennzeichnet, was noch fehlt.
- **Gewählt** (`tr.selected`, `tr.ui-markiert`, Hybrid `.hybrid-row.selected`): `gewaehlt` plus 2px-Innenlinie in `akzent` rund um die Zeile; folgt in fixierten Randspalten mit.
- **Leer:** ganze Tabellenzeile mit `msv_empty_row($colspan, 'Keine … gefunden')` aus `partials/empty_state.inc.php` (Icon `bi-inbox`, zentriert, gedämpft). Für einen Block oder eine selbst gebaute Zelle (JS-Listen, Karten) `.ui-leerzustand`: `<div class="ui-leerzustand"><i class="bi bi-…" aria-hidden="true"></i>Keine … für 2026</div>`; Icon 2rem mit halber Deckkraft über dem Text in `text-2`, Padding 2.5rem 1rem. Fehler beim Laden zusätzlich `text-danger`.

### Podest in Ranglisten
- **Marke** `.rang-badge` (Pille 30px, 0.82rem/700, sonst `linie-zart`/`text-2`) mit `.r1`/`.r2`/`.r3` in Gold, Silber, Bronze.
- **Zeilen** der Top 3 (`tr.rank-1..3`) und **Kartenköpfe** auf Mobile (`.mobile-card.rank-1..3 .mobile-card-header`, fett) in der 40-%-Mischung; der Rang selbst fett.
- Nie Verlauf, Schatten oder Glanz; die Farbe allein trägt den Rang.

### Slide-Panel
Bearbeiten, ohne die Liste zu verlassen.
- **Aufbau:** Partial `partials/side_panel.inc.php` (`$panel_title`, `$panel_body`, `$panel_footer`, `$panel_width` → `--panel-width`, Standard 500px). Fährt von rechts ein (0.3s, `cubic-bezier(0.4, 0, 0.2, 1)`), Overlay dunkelt ab; Escape, Overlay oder «×» (neutraler Outline-Knopf mit `data-tooltip="Schliessen (Esc)"`) schliessen. `role="dialog"`, Fokusführung über `msv-panel-a11y.js`.
- **Form:** Kopf weiss mit Titel 1rem/700, Fusszeile weiss mit Linie `rand` und dem einen gefüllten Hauptknopf rechts. Mobil 100vw.
- **Erfassungs-Panel** (`.ui-erfassen` zusätzlich an `.hybrid-edit-panel` bzw. `.anlass-panel`): Titel 1.15rem/1.2 für die Schnellerfassung, darunter die Position («3 von 40», `.panel-pos`, 0.8rem `text-2`), Tasten im Fuss als `kbd.ui-kbd`. Unter 768px ohne Abdunklung (das Panel ist vollbreit) und mit festem Fuss, Fussknöpfe 48px hoch. Genutzt von Endschiessen, Partnerinnen, Heim, Kanti und JM.
- **Erfassungskarten im Panel** (`.shot-*`): enthält das Panel Erfassungskarten, wird der Panel-Grund `grund`; jede Karte weiss, Rand `rand`, Radius 10px, Padding 12px 14px, Titel 0.9rem/600 mit Icon in `text-3`, Total rechts in der Zahl-Rolle. Rasterfelder 38×36px (Dezimal 46px, breit 56px), Radius 6px. Deaktivierte Karten `flaeche-2`. Unter 768px stapeln sich nebeneinanderliegende Karten.

### Tastenhinweise
Wo eine Seite Tastenkürzel unterstützt (Ctrl+S, Enter, Pfeile, Esc), steht unter der Tabelle eine Leiste `.ui-tasten` (`flaeche-2`, Linie oben, 0.78rem, `text-2`) mit `kbd`-Tasten: weiss, Rand `feldrand` mit 2px-Unterkante, Radius 5px, 0.75rem/700. Einzelne Tasten im Panel-Fuss als `kbd.ui-kbd`.

### Export-Toolbar
Gruppierte Dokument-Knöpfe auf Ranglisten- und Ausdruck-Seiten. Freistehend eine Karte (`lg`, Rand `rand`, Padding 12px 20px); innerhalb einer Inhalts-Card randlos mit Linie unten. Kopf 0.875rem/600, Gruppenlabels in Label-Typografie, Trenner in `rand`. Knöpfe türkis (Ausgabe) oder grün (Import).

### Upload-Fläche und Lade-Overlay
- **Upload-Fläche** (`.upload-area`, gleichwertig `.import-area`): Datei ablegen oder klicken. `flaeche-2`, 2px gestrichelt in `feldrand`, Radius `lg`, Padding 40px 24px, zentriert; Icon (`.bi` als direktes Kind) 2.5rem in `text-3`, Titel `h4` 1.05rem/600. Hover `flaeche` mit dunklerem Rand, beim Ziehen (`.dragover`, `.dragging`) `gewaehlt` mit Rand `akzent`, Fokus 2px `akzent`. Mit `role="button"`, `tabindex="0"` und `aria-label` bedienbar machen; keine Inline-Grössen oder -Farben am Icon. Abstand nach aussen setzt die Seite.
- **Lade-Overlay** (`.loading-overlay > .loading-spinner`): über der ganzen Seite, Grund `rgba(26, 35, 50, .55)`, darin eine weisse Karte (Radius `lg`, Padding 2rem). Kein Weichzeichner.

### Flag-Dots
Merkmal an/aus in Listen (`.flag-dots > .flag-dot.on|.off`), Kreis 28px, Icon 0.75rem, Erklärung immer per `data-tooltip`. An: `akzent-dunkel`, Icon weiss; aus: `linie-zart` mit Icon in `leer`. «Aktiv» trägt zusätzlich `.ok` und ist an grün (`ok-fg`). Weitere Bedeutungen färbt die Seite über `data-flag` und Tokens (Mitglieder: Ehrenmitglied `warn-punkt`, Verstorben `k-grau`, JSK-Leiter `k-tuerkis`), nie per Inline-Style, damit die Farbe dem Umschalten folgt. Kein Vergrössern beim Überfahren.

### Hinweise (Alerts)
Radius 10px, 1px-Rand im Ton: Info `#eef4ff`/`#c9d9f7`/`akzent-tief`, Erfolg `ok-*`, Warnung `warn-*`, Gefahr `fehler-bg`/`fehler-rand`/`fehler`. Kein farbiger Seitenstreifen.

### Dialoge
- **Bootstrap-Modal:** Rand `rand`, Radius `lg`, Dialog-Schatten; Kopf weiss mit Linie `linie`, Fuss `flaeche-2`.
- **Kurzdialoge und Toasts** über `inc/js/msv-toast.js`: `msvToast(msg, type)` oben rechts, `msvError`, `msvSuccess`, `msvConfirm` (gibt das SweetAlert2-Ergebnis zurück, `.isConfirmed` prüfen), `msvConfirmDelete` (gefüllter roter Bestätigungsknopf). Popup `.msv-swal` 22rem, Bootstrap-Knöpfe `btn-sm`: Bestätigen gefüllt, Abbrechen `btn-outline-secondary`. Ajax über `msvPost`, Meldungen über `msvXhrMessage`, Ausgabe escapen mit `msvEsc`; keine lokalen Kopien.

### Navigation
Links eine 280px breite Sidebar (Standard, umschaltbar auf Topbar), Einträge aus Tabelle `navigation`, Root «Einstellungen» bündelt Konfiguration; unter 992px Off-Canvas. Der Stil der Navigation ist noch nicht auf die `--ui-*` Tokens umgestellt (siehe «Noch nicht migriert»); neue Navigation übernimmt nicht deren Farben, sondern `akzent`/`gewaehlt`.

### Öffentliche Seiten
Login, Registrierung (Mitglied, Jungschütze), Passwort zurücksetzen und öffentlicher Fragebogen laden statt `header.inc.php` und `msv-ui.css` die Datei `css/msv-oeffentlich.css` (Tokenauszug plus Abbildung der Inline-Variablen der Seiten auf die Tokens). Eine weisse Karte auf `grund` mit der öffentlichen Karten-Schattierung, flacher weisser Kopf mit Titel 1.15rem/600 und Icon in `akzent-dunkel`, ein gefüllter Hauptknopf in `akzent-dunkel` (Hover `#22448a`), keine Verläufe, kein Hüpfen, keine Einblend-Animation. Im Fragebogen bleibt «Absenden» grün, «Zurück» neutral.

## Do's and Don'ts

### Do:
- **Do** jede Seite mit `partials/page_header.inc.php` beginnen; Seitentitel = Menütext = Browser-Tab.
- **Do** die Jahresauswahl in `$page_title_after` neben den Titel setzen, hinter die «?»-Hilfe, und dann `$page_show_mobile = true`.
- **Do** die Breite mit genau einer Klasse `content-width-wide|default|narrow` am `.main-content-wrapper` setzen.
- **Do** alle Knöpfe als Outline nach Zweck und `btn-sm`: grün Anlegen/Hochladen/Import, blau Speichern/Bearbeiten, türkis Export/PDF/Drucken, rot nur Löschen, grau Abbrechen/neutral.
- **Do** pro Fläche (Seite, Panel, Dialog) höchstens einen gefüllten `btn-primary` für die Hauptaktion verwenden.
- **Do** `$page_specific_css` als rohes CSS schreiben (der Header wrappt es in `<style>`) und Farben darin nur über `--ui-*` angeben.
- **Do** die «?»-Hilfe (`.btn-help`, `data-help="seite.thema"`) neben Seiten-, Card- und Abschnittstitel setzen und den Text als Migration mitliefern.
- **Do** Erklärungen an Knöpfen über `data-tooltip` geben.
- **Do** auf Seiten mit Tastenkürzeln (Ctrl+S, Enter, Esc) die Leiste `.ui-tasten` mit `kbd` zeigen.
- **Do** Zustände über Zeilenfarbe und Statuspunkt zeigen (`tr.ui-offen`, `.ui-status`), Auswahl über `tr.selected`/`.ui-markiert`.
- **Do** Bearbeiten im Slide-Panel über `partials/side_panel.inc.php`; Löschen bestätigen mit `msvConfirmDelete`.
- **Do** Erfassungsseiten in Fensterhöhe mit `.ui-vollhoehe`, `.ui-vollhoehe-karte` und `.ui-scroll` bauen und das Panel mit `.ui-erfassen` auszeichnen, statt den Rahmen pro Seite nachzubauen.
- **Do** Leerzustände, Upload-Flächen, Podest und Flag-Dots über die zentralen Klassen (`.ui-leerzustand`, `.upload-area`, `.rang-badge`/`rank-1..3`, `.flag-dot`) zeigen; die Seite setzt höchstens Abstände.
- **Do** Token-Änderungen in `msv-ui.css` von Hand in `msv-oeffentlich.css` nachziehen, soweit die öffentlichen Seiten den Wert verwenden.

### Don't:
- **Don't** Verläufe einsetzen (`linear-gradient`, `radial-gradient`) – auch nicht für Ränge oder Fortschritt.
- **Don't** farbige Seitenstreifen (`border-left` über 1px als Akzent) an Karten, Hinweisen oder Menüeinträgen.
- **Don't** Hover mit Bewegung: kein `transform: translateY`, kein Anheben, kein Hover-Schatten.
- **Don't** Inhalte einblenden lassen oder dauerhaft animieren (Hereingleiten, Pulsieren).
- **Don't** Schriften unter 0.72rem auf dem Bildschirm.
- **Don't** Vereinsrot ausserhalb des Logos zeigen; Rot nur für Gefahr.
- **Don't** `title=` für Tooltips verwenden.
- **Don't** die «?»-Hilfe pro Knopf setzen.
- **Don't** ein eigenes `max-width` auf Seiten setzen.
- **Don't** `<style>` oder `<link>` in `$page_specific_css` schreiben.
- **Don't** Karten in Karten verschachteln oder Karten einen Schatten geben.
- **Don't** Hex-Farben in neues Seiten-CSS schreiben.
- **Don't** neue Optik in `css/msv-styles.css` oder `css/fixes/resultate-unified.css` ablegen; Aussehen gehört nach `msv-ui.css`.
- **Don't** eine zweite Schrift oder externe Schriftserver einbinden.

## Noch nicht migriert (bewusst)

- **Mitgliederportal** (`portal/`, `css/portal.css`): behält sein eigenes Erscheinungsbild und folgt später. Dieses Dokument gilt dort nicht.
- **`css/msv-styles.css`** (rund 1410 Zeilen Altbestand) lädt weiterhin unter `msv-ui.css`. Es liefert noch Struktur (Breitenklassen, Slide-Panel-Mechanik, Hilfesystem, Tooltip) und alte Optik, die `msv-ui.css` überschreibt, wo sie sichtbar wird. Wo nicht überschrieben, ist der alte Stil noch sichtbar; das ist kein Muster für neue Arbeit. Flag-Dots, Import-Fläche und Rang-Badge sind seit 08.10.2026 ganz nach `msv-ui.css` umgezogen, die Podest-Verläufe aus `mobile-cards.css` ebenso; ebenso `.info-card` (ohne Seitenstreifen). Die Einblend-Animation aller `.table-wrapper` und das Anheben von `#pdf-link` sind entfernt.
- **Seiten mit eigenem Slide-Panel** statt `partials/side_panel.inc.php`: Einzel- und Sektionsrangierungen (`.edit-panel`, fast identische Kopien), Navigation (`.nav-edit-panel`). Umbau gehört zusammen mit einem gemeinsamen Panel-Verhalten (Esc, Ungespeichertes) gemacht.
- **Restliche Seiten-Hex-Werte** (Stand 08.10.2026, nach der Polish-Runde): Die Seiten selbst sind auf Tokens; übrig sind Einzelwerte in Navigation-Editor (4), Hilfetexte (2), Foto-Galerie, Einzel- und Sektionsrangierungen, JM-Definition und Gruppen (je 1). Der Rest steckt in `msv-styles.css` (siehe oben). Ebenfalls offen: 2px-Ränder an einzelnen Karten, Karte in Karte im Navigation-Editor, Skeleton mit Verlauf, Fortschrittsbalken animieren `width` statt `transform`.
- **Navigation** (`inc/navigation.inc.php`, eigener Inline-`<style>`): eigene Blautöne und Aktiv-Markierung, nicht auf Tokens.
- **Doppelte Tokens:** `msv-oeffentlich.css` trägt einen Auszug der `--ui-*` Tokens aus `msv-ui.css` (inkl. `akzent-tief`, `fehler-rand`). Die Werte sind am 08.10.2026 identisch und müssen von Hand synchron gehalten werden. Nur im Admin: `leer`, `feldrand-leer`, `rand-stark`, `warn-zeile-hover` und das Podest.
