<?php
// benutzerverwaltung.php - Benutzerverwaltung
require_once 'config.php';

$page_specific_css = '<link rel="stylesheet" href="css/benutzerverwaltung.css?v=' . time() . '">';
include 'header.inc.php';

// Zugriffsschutz - nur User mit ID 1 darf zugreifen
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header('Location: /index.php');
    exit();
}

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

// Benutzer löschen
if (isset($_POST['delete_user']) && isset($_POST['user_id']) && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $userId = intval($_POST['user_id']);
        
        // Verhindere Selbstlöschung
        if ($userId != 1) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $message = "Benutzer erfolgreich gelöscht.";
                $messageType = "success";
            } else {
                $message = "Fehler beim Löschen: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        } else {
            $message = "Du kannst dich nicht selbst löschen!";
            $messageType = "warning";
        }
    } else {
        $message = "Ungültiger CSRF Token!";
        $messageType = "danger";
    }
}

// Benutzer hinzufügen oder bearbeiten
if (isset($_POST['save_user']) && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $editId = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;
        $username = trim($_POST['username']);
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Validierung
        $errors = [];
        if (empty($username)) $errors[] = "Benutzername ist erforderlich";
        if (empty($email)) $errors[] = "E-Mail ist erforderlich";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Ungültige E-Mail-Adresse";
        
        if (empty($errors)) {
            if ($editId) {
                // Benutzer bearbeiten
                if (!empty($password)) {
                    // Mit Passwortänderung
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, password_hash = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $username, $fullName, $email, $passwordHash, $editId);
                } else {
                    // Ohne Passwortänderung
                    $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $username, $fullName, $email, $editId);
                }
                
                if ($stmt->execute()) {
                    $message = "Benutzer erfolgreich aktualisiert.";
                    $messageType = "success";
                } else {
                    $message = "Fehler beim Aktualisieren: " . $conn->error;
                    $messageType = "danger";
                }
            } else {
                // Neuer Benutzer
                if (empty($password)) {
                    $message = "Passwort ist für neue Benutzer erforderlich";
                    $messageType = "danger";
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password_hash) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $username, $fullName, $email, $passwordHash);
                    
                    if ($stmt->execute()) {
                        $message = "Benutzer erfolgreich hinzugefügt.";
                        $messageType = "success";
                    } else {
                        if ($conn->errno == 1062) {
                            $message = "Benutzername oder E-Mail existiert bereits!";
                        } else {
                            $message = "Fehler beim Hinzufügen: " . $conn->error;
                        }
                        $messageType = "danger";
                    }
                }
            }
            if (isset($stmt)) $stmt->close();
        } else {
            $message = implode("<br>", $errors);
            $messageType = "danger";
        }
    } else {
        $message = "Ungültiger CSRF Token!";
        $messageType = "danger";
    }
}

// Alle Benutzer laden
$result = $conn->query("SELECT id, username, full_name, email FROM users ORDER BY id");
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<style>
/* Schlichte Tabellen-Styles passend zu anderen MSV-Seiten */
.table > thead > tr > th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    padding: 0.75rem;
}

.table > tbody > tr:hover {
    background-color: #f8f9fa;
}

.user-initial {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: var(--secondary-color);
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
}

.badge-admin {
    background-color: var(--secondary-color);
    font-size: 0.75rem;
}

/* Kompakte Action-Buttons */
.btn-action-group {
    display: inline-flex;
    gap: 0.25rem;
}

.btn-action {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* Info-Card oben */
.info-card {
    background-color: #f8f9fa;
    border-left: 4px solid var(--secondary-color);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: 0.25rem;
}

.info-card .count {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--secondary-color);
}

/* Toast Container */
.toast-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 9999;
}

.toast-message {
    min-width: 300px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-radius: 0.25rem;
    background: white;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
}

.toast-message.show {
    opacity: 1;
    transform: translateX(0);
}

.toast-message.toast-success {
    border-left: 4px solid #28a745;
}

.toast-message.toast-error {
    border-left: 4px solid #dc3545;
}

.toast-message.toast-warning {
    border-left: 4px solid #ffc107;
}
</style>

<!-- Benutzerverwaltung -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-9 col-lg-10 col-md-12 col-12 ps-0">
            <div class="main-content-wrapper">
                
                <!-- Header -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-people-fill me-2"></i>
                            Benutzerverwaltung
                        </h2>
                    </div>
                </div>
                
                <div class="content-background">
                    
                    <!-- Info Card mit Anzahl -->
                    <div class="info-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="count"><?php echo count($users); ?></div>
                                <small class="text-muted">Registrierte Benutzer</small>
                            </div>
                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
                                <i class="bi bi-person-plus me-2"></i>Neuer Benutzer
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-2"></i>
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Benutzertabelle -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">ID</th>
                                    <th>Benutzer</th>
                                    <th>E-Mail</th>
                                    <th style="width: 120px;" class="text-center">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <span class="text-muted"><?php echo $user['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="user-initial me-2">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </span>
                                            <div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                    <?php if ($user['id'] == 1): ?>
                                                        <span class="badge badge-admin ms-1">Admin</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($user['full_name'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['full_name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="bi bi-envelope me-1"></i>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-action-group">
                                            <button class="btn btn-sm btn-outline-secondary btn-action" 
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                    title="Bearbeiten">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <?php if ($user['id'] != 1): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Benutzer <?php echo htmlspecialchars($user['username']); ?> wirklich löschen?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger btn-action" title="Löschen">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary btn-action" disabled title="Admin kann nicht gelöscht werden">
                                                <i class="bi bi-shield-lock"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-3">Keine Benutzer vorhanden</p>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Benutzer hinzufügen/bearbeiten -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="userModalTitle">
                        <i class="bi bi-person-plus me-2"></i>
                        Neuer Benutzer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person me-1"></i>
                            Benutzername
                        </label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">
                            <i class="bi bi-card-text me-1"></i>
                            Vollständiger Name
                        </label>
                        <input type="text" class="form-control" id="full_name" name="full_name">
                        <small class="text-muted">Optional</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope me-1"></i>
                            E-Mail
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-key me-1"></i>
                            <span id="passwordLabel">Passwort</span>
                        </label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="text-muted" id="passwordHelp" style="display: none;">
                            Leer lassen, um das aktuelle Passwort beizubehalten
                        </small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Abbrechen
                    </button>
                    <button type="submit" name="save_user" class="btn btn-outline-success">
                        <i class="bi bi-check-circle me-2"></i>Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<script>
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

function resetUserForm() {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Neuer Benutzer';
    document.getElementById('edit_id').value = '';
    document.getElementById('username').value = '';
    document.getElementById('full_name').value = '';
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordLabel').innerText = 'Passwort';
    document.getElementById('passwordHelp').style.display = 'none';
}

function editUser(user) {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Benutzer bearbeiten';
    document.getElementById('edit_id').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('full_name').value = user.full_name || '';
    document.getElementById('email').value = user.email;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('passwordLabel').innerText = 'Neues Passwort (optional)';
    document.getElementById('passwordHelp').style.display = 'block';
    
    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}

// Auto-hide Alerts nach 5 Sekunden
$(document).ready(function() {
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
});
</script>

<?php
include 'footer.inc.php';
?>