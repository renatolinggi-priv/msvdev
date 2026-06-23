<?php
// inc/cuprang/cup_table_renderer.php – edles Kartenlayout für Cup & Ranglisten (PDF-tauglich, ohne externe Assets)

require_once __DIR__ . '/cup_logic.php';
require_once __DIR__ . '/cup_repository.php';

if (!function_exists('esc')) {
    function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/** Styles */
if (!function_exists('cup_inject_cup_styles')) {
    function cup_inject_cup_styles(): string {
        static $injected = false;
        if ($injected) return '';
        $injected = true;
        ob_start(); ?>
<style>
:root{
  --bd:#e3e5e8; --bd-strong:#cfd3d8; --muted:#8b95a1; --ink:#1f2937;
  --win-bg:#e9f7ee; --accent:#5b8cff;
  --gold:#fff4d6; --silver:#f0f3f6; --bronze:#f8e9dc;
  --card:#ffffff; --pill-bg:#f6f7f9;
}
.cup-wrapper{max-width:900px;margin:0; padding-left: 6px;} /* linksbündig, schmaler */
.cup-section{margin-bottom:18px;}
/* Runden bzw. Final/Standcup nebeneinander (umbrechend auf schmalen Screens) */
.cup-rounds, .cup-final-row{display:flex; flex-wrap:wrap; gap:18px 24px; align-items:flex-start;}
.cup-rounds{padding:12px 16px 16px;}
.cup-round{flex:1 1 300px; min-width:240px; max-width:460px;}
/* margin:0 !important hebt die globale .table-wrapper+.table-wrapper{margin-top:2rem} auf → beide Karten top-bündig */
.cup-final-row > .table-wrapper{flex:1 1 300px !important; min-width:260px; margin:0 !important;}
.cup-round .cup-wrapper{max-width:none; padding-left:0;}
.cup-final-row .cup-wrapper{max-width:none; padding:12px 16px 16px;}
.cup-round .cup-section, .cup-final-row .cup-section{margin-bottom:0;}
.cup-legend{display:flex; gap:16px; align-items:center; font-size:11px; color:var(--muted); margin:4px 0 12px;}
.cup-legend .lg-win::before{content:""; display:inline-block; width:11px; height:11px; border-radius:3px; background:var(--win-bg); border:1px solid #bfe6cd; margin-right:6px; vertical-align:-1px;}
.cup-legend .lg-out{text-decoration:line-through;}

.badge{display:inline-block;font-size:10px;padding:2px 8px;border-radius:999px;line-height:1;margin-left:6px;}
.badge-win{background:#2e7d32;color:#fff;}
.badge-out{background:#b00020;color:#fff;}
.badge-rank{
  background:#eef1f6;color:#0b1526; font-weight:700; letter-spacing:.2px;
  font-variant-numeric: tabular-nums; border:1px solid var(--bd-strong); padding:2px 10px; border-radius:999px;
}

/* generische Card mit Accent-Leiste */
.cardline{
  position:relative; display:grid; grid-template-columns:auto 1fr auto; gap:10px; align-items:center;
  background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:5px 10px;
}
.cardline::before{
  content:""; position:absolute; left:0; top:6px; bottom:6px; width:3px; border-radius:3px;
  background: linear-gradient(180deg, var(--accent), #76a5ff);
  opacity:.5;
}
.cardline.top1{ background: var(--gold); }
.cardline.top2{ background: var(--silver); }
.cardline.top3{ background: var(--bronze); }

/* Name + Verein */
.fullname{white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--ink); font-weight:600;}
.club{display:inline; font-size:12px; color:var(--muted); font-weight:500;}
.club::before{content:"·"; margin:0 6px; color:#cbd5e1;}

/* Score-Pillen rechts */
.score{
  min-width:36px; text-align:center; font-variant-numeric: tabular-nums;
  background: var(--pill-bg);
  border:1px solid var(--bd); border-radius:10px; padding:2px 8px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.9), inset 0 -1px 0 rgba(0,0,0,.03);
}

/* Matchliste – pro Teilnehmer eine Zeile, Gewinner grün hervorgehoben */
.matchlist{display:flex; flex-direction:column; gap:10px;}
.match{ background:var(--card); border:1px solid var(--bd); border-radius:12px; overflow:hidden;
  box-shadow:0 1px 2px rgba(16,24,40,.05); }
.prow{ display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center; padding:5px 12px; }
.prow + .prow{ border-top:1px solid var(--bd); }
.prow.win{ background:var(--win-bg); }
.pname{ min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--ink); font-weight:600; }
.prow.win .who{ color:#1b5e20; font-weight:700; }
.prow.win .score{ font-weight:700; }
.prow.lose .who{ color:var(--muted); text-decoration:line-through; font-weight:500; }

/* Legende */
.legend-pill{display:inline-block; font-size:10px; padding:2px 8px; border-radius:999px; border:1px solid var(--bd); background:#fafbfc; margin-right:6px;}
</style>
<?php
        return ob_get_clean();
    }
}

/** Namen in einem Rutsch holen */
if (!function_exists('cup_build_namecache_from_pairs')) {
    function cup_build_namecache_from_pairs(mysqli $conn, array $pairs): array {
        $ids = [];
        foreach ($pairs as $r) foreach (['Participant1','Participant2','Participant3'] as $k) if (!empty($r[$k])) $ids[]=(int)$r[$k];
        $cache = get_member_names_bulk($conn, $ids);
        foreach ($ids as $id) if (!isset($cache[$id])) $cache[$id]='Mitglied #'.$id;
        return $cache;
    }
}

/** 2er-Tiebreak via LowShot1/2, falls cup_logic keinen Winner liefert */
if (!function_exists('cup_resolve_winner_if_needed')) {
    function cup_resolve_winner_if_needed(array $row, ?int $winner): ?int {
        if (!empty($row['Participant3'])) return $winner;
        if ($winner === 1 || $winner === 2) return $winner;

        $r1 = is_numeric($row['Result1'] ?? null) ? (int)$row['Result1'] : null;
        $r2 = is_numeric($row['Result2'] ?? null) ? (int)$row['Result2'] : null;
        if ($r1 === null || $r2 === null) return $winner;
        if ($r1 > $r2) return 1; if ($r2 > $r1) return 2;

        $l1 = is_numeric($row['LowShot1'] ?? null) ? (int)$row['LowShot1'] : null;
        $l2 = is_numeric($row['LowShot2'] ?? null) ? (int)$row['LowShot2'] : null;
        if ($l1 === null || $l2 === null) return $winner;
        if ($l1 > $l2) return 1; if ($l2 > $l1) return 2;
        return $winner;
    }
}

/** RUNDE – Karten mit edlem Finish */
if (!function_exists('cup_render_round_table')) {
    function cup_render_round_table(mysqli $conn, array $allPairs, int $round): string {
        $rows = array_values(array_filter($allPairs, fn($r)=>(int)$r['Round']===$round));
        $html = cup_inject_cup_styles();
        if (empty($rows)) return $html.'<div class="text-muted">Keine Paarungen für Runde '.esc($round).'</div>';

        $names = cup_build_namecache_from_pairs($conn, $rows);

        $html .= '<div class="cup-wrapper"><div class="cup-section">';
        $html .= '<div class="cup-legend">'
               . '<span class="lg-win">Gewinner</span><span class="lg-out">Ausgeschieden</span>'
               . '</div>';
        $html .= '<div class="matchlist">';

        $fmtScore = function($s){ return ($s === '' || $s === null || $s === 0 || $s === '0') ? '–' : esc($s); };
        // $winLabel: "Gewinner" wenn nur einer weiterkommt, sonst "Weiter" (3er mit 2 Weiterkommenden)
        $prow = function($name, $score, $state, $winLabel) use ($fmtScore) {
            $badge = $state === 'win' ? ' <span class="badge badge-win">'.esc($winLabel).'</span>'
                   : ($state === 'lose' ? ' <span class="badge badge-out">Out</span>' : '');
            return '<div class="prow'.($state ? ' '.$state : '').'">'
                 .   '<span class="pname"><span class="who">'.esc($name).'</span>'.$badge.'</span>'
                 .   '<span class="score">'.$fmtScore($score).'</span>'
                 . '</div>';
        };

        foreach ($rows as $row) {
            $p1 = (int)($row['Participant1'] ?? 0);
            $p2 = (int)($row['Participant2'] ?? 0);
            $p3 = !empty($row['Participant3']) ? (int)$row['Participant3'] : null;

            $n1 = $p1 ? ($names[$p1] ?? '#'.$p1) : '';
            $n2 = $p2 ? ($names[$p2] ?? '#'.$p2) : '';
            $n3 = $p3 ? ($names[$p3] ?? '#'.$p3) : '';

            $r1 = $row['Result1'] ?? '';
            $r2 = $row['Result2'] ?? '';
            $r3 = $row['Result3'] ?? '';

            // Weiterkommen-Status exakt wie im Cup-Editor (Advancers + ManualWinner berücksichtigt)
            $info = cup_pair_states($row);
            $st = $info['states'];
            $winLabel = $info['advCount'] === 1 ? 'Gewinner' : 'Weiter';

            $html .= '<div class="match">';
            $html .= $prow($n1, $r1, $st[1], $winLabel);
            $html .= $prow($n2, $r2, $st[2], $winLabel);
            if ($p3) {
                $html .= $prow($n3, $r3, $st[3], $winLabel);
            }
            $html .= '</div>';
        }

        $html .= '</div></div></div>'; // matchlist + section + wrapper
        return $html;
    }
}

/** FINALE Rangliste – edle Card-Liste */
if (!function_exists('cup_render_final_ranking_table')) {
    function cup_render_final_ranking_table(mysqli $conn, array $raw): string {
        $html = cup_inject_cup_styles();
        foreach ($raw as &$r) if (!isset($r['Punkte']) && isset($r['Result'])) $r['Punkte']=$r['Result']; unset($r);

        $need=[]; foreach ($raw as $r) if(empty($r['Teilnehmer']) && !empty($r['ParticipantID'])) $need[]=(int)$r['ParticipantID'];
        if ($need){ $bulk=get_member_names_bulk($conn,$need); foreach($raw as &$r){ if(empty($r['Teilnehmer']) && !empty($r['ParticipantID'])) $r['Teilnehmer']=$bulk[(int)$r['ParticipantID']]??('Mitglied #'.$r['ParticipantID']); } unset($r); }

        usort($raw,function($a,$b){ $pa=(int)($a['Punkte']??0); $pb=(int)($b['Punkte']??0);
            if($pb!==$pa) return $pb<=>$pa; return (int)($b['Tiefschuss']??0)<=>(int)($a['Tiefschuss']??0); });
        $ranked=[]; $rank=0; $i=0; $prev=null;
        foreach($raw as $r){ $i++; $k=$r['Punkte'].'|'.$r['Tiefschuss']; if($k!==$prev){$rank=$i;$prev=$k;} $r['Rang']=$rank; $ranked[]=$r; }

        $html .= '<div class="cup-wrapper"><div class="cup-section"><div class="ranklist">';
        foreach($ranked as $r){
            $cls = $r['Rang']===1?'top1':($r['Rang']===2?'top2':($r['Rang']===3?'top3':''));
            $med = $r['Rang']===1?' 🥇':($r['Rang']===2?' 🥈':($r['Rang']===3?' 🥉':''));
            $html .= '<div class="cardline '.$cls.'">' 
            .   '<div class="badge-rank">'.$r['Rang'].'</div>'
            .   '<div class="fullname">'.esc($r['Teilnehmer']??'').$med.'</div>'
            .   '<div class="score">'.esc($r['Punkte']??'').'</div>'
            . '</div>';
        }
        $html .= '</div></div></div>';
        return $html;
    }
}

/** STANDCUP-Final – gleicher Stil inkl. Top-3 Farben */
if (!function_exists('cup_render_standcup_table')) {
    function cup_render_standcup_table(mysqli $conn, array $rows): string {
        $html = cup_inject_cup_styles();
        if (empty($rows)) return $html.'<div class="text-muted">Keine Einträge.</div>';

        foreach ($rows as &$r){
            if (!isset($r['Punkte']) && isset($r['Result'])) $r['Punkte']=$r['Result'];
            if (!isset($r['club'])   && isset($r['Club']))   $r['club']=$r['Club'];
        } unset($r);

        $need=[]; foreach($rows as $r) if(empty($r['ParticipantName']) && !empty($r['ParticipantID'])) $need[]=(int)$r['ParticipantID'];
        if ($need){ $bulk=get_member_names_bulk($conn,$need); foreach($rows as &$r){ if(empty($r['ParticipantName']) && !empty($r['ParticipantID'])){ $id=(int)$r['ParticipantID']; $r['ParticipantName']=$bulk[$id]??('Mitglied #'.$id); } } unset($r); }

        // Sort + Rang (wie zuvor)
        usort($rows,fn($a,$b)=>((int)($b['Punkte']??-INF))<=>((int)($a['Punkte']??-INF)));
        $rank=0;$i=0;$prev=null;
        foreach($rows as &$r){ $i++; $k=(string)($r['Punkte']??''); if($k!==$prev){$rank=$i;$prev=$k;} $r['_rank']=$rank; } unset($r);

        $html .= '<div class="cup-wrapper"><div class="cup-section"><div class="ranklist">';
        foreach($rows as $r){
            $name = esc($r['ParticipantName'] ?? '');
            $club = esc($r['club'] ?? '');
            // Top-3 Farbklassen analog finale Rangliste
            $cls = ($r['_rank']===1?'top1':($r['_rank']===2?'top2':($r['_rank']===3?'top3':'')));
            $html .= '<div class="cardline '.$cls.'">' 
            .   '<div class="badge-rank">'.$r['_rank'].'</div>'
            .   '<div>'
            .      '<span class="fullname">'.$name.'</span>'
            .      ($club ? '<span class="club">'.$club.'</span>' : '')
            .   '</div>'
            .   '<div class="score">'.esc($r['Punkte']??'').'</div>'
            . '</div>';
        }
        $html .= '</div></div></div>';
        return $html;
    }
}
