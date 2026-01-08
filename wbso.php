<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

require 'includes/header.php';
require_once 'includes/db.php';

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
    } else {
        // Fetch budget entries for this WBSO
        $budgetStmt = $pdo->prepare("SELECT * FROM WbsoBudget WHERE WbsoId = ? ORDER BY Year");
        $budgetStmt->execute([$editId]);
        $budgetEntries = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

        // Create array mapping year to budget hours for easy lookup
        $budgetByYear = [];
        foreach ($budgetEntries as $budget) {
            $budgetByYear[$budget['Year']] = $budget['Hours'];
        }

        // Generate list of years from StartDate to EndDate (or current year + 5 if no EndDate)
        $startYear = !empty($editEntry['StartDate']) ? (int)date('Y', strtotime($editEntry['StartDate'])) : (int)date('Y');
        $endYear = !empty($editEntry['EndDate']) ? (int)date('Y', strtotime($editEntry['EndDate'])) : ((int)date('Y') + 5);

        $activeYears = range($startYear, $endYear);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new WBSO entry
    if (isset($_POST['add_wbso'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        if (empty($name)) {
            $errorMessage = 'WBSO name is required.';
        } elseif (empty($startDate)) {
            $errorMessage = 'Start date is required.';
        } else {
            // Check if a WBSO with this name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Wbso WHERE Name = ?");
            $checkStmt->execute([$name]);
            $exists = (int)$checkStmt->fetchColumn() > 0;

            if ($exists) {
                $errorMessage = 'A WBSO entry with this name already exists.';
            } else {
                // Insert new WBSO entry
                $insertStmt = $pdo->prepare("INSERT INTO Wbso (Name, Description, StartDate, EndDate) VALUES (?, ?, ?, ?)");
                $insertStmt->execute([$name, $description ?: null, $startDate, $endDate]);

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
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        if (empty($name)) {
            $errorMessage = 'WBSO name is required.';
        } elseif (empty($startDate)) {
            $errorMessage = 'Start date is required.';
        } else {
            // Check if another WBSO with this name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Wbso WHERE Name = ? AND Id != ?");
            $checkStmt->execute([$name, $id]);
            $exists = (int)$checkStmt->fetchColumn() > 0;

            if ($exists) {
                $errorMessage = 'Another WBSO entry with this name already exists.';
            } else {
                // Update WBSO entry
                $updateStmt = $pdo->prepare("UPDATE Wbso SET Name = ?, Description = ?, StartDate = ?, EndDate = ? WHERE Id = ?");
                $updateStmt->execute([$name, $description ?: null, $startDate, $endDate, $id]);

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
            // Delete the WBSO entry (budgets will cascade delete)
            $deleteStmt = $pdo->prepare("DELETE FROM Wbso WHERE Id = ?");
            $deleteStmt->execute([$id]);

            $successMessage = 'WBSO entry deleted successfully.';

            // Redirect to avoid form resubmission
            header("Location: wbso.php?success=deleted");
            exit;
        }
    }

    // Save budgets for all years
    elseif (isset($_POST['save_budgets'])) {
        $wbsoId = (int)$_POST['wbso_id'];
        $yearBudgets = $_POST['year_budgets'] ?? [];

        // Process each year's budget
        foreach ($yearBudgets as $year => $hours) {
            $year = (int)$year;
            $hours = (int)$hours;

            if ($hours < 0) {
                continue; // Skip negative values
            }

            // Check if budget exists for this year
            $checkBudgetStmt = $pdo->prepare("SELECT Id FROM WbsoBudget WHERE WbsoId = ? AND Year = ?");
            $checkBudgetStmt->execute([$wbsoId, $year]);
            $existingBudget = $checkBudgetStmt->fetch(PDO::FETCH_ASSOC);

            if ($hours > 0) {
                if ($existingBudget) {
                    // Update existing budget
                    $updateBudgetStmt = $pdo->prepare("UPDATE WbsoBudget SET Hours = ? WHERE WbsoId = ? AND Year = ?");
                    $updateBudgetStmt->execute([$hours, $wbsoId, $year]);
                } else {
                    // Insert new budget
                    $insertBudgetStmt = $pdo->prepare("INSERT INTO WbsoBudget (WbsoId, Year, Hours) VALUES (?, ?, ?)");
                    $insertBudgetStmt->execute([$wbsoId, $year, $hours]);
                }
            } else {
                // If hours is 0 and budget exists, delete it
                if ($existingBudget) {
                    $deleteBudgetStmt = $pdo->prepare("DELETE FROM WbsoBudget WHERE WbsoId = ? AND Year = ?");
                    $deleteBudgetStmt->execute([$wbsoId, $year]);
                }
            }
        }

        $successMessage = 'Budgets updated successfully.';

        // Redirect back to edit page
        header("Location: wbso.php?edit=$wbsoId&success=budget_saved");
        exit;
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
    } elseif ($action === 'budget_saved') {
        $successMessage = 'Budget updated successfully.';
    } elseif ($action === 'budget_deleted') {
        $successMessage = 'Budget deleted successfully.';
    }
}

// Fetch all WBSO entries with budget info for current year
$stmt = $pdo->prepare("SELECT w.Id, w.Name, w.Description, w.StartDate, w.EndDate,
    COUNT(DISTINCT Activities.Id) AS UsageCount,
    COALESCE(SUM(Hours.Hours), 0) AS HoursLogged,
    (SELECT wb2.Hours FROM WbsoBudget wb2 WHERE wb2.WbsoId = w.Id AND wb2.Year = :selectedYear) AS BudgetHours
    FROM Wbso w
    LEFT JOIN Activities ON Activities.Wbso = w.Id
    LEFT JOIN Hours ON Hours.Project = Activities.Project AND Hours.Activity = Activities.Key AND Hours.Year = :selectedYear
    GROUP BY w.Id, w.Name, w.Description, w.StartDate, w.EndDate
    ORDER BY w.Name DESC");
$stmt->execute(['selectedYear' => $selectedYear]);
$wbsoEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If we're still here, check if output has started before sending headers
if (isset($successMessage) && !empty($successMessage) && ob_get_length() === 0) {
    header("Location: wbso.php");
    exit;
}
?>

<section class="white">
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
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Budget (<?= $selectedYear ?>)</th>
                                        <th>Hours Logged (<?= $selectedYear ?>)</th>
                                        <th>Usage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $totalBudget = 0;
                                    $totalLogged = 0;
                                    foreach ($wbsoEntries as $entry):
                                        $totalBudget += $entry['BudgetHours'] ?? 0;
                                        $hourslogged = ($entry['HoursLogged'] ?? 0) / 100;
                                        $totalLogged += $hourslogged;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($entry['Name']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['Description'] ?? ''); ?></td>
                                            <td>
                                                <?php
                                                if (!empty($entry['StartDate'])) {
                                                    echo date('d-m-Y', strtotime($entry['StartDate']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if (!empty($entry['EndDate'])) {
                                                    echo date('d-m-Y', strtotime($entry['EndDate']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo number_form($entry['BudgetHours'] ?? 0); ?></td>
                                            <td><?= number_form($hourslogged) ?></td>
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
                                    <tr>
                                        <td></td>
                                        <td>Totals</td>
                                        <td></td>
                                        <td></td>
                                        <td><?= number_form($totalBudget) ?></td>
                                        <td><?= number_form($totalLogged) ?></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
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
                                <input type="text" name="description" id="description" class="form-control"
                                       value="<?php echo htmlspecialchars($editEntry['Description'] ?? ''); ?>">
                                <small class="form-text text-muted">Optional detailed description (max 64 characters)</small>
                            </div>

                            <div class="form-group">
                                <label for="start_date">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required
                                       value="<?php echo !empty($editEntry['StartDate']) ? date('Y-m-d', strtotime($editEntry['StartDate'])) : ''; ?>">
                                <small class="form-text text-muted">Start date for this WBSO category</small>
                            </div>

                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                       value="<?php echo !empty($editEntry['EndDate']) ? date('Y-m-d', strtotime($editEntry['EndDate'])) : ''; ?>">
                                <small class="form-text text-muted">End date for this WBSO category (optional)</small>
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

                <!-- Budget Management (only when editing) -->
                <?php if ($editId): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h3>Yearly Budgets</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Set budget hours for each year this WBSO is active
                            (<?= $startYear ?> to <?= $endYear ?>).
                            Leave blank or set to 0 to remove a budget.
                        </p>

                        <form method="POST">
                            <input type="hidden" name="wbso_id" value="<?= $editId ?>">

                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Year</th>
                                        <th>Budget Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeYears as $year): ?>
                                    <tr>
                                        <td><strong><?= $year ?></strong></td>
                                        <td>
                                            <input type="number"
                                                   name="year_budgets[<?= $year ?>]"
                                                   class="form-control form-control-sm"
                                                   value="<?= $budgetByYear[$year] ?? '' ?>"
                                                   min="0"
                                                   placeholder="Hours">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <button type="submit" name="save_budgets" class="btn btn-success">Save All Budgets</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Information Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Information</h3>
                    </div>
                    <div class="card-body">
                        <p>WBSO categories are used to classify activities for the Dutch R&D tax credit scheme (WBSO).</p>
                        <p>Each category has a name, description, and validity period (start and end dates).</p>
                        <p>Budget hours are managed per year separately. The table shows budgets for the currently selected year (<?= $selectedYear ?>).</p>
                        <p>Categories that are used in activities cannot be deleted.</p>
                        <p><a href="wbso_overview.php" class="btn btn-sm btn-info">View WBSO Overview</a></p>
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