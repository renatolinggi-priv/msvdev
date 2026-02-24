<?php
// portal/check_session.php - iOS PWA Session-Wiederherstellung via localStorage
// Wird aufgerufen wenn requireLogin() fehlschlägt (Session abgelaufen, Cookie fehlt)
// Kein requireLogin() hier - Seite ist absichtlich ohne Auth-Guard

require_once __DIR__ . '/../inc/session_config.inc.php';

// Zielseite ermitteln und absichern (kein Path-Traversal, nur .php-Dateien im portal/)
$goto = basename($_GET['goto'] ?? 'dashboard.php');
if (!preg_match('/^[a-zA-Z0-9_-]+\.php$/', $goto)) {
    $goto = 'dashboard.php';
}

// Session bereits aktiv → direkt weiterleiten
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $goto);
    exit;
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MSV Wilen</title>
    <style>
        body {
            margin: 0;
            background: #f5f6fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .loader {
            text-align: center;
            color: #718096;
        }
        .loader img {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .loader p { font-size: 0.9rem; margin: 0; }
    </style>
</head>
<body>
<div class="loader">
    <img src="../icons/icon-32x32.png" alt="MSV">
    <p>Bitte warten…</p>
</div>
<script>
(function () {
    var goto = <?php echo json_encode($goto); ?>;
    var token = null;
    try { token = localStorage.getItem('msv_rt'); } catch (e) {}

    if (!token) {
        // Kein Token → direkt zum Login
        window.location.replace('../login.php');
        return;
    }

    // Session via AJAX wiederherstellen
    fetch('../api/restore_session_ajax.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            window.location.replace(goto);
        } else {
            // Ungültiger Token → aus localStorage entfernen, zum Login
            try { localStorage.removeItem('msv_rt'); } catch (e) {}
            window.location.replace('../login.php');
        }
    })
    .catch(function () {
        // Netzwerkfehler → zum Login
        window.location.replace('../login.php');
    });
})();
</script>
</body>
</html>
