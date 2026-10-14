<?php
// generate_pdf.php für Kantonalstich
use Dompdf\Dompdf;
use Dompdf\Options;

// Ausgabepufferung starten
ob_start();

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require '../vendor/autoload.php';
    include '../config.php';
    require_once __DIR__ . '/../admin_api_guard.inc.php';
    adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
    require_once __DIR__ . '/../pdf/pdf_theme.php';

    // Verbindung prüfen
    if ($conn->connect_error) {
        throw new Exception("Verbindung fehlgeschlagen: " . $conn->connect_error);
    }

    // Parameter validieren
    $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $currentYear = date('Y');
    if ($selectedYear < 2000 || $selectedYear > $currentYear + 1) {
        $selectedYear = $currentYear;
    }

    // Funktion zum Erstellen einer Tabelle
    function createTable($data, $title) {
        if (empty($data)) {
            return '<h2>' . htmlspecialchars($title) . '</h2><p>Keine Ergebnisse gefunden.</p>';
        }

        $html = '<h2>' . htmlspecialchars($title) . '</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="r">Rang</th>
                                <th>Name</th>';

        for ($i = 1; $i <= 5; $i++) {
            $html .= '<th class="r">Passe ' . $i . '</th>';
        }

        $html .= '<th class="r">Total</th>
                            </tr>
                        </thead>
                        <tbody>';

        $rang = 1;
        $nachPodest = 0; // Rang 1–3 fett, Abstand vor der ersten Zeile danach (Gleichstand teilt den Rang)
        $previousTotal = null;
        $sameRankCount = 0;
        
        foreach ($data as $row) {
            // Rangbehandlung bei gleichen Totals
            if ($previousTotal !== null && $row['KantiSumme'] < $previousTotal) {
                $rang += $sameRankCount;
                $sameRankCount = 1;
            } elseif ($previousTotal === $row['KantiSumme']) {
                $sameRankCount++;
            } else {
                $sameRankCount = 1;
            }
            
            $html .= pdf_rangliste_zeile($rang <= 3 ? $rang : ($nachPodest++ === 0 ? 4 : 5)) . '
                        <td class="rang">' . $rang . '.</td>
                        <td>' . htmlspecialchars($row['Name'] . ' ' . $row['Vorname']) . '</td>';

            for ($i = 1; $i <= 5; $i++) {
                $html .= '<td class="r">' . pdf_strich_wenn_null($row["Passe$i"]) . '</td>';
            }

            $html .= '<td class="r">' . $row['KantiSumme'] . '</td>
                    </tr>';

            $previousTotal = $row['KantiSumme'];
        }

        $html .= '</tbody></table>';
        return $html;
    }

    // Alle Daten in einer Abfrage holen
    $sql = "SELECT 
        w.Kategorie,
        m.Name, 
        m.Vorname, 
        k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,
        (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + 
         COALESCE(k.Passe4, 0) + COALESCE(k.Passe5, 0)) AS KantiSumme
    FROM kantiresultate k
    INNER JOIN mitglieder m ON m.ID = k.MitgliedID
    INNER JOIN Waffen w ON w.ID = m.WaffenID 
    WHERE w.Kategorie IN ('Kat. A', 'Kat. B')
      AND k.Jahr = ?
      AND (k.Passe1 > 0 OR k.Passe2 > 0 OR k.Passe3 > 0 OR k.Passe4 > 0 OR k.Passe5 > 0)
    ORDER BY w.Kategorie, KantiSumme DESC, m.Name, m.Vorname";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Fehler beim Vorbereiten der Abfrage: " . $conn->error);
    }
    
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Daten nach Kategorie gruppieren
    $kategorienDaten = [
        'Kat. A' => [],
        'Kat. B' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $kategorienDaten[$row['Kategorie']][] = $row;
    }

    $stmt->close();
    $conn->close();

    // Leeres Jahr: kein leeres PDF (und kein leeres Blatt im Direktdruck), sondern ein Hinweis
    if (!$kategorienDaten['Kat. A'] && !$kategorienDaten['Kat. B']) {
        msvAusgabeLeer('Für ' . $selectedYear . ' sind noch keine Kantonalstich-Resultate erfasst.');
    }

    // HTML generieren
    $html = pdf_rangliste_anfang('Kantonalstich ' . $selectedYear, 'Rangliste');

    // Beide Kategorien hinzufügen
    $html .= createTable($kategorienDaten['Kat. A'], 'Kat. A (Standardgewehr)');
    $html .= createTable($kategorienDaten['Kat. B'], 'Kat. B (Ordonnanzwaffen)');
    $html .= '</body></html>';

    // PDF generieren
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';
    $dompdf->setPaper('A4', pdfOrientationParam('portrait')); // Ausrichtung aus dem Druckprofil, Default hoch
    $dompdf->render();
    pdf_seitenfuss($dompdf, 'Kantonalstich ' . $selectedYear);

    // Verzeichnis prüfen
    if (!is_dir('dat')) {
        if (!mkdir('dat', 0755, true)) {
            throw new Exception("Konnte Verzeichnis nicht erstellen");
        }
    }

    // PDF speichern
    $date = new DateTime();
    $pdfFilePath = 'dat/RanglisteKantonalstich_' . $date->format('Y-m-d_H-i-s') . '.pdf';
    
    if (!file_put_contents($pdfFilePath, $dompdf->output())) {
        throw new Exception("Konnte PDF nicht speichern");
    }

    // Ausgabepuffer leeren
    ob_end_clean();

    // JSON-Antwort zurückgeben
    header('Content-Type: application/json');
    echo json_encode(array('pdf_link' => "kantirang/" . $pdfFilePath));

} catch (Exception $e) {
    ob_end_clean();
    error_log("PDF-Generator Fehler: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(array('pdf_link' => null));
}
?>