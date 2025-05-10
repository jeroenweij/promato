<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

require 'includes/header.php';
require 'includes/db.php';

// Initialize variables
$wbsoEntries = [];
$editId = null;
$editEntry = null;
$successMessage = '';
$errorMessage = '';

// Check if edit parameter is provided in the URL
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    
    // Fetch the WBSO entry for editing
    $editStmt = $pdo->prepare("SELECT * FROM Wbso WHERE Id = ?");
    $editStmt->execute([$editId]);
    $editEntry = $editStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$editEntry) {
        $errorMessage = 'WBSO entry not found.';
        $editId = null;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new WBSO entry
    if (isset($_POST['add_wbso'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $hours = isset($_POST['hours']) ? (int)$_POST['hours'] : null;
        $date = !empty($_POST['date']) ? $_POST['date'] : null;
        
        if (empty($name)) {
            $errorMessage = 'WBSO name is required.';
        } elseif ($hours !== null && ($hours < 0 || $hours > 32767)) {
            $errorMessage = 'Hours must be between 0 and 32767.';
        } else {
            // Check if a WBSO with this name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Wbso WHERE Name = ?");
            $checkStmt->execute([$name]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            
            if ($exists) {
                $errorMessage = 'A WBSO entry with this name already exists.';
            } else {
                // Insert new WBSO entry
                $insertStmt = $pdo->prepare("INSERT INTO Wbso (Name, Description, Hours, Date) VALUES (?, ?, ?, ?)");
                $insertStmt->execute([$name, $description ?: null, $hours, $date]);
                
                $successMessage = 'WBSO entry added successfully.';
                
                // Redirect to avoid form resubmission
                header("Location: wbso.php?success=added");
                exit;
            }
        }
    }
    
    // Update existing WBSO entry
    elseif (isset($_POST['update_wbso'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $hours = isset($_POST['hours']) ? (int)$_POST['hours'] : null;
        $date = !empty($_POST['date']) ? $_POST['date'] : null;
        
        if (empty($name)) {
            $errorMessage = 'WBSO name is required.';
        } elseif ($hours !== null && ($hours < 0 || $hours > 32767)) {
            $errorMessage = 'Hours must be between 0 and 32767.';
        } else {
            // Check if another WBSO with this name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Wbso WHERE Name = ? AND Id != ?");
            $checkStmt->execute([$name, $id]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            
            if ($exists) {
                $errorMessage = 'Another WBSO entry with this name already exists.';
            } else {
                // Update WBSO entry
                $updateStmt = $pdo->prepare("UPDATE Wbso SET Name = ?, Description = ?, Hours = ?, Date = ? WHERE Id = ?");
                $updateStmt->execute([$name, $description ?: null, $hours, $date, $id]);
                
                $successMessage = 'WBSO entry updated successfully.';
                
                // Redirect to avoid form resubmission
                header("Location: wbso.php?success=updated");
                exit;
            }
        }
    }
    
    // Delete WBSO entry
    elseif (isset($_POST['delete_wbso'])) {
        $id = (int)$_POST['id'];
        
        // Check if this WBSO is used in any activities
        $checkUsageStmt = $pdo->prepare("SELECT COUNT(*) FROM Activities WHERE Wbso = ?");
        $checkUsageStmt->execute([$id]);
        $isUsed = (int)$checkUsageStmt->fetchColumn() > 0;
        
        if ($isUsed) {
            $errorMessage = 'This WBSO entry cannot be deleted because it is being used in one or more activities.';
        } else {
            // Delete the WBSO entry
            $deleteStmt = $pdo->prepare("DELETE FROM Wbso WHERE Id = ?");
            $deleteStmt->execute([$id]);
            
            $successMessage = 'WBSO entry deleted successfully.';
            
            // Redirect to avoid form resubmission
            header("Location: wbso.php?success=deleted");
            exit;
        }
    }
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $action = $_GET['success'];
    if ($action === 'added') {
        $successMessage = 'WBSO entry added successfully.';
    } elseif ($action === 'updated') {
        $successMessage = 'WBSO entry updated successfully.';
    } elseif ($action === 'deleted') {
        $successMessage = 'WBSO entry deleted successfully.';
    }
}

// Fetch all WBSO entries
$stmt = $pdo->query("SELECT w.*, 
    COUNT(DISTINCT Activities.Id) AS UsageCount, 
    COALESCE(SUM(Hours.Hours), 0) AS HoursLogged 
    FROM Wbso w
    LEFT JOIN Activities ON Activities.Wbso = w.Id 
    LEFT JOIN Hours ON Hours.Project = Activities.Project AND Hours.Activity = Activities.Key 
    GROUP BY w.Id, w.Name
    ORDER BY w.Name DESC;");
$wbsoEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If we're still here, check if output has started before sending headers
if (isset($successMessage) && !empty($successMessage) && ob_get_length() === 0) {
    header("Location: wbso.php");
    exit;
}
?>

<section id="wbso-management">
    <div class="container">        
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- WBSO Entries List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h2>WBSO Categories</h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($wbsoEntries) > 0): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Hours</th>
                                        <th>Hours logged</th>
                                        <th>Date</th>
                                        <th>Usage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wbsoEntries as $entry): 
                                        $hourslogged = $entry['HoursLogged'] ?? 0;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($entry['Name']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['Description'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($entry['Hours'] ?? 'N/A'); ?></td>
                                            <td><?= round($hourslogged/100) ?></td>
                                            <td>
                                                <?php 
                                                if (!empty($entry['Date'])) {
                                                    echo date('Y-m-d', strtotime($entry['Date']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo $entry['UsageCount']; ?></td>
                                            <td>
                                                <a href="wbso.php?edit=<?php echo $entry['Id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this WBSO category?');">
                                                    <input type="hidden" name="id" value="<?php echo $entry['Id']; ?>">
                                                    <button type="submit" name="delete_wbso" class="btn btn-sm btn-danger" <?php echo $entry['UsageCount'] > 0 ? 'disabled title="Cannot delete: used in activities"' : ''; ?>>
                                                        Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No WBSO categories found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Add/Edit WBSO Form -->
                <div class="card">
                    <div class="card-header">
                        <h2><?php echo $editId ? 'Edit WBSO Category' : 'Add New WBSO Category'; ?></h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($editId): ?>
                                <input type="hidden" name="id" value="<?php echo $editId; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="name">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control" required 
                                       value="<?php echo htmlspecialchars($editEntry['Name'] ?? ''); ?>">
                                <small class="form-text text-muted">Short name for the WBSO category (max 16 characters)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" name="description" id="description" class="form-control" required
                                       value="<?php echo htmlspecialchars($editEntry['Description'] ?? ''); ?>">
                                <small class="form-text text-muted">Optional detailed description (max 64 characters)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="hours">Hours</label>
                                <input type="number" name="hours" id="hours" class="form-control" required min="0" max="32767"
                                       value="<?php echo htmlspecialchars($editEntry['Hours'] ?? ''); ?>">
                                <small class="form-text text-muted">Number of hours for this WBSO category</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" name="date" id="date" class="form-control" required
                                       value="<?php echo !empty($editEntry['Date']) ? date('Y-m-d', strtotime($editEntry['Date'])) : ''; ?>">
                                <small class="form-text text-muted">Date associated with this WBSO category</small>
                            </div>
                            
                            <div class="form-group">
                                <?php if ($editId): ?>
                                    <button type="submit" name="update_wbso" class="btn btn-primary">Update WBSO Category</button>
                                    <a href="wbso.php" class="btn btn-secondary">Cancel</a>
                                <?php else: ?>
                                    <button type="submit" name="add_wbso" class="btn btn-success">Add WBSO Category</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Information Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Information</h3>
                    </div>
                    <div class="card-body">
                        <p>WBSO categories are used to classify activities for the Dutch R&D tax credit scheme (WBSO).</p>
                        <p>Each category should have a short, unique name and may have a longer description.</p>
                        <p>Categories that are used in activities cannot be deleted.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// End output buffering and flush
ob_end_flush();
require 'includes/footer.php';
?>