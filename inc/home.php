<?php
include 'dbconnect.inc.php';
include 'header.inc.php';
?>

<style>
/* Home-Page spezifische Styles */
.hero-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 1rem;
    padding: 1.5rem; /* Weiter reduziert */
    margin-bottom: 1.5rem; /* Weiter reduziert */
    text-align: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(59, 89, 152, 0.1) 0%, transparent 70%);
    animation: float 20s infinite ease-in-out;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-logo {
    width: 60px; /* Weiter reduziert */
    height: 60px; /* Weiter reduziert */
    border-radius: 50%;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    margin-bottom: 1rem; /* Weiter reduziert */
    transition: transform 0.3s ease;
    border: 3px solid #ffffff;
}

.hero-logo:hover {
    transform: scale(1.05);
}

.hero-title {
    font-size: 1.5rem; /* Weiter reduziert */
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0.3rem; /* Weiter reduziert */
    background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 0.85rem; /* Weiter reduziert */
    color: #718096;
    margin-bottom: 0;
    font-weight: 400;
}

.quick-access-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.2rem;
    margin-bottom: 1.5rem;
}

.quick-access-card {
    background: #ffffff;
    border-radius: 1rem;
    padding: 0; /* Padding entfernt für Link */
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid #f7fafc;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.quick-access-card-link {
    display: block;
    padding: 1.5rem;
    text-decoration: none;
    color: inherit;
    height: 100%;
}

.quick-access-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3b5998, #0ea5e9);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.quick-access-card:hover::before {
    transform: scaleX(1);
}

.quick-access-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}

.quick-access-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, #e6f2ff, #cce7ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: #3b5998;
    transition: all 0.3s ease;
}

.quick-access-card:hover .quick-access-icon {
    background: linear-gradient(135deg, #3b5998, #0ea5e9);
    color: #ffffff;
    transform: scale(1.1);
}

.quick-access-title {
    font-size: 1rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.quick-access-description {
    color: #718096;
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 1rem;
}

.quick-access-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #3b5998;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.quick-access-link:hover {
    color: #0ea5e9;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: #ffffff;
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border: 1px solid #f7fafc;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: #3b5998;
    display: block;
}

.stat-label {
    color: #718096;
    font-size: 0.85rem;
    margin-top: 0.3rem;
}

.info-section {
    background: #ffffff;
    border-radius: 0.75rem;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border: 1px solid #f7fafc;
}

.info-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-content {
    color: #4a5568;
    line-height: 1.5;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .hero-section {
        padding: 2rem 1.5rem; /* Angepasst */
    }
    
    .hero-title {
        font-size: 1.5rem; /* Angepasst von 2rem */
    }
    
    .hero-subtitle {
        font-size: 0.9rem; /* Angepasst von 1rem */
    }
    
    .quick-access-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}
</style>

<div class="main-content-wrapper">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <img src="jmrang/dat/MSVWilen_Logo.jpg" alt="MSV Wilen Logo" class="hero-logo">
            <h1 class="hero-title">MSV Wilen</h1>
            <p class="hero-subtitle">Militärschützenverein Wilen - Resultaterfassung & Verwaltung</p>
        </div>
    </div>

    <!-- Kombinierte Info Section -->
    <div class="info-section">
        <div class="row align-items-center mb-3">
            <div class="col-md-8">
                <h3 class="info-title mb-0">
                    <i class="bi bi-person-circle me-2" style="color: #3b5998;"></i>
                    Willkommen, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Benutzer'); ?>!
                </h3>
            </div>
            <div class="col-md-4 text-end">
                <div class="stat-card">
                    <span class="stat-number"><?php echo date('Y'); ?></span>
                    <div class="stat-label">Aktuelles Jahr</div>
                </div>
            </div>
        </div>
        
        <div class="info-content">
            <?php 
            // Login-Zeit Info
            $login_time = $_SESSION['session_created'] ?? $_SESSION['last_activity'] ?? time();
            $login_datetime = date('d.m.Y H:i', $login_time);
            ?>
            
            <p class="mb-3"><i class="bi bi-clock-fill text-success me-2"></i><strong>Letzter Login:</strong> <?php echo $login_datetime; ?> Uhr</p>
            
            <hr class="my-3">
            
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Ausstehende Resultate</h5>
            
            <?php
            // Aktuelles Jahr und Datum
            $current_year = date('Y');
            $today = date('Y-m-d');
            
            // Query für vergangene Schiessanlässe ohne Resultate
            // Extrahiert das letzte Datum aus den Schiesstagen und prüft ob es vergangen ist
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
            
            if ($pending_result && $pending_result->num_rows > 0) {
                echo '<div class="list-group">';
                while ($row = $pending_result->fetch_assoc()) {
                    $dates_preview = '';
                    if (!empty($row['Schiesstage'])) {
                        // Extrahiere nur die Datumsangaben (vereinfacht)
                        $lines = explode("\n", $row['Schiesstage']);
                        $first_line = isset($lines[0]) ? trim($lines[0]) : '';
                        if (preg_match('/(\d+\.\s+\w+)/', $first_line, $matches)) {
                            $dates_preview = ' - ' . $matches[1];
                        }
                    }
                    
                    echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                    echo '<div>';
                    echo '<i class="bi bi-circle text-danger me-2"></i>';
                    echo '<strong>' . htmlspecialchars($row['Bezeichnung']) . '</strong>';
                    echo '<small class="text-muted">' . $dates_preview . '</small>';
                    echo '</div>';
                    echo '<span class="badge bg-warning text-dark">Ausstehend</span>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<p class="text-muted"><i class="bi bi-check-circle text-success me-2"></i>Keine ausstehenden Resultate</p>';
            }
            ?>
        </div>
    </div>

    <!-- Quick Access Grid -->
    <div class="quick-access-grid">
        <!-- Munitionskauf als erste Kachel -->
        <div class="quick-access-card">
            <a href="munitionskauf.php" class="quick-access-card-link">
                <div class="quick-access-icon" style="background: linear-gradient(135deg, #ffe6e6, #ffcccc); color: #dc3545;">
                    <i class="bi bi-cart-check"></i>
                </div>
                <h3 class="quick-access-title">Munitionverkauf</h3>
                <p class="quick-access-description">
                    Munitionskäufe erfassen
                </p>
                <div class="quick-access-link" style="color: #dc3545;">
                    Zur Erfassung <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        <div class="quick-access-card">
            <a href="endschloesen.php" class="quick-access-card-link">
                <div class="quick-access-icon" style="background: linear-gradient(135deg, #ffe6e6, #ffcccc); color: #dc3545;">
                    <i class="bi bi-cart-check"></i>
                </div>
                <h3 class="quick-access-title">Endschiessen Stichausgabe</h3>
                <p class="quick-access-description">
                    
                </p>
                <div class="quick-access-link" style="color: #dc3545;">
                    Zur Erfassung <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>
        <div class="quick-access-card">
            <a href="jmresultate.php" class="quick-access-card-link">
                <div class="quick-access-icon">
                    <i class="bi bi-trophy"></i>
                </div>
                <h3 class="quick-access-title">Jahresmeisterschaft</h3>
                <p class="quick-access-description">
                </p>
                <div class="quick-access-link">
                    Zur Erfassung<i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        <div class="quick-access-card">
            <a href="heimresultate.php" class="quick-access-card-link">
                <div class="quick-access-icon">
                    <i class="bi bi-house"></i>
                </div>
                <h3 class="quick-access-title">Heimmeisterschaft</h3>
                <p class="quick-access-description">
                </p>
                <div class="quick-access-link">
                    Zur Erfassung <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        <div class="quick-access-card">
            <a href="kantiresultate.php" class="quick-access-card-link">
                <div class="quick-access-icon">
                    <i class="bi bi-geo-alt"></i>
                </div>
                <h3 class="quick-access-title">Kantonalstich</h3>
                <p class="quick-access-description">
                </p>
                <div class="quick-access-link">
                    Zur Erfassung <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        <div class="quick-access-card">
            <a href="endresultate.php" class="quick-access-card-link">
                <div class="quick-access-icon">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <h3 class="quick-access-title">Endschiessen</h3>
                <p class="quick-access-description">
                </p>
                <div class="quick-access-link">
                    Zur Erfassung <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        <div class="quick-access-card">
            <a href="cup2.php" class="quick-access-card-link">
                <div class="quick-access-icon">
                    <i class="bi bi-journals"></i>
                </div>
                <h3 class="quick-access-title">CUP</h3>
                <p class="quick-access-description">
                </p>
                <div class="quick-access-link">
                    Zur CUP Resultateerfassung<i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        <div class="quick-access-card">
            <a href="mitgliederverwaltung.php" class="quick-access-card-link">
                <div class="quick-access-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h3 class="quick-access-title">Mitgliederverwaltung</h3>
                <p class="quick-access-description">
                </p>
                <div class="quick-access-link">
                    Zur Mitgliederverwaltung <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>
    </div>
</div>

<?php include 'footer.inc.php'; ?>
