<?php
// benutzerverwaltung.php - Erweiterte Benutzerverwaltung mit Rollen/Status/Mitglied-Zuordnung
require_once 'config.php';
require_once __DIR__ . '/../auth.php';

// Zugriffsschutz - nur Admins (vor header.inc.php, da dieser bereits Output erzeugt)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') != 'admin') {
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_id'] ?? 0) != 1) {
        header('Location: home.php');
        exit();
    }
}

include 'header.inc.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Alle Benutzer laden (erweitert mit Rolle, Status, Mitglied)
$result = $conn->query("
    SELECT u.id, u.username, u.full_name, u.email, u.role, u.status, u.mitglied_id, u.jungschuetze_id,
           u.created_at, u.approved_at,
           m.Vorname AS m_vorname, m.Name AS m_name,
           j.Vorname AS j_vorname, j.Name AS j_name
    FROM users u
    LEFT JOIN mitglieder m ON u.mitglied_id = m.ID
    LEFT JOIN jungschuetzen j ON u.jungschuetze_id = j.id
    ORDER BY
        CASE u.status WHEN 'pending' THEN 0 ELSE 1 END,
        u.created_at DESC, u.id
");
$users = [];
$pending_count = 0;
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
    if ($row['status'] == 'pending') $pending_count++;
}

// Mitglieder fuer Zuordnungs-Dropdown laden
$mitglieder_result = $conn->query("
    SELECT m.ID, m.Vorname, m.Name
    FROM mitglieder m
    WHERE m.Status = 1
    AND m.ID NOT IN (SELECT mitglied_id FROM users WHERE mitglied_id IS NOT NULL)
    ORDER BY m.Name, m.Vorname
");
$freie_mitglieder = [];
while ($row = $mitglieder_result->fetch_assoc()) {
    $freie_mitglieder[] = $row;
}

$role_labels = ['admin' => 'Admin', 'vorstand' => 'Vorstand', 'mitglied' => 'Mitglied', 'jungschuetze' => 'Jungschütze'];
$status_labels = ['pending' => 'Ausstehend', 'approved' => 'Aktiv', 'rejected' => 'Abgelehnt', 'disabled' => 'Deaktiviert'];
$status_colors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'disabled' => 'secondary'];
?>

<style>
/* Tabellen-Header kommt zentral (.table thead th: 0.75rem, uppercase) */
.table > tbody > tr:hover { background-color: #f8f9fa; }
.row-pending { background-color: #fff9e6 !important; }
.row-pending:hover { background-color: #fff3cd !important; }
.user-initial {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px; height: 32px;
    border-radius: 50%;
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
}
.initial-admin { background: linear-gradient(135deg, #dc3545, #c82333); }
.initial-vorstand { background: linear-gradient(135deg, #ffc107, #e0a800); color: #343a40; }
.initial-mitglied { background: linear-gradient(135deg, #667eea, #764ba2); }
.initial-jungschuetze { background: linear-gradient(135deg, #14b8a6, #0d9488); }
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
.pending-alert {
    background: linear-gradient(135deg, #fff9e6, #fff3cd);
    border: 1px solid #ffc107;
    border-left: 4px solid #ffc107;
    border-radius: 0.375rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
}
.btn-action-group { display: inline-flex; gap: 0.25rem; }
.btn-action {
    width: 32px; height: 32px; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-11 col-lg-12 col-md-12 col-12 ps-0">
            <div class="main-content-wrapper">
                <?php $page_title = 'Benutzerverwaltung'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                    <!-- Info Card -->
                    <div class="info-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="count"><?php echo count($users); ?></div>
                                <small class="text-muted">Registrierte Benutzer</small>
                            </div>
                            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
                                <i class="bi bi-person-plus me-2"></i>Neuer Benutzer
                            </button>
                        </div>
                    </div>

                    <?php if ($pending_count > 0): ?>
                    <div class="pending-alert">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                        <strong><?php echo $pending_count; ?> neue Registrierung<?php echo $pending_count > 1 ? 'en' : ''; ?></strong>
                        warten auf Freischaltung
                    </div>
                    <?php endif; ?>

                    <!-- Desktop Tabelle -->
                    <div class="desktop-table-container">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="benutzerverwaltungTable">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">ID</th>
                                        <th>Benutzer</th>
                                        <th>E-Mail</th>
                                        <th>Rolle</th>
                                        <th>Status</th>
                                        <th>Mitglied</th>
                                        <th style="width:180px;" class="text-center">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($users as $user):
                                    $is_pending = ($user['status'] == 'pending');
                                    $initial_class = 'initial-' . ($user['role'] ?? 'mitglied');
                                ?>
                                <tr class="<?php echo $is_pending ? 'row-pending' : ''; ?>">
                                    <td><span class="text-muted"><?php echo $user['id']; ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="user-initial <?php echo $initial_class; ?> me-2">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </span>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <?php if (!empty($user['full_name'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($user['full_name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><small class="text-muted"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?></small></td>
                                    <td>
                                        <select class="form-select form-select-sm" style="width:auto; min-width:110px;"
                                                onchange="changeRole(<?php echo $user['id']; ?>, this.value)"
                                                <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                            <?php foreach ($role_labels as $rk => $rl): ?>
                                            <option value="<?php echo $rk; ?>" <?php echo ($user['role'] ?? '') == $rk ? 'selected' : ''; ?>><?php echo $rl; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_colors[$user['status'] ?? 'approved'] ?? 'secondary'; ?>">
                                            <?php echo $status_labels[$user['status'] ?? 'approved'] ?? 'Unbekannt'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (($user['role'] ?? '') === 'jungschuetze'): ?>
                                            <?php if ($user['jungschuetze_id']): ?>
                                                <small class="text-info">
                                                    <i class="bi bi-person-bounding-box me-1"></i>
                                                    <?php echo htmlspecialchars(($user['j_vorname'] ?? '') . ' ' . ($user['j_name'] ?? '')); ?>
                                                    <span class="text-muted">(JSK)</span>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted"><i class="bi bi-dash me-1"></i>kein JSK</small>
                                            <?php endif; ?>
                                        <?php elseif ($user['mitglied_id']): ?>
                                            <small class="text-success">
                                                <i class="bi bi-link-45deg me-1"></i>
                                                <?php echo htmlspecialchars(($user['m_vorname'] ?? '') . ' ' . ($user['m_name'] ?? '')); ?>
                                                <span class="text-muted">(<?php echo $user['mitglied_id']; ?>)</span>
                                            </small>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="openAssignModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="bi bi-link me-1"></i>Zuordnen
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-action-group">
                                            <?php if ($is_pending): ?>
                                                <button class="btn btn-sm btn-outline-success btn-action" onclick="userAction(<?php echo $user['id']; ?>, 'approve')" data-tooltip="Freischalten">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-action" onclick="userAction(<?php echo $user['id']; ?>, 'reject')" data-tooltip="Ablehnen">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php else: ?>
                                                <?php if (($user['status'] ?? 'approved') == 'approved' && $user['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-outline-secondary btn-action" onclick="userAction(<?php echo $user['id']; ?>, 'disable')" data-tooltip="Deaktivieren">
                                                        <i class="bi bi-pause-circle"></i>
                                                    </button>
                                                <?php elseif (($user['status'] ?? '') == 'disabled'): ?>
                                                    <button class="btn btn-sm btn-outline-success btn-action" onclick="userAction(<?php echo $user['id']; ?>, 'enable')" data-tooltip="Aktivieren">
                                                        <i class="bi bi-play-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <button class="btn btn-sm btn-outline-primary btn-action"
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" data-tooltip="Bearbeiten">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <?php if ($user['id'] != $_SESSION['user_id'] && $user['id'] != 1): ?>
                                            <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" data-tooltip="Löschen">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="mobile-cards-container" id="mobileBenutzerverwaltungCards" style="display:none;">
                        <div class="mobile-cards-scroll">
                            <?php foreach ($users as $user):
                                $is_pending = ($user['status'] == 'pending');
                            ?>
                            <div class="mobile-card" style="<?php echo $is_pending ? 'border-left: 4px solid #ffc107; background: #fff9e6;' : ''; ?> border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if (!empty($user['full_name'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($user['full_name']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-<?php echo $status_colors[$user['status'] ?? 'approved'] ?? 'secondary'; ?>">
                                        <?php echo $status_labels[$user['status'] ?? 'approved'] ?? '-'; ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.85rem; color: #6c757d;">
                                    <div><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div><i class="bi bi-shield me-1"></i><?php echo $role_labels[$user['role'] ?? 'mitglied'] ?? '-'; ?></div>
                                    <?php if ($user['mitglied_id']): ?>
                                    <div><i class="bi bi-link-45deg me-1"></i><?php echo htmlspecialchars(($user['m_vorname'] ?? '') . ' ' . ($user['m_name'] ?? '') . ' (' . $user['mitglied_id'] . ')'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 btn-action-group">
                                    <?php if ($is_pending): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="userAction(<?php echo $user['id']; ?>, 'approve')"><i class="bi bi-check-lg me-1"></i>Freischalten</button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="userAction(<?php echo $user['id']; ?>, 'reject')"><i class="bi bi-x-lg me-1"></i>Ablehnen</button>
                                    <?php else: ?>
                                        <?php if (($user['status'] ?? 'approved') == 'approved' && $user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="userAction(<?php echo $user['id']; ?>, 'disable')"><i class="bi bi-pause-circle me-1"></i>Deaktivieren</button>
                                        <?php elseif (($user['status'] ?? '') == 'disabled'): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="userAction(<?php echo $user['id']; ?>, 'enable')"><i class="bi bi-play-circle me-1"></i>Aktivieren</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!$user['mitglied_id']): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="openAssignModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"><i class="bi bi-link me-1"></i>Zuordnen</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"><i class="bi bi-pencil me-1"></i>Bearbeiten</button>
                                    <?php if ($user['id'] != $_SESSION['user_id'] && $user['id'] != 1): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"><i class="bi bi-trash me-1"></i>Löschen</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Benutzer bearbeiten -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="benutzerverwaltung.php">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="userModalTitle"><i class="bi bi-person-plus me-2"></i>Neuer Benutzer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="mb-3">
                        <label for="username" class="form-label"><i class="bi bi-person me-1"></i>Benutzername</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label"><i class="bi bi-card-text me-1"></i>Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><i class="bi bi-envelope me-1"></i>E-Mail</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><i class="bi bi-key me-1"></i><span id="passwordLabel">Passwort</span></label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="text-muted" id="passwordHelp" style="display: none;">Leer lassen um aktuelles Passwort beizubehalten</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i>Abbrechen</button>
                    <button type="submit" name="save_user" class="btn btn-outline-primary btn-sm"><i class="bi bi-save me-2"></i>Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Mitglied zuordnen -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i>Mitglied zuordnen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Benutzer: <strong id="assignUsername"></strong></p>
                <input type="hidden" id="assignUserId">
                <div class="mb-3">
                    <label class="form-label">Mitglied auswählen</label>
                    <select class="form-select" id="assignMitgliedId">
                        <option value="">-- Kein Mitglied --</option>
                        <?php foreach ($freie_mitglieder as $m): ?>
                        <option value="<?php echo $m['ID']; ?>"><?php echo htmlspecialchars($m['Name'] . ' ' . $m['Vorname'] . ' (' . $m['ID'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="assignMitglied()"><i class="bi bi-check-circle me-2"></i>Zuordnen</button>
            </div>
        </div>
    </div>
</div>

<?php
// Bestehende Speicher-Logik (fuer das Bearbeiten-Modal)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user']) && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $editId = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;
        $u_username = trim($_POST['username']);
        $u_fullName = trim($_POST['full_name']);
        $u_email = trim($_POST['email']);
        $u_password = $_POST['password'];

        $saveError = '';
        try {
            if ($editId) {
                if (!empty($u_password)) {
                    $passwordHash = password_hash($u_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, password_hash=? WHERE id=?");
                    $stmt->bind_param("ssssi", $u_username, $u_fullName, $u_email, $passwordHash, $editId);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=? WHERE id=?");
                    $stmt->bind_param("sssi", $u_username, $u_fullName, $u_email, $editId);
                }
                $stmt->execute();
                $stmt->close();
            } else {
                if (!empty($u_password)) {
                    $passwordHash = password_hash($u_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'admin', 'approved')");
                    $stmt->bind_param("ssss", $u_username, $u_fullName, $u_email, $passwordHash);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $saveError = 'Ein Benutzer mit dieser E-Mail-Adresse oder diesem Benutzernamen existiert bereits.';
            } else {
                $saveError = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
        if ($saveError) {
            echo '<script>document.addEventListener("DOMContentLoaded", function(){ msvToast(' . json_encode($saveError) . ', "error"); });</script>';
        } else {
            echo '<script>window.location.href="benutzerverwaltung.php";</script>';
            exit;
        }
    }
}
?>

<script>
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

function userAction(userId, action) {
    let confirmText = '';
    switch (action) {
        case 'approve': confirmText = 'Benutzer freischalten?'; break;
        case 'reject': confirmText = 'Registrierung ablehnen?'; break;
        case 'disable': confirmText = 'Benutzer deaktivieren?'; break;
        case 'enable': confirmText = 'Benutzer aktivieren?'; break;
    }
    msvConfirm(confirmText).then(result => {
        if (result.isConfirmed) {
            $.post('../api/user_management.php', {
                action: action, user_id: userId, csrf_token: CSRF_TOKEN
            }, function(resp) {
                if (resp.success) {
                    msvToast(resp.message, 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    msvToast(resp.message, 'error');
                }
            }, 'json').fail(function() {
                msvToast('Fehler bei der Verarbeitung', 'error');
            });
        }
    });
}

function changeRole(userId, newRole) {
    $.post('../api/user_management.php', {
        action: 'change_role', user_id: userId, role: newRole, csrf_token: CSRF_TOKEN
    }, function(resp) {
        if (resp.success) {
            msvToast(resp.message, 'success');
        } else {
            msvToast(resp.message, 'error');
            setTimeout(() => location.reload(), 800);
        }
    }, 'json');
}

function deleteUser(userId, username) {
    msvConfirmDelete('Benutzer "' + username + '"').then(result => {
        if (result.isConfirmed) {
            $.post('../api/user_management.php', {
                action: 'delete', user_id: userId, csrf_token: CSRF_TOKEN
            }, function(resp) {
                if (resp.success) {
                    msvToast(resp.message, 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    msvToast(resp.message, 'error');
                }
            }, 'json');
        }
    });
}

function openAssignModal(userId, username) {
    $('#assignUserId').val(userId);
    $('#assignUsername').text(username);
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}

function assignMitglied() {
    var userId = $('#assignUserId').val();
    var mitgliedId = $('#assignMitgliedId').val();
    $.post('../api/user_management.php', {
        action: 'assign_mitglied', user_id: userId, mitglied_id: mitgliedId, csrf_token: CSRF_TOKEN
    }, function(resp) {
        if (resp.success) {
            msvToast(resp.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('assignModal')).hide();
            setTimeout(() => location.reload(), 800);
        } else {
            msvToast(resp.message, 'error');
        }
    }, 'json');
}

function resetUserForm() {
    $('#userModalTitle').html('<i class="bi bi-person-plus me-2"></i>Neuer Benutzer');
    $('#edit_id').val('');
    $('#username').val('');
    $('#full_name').val('');
    $('#email').val('');
    $('#password').val('').attr('required', true);
    $('#passwordLabel').text('Passwort');
    $('#passwordHelp').hide();
}

function editUser(user) {
    $('#userModalTitle').html('<i class="bi bi-pencil me-2"></i>Benutzer bearbeiten');
    $('#edit_id').val(user.id);
    $('#username').val(user.username);
    $('#full_name').val(user.full_name || '');
    $('#email').val(user.email);
    $('#password').val('').removeAttr('required');
    $('#passwordLabel').text('Neues Passwort (optional)');
    $('#passwordHelp').show();
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>

<style>
@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    #mobileBenutzerverwaltungCards { display: block !important; }
}
@media (min-width: 768px) {
    #mobileBenutzerverwaltungCards { display: none !important; }
}
</style>

<?php include 'footer.inc.php'; ?>
