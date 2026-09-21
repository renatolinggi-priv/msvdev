<?php
include 'dbconnect.inc.php';
require_once __DIR__ . '/dashboard_phasen.inc.php';
require_once __DIR__ . '/dashboard_widgets.inc.php';
include 'header.inc.php';
?>

<style>
/* Home-Page – Kompakte Version */

/* Willkommenszeile */
.home-welcome {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.home-welcome-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.home-welcome-logo {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 2px solid #fff;
}

.home-welcome-text h1 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin: 0;
    line-height: 1.3;
}

.home-welcome-text .subtitle {
    font-size: 0.78rem;
    color: #718096;
    margin: 0;
}

.home-welcome-right {
    font-size: 0.78rem;
    color: #718096;
}

.home-welcome-right i {
    color: #38a169;
    margin-right: 0.25rem;
}

/* Quick Access Grid – die Spaltenzahl waechst in festen Stufen mit der
   Bildschirmbreite. Feste Stufen statt auto-fill, damit auf sehr breiten
   Schirmen nicht zehn schmale Kacheln nebeneinander stehen, deren Text
   abgeschnitten wird. */
.home-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

@media (min-width: 576px) {
    .home-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 768px) {
    .home-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (min-width: 1200px) {
    .home-grid { grid-template-columns: repeat(4, 1fr); }
}

@media (min-width: 1600px) {
    .home-grid { grid-template-columns: repeat(5, 1fr); }
}

@media (min-width: 1900px) {
    .home-grid { grid-template-columns: repeat(6, 1fr); }
}

.home-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.6rem;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.home-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #3b5998, #0ea5e9);
    transform: scaleX(0);
    transition: transform 0.2s ease;
}

.home-card:hover::before {
    transform: scaleX(1);
}

.home-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #cbd5e1;
}

.home-card a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    text-decoration: none;
    color: inherit;
}

.home-card-icon {
    width: 38px;
    height: 38px;
    min-width: 38px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    background: #eef2ff;
    color: #3b5998;
}

.home-card-icon.red {
    background: #fef2f2;
    color: #dc3545;
}

.home-card-icon.green {
    background: #f0fdf4;
    color: #16a34a;
}

.home-card-icon.info {
    background: #ecfeff;
    color: #0e7490;
}

/* Zonen-Überschrift */
.home-zone-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
    margin: 0 0 0.6rem;
}

.home-zone-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e2e8f0;
}

/* Saison-Hinweis auf der Kachel */
.home-card-note {
    font-size: 0.7rem;
    color: #3b5998;
    margin: 0.15rem 0 0;
    overflow-wrap: anywhere;
}

/* Zone 2 – eingeklappte Liste */
.home-more {
    margin-top: 1.5rem;
}

.home-more > summary {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
    list-style: none;
    padding: 0.35rem 0;
}

.home-more > summary::-webkit-details-marker {
    display: none;
}

.home-more > summary:hover {
    color: #3b5998;
}

.home-more > summary .home-more-caret {
    transition: transform 0.2s ease;
}

.home-more[open] > summary .home-more-caret {
    transform: rotate(90deg);
}

.home-more-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 0.35rem;
    margin-top: 0.5rem;
}

.home-more-item a {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.7rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: #fff;
    text-decoration: none;
    color: #64748b;
    font-size: 0.82rem;
    transition: all 0.15s ease;
}

.home-more-item a:hover {
    border-color: #cbd5e1;
    color: #2d3748;
}

.home-more-item i.home-more-icon {
    color: #cbd5e1;
    font-size: 0.95rem;
}

.home-more-item .home-more-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.home-more-item .home-more-status {
    font-size: 0.68rem;
    color: #a0aec0;
    white-space: nowrap;
}

.home-card-text {
    flex: 1;
    min-width: 0;
}

.home-card-title {
    font-size: 0.88rem;
    font-weight: 600;
    color: #2d3748;
    margin: 0;
    line-height: 1.3;
}

/* Umbrechen statt abschneiden: bei vier bis fünf Spalten sind die Kacheln
   schmal, und ein abgeschnittener Hinweis ist wertlos. Die Grid-Zeile gleicht
   unterschiedlich hohe Kacheln ohnehin aus. */
.home-card-desc {
    font-size: 0.75rem;
    color: #94a3b8;
    margin: 0;
    overflow-wrap: anywhere;
}

.home-card-arrow {
    color: #cbd5e1;
    font-size: 0.85rem;
    transition: transform 0.2s ease;
}

.home-card:hover .home-card-arrow {
    color: #3b5998;
    transform: translateX(3px);
}

/* Aufgaben – "Das wartet auf dich", standardmässig eingeklappt */
.home-tasks {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 0.5rem;
    padding: 0.6rem 0.75rem;
    margin-bottom: 1rem;
}

.home-tasks h6 {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0;
    color: #92400e;
    padding: 0 0.25rem;
}

.home-tasks > summary {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    list-style: none;
    font-size: 0.85rem;
    font-weight: 600;
    color: #92400e;
    padding: 0 0.25rem;
}

.home-tasks > summary::-webkit-details-marker {
    display: none;
}

.home-tasks > summary .home-tasks-caret {
    font-size: 0.8rem;
    transition: transform 0.2s ease;
}

.home-tasks[open] > summary .home-tasks-caret {
    transform: rotate(90deg);
}

.home-tasks-count {
    background: #f59e0b;
    color: #fff;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.05rem 0.4rem;
}

.home-tasks-list {
    margin-top: 0.35rem;
}

.home-task a {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.25rem;
    text-decoration: none;
    color: #4a5568;
    font-size: 0.82rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.home-task:last-child a {
    border-bottom: none;
}

.home-task a:hover {
    color: #2d3748;
}

.home-task a:hover .home-task-text {
    text-decoration: underline;
}

.home-task-icon {
    color: #d97706;
    font-size: 0.9rem;
}

.home-task-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.home-task-badge {
    font-size: 0.68rem;
    color: #92400e;
    background: #fef3c7;
    border-radius: 0.25rem;
    padding: 0.1rem 0.35rem;
    white-space: nowrap;
}

/* Alles erledigt */
.home-tasks-ok {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.home-tasks-ok h6 {
    color: #166534;
    margin: 0;
}

/* Termine und Jubiläen: nebeneinander, sobald je 300px Platz da sind,
   sonst untereinander. Steht zwischen Aufgaben und Kacheln. */
.home-cols {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.home-panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.6rem;
    padding: 0.75rem 0.9rem;
}

.home-panel h6 {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
    margin: 0 0 0.5rem;
}

.home-panel-row {
    display: flex;
    align-items: baseline;
    gap: 0.6rem;
    padding: 0.32rem 0;
    font-size: 0.82rem;
    color: #4a5568;
    border-bottom: 1px solid rgba(0,0,0,0.04);
}

.home-panel-row:last-child {
    border-bottom: none;
}

.home-panel-datum {
    font-variant-numeric: tabular-nums;
    color: #3b5998;
    font-weight: 600;
    white-space: nowrap;
    font-size: 0.78rem;
}

.home-panel-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.home-panel-tag {
    font-size: 0.68rem;
    color: #a0aec0;
    white-space: nowrap;
}

/* Runder Geburtstag – faellt in der Liste auf */
.home-panel-rund .home-panel-name {
    font-weight: 600;
    color: #2d3748;
}

.home-panel-rund .home-panel-tag {
    color: #b45309;
    font-weight: 600;
}

.home-panel-leer {
    font-size: 0.8rem;
    color: #a0aec0;
    padding: 0.32rem 0;
}

/* Mobile – die Spaltenzahl des Kachelrasters regeln die Stufen oben,
   hier nur der engere Abstand und die Touch-Anpassungen. */
@media (max-width: 767.98px) {
    .home-grid {
        gap: 0.5rem;
    }

    .home-more-list {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .home-welcome {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .home-card a {
        padding: 0.75rem;
    }
    
    .home-card-icon {
        width: 36px;
        height: 36px;
        min-width: 36px;
        min-height: 36px; /* WCAG Touch Target via parent link */
    }
}

</style>

<div class="main-content-wrapper">
    
    <!-- Willkommenszeile -->
    <div class="home-welcome">
        <div class="home-welcome-left">
            <img src="jmrang/dat/MSVWilen_Logo.jpg" alt="MSV Wilen" class="home-welcome-logo">
            <div class="home-welcome-text">
                <h1>Willkommen, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Benutzer'); ?></h1>
                <p class="subtitle">MSV Wilen – Resultaterfassung & Verwaltung</p>
            </div>
        </div>
        <div class="home-welcome-right">
            <?php 
            $login_time = $_SESSION['session_created'] ?? $_SESSION['last_activity'] ?? time();
            ?>
            <i class="bi bi-clock-fill"></i>Login: <?php echo date('d.m.Y H:i', $login_time); ?>
        </div>
    </div>

    <!-- Offene Punkte quer durch die App -->
    <?php
    $current_year = (int)date('Y');
    $today = date('Y-m-d');
    $ist_admin = ($_SESSION['user_role'] ?? '') === 'admin' || (int)($_SESSION['user_id'] ?? 0) === 1;

    // Termine und Saisonphasen kommen aus dashboard_phasen.inc.php. Die
    // ausstehenden Anlaesse stuetzen sich dort auf die echten Schiesstage
    // (JMSchiesstage) statt auf den Monatsnamen im Freitext.
    $pending_rows = msvDashboardAusstehend($conn, $current_year, $today);
    $dashboard    = msvDashboardKarten($conn, $current_year, $today, count($pending_rows));
    $aufgaben     = msvDashAufgaben($conn, $today, $pending_rows, $ist_admin);
    ?>

    <?php if ($aufgaben): ?>
        <details class="home-tasks">
            <summary>
                <i class="bi bi-chevron-right home-tasks-caret"></i>
                <i class="bi bi-exclamation-triangle"></i>
                Das wartet auf dich
                <span class="home-tasks-count"><?php echo count($aufgaben); ?></span>
            </summary>
            <div class="home-tasks-list">
                <?php foreach ($aufgaben as $aufgabe): ?>
                    <div class="home-task">
                        <a href="<?php echo htmlspecialchars($aufgabe['link']); ?>">
                            <i class="bi <?php echo htmlspecialchars($aufgabe['icon']); ?> home-task-icon"></i>
                            <span class="home-task-text"><?php echo htmlspecialchars($aufgabe['text']); ?></span>
                            <?php if (!empty($aufgabe['badge'])): ?>
                                <span class="home-task-badge"><?php echo htmlspecialchars($aufgabe['badge']); ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php else: ?>
        <div class="home-tasks home-tasks-ok">
            <h6><i class="bi bi-check-circle me-1"></i>Nichts offen – alles erledigt</h6>
        </div>
    <?php endif; ?>

    <!-- Nächste Termine und Jubiläen – nebeneinander, sobald Platz da ist -->
    <?php
    $naechste_termine = msvDashTermine($conn, $today);
    $jubilaeen        = msvDashJubilaeen($conn, $current_year, $today);
    ?>
    <div class="home-cols">
        <div class="home-panel">
            <h6><i class="bi bi-calendar-event"></i>Nächste Termine</h6>
            <?php if ($naechste_termine): ?>
                <?php foreach ($naechste_termine as $termin): ?>
                    <div class="home-panel-row">
                        <span class="home-panel-datum"><?php echo htmlspecialchars(msvDashDatumBereich($termin['von'], $termin['bis'])); ?></span>
                        <span class="home-panel-name"><?php echo htmlspecialchars($termin['titel']); ?></span>
                        <span class="home-panel-tag"><?php echo htmlspecialchars(msvDashRelativ($termin['von'], $today)); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="home-panel-leer">Keine kommenden Termine gefunden</div>
            <?php endif; ?>
        </div>

        <div class="home-panel">
            <h6><i class="bi bi-award"></i>Vereinsjubiläen <?php echo (int)$current_year; ?></h6>
            <?php if ($jubilaeen['jubilaeen']): ?>
                <?php foreach ($jubilaeen['jubilaeen'] as $j): ?>
                    <div class="home-panel-row">
                        <span class="home-panel-datum"><?php echo (int)$j['jahre']; ?> Jahre</span>
                        <span class="home-panel-name"><?php echo htmlspecialchars($j['person']); ?></span>
                        <span class="home-panel-tag">seit <?php echo (int)$j['seit']; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="home-panel-leer">Keine Vereinsjubiläen gefunden</div>
            <?php endif; ?>
        </div>

        <div class="home-panel">
            <h6><i class="bi bi-balloon"></i>Geburtstage</h6>
            <?php if ($jubilaeen['geburtstage']): ?>
                <?php foreach ($jubilaeen['geburtstage'] as $g): ?>
                    <div class="home-panel-row<?php echo !empty($g['rund']) ? ' home-panel-rund' : ''; ?>">
                        <span class="home-panel-datum"><?php echo htmlspecialchars(msvDashDatum($g['datum'])); ?></span>
                        <span class="home-panel-name"><?php echo htmlspecialchars($g['person']); ?></span>
                        <span class="home-panel-tag">wird <?php echo (int)$g['alter']; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="home-panel-leer">Keine Geburtstage in den nächsten 90 Tagen</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Access – saisonal gesteuert, siehe dashboard_phasen.inc.php -->
    <?php
    // Oben zuerst das, was gerade Saison hat - die Dauerbrenner (Munition,
    // Heim, Kanti, Mitglieder, Portal) folgen danach.
    $aktiv_saison   = [];
    $aktiv_dauernd  = [];
    $karten_weitere = [];
    foreach ($dashboard['karten'] as $karte) {
        if (!$karte['aktiv']) {
            $karten_weitere[] = $karte;
        } elseif ($karte['dauerhaft']) {
            $aktiv_dauernd[] = $karte;
        } else {
            $aktiv_saison[] = $karte;
        }
    }
    $karten_aktiv = array_merge($aktiv_saison, $aktiv_dauernd);
    ?>

    <p class="home-zone-title"><i class="bi bi-lightning-charge-fill"></i>Jetzt aktuell</p>
    <div class="home-grid">
        <?php foreach ($karten_aktiv as $karte): ?>
            <div class="home-card">
                <a href="<?php echo htmlspecialchars($karte['link']); ?>">
                    <div class="home-card-icon <?php echo htmlspecialchars($karte['iconClass']); ?>"><i class="bi <?php echo htmlspecialchars($karte['icon']); ?>"></i></div>
                    <div class="home-card-text">
                        <p class="home-card-title"><?php echo htmlspecialchars($karte['titel']); ?></p>
                        <p class="home-card-desc"><?php echo htmlspecialchars($karte['desc']); ?></p>
                        <?php if (!empty($karte['hinweis'])): ?>
                            <p class="home-card-note"><?php echo htmlspecialchars($karte['hinweis']); ?></p>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-chevron-right home-card-arrow"></i>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($karten_weitere): ?>
        <details class="home-more">
            <summary>
                <i class="bi bi-chevron-right home-more-caret"></i>
                Weitere Bereiche (<?php echo count($karten_weitere); ?>)
            </summary>
            <div class="home-more-list">
                <?php foreach ($karten_weitere as $karte): ?>
                    <div class="home-more-item">
                        <a href="<?php echo htmlspecialchars($karte['link']); ?>">
                            <i class="bi <?php echo htmlspecialchars($karte['icon']); ?> home-more-icon"></i>
                            <span class="home-more-name"><?php echo htmlspecialchars($karte['titel']); ?></span>
                            <?php if (!empty($karte['status'])): ?>
                                <span class="home-more-status"><?php echo htmlspecialchars($karte['status']); ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

</div>

<?php include 'footer.inc.php'; ?>
