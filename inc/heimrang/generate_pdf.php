<?php
// inc/cuprang/generate_cup_pdf.php
// PDF im Karten-Look (mit Logo-Header, ohne Tiefschuss)

use Dompdf\Dompdf;
use Dompdf\Options;

declare(strict_types=1);
ob_start();

$resp = ['success' => false, 'pdf_link' => '', 'error' => null];

try {
    // === Dompdf laden ===
    $dompdfAuto = dirname(__DIR__) . '/dompdf/autoload.php';
    if (file_exists($dompdfAuto)) require_once $dompdfAuto;

    require_once dirname(__DIR__) . '/dbconnect.inc.php';
    require_once __DIR__ . '/cup_repository.php';
    require_once __DIR__ . '/cup_table_renderer.php';

    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

    $conn = get_db_connection();
    if (!$conn) throw new RuntimeException('DB-Verbindung fehlgeschlagen.');

    $pairs = cup_fetch_pairs($conn, $year);
    $finalRaw = cup_fetch_final_results($conn, $year);
    $standRaw = function_exists('cup_fetch_standcup_final') ? cup_fetch_standcup_final($conn, $year) : [];

    // Runden bestimmen
    $rounds = array_values(array_unique(array_map(fn($r) => (int)$r['Round'], $pairs)));
    sort($rounds, SORT_ASC);

    // Finaldaten vorbereiten (ohne Tiefschuss)
    $final = [];
    foreach ($finalRaw as $r) {
        if (empty($r['Teilnehmer']) && !empty($r['ParticipantID'])) {
            $r['Teilnehmer'] = get_member_name($conn, (int)$r['ParticipantID']);
        }
        unset($r['LowShot'], $r['Tiefschuss']);
        $final[] = $r;
    }

    // Standcup-Namen sicherstellen
    $stand = [];
    foreach ($standRaw as $r) {
        if (empty($r['ParticipantName']) && !empty($r['ParticipantID'])) {
            $r['ParticipantName'] = get_member_name($conn, (int)$r['ParticipantID']);
        }
        $stand[] = $r;
    }

    // === HTML aufbauen ===
    ob_start(); ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Cup <?= $year ?></title>
<style>
@page { margin: 1.5cm; }
body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
.header { position: relative; margin-bottom: 30px; min-height: 100px; }
.logo { position: absolute; top: 0; left: 0; width: 100px; height: auto; }
h1 { text-align: center; font-size: 20px; margin: 0; padding-top: 20px; }
.section { margin-bottom: 25px; page-break-inside: avoid; }
.section h2 { font-size: 16px; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 4px; }
.footer { text-align: center; font-size: 9px; color: #666; margin-top: 30px; padding-top: 10px; border-top: 1px solid #ccc; }
</style>
<?php echo cup_inject_cup_styles(); ?>
</head>
<body>

<div class="header">
  <img src="/images/MSVWilen_Logo.jpg" class="logo" alt="MSV Wilen Logo">
  <h1>Cup <?= $year ?></h1>
</div>

<div class="section">
  <h2>Paarungen <?= $year ?></h2>
  <?php
  if (empty($pairs)) {
      echo '<p>Keine Paarungen vorhanden.</p>';
  } else {
      foreach ($rounds as $rnd) {
          echo '<h3>Runde '.(int)$rnd.'</h3>';
          echo cup_render_round_table($conn, $pairs, $rnd);
      }
  }
  ?>
</div>

<div class="section">
  <h2>Finale Rangliste <?= $year ?></h2>
  <?php
  if (empty($final)) {
      echo '<p>Noch keine Finalresultate vorhanden.</p>';
  } else {
      // eigenes Rendering ohne Tiefschuss
      echo '<div class="cup-wrapper"><div class="cup-section"><div class="ranklist">';
      $rank = 0;
      foreach ($final as $r) {
          $rank++;
          $cls = ($rank===1?'top1':($rank===2?'top2':($rank===3?'top3':'')));
          $med = ($rank===1?' 🥇':($rank===2?' 🥈':($rank===3?' 🥉':''));
          echo '<div class="cardline '.$cls.'">';
          echo '<div class="badge-rank">'.$rank.'</div>';
          echo '<div class="fullname">'.esc($r['Teilnehmer']).$med.'</div>';
          echo '<div class="score">'.esc($r['Punkte']).'</div>';
          echo '<div></div>';
          echo '</div>';
      }
      echo '</div></div></div>';
  }
  ?>
</div>

<div class="section">
  <h2>Standcup Final <?= $year ?></h2>
  <?php
  if (empty($stand)) {
      echo '<p>Noch keine Standcup-Finaldaten vorhanden.</p>';
  } else {
      echo '<div class="cup-wrapper"><div class="cup-section"><div class="ranklist">';
      $rank = 0;
      foreach ($stand as $r) {
          $rank++;
          $cls = ($rank===1?'top1':($rank===2?'top2':($rank===3?'top3':'')));
          echo '<div class="cardline '.$cls.'">';
          echo '<div class="badge-rank">'.$rank.'</div>';
          echo '<div><span class="fullname">'.esc($r['ParticipantName']).'</span>';
          if (!empty($r['club'])) echo '<span class="club">'.esc($r['club']).'</span>';
          echo '</div>';
          echo '<div class="score">'.esc($r['Punkte']).'</div>';
          echo '<div></div>';
          echo '</div>';
      }
      echo '</div></div></div>';
  }
  ?>
</div>

<div class="footer">
  Generiert am <?= date('d.m.Y \u\m H:i') ?> Uhr
</div>

</body>
</html>
<?php
    $html = ob_get_clean();

    // === PDF rendern ===
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Speichern
    $targetDir = __DIR__ . '/exports';
    if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
    $filename = 'cup_'.$year.'_'.date('Y-m-d_H-i-s').'.pdf';
    $filePath = $targetDir.'/'.$filename;
    file_put_contents($filePath, $dompdf->output());

    $pdfUrl = '/inc/cuprang/exports/'.$filename.'?t='.time();

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success'=>true, 'pdf_link'=>$pdfUrl]);

} catch (\Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
