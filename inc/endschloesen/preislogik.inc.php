<?php
/**
 * Preislogik Endschiessen lösen – die EINZIGE Stelle, an der der Stich-Preis
 * eines Teilnehmers berechnet wird.
 *
 * Genutzt von
 *   - endschloesen_api.php  (save_selection: gast_spezialpreis wird serverseitig
 *                            gesetzt, der Client schickt keinen Preis mehr;
 *                            get_year_details: Partner-Zabig)
 *   - generate_pdf_endschloesen.php (Partner-Zabig, Einnahmen)
 *
 * Das JavaScript in endschloesen.php spiegelt dieselben Regeln nur für die
 * Live-Anzeige des Totals. Verbindlich ist immer der Server. Wer eine Regel
 * ändert, ändert sie hier UND in calcStichPreis() der Seite.
 *
 * Teilnehmertypen:
 *   mitglied  Einzelpreise je Stich (endstich_definition.price_cents); PROBE gratis;
 *             ZABIG mit Partner -> Spezialpreis partner_zabig
 *   gast      Löst der Gast ALLE Stiche (alle aktiven ausser PROBE) und ist gast_alle > 0,
 *             gilt dieser Pauschalpreis. Sonst: Kombi-Preis nach Anzahl gelöster Stiche aus
 *             END/SCHWINI_P1/SCHWINI_P2 (1 = Einzelpreis, 2 = gast_kombi_2, ab 3 = gast_kombi_3),
 *             plus gast_sie_und_er falls SIEUNDER,
 *             plus Einzelpreis (endstich_definition.price_cents) für jeden weiteren,
 *             optional gelösten Stich (KUNST, GLUECK, ZABIG, DIFF …; seit 16.09.2026 erlaubt,
 *             PROBE bleibt Jungschützen vorbehalten)
 *   js        Jungschütze/-in (Gast mit Geburtsdatum): Paketpreis js_paket_preis,
 *             sobald mindestens ein Paket-Stich gelöst ist (0 = gratis)
 */

const ENDSCH_PREIS_DEFAULTS = [
    'munition_pro_schuss' => 50,
    'munition_gp11_60'    => 3000,
    'munition_gp90_50'    => 2500,
    'gast_kombi_2'        => 3500,
    'gast_kombi_3'        => 4900,
    'gast_alle'           => 0,
    'gast_sie_und_er'     => 1000,
    'partner_zabig'       => 1000,
    'js_paket_preis'      => 0,
];

/** Stiche, die im JS-Paket enthalten sind (und die einzigen, die ein JS lösen kann). */
const ENDSCH_JS_PAKET_CODES = ['END', 'SCHWINI_P1', 'ZABIG', 'PROBE'];

/** Stiche, die bei Gästen für den Kombi-Preis zählen. */
const ENDSCH_GAST_KOMBI_CODES = ['END', 'SCHWINI_P1', 'SCHWINI_P2'];

/**
 * Standard-Stiche eines Gastes (Kombi-Preis + Sie und Er). Der Button «Alle» der Seite wählt genau diese.
 * Alle übrigen aktiven Stiche ausser PROBE kann ein Gast optional zum Einzelpreis dazulösen.
 */
const ENDSCH_GAST_ERLAUBT = ['END', 'SCHWINI_P1', 'SCHWINI_P2', 'SIEUNDER'];

/** Stiche, die ein Gast NICHT lösen kann. */
const ENDSCH_GAST_GESPERRT = ['PROBE'];

/**
 * Spezialpreise laden: typ => Cents. Fehlende Einträge werden mit den Defaults ergänzt,
 * damit die Berechnung auch bei leerer Tabelle deterministisch bleibt.
 */
function endschLadeSpezialpreise(mysqli $conn): array
{
    $preise = ENDSCH_PREIS_DEFAULTS;
    $res = $conn->query("SELECT typ, price_cents FROM endstich_spezialpreise WHERE active = 1");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $preise[$row['typ']] = (int)$row['price_cents'];
        }
    }
    return $preise;
}

/**
 * Stich-Definitionen laden: code => ['id','name','shots','price_cents'] (nur aktive).
 */
function endschLadeStiche(mysqli $conn): array
{
    $stiche = [];
    $res = $conn->query("SELECT id, code, name, shots, price_cents FROM endstich_definition WHERE active = 1");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $stiche[$row['code']] = [
                'id'          => (int)$row['id'],
                'name'        => $row['name'],
                'shots'       => (int)$row['shots'],
                'price_cents' => (int)$row['price_cents'],
            ];
        }
    }
    return $stiche;
}

/**
 * Hat der Gast alle für ihn lösbaren Stiche gewählt? (alle aktiven ausser den gesperrten)
 *
 * @param string[] $codes     gelöste Codes
 * @param string[] $alleCodes alle aktiven Codes (array_keys(endschLadeStiche()))
 */
function endschGastAlleGeloest(array $codes, array $alleCodes): bool
{
    $noetig = array_values(array_diff($alleCodes, ENDSCH_GAST_GESPERRT));
    return $noetig && !array_diff($noetig, $codes);
}

/** Teilnehmertyp eines Gastes: mit Geburtsdatum = Jungschütze/-in. */
function endschTeilnehmerTyp(?string $geburtsdatum): string
{
    return !empty($geburtsdatum) ? 'js' : 'gast';
}

/**
 * Stich-Preis in Cents.
 *
 * @param string[] $codes        gelöste Stich-Codes
 * @param string   $typ          'mitglied' | 'gast' | 'js'
 * @param bool     $zabigPartner Zabig mit Partner (nur Mitglieder)
 * @param array      $stiche      aus endschLadeStiche()
 * @param array      $spezial     aus endschLadeSpezialpreise()
 * @param array|null $alleCodes   alle aktiven Stich-Codes (array_keys(endschLadeStiche())); nur damit kann
 *                                die Gäste-Pauschale «alle Stiche» geprüft werden. Wer nur die gelösten
 *                                Definitionen zur Hand hat (Altdaten-Fallback), übergibt null.
 */
function endschBerechnePreis(array $codes, string $typ, bool $zabigPartner, array $stiche, array $spezial, ?array $alleCodes = null): int
{
    $codes = array_values(array_unique(array_map('strval', $codes)));
    $preisVon = function (string $code) use ($stiche): int {
        return isset($stiche[$code]) ? (int)$stiche[$code]['price_cents'] : 0;
    };

    if ($typ === 'js') {
        $paket = array_intersect($codes, ENDSCH_JS_PAKET_CODES);
        return $paket ? (int)($spezial['js_paket_preis'] ?? 0) : 0;
    }

    if ($typ === 'gast') {
        // Pauschale, wenn der Gast alle lösbaren Stiche gewählt hat (gast_alle = 0 → Pauschale aus)
        $pauschale = (int)($spezial['gast_alle'] ?? 0);
        if ($pauschale > 0 && $alleCodes !== null && endschGastAlleGeloest($codes, $alleCodes)) {
            return $pauschale;
        }
        $kombi = array_values(array_intersect($codes, ENDSCH_GAST_KOMBI_CODES));
        $preis = 0;
        if (count($kombi) === 1) {
            $preis = $preisVon($kombi[0]);
        } elseif (count($kombi) === 2) {
            $preis = (int)($spezial['gast_kombi_2'] ?? 0);
        } elseif (count($kombi) >= 3) {
            $preis = (int)($spezial['gast_kombi_3'] ?? 0);
        }
        if (in_array('SIEUNDER', $codes, true)) {
            $preis += (int)($spezial['gast_sie_und_er'] ?? 0);
        }
        // Optional dazugelöste Stiche (nicht Kombi, nicht Sie und Er) zum Einzelpreis
        foreach ($codes as $code) {
            if (in_array($code, ENDSCH_GAST_ERLAUBT, true) || in_array($code, ENDSCH_GAST_GESPERRT, true)) {
                continue;
            }
            $preis += $preisVon($code);
        }
        return $preis;
    }

    // Mitglied
    $preis = 0;
    foreach ($codes as $code) {
        if ($code === 'PROBE') {
            continue;
        }
        if ($code === 'ZABIG' && $zabigPartner) {
            $preis += (int)($spezial['partner_zabig'] ?? 0);
            continue;
        }
        $preis += $preisVon($code);
    }
    return $preis;
}

/** Preis für zusätzliche Munition in Cents. */
function endschZusatzPreis(int $anzahl, array $spezial): int
{
    return max(0, $anzahl) * (int)($spezial['munition_pro_schuss'] ?? ENDSCH_PREIS_DEFAULTS['munition_pro_schuss']);
}
