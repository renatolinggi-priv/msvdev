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
        $chatAccess = jskIstBetreuer($__cdb, $__cuid);
        if (!$chatAccess) { try { $chatAccess = isJskLeiter($__cdb, $__cuid); } catch (Throwable $e) {} }
    }
    // Ungelesen-Zähler getrennt absichern: ein Fehler hier darf den Chat-Zugriff NICHT aufheben
    if ($chatAccess) { try { $chatUnread = chatUnreadCount($__cdb, $__cuid); } catch (Throwable $e) { $chatUnread = 0; } }
} catch (Throwable $e) { $chatUnread = 0; }

// Benachrichtigungs-Glocke: Ungelesen-Zähler (best-effort, bricht nie die Seite).
$bnUnread = 0;
try {
    require_once __DIR__ . '/../inc/benachrichtigungen_inbox.inc.php';   // abgelaufene Erinnerungen zählen nicht
    $bnUnread = inboxNeuAnzahl(getDB(), (int) ($_SESSION['user_id'] ?? 0));
} catch (Throwable $e) { $bnUnread = 0; }

// Jungschuetzen haben einen eingeschraenkten Portal-Zugang: nur ihre eigenen Seiten.
// Member-Seiten (mit mitglied_id-Bezug) wuerden sonst brechen -> zentrale Weiche.
if (isJungschuetze()) {
    // Alle jsk_*.php-Seiten sind automatisch erlaubt (neue JSK-Seiten muessen nur noch in die Nav),
    // dazu die geteilten Seiten aus der Allowlist.
    $jsAllowed = ['chat.php', 'benachrichtigungen.php', 'mitteilungen.php', 'changelog.php', 'check_session.php'];
    $curScript = basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
    if (strpos($curScript, 'jsk_') !== 0 && !in_array($curScript, $jsAllowed, true)) {
        header('Location: jsk_dashboard.php');
        exit;
    }
}

$portal_user_name = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Mitglied';
$portal_user_role = $_SESSION['user_role'] ?? 'mitglied';
$portal_rolle_text = ['admin' => 'Admin', 'vorstand' => 'Vorstand', 'mitglied' => 'Mitglied', 'jungschuetze' => 'Jungschütze'][$portal_user_role] ?? ucfirst($portal_user_role);
$portal_page_title = $portal_page_title ?? 'Mitgliederportal';

// Optik «Vereinsfahne» (freigegeben 09.10.2026, für alle): css/portal-fahne.css + portal-fahne-seiten.css,
// alle Regeln unter html.portal-fahne. Zum Vergleich können Admins vorübergehend die bisherige Ansicht
// öffnen: ?vorschau=aus (Cookie 30 Tage), ?vorschau=an wieder zurück. Kein Menüeintrag mehr.
$portal_fahne = true;
if (isAdmin()) {
    $__seite = basename($_SERVER['PHP_SELF']);
    if (isset($_GET['vorschau']) && in_array($_GET['vorschau'], ['an', 'aus'], true)) {
        $__an = ($_GET['vorschau'] === 'an');
        $__q = $_GET;
        unset($__q['vorschau']);
        if (!headers_sent()) {
            setcookie('msv_portal_fahne', $__an ? '' : '0', [
                'expires'  => $__an ? time() - 3600 : time() + 30 * 86400,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            header('Location: ' . $__seite . ($__q ? '?' . http_build_query($__q) : ''));
            exit;
        }
        $_COOKIE['msv_portal_fahne'] = $__an ? '' : '0';
    }
    $portal_fahne = (($_COOKIE['msv_portal_fahne'] ?? '') !== '0');
}
?>
<!DOCTYPE html>
<html lang="de-CH"<?php echo $portal_fahne ? ' class="portal-fahne"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="<?php echo $portal_fahne ? '#ffffff' : '#3b5998'; ?>">
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
    <link rel="manifest" href="../manifest-portal.json">
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

    <!-- Bibliotheken vom eigenen Server, feste Versionen (seit 16.10.2026; vorher drei fremde CDNs: eine Herkunft
         statt vier, keine IP-Adressen an Dritte, kein unangekündigtes Update). Neue Version = neuer Dateiname. -->
    <link rel="stylesheet" href="../css/lib/bootstrap-5.3.0.min.css">
    <link rel="stylesheet" href="../css/lib/bootstrap-icons-1.11.3/bootstrap-icons.css">

    <!-- Portal Design-System (Tokens + Komponenten) -->
    <!-- ?v=filemtime: bricht den Service-Worker-Cache (cache-first auf .css) bei jeder Aenderung auf -->
    <link rel="stylesheet" href="../css/portal.css?v=<?php echo @filemtime(__DIR__ . '/../css/portal.css') ?: '1'; ?>">

    <!-- jQuery + Bootstrap JS -->
    <script src="../inc/js/lib/jquery-3.6.0.min.js"></script>
    <script src="../inc/js/lib/bootstrap-5.3.0.bundle.min.js"></script>
    <!-- SweetAlert2 + Toast -->
    <script src="../inc/js/lib/sweetalert2-11.26.25.all.min.js"></script>
    <script src="../inc/js/msv-toast.js?v=<?php echo @filemtime(__DIR__ . '/../inc/js/msv-toast.js') ?: '1'; ?>"></script>
    <!-- PDF.js lädt erst beim Öffnen eines Dokuments (ladePdfJs() in portal_footer.php) -->

    <link rel="stylesheet" href="../css/portal-kopf.css?v=<?php echo @filemtime(__DIR__ . '/../css/portal-kopf.css') ?: '1'; ?>">
    <?php if (!empty($portal_page_css)): /* seitenspezifische Styles */ ?>
    <style><?php echo $portal_page_css; ?></style>
    <?php endif; ?>
    <?php if ($portal_fahne): ?>
    <link rel="stylesheet" href="../css/portal-fahne.css?v=<?php echo @filemtime(__DIR__ . '/../css/portal-fahne.css') ?: '1'; ?>">
    <link rel="stylesheet" href="../css/portal-fahne-seiten.css?v=<?php echo @filemtime(__DIR__ . '/../css/portal-fahne-seiten.css') ?: '1'; ?>">
    <?php endif; ?>
</head>
<body class="<?php echo htmlspecialchars($portal_body_class ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $current_page = basename($_SERVER['PHP_SELF']);
    // Menüeintrag aktiv? Eigene Seite oder eine der Seiten unter «aktiv_auf» (z.B. Resultate-Reiter)
    $portalNavAktiv = fn(array $e): bool => $current_page === $e['link'] || in_array($current_page, $e['aktiv_auf'] ?? [], true);

    if (isJungschuetze()) {
        // Eingeschraenkte Navigation fuer Jungschuetzen
        $nav_groups = [
            ['type' => 'link', 'link' => 'jsk_dashboard.php', 'text' => 'Übersicht', 'icon' => 'bi-house'],
            ['type' => 'link', 'link' => 'chat.php', 'text' => 'Jungschützenchat', 'icon' => 'bi-chat-dots'],
            ['type' => 'link', 'link' => 'jsk_termin.php', 'text' => 'Schiessanfrage', 'icon' => 'bi-calendar-plus'],
            ['type' => 'link', 'link' => 'jsk_termine.php', 'text' => 'Termine', 'icon' => 'bi-calendar3'],
            ['type' => 'link', 'link' => 'jsk_resultate.php', 'text' => 'Meine Resultate', 'icon' => 'bi-trophy'],
            ['type' => 'link', 'link' => 'jsk_dokumente.php', 'text' => 'JSK-Dokumente', 'icon' => 'bi-mortarboard'],
            ['type' => 'link', 'link' => 'jsk_profil.php', 'text' => 'Meine Daten', 'icon' => 'bi-person-vcard'],
        ];
    } else {
        // Desktop: Gruppierte Navigation (Dropdowns)
        $nav_groups = [
            ['type' => 'link', 'link' => 'dashboard.php', 'text' => 'Dashboard', 'icon' => 'bi-house'],
            ['type' => 'link', 'link' => 'termine.php', 'text' => 'Termine', 'icon' => 'bi-calendar3'],
            ['type' => 'link', 'link' => 'anlaesse.php', 'text' => 'Fotos', 'icon' => 'bi-images'],
            // Ein Eintrag für alle Resultate: JM, Heim, Kanti und Wanderpreise sind Reiter derselben Seite
            // (inc_resultate_reiter.php); «aktiv_auf» markiert den Eintrag auf jedem Reiter.
            ['type' => 'link', 'link' => 'meine_jm.php', 'text' => 'Resultate', 'icon' => 'bi-trophy',
             'aktiv_auf' => ['meine_heim.php', 'meine_kanti.php', 'meine_wanderpreise.php']],
            ['type' => 'dropdown', 'text' => 'Persönliches', 'icon' => 'bi-person', 'items' => [
                ['link' => 'mein_fragebogen.php', 'text' => 'Umfragen', 'icon' => 'bi-clipboard-check'],
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

        // Jungschuetzen-Betreuung: Board fuer aktivierte Betreuer und Jungschuetzenleiter + global aktive Funktion
        if (jskFeatureAktiv()) {
            $__jsBetreuer = jskIstBetreuer(getDB(), (int) ($_SESSION['user_id'] ?? 0));
            if (!$__jsBetreuer) { try { $__jsBetreuer = isJskLeiter(getDB(), (int) ($_SESSION['user_id'] ?? 0)); } catch (Throwable $e) {} }
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
            <a class="navbar-brand" href="<?php echo isJungschuetze() ? 'jsk_dashboard.php' : 'dashboard.php'; ?>">
                <?php if ($portal_fahne): ?>
                <img src="../icons/icon-96x96.png" alt="" width="36" height="36" class="portal-brand-logo"><span>MSV Wilen</span>
                <?php else: ?>
                <img src="../icons/icon-32x32.png" alt="MSV" width="22" height="22" style="border-radius:4px; vertical-align:-3px;"> MSV Wilen
                <?php endif; ?>
            </a>

            <!-- Glocke + Hamburger: rechts gruppiert. Glocke immer sichtbar (neben dem
                 Hamburger auf Mobile, rechts auf Desktop). -->
            <div class="d-flex align-items-center ms-auto order-lg-last">
                <div class="dropdown portal-bell">
                    <button class="btn btn-link nav-link position-relative portal-bell-btn" type="button"
                            id="portalBellBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            data-bs-display="static" aria-expanded="false" aria-label="Mitteilungen">
                        <i class="bi bi-bell"></i>
                        <span class="bn-badge badge rounded-pill bg-danger"<?php echo $bnUnread > 0 ? '' : ' hidden'; ?>><?php echo $bnUnread > 99 ? '99+' : (int) $bnUnread; ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end bn-dropdown" aria-labelledby="portalBellBtn">
                        <div class="bn-dd-head">
                            <span>Mitteilungen</span>
                            <button type="button" class="bn-mark-all" id="bnMarkAll">Alle als gelesen</button>
                        </div>
                        <div class="bn-dd-list" id="bnDropdownList">
                            <div class="bn-dd-empty text-muted">Wird geladen…</div>
                        </div>
                        <a class="bn-dd-foot" href="mitteilungen.php">Alle Mitteilungen</a>
                    </div>
                </div>

                <button class="navbar-toggler border-0 ms-1" type="button" id="portalMenuToggler"
                        aria-label="Menü öffnen" aria-controls="portalOffcanvas" aria-expanded="false">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse" id="portalNav">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($nav_groups as $group): ?>
                        <?php if ($group['type'] === 'link'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $portalNavAktiv($group) ? 'active' : ''; ?>" href="<?php echo $group['link']; ?>"<?php echo $portalNavAktiv($group) ? ' aria-current="page"' : ''; ?>>
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
                            <li><span class="dropdown-item-text text-muted small"><?php echo htmlspecialchars($portal_rolle_text); ?></span></li>
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
                                    <i class="bi bi-sliders me-2 text-muted"></i>Benachrichtigungen
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="changelog.php">
                                    <i class="bi bi-megaphone me-2 text-muted"></i>Neuigkeiten
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="../inc/user_logout.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Abmelden
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Mobile Off-Canvas Menu -->
    <div class="offcanvas-overlay" id="portalOverlay"></div>
    <div class="offcanvas-nav" id="portalOffcanvas" role="dialog" aria-modal="true" aria-labelledby="portalMenuTitel">
        <div class="offcanvas-header">
            <?php if ($portal_fahne): ?>
            <h5 class="offcanvas-title" id="portalMenuTitel"><img src="../icons/icon-96x96.png" alt="" width="32" height="32" class="portal-brand-logo">MSV Wilen</h5>
            <?php else: ?>
            <h5 class="offcanvas-title" id="portalMenuTitel"><img src="../icons/icon-32x32.png" alt="MSV" width="20" height="20" style="border-radius:4px; vertical-align:-2px; margin-right:8px;">MSV Wilen Mitgliederportal</h5>
            <?php endif; ?>
            <button class="offcanvas-close" id="portalMenuClose" aria-label="Schliessen">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div class="offcanvas-body">
            <ul class="mobile-nav-list">
                <?php foreach ($nav_items as $item):
                    $is_active = $portalNavAktiv($item);
                ?>
                <li class="mobile-nav-item">
                    <a class="mobile-nav-link <?php echo $is_active ? 'active' : ''; ?>" href="<?php echo $item['link']; ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
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
                        <div class="mobile-user-role"><?php echo htmlspecialchars($portal_rolle_text); ?></div>
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
                            <i class="bi bi-sliders"></i>
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
                        <form method="post" action="../inc/user_logout.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
                            <button type="submit" class="mobile-nav-link text-danger w-100 text-start" style="border:0;background:none;">
                                <i class="bi bi-box-arrow-right"></i>
                                Abmelden
                            </button>
                        </form>
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
            // Fokus erst, wenn das Menü sichtbar ist (visibility wechselt mit der Klasse .show)
            if (closeBtn) setTimeout(function () { closeBtn.focus(); }, 60);
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

    <!-- Portal Content (Landmarke für Screenreader: VoiceOver/TalkBack springen über «Hauptbereich» hierher) -->
    <main class="portal-content" id="inhalt">
    <?php $__start = isJungschuetze() ? 'jsk_dashboard.php' : 'dashboard.php';
    // Seiten können den Weg zurück selbst setzen (z.B. Galerie → «Fotos»), sonst zur Startseite
    $__zurueckHref = $portal_back_href ?? $__start;
    $__zurueckText = $portal_back_label ?? ($portal_fahne ? 'Start' : 'Dashboard');
    if ($current_page !== $__start): ?>
    <a href="<?php echo htmlspecialchars($__zurueckHref, ENT_QUOTES, 'UTF-8'); ?>" class="portal-back-link"><i class="bi bi-arrow-left me-1"></i><span class="back-label"><?php echo htmlspecialchars($__zurueckText, ENT_QUOTES, 'UTF-8'); ?></span></a>
    <?php /* Touch (iOS/Android): schwebender Knopf unten rechts im Daumenbereich statt des Links oben (CSS: Ende portal-fahne.css) */ ?>
    <a href="<?php echo htmlspecialchars($__zurueckHref, ENT_QUOTES, 'UTF-8'); ?>" class="portal-back-fab" aria-label="Zurück: <?php echo htmlspecialchars($__zurueckText, ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
    <?php endif; ?>

    <?php if ($current_page === 'dashboard.php' && empty($portal_hide_pwa_install) && !$portal_fahne) include __DIR__ . '/inc_pwa_install.php'; /* "Als App installieren"-Hinweis nur auf dem Dashboard (iOS-Anleitung / Android-Button); zeigt sich nur, wenn noch nicht installiert. Opt-out via $portal_hide_pwa_install */ ?>
