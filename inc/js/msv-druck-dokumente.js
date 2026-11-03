/**
 * msv-druck-dokumente.js — Katalog aller Dokumente mit Direktdruck (QZ Tray)
 *
 * Eine Quelle für die Drucksteuerung (inc/drucksteuerung.php, Profilliste) und den Direktdruck
 * auf den Seiten (js/msv-direktdruck.js, Drucker beim ersten Druck wählen). Seit 1.68.52.
 *
 * doc_type = Schlüssel in print_profiles und data-druck-doctype der Seiten.
 * format    = feste Seitengrösse des Generators { paper, orient, text }
 * formate   = wählbar (Ranglisten); der erste Eintrag ist die Vorgabe des Generators. Die Wahl landet in
 *             print_profiles.orientation und optionen.format und geht als ?orientation= an den Generator.
 * broschuere= Blatt zum Falten (A5-Heft oder Standblatt, A4 quer): ohne Duplex im Profil druckt MsvDruck.print()
 *             automatisch beidseitig über die kurze Kante (seit 1.68.53 zentral, auch für die Standblätter).
 */
(function (global) {
  'use strict';

  const HOCH = { value: 'A4P', label: 'A4 hoch', paper: 'A4', orient: 'portrait' };
  const QUER = { value: 'A4L', label: 'A4 quer', paper: 'A4', orient: 'landscape' };
  const F_HOCH = { paper: 'A4', orient: 'portrait', text: 'A4 hoch' };
  const F_QUER = { paper: 'A4', orient: 'landscape', text: 'A4 quer' };

  const GRUPPEN = [
    {
      gruppe: 'Jahresmeisterschaft',
      dokumente: [
        { doc_type: 'jm_standblatt', label: 'JM Standblatt', desc: 'Heimmeisterschaft und Kantonalstich, 2 Seiten zum Falten', format: F_QUER, broschuere: true },
        { doc_type: 'jmrang', label: 'JM Rangliste', desc: 'Nach Rang oder nach Name', formate: [QUER, HOCH] },
        { doc_type: 'jmdurchschnitt', label: 'JM Durchschnitte', desc: 'Durchschnitte pro Anlass', formate: [HOCH, QUER] },
        { doc_type: 'jmdefinition', label: 'Jahresprogramm', desc: 'Alle Anlässe des Jahres auf einer Seite', format: F_HOCH },
      ],
    },
    {
      gruppe: 'Endschiessen',
      dokumente: [
        { doc_type: 'endschiessen_standblatt', label: 'Endschiessen Standblatt', desc: 'Aus «Endschiessen lösen», 2 Seiten zum Falten', format: F_QUER, broschuere: true },
        { doc_type: 'endschiessen_abrechnung', label: 'Endschiessen Abrechnung', desc: 'Aus «Endschiessen lösen»', format: F_QUER },
        { doc_type: 'endschrang', label: 'Endschiessen Ranglisten', desc: 'Gesamt, Zwischen, Anmeldungen, Einzelwettbewerbe, Partner', formate: [HOCH, QUER] },
        { doc_type: 'absendenbuch', label: 'Absendenbuch (Broschüre)', desc: 'A5-Heft auf A4 quer, 2 Seiten pro Blatt, gefaltet', format: F_QUER, broschuere: true },
        { doc_type: 'endsch_targetprint', label: 'Endschiessen Zielscheiben', desc: 'Zielscheiben aus der CSV-Datei der Anlage', format: F_HOCH },
        { doc_type: 'munitionskauf', label: 'Munitionskauf Liste', desc: 'Käufe im gewählten Zeitraum', format: F_HOCH },
      ],
    },
    {
      gruppe: 'Ranglisten',
      dokumente: [
        { doc_type: 'kantirang', label: 'Kantonalstich Rangliste', desc: 'Auch von «Kantonalstich Abrechnung» genutzt', formate: [HOCH, QUER] },
        { doc_type: 'heimrang', label: 'Heimmeisterschaft Rangliste', desc: 'Alle Passen', formate: [QUER, HOCH] },
        { doc_type: 'sektionrang', label: 'Sektionsmeisterschaft Rangliste', desc: 'Runde 1, Runde 2 und Schnitt', formate: [HOCH, QUER] },
        { doc_type: 'sektionsrangierungen', label: 'Sektionsrangierungen', desc: 'Rangierungen fremder Anlässe', formate: [HOCH, QUER] },
        { doc_type: 'einzelrangierung', label: 'Einzelrangierungen', desc: 'Einzelresultate fremder Anlässe', formate: [HOCH, QUER] },
        { doc_type: 'cuprang', label: 'Vereinscup Rangliste', desc: 'Turnierbaum und Rangliste', formate: [HOCH, QUER] },
      ],
    },
    {
      gruppe: 'Einsätze',
      dokumente: [
        { doc_type: 'einsatzplan', label: 'Einsatzplan', desc: 'Obligatorisch, Feldschiessen und Chilbi quer, Schlossturm hoch', format: { paper: 'A4', orient: 'portrait', text: 'A4, je Plan' } },
      ],
    },
    {
      gruppe: 'Verein',
      dokumente: [
        { doc_type: 'monatsblatt', label: 'Monatsblatt', desc: 'A5-Heft auf A4 quer, 2 Seiten pro Blatt, gefaltet', format: F_QUER, broschuere: true },
        { doc_type: 'fragebogen', label: 'Fragebogen', desc: 'Auswertung des Mitgliederfragebogens', format: F_QUER },
      ],
    },
  ];

  const MsvDruckDokumente = {
    gruppen: GRUPPEN,

    /** Definition zu einem doc_type (oder null). */
    finde(docType) {
      for (const g of GRUPPEN) {
        const d = g.dokumente.find(x => x.doc_type === docType);
        if (d) return d;
      }
      return null;
    },

    /** Gewähltes Format einer Definition: formate[value] oder das feste Format. */
    format(def, wert) {
      if (!def) return { paper: 'A4', orient: 'portrait', text: 'A4 hoch' };
      if (def.formate) {
        const f = def.formate.find(o => o.value === wert) || def.formate[0];
        return { paper: f.paper, orient: f.orient, text: f.label, value: f.value };
      }
      return def.format;
    },

    /** Vorgabewerte für ein neues Profil (Drucker kommt vom Aufrufer). */
    profilVorgabe(docType) {
      const def = this.finde(docType);
      const f = this.format(def, null);
      return {
        doc_type: docType,
        anzeigename: def ? def.label : docType,
        print_mode: 'pixel',
        copies: 1,
        paper_size: f.paper || 'A4',
        orientation: f.orient || 'portrait',
        color_mode: 'blackwhite',
        duplex: '',
        optionen: def && def.formate ? JSON.stringify({ format: f.value }) : '{}',
      };
    },
  };

  global.MsvDruckDokumente = MsvDruckDokumente;
})(window);
