<?php
$pageSpecificCSS = ['teamadmin.css'];
require 'includes/header.php';
require 'includes/db.php';

// Check if user has admin access
if (($_SESSION['auth_level'] ?? 0) < 4) {
    die("Access denied. Admin privileges required.");
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $name = trim($_POST['name']);
                    $planable = isset($_POST['planable']) ? 1 : 0;
                    $ord = (int)$_POST['ord'];
                    
                    if (empty($name)) {
                        throw new Exception("Team name is required.");
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO Teams (Name, Planable, Ord) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $planable, $ord]);
                    
                    $message = "Team '" . htmlspecialchars($name) . "' added successfully!";
                    $messageType = "success";
                    break;
                    
                case 'edit':
                    $id = (int)$_POST['id'];
                    $name = trim($_POST['name']);
                    $planable = isset($_POST['planable']) ? 1 : 0;
                    $ord = (int)$_POST['ord'];
                    
                    if (empty($name)) {
                        throw new Exception("Team name is required.");
                    }
                    
                    $stmt = $pdo->prepare("UPDATE Teams SET Name = ?, Planable = ?, Ord = ? WHERE Id = ?");
                    $stmt->execute([$name, $planable, $ord, $id]);
                    
                    $message = "Team updated successfully!";
                    $messageType = "success";
                    break;
                    
                case 'delete':
                    $id = (int)$_POST['id'];
                    
                    // Check if team has personnel
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Personel WHERE Team = ?");
                    $checkStmt->execute([$id]);
                    $count = $checkStmt->fetchColumn();
                    
                    if ($count > 0) {
                        throw new Exception("Cannot delete team with assigned personnel. Please reassign $count person(s) first.");
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM Teams WHERE Id = ?");
                    $stmt->execute([$id]);
                    
                    $message = "Team deleted successfully!";
                    $messageType = "success";
                    break;
            }
        }
        
        $pdo->commit();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message) . "&type=" . $messageType);
        // Redirect to prevent form resubmission
        echo "<script>
        window.location.replace('" . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message) . "&type=" . $messageType . "');
        </script>";
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get message from redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'info';
}

// Fetch all teams with personnel count
$teamsStmt = $pdo->query("
    SELECT 
        t.Id, 
        t.Name, 
        t.Planable, 
        t.Ord,
        COUNT(p.Id) as PersonnelCount
    FROM Teams t
    LEFT JOIN Personel p ON p.Team = t.Id AND p.plan = 1
    GROUP BY t.Id, t.Name, t.Planable, t.Ord
    ORDER BY t.Ord, t.Name
");
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section id="team-management">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Team Management</h1>
            <button class="btn btn-primary" onclick="showAddModal()">
                <i class="fas fa-plus"></i> Add New Team
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType == 'success' ? 'success' : ($messageType == 'error' ? 'danger' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <?php if (empty($teams)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No teams found. Click "Add New Team" to create your first team.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($teams as $team): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card team-card h-100 <?= $team['Planable'] ? '' : 'inactive' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($team['Name']) ?></h5>
                                <span class="badge <?= $team['Planable'] ? 'badge-planable' : 'badge-not-planable' ?>">
                                    <?= $team['Planable'] ? 'Planable' : 'Not Planable' ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-users"></i> <?= $team['PersonnelCount'] ?> member(s)
                                </small>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-sort"></i> Order: <?= $team['Ord'] ?>
                                </small>
                            </div>
                            
                            <div class="team-actions">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editTeam(<?= htmlspecialchars(json_encode($team)) ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteTeam(<?= $team['Id'] ?>, '<?= htmlspecialchars($team['Name'], ENT_QUOTES) ?>', <?= $team['PersonnelCount'] ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Add Team Modal -->
<div class="modal fade" id="addTeamModal" tabindex="-1" role="dialog" aria-labelledby="addTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTeamModalLabel">Add New Team</h5>
                    <button type="button" class="close" onclick="closeAddModal()" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="add_name" class="form-label">Team Name *</label>
                        <input type="text" class="form-control" id="add_name" name="name" required maxlength="16">
                        <small class="form-text text-muted">Maximum 16 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_ord" class="form-label">Order</label>
                        <input type="number" class="form-control" id="add_ord" name="ord" value="1" min="1" max="120">
                        <small class="form-text text-muted">Display order (lower numbers appear first)</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="add_planable" name="planable" checked>
                            <label class="form-check-label" for="add_planable">
                                Planable
                            </label>
                            <small class="form-text text-muted d-block">
                                If checked, this team can be used in capacity planning
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Team Modal -->
<div class="modal fade" id="editTeamModal" tabindex="-1" role="dialog" aria-labelledby="editTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTeamModalLabel">Edit Team</h5>
                    <button type="button" class="close" onclick="closeEditModal()" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Team Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required maxlength="16">
                        <small class="form-text text-muted">Maximum 16 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_ord" class="form-label">Order</label>
                        <input type="number" class="form-control" id="edit_ord" name="ord" min="1" max="255">
                        <small class="form-text text-muted">Display order (lower numbers appear first)</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_planable" name="planable">
                            <label class="form-check-label" for="edit_planable">
                                Planable
                            </label>
                            <small class="form-text text-muted d-block">
                                If checked, this team can be used in capacity planning
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteTeamModal" tabindex="-1" role="dialog" aria-labelledby="deleteTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteTeamModalLabel">Confirm Deletion</h5>
                    <button type="button" class="close text-white" onclick="closeDeleteModal()" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <p>Are you sure you want to delete the team <strong id="delete_name"></strong>?</p>
                    <p class="text-danger" id="delete_warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i> This team has assigned personnel. Please reassign them first.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_confirm_btn">Delete Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal functions using jQuery (Bootstrap 4 compatible)
function showAddModal() {
    $('#addTeamModal').modal('show');
}

function closeAddModal() {
    $('#addTeamModal').modal('hide');
}

function closeEditModal() {
    $('#editTeamModal').modal('hide');
}

function closeDeleteModal() {
    $('#deleteTeamModal').modal('hide');
}

function editTeam(team) {
    document.getElementById('edit_id').value = team.Id;
    document.getElementById('edit_name').value = team.Name;
    document.getElementById('edit_ord').value = team.Ord;
    document.getElementById('edit_planable').checked = team.Planable == 1;
    
    $('#editTeamModal').modal('show');
}

function deleteTeam(id, name, personnelCount) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    
    const warning = document.getElementById('delete_warning');
    const confirmBtn = document.getElementById('delete_confirm_btn');
    
    if (personnelCount > 0) {
        warning.style.display = 'block';
        confirmBtn.disabled = true;
    } else {
        warning.style.display = 'none';
        confirmBtn.disabled = false;
    }
    
    $('#deleteTeamModal').modal('show');
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            $(alert).alert('close');
        });
    }, 5000);
});
</script>

<?php require 'includes/footer.php'; ?>