<?php
include 'dbconnect.inc.php';
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

/* Ausstehende Resultate – kompakter Bereich */
.home-pending {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1.25rem;
}

.home-pending-ok {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.home-pending h6 {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #92400e;
}

.home-pending-ok h6 {
    color: #166534;
}

.home-pending .pending-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.35rem 0;
    font-size: 0.82rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.home-pending .pending-item:last-child {
    border-bottom: none;
}

/* Quick Access Grid – kompakt */
.home-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.75rem;
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

.home-card-desc {
    font-size: 0.75rem;
    color: #94a3b8;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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

/* Mobile */
@media (max-width: 767.98px) {
    .home-grid {
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

/* Tablet: 2 Spalten */
@media (min-width: 768px) and (max-width: 991.98px) {
    .home-grid {
        grid-template-columns: repeat(2, 1fr);
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

    <!-- Ausstehende Resultate -->
    <?php
    $current_year = date('Y');
    $today = date('Y-m-d');
    
    $pending_query = "
        SELECT
            jd.ID,
            jd.Bezeichnung,
            jd.Schiesstage,
            jd.Reihenfolge,
            CASE
                WHEN jd.Schiesstage LIKE '%Dezember%' THEN CONCAT(jd.year, '-12-31')
                WHEN jd.Schiesstage LIKE '%November%' THEN CONCAT(jd.year, '-11-30')
                WHEN jd.Schiesstage LIKE '%Oktober%' THEN CONCAT(jd.year, '-10-31')
                WHEN jd.Schiesstage LIKE '%September%' THEN CONCAT(jd.year, '-09-30')
                WHEN jd.Schiesstage LIKE '%August%' THEN CONCAT(jd.year, '-08-31')
                WHEN jd.Schiesstage LIKE '%Juli%' THEN CONCAT(jd.year, '-07-31')
                WHEN jd.Schiesstage LIKE '%Juni%' THEN CONCAT(jd.year, '-06-30')
                WHEN jd.Schiesstage LIKE '%Mai%' THEN CONCAT(jd.year, '-05-31')
                WHEN jd.Schiesstage LIKE '%April%' THEN CONCAT(jd.year, '-04-30')
                WHEN jd.Schiesstage LIKE '%März%' THEN CONCAT(jd.year, '-03-31')
                WHEN jd.Schiesstage LIKE '%Februar%' THEN CONCAT(jd.year, '-02-28')
                WHEN jd.Schiesstage LIKE '%Januar%' THEN CONCAT(jd.year, '-01-31')
                ELSE CONCAT(jd.year, '-12-31')
            END as approx_date,
            (SELECT COUNT(*) FROM jmresultate jr WHERE jr.jmdefinitionID = jd.ID) as result_count
        FROM JMDefinition jd
        WHERE
            jd.year = ?
            AND jd.hidden = 0
            AND jd.Info = 0
            AND jd.Erweitert = 0
            AND jd.Maxpunkte > 0
            AND LENGTH(jd.Schiesstage) > 0
        HAVING
            approx_date < ?
            AND result_count = 0
        ORDER BY jd.Reihenfolge ASC
        LIMIT 10
    ";

    $pending_stmt = $conn->prepare($pending_query);
    $pending_stmt->bind_param("ss", $current_year, $today);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    $has_pending = $pending_result && $pending_result->num_rows > 0;
    ?>
    
    <div class="home-pending <?php echo $has_pending ? '' : 'home-pending-ok'; ?>">
        <?php if ($has_pending): ?>
            <h6><i class="bi bi-exclamation-triangle me-1"></i>Ausstehende Resultate</h6>
            <?php while ($row = $pending_result->fetch_assoc()): ?>
                <div class="pending-item">
                    <span><i class="bi bi-circle-fill text-danger me-1" style="font-size:0.5rem;vertical-align:middle;"></i><?php echo htmlspecialchars($row['Bezeichnung']); ?></span>
                    <span class="badge bg-warning text-dark" style="font-size:0.7rem;">Ausstehend</span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <h6><i class="bi bi-check-circle me-1"></i>Alle Resultate erfasst</h6>
        <?php endif; ?>
    </div>

    <!-- Quick Access -->
    <div class="home-grid">
        <div class="home-card">
            <a href="munitionskauf.php">
                <div class="home-card-icon red"><i class="bi bi-cart-check"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">Munitionverkauf</p>
                    <p class="home-card-desc">Munitionskäufe erfassen</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>

        <div class="home-card">
            <a href="endschloesen.php">
                <div class="home-card-icon red"><i class="bi bi-bullseye"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">Endschiessen Stichausgabe</p>
                    <p class="home-card-desc">Stiche ausgeben</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>

        <div class="home-card">
            <a href="jmresultate.php">
                <div class="home-card-icon"><i class="bi bi-trophy"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">Jahresmeisterschaft</p>
                    <p class="home-card-desc">Resultate erfassen</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>

        <div class="home-card">
            <a href="heimresultate.php">
                <div class="home-card-icon"><i class="bi bi-house"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">Heimmeisterschaft</p>
                    <p class="home-card-desc">Resultate erfassen</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>

        <div class="home-card">
            <a href="kantiresultate.php">
                <div class="home-card-icon"><i class="bi bi-geo-alt"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">Kantonalstich</p>
                    <p class="home-card-desc">Resultate erfassen</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>

        <div class="home-card">
            <a href="endresultate.php">
                <div class="home-card-icon"><i class="bi bi-calendar-event"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">Endschiessen</p>
                    <p class="home-card-desc">Resultate erfassen</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>

        <div class="home-card">
            <a href="cup2.php">
                <div class="home-card-icon"><i class="bi bi-journals"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">CUP</p>
                    <p class="home-card-desc">CUP Resultate erfassen</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>

        <div class="home-card">
            <a href="mitgliederverwaltung.php">
                <div class="home-card-icon"><i class="bi bi-people"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">Mitgliederverwaltung</p>
                    <p class="home-card-desc">Mitglieder verwalten</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>

        <div class="home-card">
            <a href="../portal/dashboard.php">
                <div class="home-card-icon" style="background:#f0fdf4; color:#16a34a;"><i class="bi bi-box-arrow-up-right"></i></div>
                <div class="home-card-text">
                    <p class="home-card-title">Mitgliederportal</p>
                    <p class="home-card-desc">Portal-Ansicht öffnen</p>
                </div>
                <i class="bi bi-chevron-right home-card-arrow"></i>
            </a>
        </div>
    </div>

</div>

<?php include 'footer.inc.php'; ?>
