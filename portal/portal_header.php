<?php
// portal_header.php - Layout fuer das Mitgliederportal
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

// requireLogin() stellt die Session bei Bedarf aus dem Remember-Cookie wieder her (iOS PWA)
requireLogin();

// CSRF-Token sicherstellen
ensureCsrfToken();

// Chat-Zugriff + Ungelesen-Zähler (best-effort, bricht nie die Seite).
// Zugriff: Jungschützen + Jungschützenleiter immer; Mitglieder nur mit aktivierter
// „Jungschützen betreuen"-Einstellung.
$chatUnread = 0;
$chatAccess = false;
try {
    require_once __DIR__ . '/../inc/chat.inc.php';
    $__cdb = getDB();
    $__cuid = (int) ($_SESSION['user_id'] ?? 0);
    if (isJungschuetze()) {
        $chatAccess = true;
    } else {
        try {
            $__cs = $__cdb->prepare('SELECT jsk_betreuung FROM benachrichtigung_prefs WHERE user_id = ?');
            $__cs->execute([$__cuid]);
            $chatAccess = ((int) $__cs->fetchColumn() === 1);
        } catch (Throwable $e) { /* prefs evtl. n/a */ }
        if (!$chatAccess) { try { $chatAccess = isJskLeiter($__cdb, $__cuid); } catch (Throwable $e) {} }
    }
    // Ungelesen-Zähler getrennt absichern: ein Fehler hier darf den Chat-Zugriff NICHT aufheben
    if ($chatAccess) { try { $chatUnread = chatUnreadCount($__cdb, $__cuid); } catch (Throwable $e) { $chatUnread = 0; } }
} catch (Throwable $e) { $chatUnread = 0; }

// Jungschuetzen haben einen eingeschraenkten Portal-Zugang: nur ihre eigenen Seiten.
// Member-Seiten (mit mitglied_id-Bezug) wuerden sonst brechen -> zentrale Weiche.
if (isJungschuetze()) {
    $jsAllowed = ['jsk_dashboard.php', 'jsk_termin.php', 'jsk_termine.php', 'jsk_dokumente.php', 'jsk_profil.php', 'chat.php', 'benachrichtigungen.php', 'changelog.php', 'check_session.php'];
    $curScript = basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
    if (!in_array($curScript, $jsAllowed, true)) {
        header('Location: jsk_dashboard.php');
        exit;
    }
}

$portal_user_name = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Mitglied';
$portal_user_role = $_SESSION['user_role'] ?? 'mitglied';
$portal_page_title = $portal_page_title ?? 'Mitgliederportal';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#3b5998">
    <title><?php echo htmlspecialchars($portal_page_title); ?> - MSV Wilen</title>

    <!-- iOS PWA: Remember-Token in localStorage sichern (persistiert nach App-Neustart) -->
    <?php if (!empty($_COOKIE['msv_remember'])): ?>
    <script>try{localStorage.setItem('msv_rt',<?php echo json_encode($_COOKIE['msv_remember']); ?>);}catch(e){}</script>
    <?php endif; ?>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../icons/icon-16x16.png">

    <!-- PWA -->
    <link rel="manifest" href="../manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="../icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MSV Wilen">

    <!-- PWA: 'beforeinstallprompt' so frueh wie moeglich abfangen (feuert oft vor dem
         Install-Hinweis weiter unten). Das Event wird geparkt -> inc_pwa_install.php nutzt es. -->
    <script>
    window.addEventListener('beforeinstallprompt', function(e){
        e.preventDefault();
        window.__msvPWAprompt = e;
        try { window.dispatchEvent(new Event('msv-pwa-available')); } catch(_){}
    });
    </script>

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Portal Design-System (Tokens + Komponenten) -->
    <link rel="stylesheet" href="../css/portal.css">

    <!-- jQuery + Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 + Toast -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../inc/js/msv-toast.js"></script>
    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        /* Design-Tokens (:root) + Karten/Header/Felder: siehe css/portal.css */

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: var(--nav-height);
            min-height: 100vh;       /* Fallback fuer aeltere Browser */
            min-height: 100dvh;      /* iOS/Safari: sichtbare Hoehe ohne Browserleiste -> Footer nicht unter der Falz */
            display: flex;
            flex-direction: column;
        }

        /* Portal Navbar */
        .portal-navbar {
            background: white;
            border-bottom: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            height: var(--nav-height);
        }

        .portal-navbar .navbar-brand {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .portal-navbar .navbar-brand i {
            color: var(--primary-color);
        }

        .portal-navbar .nav-link {
            font-weight: 500;
            color: #495057;
            padding: 0.3rem 0.55rem !important;
            border-radius: 6px;
            transition: all var(--transition-speed) ease;
            font-size: 0.82rem;
            white-space: nowrap;
            position: relative;
        }

        .portal-navbar .nav-link i {
            font-size: 0.9rem;
        }

        .portal-navbar .nav-link:hover {
            background: rgba(59, 89, 152, 0.08);
            color: var(--primary-color);
        }

        .portal-navbar .nav-link.active {
            background: rgba(59, 89, 152, 0.1);
            color: var(--primary-color);
            font-weight: 600;
        }

        .portal-navbar .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 15%;
            right: 15%;
            height: 2px;
            background: var(--primary-color);
            border-radius: 1px;
        }

        .portal-user-badge {
            background: linear-gradient(135deg, var(--primary-color), #2d4373);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* .portal-content, .portal-card(-body), .portal-page-header: siehe css/portal.css */

        /* PWA Standalone: Nur Seitentitel ausblenden (Navbar zeigt ihn bereits), Jahresauswahl bleibt */
        @media (display-mode: standalone) {
            .portal-page-header > div:first-child {
                display: none !important;
            }
            .portal-page-header {
                justify-content: flex-end !important;
                margin-bottom: 0.75rem;
            }
        }

        /* Responsive (Content-/Header-Abstaende: siehe css/portal.css) */
        @media (max-width: 767.98px) {
            .d-mobile-none {
                display: none !important;
            }
        }

        /* Back Link - Desktop: Textlink */
        .portal-back-link {
            display: inline-flex;
            align-items: center;
            color: var(--secondary-color);
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 1rem;
            transition: color 0.2s;
        }
        .portal-back-link:hover {
            color: var(--primary-color);
        }
        .portal-back-link .back-label { display: inline; }
        .portal-back-fab { display: none; }

        /* Back Link - Mobile: Floating Action Button */
        @media (max-width: 767.98px) {
            .portal-back-link { display: none; }
            .portal-back-fab {
                display: flex;
                position: fixed;
                bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px));
                right: 1.25rem;
                width: 48px;
                height: 48px;
                border-radius: 50%;
                background: var(--primary-color);
                color: white;
                align-items: center;
                justify-content: center;
                font-size: 1.2rem;
                box-shadow: 0 4px 12px rgba(59,89,152,0.4);
                text-decoration: none;
                z-index: 1040;
                transition: all 0.2s;
            }
            .portal-back-fab:hover,
            .portal-back-fab:active {
                background: #2d4373;
                color: white;
                transform: scale(1.08);
            }
        }

        /* === MOBILE OFF-CANVAS MENU === */
        @media (max-width: 991.98px) {
            /* Hide default Bootstrap collapse on mobile */
            .navbar-collapse {
                display: none !important;
            }

            /* Hamburger Button - Touch-friendly */
            .navbar-toggler {
                min-width: 48px;
                min-height: 48px;
                padding: 12px;
                border: none;
                font-size: 24px;
            }
            .navbar-toggler:focus {
                box-shadow: none;
            }

            /* Off-Canvas Container */
            .offcanvas-nav {
                position: fixed;
                top: 0;
                left: -280px;
                width: 280px;
                height: 100vh;       /* Fallback fuer aeltere Browser */
                height: 100dvh;      /* iOS Safari: sichtbare Hoehe OHNE untere Adressleiste -> letzte Eintraege/Abmelden bleiben sichtbar */
                background: white;
                z-index: 9999;
                transition: left 0.3s ease;
                overflow-y: auto;
                box-shadow: 4px 0 12px rgba(0,0,0,0.15);
                display: flex;
                flex-direction: column;
            }
            .offcanvas-nav.show {
                left: 0;
            }

            /* Overlay */
            .offcanvas-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 9998;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease, visibility 0.3s ease;
            }
            .offcanvas-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            /* Off-Canvas Header */
            .offcanvas-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.65rem 1rem;
                border-bottom: 2px solid #e9ecef;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            }
            .offcanvas-title {
                font-size: 15px;
                font-weight: 600;
                color: var(--primary-color);
                margin: 0;
            }
            .offcanvas-close {
                min-width: 40px;
                min-height: 40px;
                background: transparent;
                border: none;
                font-size: 24px;
                color: #6c757d;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                transition: all 0.2s;
            }
            .offcanvas-close:active {
                background: #e9ecef;
                transform: scale(0.95);
            }

            /* Off-Canvas Body */
            .offcanvas-body {
                padding: 0;
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            /* Mobile Menu Items */
            .mobile-nav-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            .mobile-nav-item {
                border-bottom: 1px solid #f0f0f0;
            }
            .mobile-nav-link {
                display: flex;
                align-items: center;
                padding: 10px 16px;
                color: #212529;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                min-height: 40px;
                transition: all 0.2s;
            }
            .mobile-nav-link:active {
                background: #f8f9fa;
            }
            .mobile-nav-link.active {
                background: rgba(59, 89, 152, 0.1);
                color: var(--primary-color);
                font-weight: 600;
                border-left: 4px solid var(--primary-color);
                padding-left: 12px;
            }
            .mobile-nav-link i {
                width: 20px;
                margin-right: 10px;
                font-size: 15px;
                text-align: center;
            }

            /* User Section */
            .mobile-user-section {
                margin-top: auto;
                border-top: 2px solid #dee2e6;
                padding: 0.65rem;
                /* iPhone: Inhalt ueber den Home-Indicator anheben (sonst kaum sichtbar) */
                padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
                background: #f8f9fa;
            }
            .mobile-user-header {
                display: flex;
                align-items: center;
                padding: 8px 10px;
                background: white;
                border-radius: 8px;
                margin-bottom: 0.5rem;
            }
            .mobile-user-icon {
                width: 32px;
                height: 32px;
                background: linear-gradient(135deg, var(--primary-color), #2d4373);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 14px;
                margin-right: 10px;
                flex-shrink: 0;
            }
            .mobile-user-info {
                overflow: hidden;
            }
            .mobile-user-name {
                font-weight: 600;
                color: #212529;
                font-size: 14px;
            }
            .mobile-user-role {
                font-size: 12px;
                color: #6c757d;
            }
        }

        /* Desktop: Off-Canvas Elemente komplett ausblenden */
        @media (min-width: 992px) {
            .offcanvas-nav,
            .offcanvas-overlay {
                display: none !important;
            }

            /* Desktop Dropdown Navigation — nur Nav-Dropdowns per Hover, User-Dropdown bleibt click */
            .portal-navbar .navbar-nav.me-auto .nav-item.dropdown:hover > .dropdown-menu {
                display: block;
                margin-top: 0;
            }
            .portal-navbar .dropdown-menu {
                border: 1px solid #e9ecef;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                border-radius: 8px;
                padding: 0.35rem 0;
                min-width: 210px;
            }
            .portal-navbar .dropdown-item {
                padding: 0.45rem 1rem;
                font-size: 0.85rem;
                font-weight: 500;
                color: #495057;
            }
            .portal-navbar .dropdown-item:hover {
                background: rgba(59, 89, 152, 0.08);
                color: var(--primary-color);
            }
            .portal-navbar .dropdown-item.active {
                background: rgba(59, 89, 152, 0.1);
                color: var(--primary-color);
                font-weight: 600;
            }
            .portal-navbar .dropdown-item i {
                color: #6c757d;
                width: 20px;
                display: inline-block;
            }
            .portal-navbar .dropdown-item.active i,
            .portal-navbar .dropdown-item:hover i {
                color: var(--primary-color);
            }
            .portal-navbar .nav-link.dropdown-toggle::after {
                font-size: 0.6em;
                vertical-align: 0.15em;
            }
        }

        /* Seitenspezifische Styles koennen via $portal_page_css eingebunden werden */
        <?php echo $portal_page_css ?? ''; ?>
    </style>
</head>
<body class="<?php echo htmlspecialchars($portal_body_class ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $current_page = basename($_SERVER['PHP_SELF']);

    if (isJungschuetze()) {
        // Eingeschraenkte Navigation fuer Jungschuetzen
        $nav_groups = [
            ['type' => 'link', 'link' => 'jsk_dashboard.php', 'text' => 'Übersicht', 'icon' => 'bi-house'],
            ['type' => 'link', 'link' => 'chat.php', 'text' => 'Jungschützenchat', 'icon' => 'bi-chat-dots'],
            ['type' => 'link', 'link' => 'jsk_termin.php', 'text' => 'Schiessanfrage', 'icon' => 'bi-calendar-plus'],
            ['type' => 'link', 'link' => 'jsk_termine.php', 'text' => 'Termine', 'icon' => 'bi-calendar3'],
            ['type' => 'link', 'link' => 'jsk_dokumente.php', 'text' => 'Dokumente', 'icon' => 'bi-mortarboard'],
            ['type' => 'link', 'link' => 'jsk_profil.php', 'text' => 'Meine Daten', 'icon' => 'bi-person-vcard'],
        ];
    } else {
        // Desktop: Gruppierte Navigation (Dropdowns)
        $nav_groups = [
            ['type' => 'link', 'link' => 'dashboard.php', 'text' => 'Dashboard', 'icon' => 'bi-house'],
            ['type' => 'link', 'link' => 'termine.php', 'text' => 'Termine', 'icon' => 'bi-calendar3'],
            ['type' => 'dropdown', 'text' => 'Resultate', 'icon' => 'bi-trophy', 'items' => [
                ['link' => 'meine_jm.php', 'text' => 'Jahresmeisterschaft', 'icon' => 'bi-bullseye'],
                ['link' => 'meine_heim.php', 'text' => 'Heimmeisterschaft', 'icon' => 'bi-house-heart'],
                ['link' => 'meine_kanti.php', 'text' => 'Kantonalstich', 'icon' => 'bi-geo-alt'],
            ]],
            ['type' => 'dropdown', 'text' => 'Persönliches', 'icon' => 'bi-person', 'items' => [
                ['link' => 'mein_fragebogen.php', 'text' => 'Umfragen', 'icon' => 'bi-clipboard-check'],
                ['link' => 'meine_wanderpreise.php', 'text' => 'Wanderpreise', 'icon' => 'bi-award'],
                ['link' => 'meine_einsaetze.php', 'text' => 'Meine Einsätze', 'icon' => 'bi-person-badge'],
                ['link' => 'kalender_abo.php', 'text' => 'Kalender-Abo', 'icon' => 'bi-calendar-plus'],
            ]],
        ];

        // Jungschützenchat-Link nur, wenn der User Zugriff hat (Betreuer/Leiter)
        if ($chatAccess) {
            array_splice($nav_groups, 1, 0, [
                ['type' => 'link', 'link' => 'chat.php', 'text' => 'Jungschützenchat', 'icon' => 'bi-chat-dots'],
            ]);
        }

        // Vorstand/Admin/Mitglied: Dokumente-Dropdown
        // (JSK-Dokumente werden NICHT über die PWA verwaltet -> kein Portal-Menüeintrag)
        if (isVorstand() || isMitglied()) {
            $dokItems = [
                ['link' => 'einsatzplaene.php', 'text' => 'Einsatzpläne', 'icon' => 'bi-calendar-check'],
                ['link' => 'protokolle.php', 'text' => 'Protokolle', 'icon' => 'bi-file-text'],
            ];
            $nav_groups[] = ['type' => 'dropdown', 'text' => 'Dokumente', 'icon' => 'bi-folder', 'items' => $dokItems];
        }

        // Jungschuetzen-Betreuung: Board nur fuer aktivierte Betreuer + global aktive Funktion
        if (jskFeatureAktiv()) {
            $__jsBetreuer = false;
            try {
                $__st = getDB()->prepare('SELECT jsk_betreuung FROM benachrichtigung_prefs WHERE user_id = ?');
                $__st->execute([(int) ($_SESSION['user_id'] ?? 0)]);
                $__jsBetreuer = ((int) $__st->fetchColumn() === 1);
            } catch (Throwable $e) { $__jsBetreuer = false; }
            if ($__jsBetreuer) {
                $nav_groups[] = ['type' => 'link', 'link' => 'jsk_betreuung.php', 'text' => 'Jungschützen', 'icon' => 'bi-people'];
            }
        }

        // "Meine Daten" wandert ins Benutzer-Dropdown (siehe unten) — kein Top-Level-Link mehr.
    }

    // Mobile: Flache Liste (für Off-Canvas)
    $nav_items = [];
    foreach ($nav_groups as $group) {
        if ($group['type'] === 'link') {
            $nav_items[] = $group;
        } else {
            foreach ($group['items'] as $item) {
                $nav_items[] = $item;
            }
        }
    }
    ?>

    <!-- Portal Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top portal-navbar">
        <div class="container-fluid" style="max-width: 1200px;">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../icons/icon-32x32.png" alt="MSV" width="22" height="22" style="border-radius:4px; vertical-align:-3px;"> MSV Wilen
            </a>

            <button class="navbar-toggler border-0" type="button" id="portalMenuToggler"
                    aria-label="Menü öffnen" aria-controls="portalOffcanvas" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="portalNav">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($nav_groups as $group): ?>
                        <?php if ($group['type'] === 'link'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == $group['link'] ? 'active' : ''; ?>" href="<?php echo $group['link']; ?>">
                                <i class="bi <?php echo $group['icon']; ?> me-1"></i><?php echo $group['text']; ?>
                            </a>
                        </li>
                        <?php else:
                            $dropdown_active = false;
                            foreach ($group['items'] as $item) {
                                if ($current_page == $item['link']) { $dropdown_active = true; break; }
                            }
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo $dropdown_active ? 'active' : ''; ?>" href="#" data-bs-toggle="dropdown">
                                <i class="bi <?php echo $group['icon']; ?> me-1"></i><?php echo $group['text']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php foreach ($group['items'] as $item): ?>
                                <li>
                                    <a class="dropdown-item <?php echo $current_page == $item['link'] ? 'active' : ''; ?>" href="<?php echo $item['link']; ?>">
                                        <i class="bi <?php echo $item['icon']; ?> me-2"></i><?php echo $item['text']; ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <span class="portal-user-badge">
                                <i class="bi bi-person-fill me-1"></i>
                                <?php echo htmlspecialchars($portal_user_name); ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small"><?php echo ucfirst($portal_user_role); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (isVorstand()): ?>
                            <li>
                                <a class="dropdown-item" href="../inc/home.php">
                                    <i class="bi bi-gear me-2 text-muted"></i>Admin-Bereich
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <?php if (!isJungschuetze()): ?>
                            <li>
                                <a class="dropdown-item <?php echo $current_page == 'meine_daten.php' ? 'active' : ''; ?>" href="meine_daten.php">
                                    <i class="bi bi-person-vcard me-2 text-muted"></i>Meine Daten
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item <?php echo $current_page == 'benachrichtigungen.php' ? 'active' : ''; ?>" href="benachrichtigungen.php">
                                    <i class="bi bi-bell me-2 text-muted"></i>Benachrichtigungen
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="changelog.php">
                                    <i class="bi bi-megaphone me-2 text-muted"></i>Neuigkeiten
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="../inc/user_logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Abmelden
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Mobile Off-Canvas Menu -->
    <div class="offcanvas-overlay" id="portalOverlay"></div>
    <div class="offcanvas-nav" id="portalOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><img src="../icons/icon-32x32.png" alt="MSV" width="20" height="20" style="border-radius:4px; vertical-align:-2px; margin-right:8px;">MSV Wilen Mitgliederportal</h5>
            <button class="offcanvas-close" id="portalMenuClose" aria-label="Schliessen">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div class="offcanvas-body">
            <ul class="mobile-nav-list">
                <?php foreach ($nav_items as $item):
                    $is_active = ($current_page == $item['link']);
                ?>
                <li class="mobile-nav-item">
                    <a class="mobile-nav-link <?php echo $is_active ? 'active' : ''; ?>" href="<?php echo $item['link']; ?>">
                        <i class="bi <?php echo $item['icon']; ?>"></i>
                        <?php echo $item['text']; ?>
                    </a>
                </li>
                <?php endforeach; ?>

                <?php if (isVorstand()): ?>
                <li class="mobile-nav-item">
                    <a class="mobile-nav-link" href="../inc/home.php">
                        <i class="bi bi-gear"></i>
                        Admin-Bereich
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="mobile-user-section">
                <div class="mobile-user-header">
                    <div class="mobile-user-icon"><i class="bi bi-person-fill"></i></div>
                    <div class="mobile-user-info">
                        <div class="mobile-user-name"><?php echo htmlspecialchars($portal_user_name); ?></div>
                        <div class="mobile-user-role"><?php echo ucfirst($portal_user_role); ?></div>
                    </div>
                </div>
                <ul class="mobile-nav-list">
                    <?php if (!isJungschuetze()): ?>
                    <li class="mobile-nav-item">
                        <a class="mobile-nav-link <?php echo $current_page == 'meine_daten.php' ? 'active' : ''; ?>" href="meine_daten.php">
                            <i class="bi bi-person-vcard"></i>
                            Meine Daten
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="mobile-nav-item">
                        <a class="mobile-nav-link <?php echo $current_page == 'benachrichtigungen.php' ? 'active' : ''; ?>" href="benachrichtigungen.php">
                            <i class="bi bi-bell"></i>
                            Benachrichtigungen
                        </a>
                    </li>
                    <li class="mobile-nav-item">
                        <a class="mobile-nav-link" href="changelog.php">
                            <i class="bi bi-megaphone"></i>
                            Neuigkeiten
                        </a>
                    </li>
                    <li class="mobile-nav-item">
                        <a class="mobile-nav-link text-danger" href="../inc/user_logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                            Abmelden
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var offcanvas = document.getElementById('portalOffcanvas');
        var overlay = document.getElementById('portalOverlay');
        var toggler = document.getElementById('portalMenuToggler');
        var closeBtn = document.getElementById('portalMenuClose');

        if (!offcanvas || !overlay || !toggler) return;

        function openMenu() {
            offcanvas.classList.add('show');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
            toggler.setAttribute('aria-expanded', 'true');
            if (closeBtn) closeBtn.focus();
        }

        function closeMenu() {
            offcanvas.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
            toggler.setAttribute('aria-expanded', 'false');
        }

        toggler.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openMenu();
        });

        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);

        // Escape schliesst das Menü, Tab bleibt im Menü gefangen (Fokus-Falle)
        document.addEventListener('keydown', function(e) {
            if (!offcanvas.classList.contains('show')) return;
            if (e.key === 'Escape') {
                closeMenu();
                toggler.focus();
                return;
            }
            if (e.key === 'Tab') {
                var focusable = offcanvas.querySelectorAll('a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])');
                if (!focusable.length) return;
                var first = focusable[0], last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault(); last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            }
        });

        // Close on nav link click
        document.querySelectorAll('#portalOffcanvas .mobile-nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                setTimeout(closeMenu, 150);
            });
        });

        // Swipe left to close
        var touchStartX = 0;
        offcanvas.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
        });
        offcanvas.addEventListener('touchmove', function(e) {
            if (e.touches[0].clientX - touchStartX < -50) {
                closeMenu();
            }
        });
    });
    </script>

    <!-- Portal Content -->
    <div class="portal-content">
    <?php if ($current_page !== 'dashboard.php'): ?>
    <a href="dashboard.php" class="portal-back-link"><i class="bi bi-arrow-left me-1"></i><span class="back-label">Dashboard</span></a>
    <a href="dashboard.php" class="portal-back-fab"><i class="bi bi-house"></i></a>
    <?php endif; ?>

    <?php if (empty($portal_hide_pwa_install)) include __DIR__ . '/inc_pwa_install.php'; /* "Als App installieren"-Hinweis (iOS-Anleitung / Android-Button); zeigt sich nur, wenn noch nicht installiert. Opt-out via $portal_hide_pwa_install (z.B. benachrichtigungen.php mit eigenem Hinweis) */ ?>
