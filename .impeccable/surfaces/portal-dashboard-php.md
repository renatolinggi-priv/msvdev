---
version: 1
slug: "portal-dashboard-php"
primary_target: "portal/dashboard.php"
related_targets: ["portal/portal_header.php","portal/portal_footer.php","css/portal.css"]
---

# Surface brief: Mitgliederportal

## Scope and mode
Mitgliederportal `portal/` (alle Seiten, inkl. Jungschützen-Bereich jsk_*), PWA, vorwiegend Handy. Mode: Operate (mit Lese-Anteilen bei Resultaten und Mitteilungen).

## Audience and job
Mitglieder (viele älter, 1–2 Besuche pro Monat) wollen ohne Suchen sehen: JM-Stand und Rang, nächster Anlass, nächster Einsatz, Neues vom Verein, Fotos. Jungschützen (Teenager) nutzen Termine, Chat, Resultate. Vorstand sieht zusätzlich Verwaltungslinks.

## Constraints
- Vorschau zuerst: Die neue Optik gilt nur mit Vorschau-Schalter (Admin), bis der Benutzer freigibt. Kein Zwischenstand für alle Mitglieder.
- Vereinsidentität als austauschbare Schicht (PRODUCT.md Prinzip 5): Vereinsfarbe, Akzent, Ehrenfarbe, Logo und Name als Tokens/Einstellung, nie hart verdrahtet.
- Rot ist heute «Gefahr»: Gefahr bekommt eine eigene Sprache (Symbol + Text, Löschen nur als Umriss), nie nur Farbe.
- Jungschützen-Bereich in derselben Optik («ein Verein»).
- Navigation und Bedienelemente bleiben Standard-Muster (Bootstrap 5); die Welt liefert nur Typo, Palette, Dichte, Signatur.
- Grundschrift 16px, nichts unter 12px, Text ≥ 4.5:1, Tippflächen ≥ 44px, Eingaben 16px.

## Direction contract
THESIS: Das Portal trägt die Vereinsfahne. Der Verein steht im Kopf jeder Seite wie die Fahne am Schützenfest; darunter ruhiger, gut lesbarer Inhalt. Verweigert das Regenbogen-Kachelraster mit Facebook-Blau.
OWN-WORLD: Fahnenrot #c00000 als Kopf und aktive Navigation (weisse Schrift), Weiss als Grund, Königsblau #0052a0 für Knöpfe, Links und Daten, Löwengold #ffe707 nur für Ehre (Kranz, Rang, Wanderpreis, nächster Anlass) mit dunkler Schrift, Tinte #1a1a24, Damastgrau #bcbdb6 für Linien. Flach: keine Schatten, keine Verläufe. Titel in kräftigen Versalien wie die Logo-Inschrift; eine Schrift (IBM Plex Sans), wenige weit auseinanderliegende Grössen, Punktzahlen gross.
STORY: Hans öffnet die App, sieht sofort seinen JM-Stand mit Rang, den nächsten Anlass und seinen nächsten Einsatz, erkennt seinen Verein und tippt mit dem Daumen weiter. Lea sieht denselben Verein mit ihren Inhalten.
FIRST VIEWPORT: Roter Fahnenkopf mit Wappen und «Hallo <Vorname>». Darunter das Saisonband: ein Feld pro JM-Anlass in echter Reihenfolge und Anzahl, geschossen rot mit Punkten, offen weiss mit Datum, nächster mit goldenem Rand, Tipp öffnet den Anlass. Dann drei Karten: JM-Stand (Total, Rang in Gold), nächster Anlass (Datum gross), nächster Einsatz; Titelbild des letzten Anlasses aus der Galerie. Untere Navigation mit 4 Zielen (Start, Resultate, Termine, Fotos) in der Daumenzone. In jeder Rangliste ist die eigene Zeile golden markiert.
FORM: Die Vereinsfahne (geflammte/damastene Fahne aus dem Logo), Platz 3 meiner geordneten Liste, seed key 5a406073, Kind assigned. Raises: Streaming-Wand (Galerie-Fotos als einzige Bilder), Sternatlas (eigene Zeile markiert), Bitmap-Specimen (Zahlen gross), Variable-Font-Specimen (keine Schatten/Verläufe), Acetat-Handbuch (echter Massstab im Saisonband).
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved
- Logo in genügender Auflösung (512er-Icon als Basis, besser Vektor).
- Datenquelle Rang im Portal (Admin rechnet ihn in jmrang/load_jm.php).
- DESIGN.md-Abschnitt Portal erst nach Freigabe (DESIGN.md derzeit von msvdev-7a reserviert).
