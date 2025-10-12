<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Validate project ID and redirect if not provided
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    header('Location: index.php');
    exit;
}

$pageSpecificCSS = ['page-project-edit.css'];
require 'includes/header.php';

// Initialize variables
$projectId = null;
$project = null;
$activities = [];
$statuses = [];
$managers = [];
$wbsoOptions = [];
$redirectNeeded = false;
$redirectUrl = '';

$projectId = $_GET['project_id'];

// Fetch the project details
$projectStmt = $pdo->prepare("SELECT * FROM Projects WHERE Id = ?");
$projectStmt->execute([$projectId]);
$project = $projectStmt->fetch(PDO::FETCH_ASSOC);

// If project is not found
if (!$project) {
    echo 'Project not found.';
    require 'includes/footer.php';
    ob_end_flush();
    exit;
}

// Fetch the activities for the project with budget information and usage check
$activityStmt = $pdo->prepare("
    SELECT Activities.*, 
           Budgets.Hours AS BudgetHours, 
           Budgets.Budget, 
           Budgets.OopSpend, 
           Budgets.Rate,
           Wbso.Name AS WbsoName,
           (SELECT COUNT(*) FROM Hours WHERE Project = Activities.Project AND Activity = Activities.Key AND (Hours>0 OR Plan>0)) as HoursCount,
           (SELECT COUNT(*) FROM TeamHours WHERE Project = Activities.Project AND Activity = Activities.Key AND (Hours>0 OR Plan>0)) as TeamHoursCount
    FROM Activities 
    LEFT JOIN Budgets ON Activities.Id = Budgets.Activity AND Budgets.`Year` = ? 
    LEFT JOIN Wbso ON Activities.Wbso = Wbso.Id
    WHERE Project = ?
    ORDER BY Activities.Key ASC
");
$activityStmt->execute([$selectedYear, $projectId]);
$activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch status options
$statusStmt = $pdo->query("SELECT * FROM Status");
$statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch project managers
$managerStmt = $pdo->query("SELECT Id, Shortname AS Name FROM Personel WHERE Type>2");
$managers = $managerStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch WBSO options
$wbsoStmt = $pdo->query("SELECT Id, Name, Description FROM Wbso");
$wbsoOptions = $wbsoStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission to update the project status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];

    $updateStatusStmt = $pdo->prepare("UPDATE Projects SET Status = ? WHERE Id = ?");
    $updateStatusStmt->execute([$newStatus, $projectId]);

    $updateStatusStmt = $pdo->prepare("UPDATE Activities SET Export = 1 WHERE Project = ?");
    $updateStatusStmt->execute([$projectId]);

    $redirectNeeded = true;
    $redirectUrl = "project_edit.php?project_id=" . $projectId;
}

// Handle form submission to update the project manager
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_manager'])) {
    $newManager = $_POST['manager'];

    $updateManagerStmt = $pdo->prepare("UPDATE Projects SET Manager = ? WHERE Id = ?");
    $updateManagerStmt->execute([$newManager, $projectId]);

    $redirectNeeded = true;
    $redirectUrl = "project_edit.php?project_id=" . $projectId;
}

// Handle bulk activity updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_activities'])) {
    $activityIds = $_POST['activity_id'] ?? [];
    $activityNames = $_POST['activity_name'] ?? [];
    $startDates = $_POST['start_date'] ?? [];
    $endDates = $_POST['end_date'] ?? [];
    $wbsos = $_POST['wbso'] ?? [];
    $visibles = $_POST['visible'] ?? [];
    $isTasks = $_POST['is_task'] ?? [];
    $isActives = $_POST['is_active'] ?? [];
    
    foreach ($activityIds as $index => $activityId) {
        $name = $activityNames[$index] ?? '';
        $startDate = $startDates[$index] ?? null;
        $endDate = $endDates[$index] ?? null;
        $wbso = !empty($wbsos[$index]) ? $wbsos[$index] : null;
        $visible = in_array($activityId, $visibles) ? 1 : 0;
        $isTask = in_array($activityId, $isTasks) ? 1 : 0;
        $isActive = in_array($activityId, $isActives) ? 1 : 0;

        $updateStmt = $pdo->prepare("UPDATE Activities SET Name = ?, StartDate = ?, EndDate = ?, Wbso = ?, Visible = ?, IsTask = ?, Active = ?, Export = 1 WHERE Id = ?");
        $updateStmt->execute([$name, $startDate, $endDate, $wbso, $visible, $isTask, $isActive, $activityId]);
    }

    $redirectNeeded = true;
    $redirectUrl = "project_edit.php?project_id=" . $projectId;
}

// Handle bulk budget updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_budgets'])) {
    $activityIds = $_POST['budget_activity_id'] ?? [];
    $budgets = $_POST['budget'] ?? [];
    $oopSpends = $_POST['oop_spend'] ?? [];
    $rates = $_POST['rate'] ?? [];
    $hours = $_POST['hours'] ?? [];
    
    foreach ($activityIds as $index => $activityId) {
        $budget = isset($budgets[$index]) && is_numeric($budgets[$index]) && $budgets[$index] !== '' ? (int)$budgets[$index] : 0;
        $oopSpend = isset($oopSpends[$index]) && is_numeric($oopSpends[$index]) && $oopSpends[$index] !== '' ? (int)$oopSpends[$index] : 0;
        $rate = isset($rates[$index]) && is_numeric($rates[$index]) && $rates[$index] !== '' ? (int)$rates[$index] : 0;
        $hour = isset($hours[$index]) && is_numeric($hours[$index]) && $hours[$index] !== '' ? (int)$hours[$index] : 0;

        // Check if a budget record exists for this activity
        $checkBudgetStmt = $pdo->prepare("SELECT Id FROM Budgets WHERE Activity = ? AND `Year` = ?");
        $checkBudgetStmt->execute([$activityId, $selectedYear]);
        $existingBudget = $checkBudgetStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingBudget) {
            // Update existing budget
            $updateBudgetStmt = $pdo->prepare("
                UPDATE Budgets SET Budget = ?, OopSpend = ?, Hours = ?, Rate = ?
                WHERE Activity = ? AND `Year` = ?
            ");
            $updateBudgetStmt->execute([$budget, $oopSpend, $hour, $rate, $activityId, $selectedYear]);
        } else {
            // Insert new budget
            $insertBudgetStmt = $pdo->prepare("
                INSERT INTO Budgets (Activity, Budget, OopSpend, Hours, Rate, `Year`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertBudgetStmt->execute([$activityId, $budget, $oopSpend, $hour, $rate, $selectedYear]);
        }
    }
    
    $redirectNeeded = true;
    $redirectUrl = "project_edit.php?project_id=" . $projectId;
}

// Add a new activity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $activityName = $_POST['new_activity_name'];
    $startDate = $_POST['new_start_date'];
    $endDate = $_POST['new_end_date'];
    $wbso = $_POST['new_wbso'] !== '' ? $_POST['new_wbso'] : null;
    $visible = isset($_POST['new_visible']) ? 1 : 0;
    $newIsTask = isset($_POST['new_is_task']) ? 1 : 0;
    $addBudget = isset($_POST['add_budget']) ? 1 : 0;

    $keyStmt = $pdo->prepare("SELECT MAX(`Key`) as MaxKey FROM Activities WHERE `Key` < 999 AND Project = ?");
    $keyStmt->execute([$projectId]);
    $maxKeyRow = $keyStmt->fetch(PDO::FETCH_ASSOC);
    $nextKey = $maxKeyRow && $maxKeyRow['MaxKey'] !== null ? $maxKeyRow['MaxKey'] + 1 : 1;

    $insertStmt = $pdo->prepare("
        INSERT INTO Activities (Project, `Key`, Name, StartDate, EndDate, Wbso, Visible, IsTask, Export)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $insertStmt->execute([
        $projectId,
        $nextKey,
        $activityName,
        $startDate,
        $endDate,
        $wbso,
        $visible,
        $newIsTask
    ]);
    
    $newActivityId = $pdo->lastInsertId();
    
    if ($addBudget) {
        $budget = $_POST['budget'] ?? 0;
        $oopSpend = $_POST['oop_spend'] ?? 0;
        $rate = $_POST['rate'] ?? 0;
        $hours = $_POST['hours'] ?? 0;
        
        $insertBudgetStmt = $pdo->prepare("
            INSERT INTO Budgets (Activity, Budget, OopSpend, Hours, Rate, Year)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insertBudgetStmt->execute([
            $newActivityId,
            $budget,
            $oopSpend,
            $hours,
            $rate,
            $selectedYear
        ]);
    }
    
    $redirectNeeded = true;
    $redirectUrl = "project_edit.php?project_id=" . $projectId;
}

// Handle activity deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity'])) {
    $activityId = $_POST['activity_id'];
    $activityKey = $_POST['activity_key'];
    
    // Check if activity can be deleted
    $checkStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM Hours WHERE Project = :project AND Activity = :activityKey AND (Hours>0 OR Plan>0)) as HoursCount,
            (SELECT COUNT(*) FROM TeamHours WHERE Project = :project AND Activity = :activityKey AND (Hours>0 OR Plan>0)) as TeamHoursCount,
            IsExported
        FROM Activities WHERE Id = :activityId
    ");
    $checkStmt->execute([
        ':project' => $projectId,
        ':activityId' => $activityId,
        ':activityKey' => $activityKey
    ]);
    $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    $canDelete = ($checkResult['HoursCount'] == 0 && 
                  $checkResult['TeamHoursCount'] == 0 && 
                  $checkResult['IsExported'] == 0);
    
    if ($canDelete) {
        $deleteBudgetStmt = $pdo->prepare("DELETE FROM Budgets WHERE Activity = ?");
        $deleteBudgetStmt->execute([$activityId]);
        $deleteActivityStmt = $pdo->prepare("DELETE FROM Activities WHERE Id = ?");
        $deleteActivityStmt->execute([$activityId]);
    }
    
    $redirectNeeded = true;
    $redirectUrl = "project_edit.php?project_id=" . $projectId;
}

// Perform redirect if needed
if ($redirectNeeded && ob_get_length() === 0) {
    header("Location: " . $redirectUrl);
    exit;
}
?>
<section class="white">
    <div class="container">
        <?php if ($redirectNeeded): ?>
        <script>
            window.location.href = "<?= htmlspecialchars($redirectUrl); ?>";
        </script>
        <?php endif; ?>

        <!-- Project Header -->
        <div class="project-header">
            <h1><?= $project['Id'] ?> - <?= htmlspecialchars($project['Name']); ?></h1>
        </div>

        <!-- Status and Manager -->
        <div class="status-manager-row">
            <div class="info-card">
                <form method="POST" class="form-inline-custom">
                    <label for="status">Status:</label>
                    <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status['Id']; ?>" <?= $status['Id'] == $project['Status'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($status['Status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="update_status" value="1">
                </form>
            </div>
            <div class="info-card">
                <form method="POST" class="form-inline-custom">
                    <label for="manager">Project Manager:</label>
                    <select name="manager" id="manager" class="form-control" onchange="this.form.submit()">
                        <?php if ($project['Manager'] == null): ?>
                            <option value="null" selected>Not set</option>
                        <?php endif; ?>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?= $manager['Id']; ?>" <?= $manager['Id'] == $project['Manager'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($manager['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="update_manager" value="1">
                </form>
            </div>
        </div>

        <!-- Activities Section -->
        <div class="activities-section">
            <h2>Activities</h2>

            <form method="POST" id="bulkActivityForm">
                <div class="table-wrapper">
                    <table class="activities-table">
                        <thead>
                        <tr>
                            <th>Code</th>
                            <th style="min-width: 200px;">Activity Name</th>
                            <th style="min-width: 150px;">WBSO</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Visible</th>
                            <th>Task</th>
                            <th>Active</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($activities as $activity): 
                            $canDelete = ($activity['HoursCount'] == 0 && 
                                         $activity['TeamHoursCount'] == 0 && 
                                         $activity['IsExported'] == 0);
                        ?>
                            <tr class="activity-row">
                                <input type="hidden" name="activity_id[]" value="<?= $activity['Id']; ?>">
                                <td class="task-code"><?= $activity['Project'] . '-' . str_pad($activity['Key'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <input type="text" name="activity_name[]" value="<?= htmlspecialchars($activity['Name']); ?>" 
                                           onchange="markRowModified(this, 'activity')">
                                </td>
                                <td>
                                    <select name="wbso[]" onchange="markRowModified(this, 'activity')">
                                        <option value="">-- None --</option>
                                        <?php foreach ($wbsoOptions as $wbsoOption): ?>
                                            <option value="<?= $wbsoOption['Id']; ?>" 
                                                    <?= $activity['Wbso'] == $wbsoOption['Id'] ? 'selected' : ''; ?>
                                                    title="<?= htmlspecialchars($wbsoOption['Description'] ?? ''); ?>">
                                                <?= htmlspecialchars($wbsoOption['Name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="date" name="start_date[]" value="<?= $activity['StartDate'] ?>" 
                                           onchange="markRowModified(this, 'activity')">
                                </td>
                                <td>
                                    <input type="date" name="end_date[]" value="<?= $activity['EndDate'] ?>" 
                                           onchange="markRowModified(this, 'activity')">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="visible[]" value="<?= $activity['Id']; ?>" 
                                           <?= $activity['Visible'] ? 'checked' : ''; ?> 
                                           onchange="markRowModified(this, 'activity')">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="is_task[]" value="<?= $activity['Id'] ?>" 
                                           <?= $activity['IsTask'] ? 'checked' : ''; ?> 
                                           onchange="markRowModified(this, 'activity')">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="is_active[]" value="<?= $activity['Id'] ?>" 
                                           <?= $activity['Active'] ? 'checked' : ''; ?> 
                                           onchange="markRowModified(this, 'activity')">
                                </td>
                                <td>
                                    <button type="button" class="btn-delete" 
                                            onclick="confirmDelete(<?= $activity['Id'] ?>, <?= $activity['Key'] ?>)"
                                            <?= !$canDelete ? 'disabled title="Cannot delete: Activity is in use"' : ''; ?>>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; align-items: center; margin-top: 1rem;">
                    <button type="submit" name="bulk_update_activities" class="btn-save-all" id="saveActivitiesBtn" disabled>
                        Save All Activities
                    </button>
                    <span class="save-indicator" id="activityIndicator">
                        <span>●</span>
                        <span id="activityModifiedCount">0</span> row(s) modified
                    </span>
                </div>
            </form>
        </div>

        <!-- Budgets Section -->
        <div class="activities-section">
            <h2>Budgets for <?= $selectedYear; ?></h2>

            <form method="POST" id="bulkBudgetForm">
                <div class="table-wrapper">
                    <table class="activities-table">
                        <thead>
                        <tr>
                            <th>Code</th>
                            <th style="min-width: 200px;">Activity Name</th>
                            <th style="min-width: 120px;">Budget (€)</th>
                            <th style="min-width: 120px;">OOP Spend (€)</th>
                            <th style="min-width: 120px;">Rate (€/h)</th>
                            <th style="min-width: 120px;">Hours</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr class="budget-row">
                                <input type="hidden" name="budget_activity_id[]" value="<?= $activity['Id']; ?>">
                                <td class="task-code"><?= $activity['Project'] . '-' . str_pad($activity['Key'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?= htmlspecialchars($activity['Name']); ?></td>
                                <td>
                                    <input type="number" name="budget[]" 
                                           value="<?= $activity['Budget'] ?? 0; ?>"
                                           data-row-index="<?= $activity['Id']; ?>"
                                           onchange="calculateBudget(this, 'budget'); markRowModified(this, 'budget');">
                                </td>
                                <td>
                                    <input type="number" name="oop_spend[]" 
                                           value="<?= $activity['OopSpend'] ?? 0; ?>"
                                           data-row-index="<?= $activity['Id']; ?>"
                                           onchange="calculateBudget(this, 'oop'); markRowModified(this, 'budget');">
                                </td>
                                <td>
                                    <input type="number" name="rate[]" 
                                           value="<?= $activity['Rate'] ?? 0; ?>"
                                           data-row-index="<?= $activity['Id']; ?>"
                                           onchange="calculateBudget(this, 'rate'); markRowModified(this, 'budget');">
                                </td>
                                <td>
                                    <input type="number" name="hours[]" 
                                           value="<?= $activity['BudgetHours'] ?? 0; ?>"
                                           data-row-index="<?= $activity['Id']; ?>"
                                           onchange="calculateBudget(this, 'hours'); markRowModified(this, 'budget');">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; align-items: center; margin-top: 1rem;">
                    <button type="submit" name="bulk_update_budgets" class="btn-save-all" id="saveBudgetsBtn" disabled>
                        Save All Budgets
                    </button>
                    <span class="save-indicator" id="budgetIndicator">
                        <span>●</span>
                        <span id="budgetModifiedCount">0</span> row(s) modified
                    </span>
                </div>
            </form>
        </div>

        <!-- Add New Activity -->
        <div class="activities-section">
            <div class="add-activity-form">
                <h3>Add New Activity</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_activity_name">Activity Name *</label>
                            <input type="text" name="new_activity_name" id="new_activity_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="new_start_date">Start Date *</label>
                            <input type="date" name="new_start_date" id="new_start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="new_end_date">End Date *</label>
                            <input type="date" name="new_end_date" id="new_end_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="new_wbso">WBSO</label>
                            <select name="new_wbso" id="new_wbso" class="form-control">
                                <option value="">-- None --</option>
                                <?php foreach ($wbsoOptions as $wbsoOption): ?>
                                    <option value="<?= $wbsoOption['Id']; ?>" 
                                            title="<?= htmlspecialchars($wbsoOption['Description'] ?? ''); ?>">
                                        <?= htmlspecialchars($wbsoOption['Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group checkbox-group">
                            <input type="checkbox" name="new_visible" id="new_visible" value="1" checked>
                            <label for="new_visible" style="margin: 0;">Visible</label>
                        </div>
                        <div class="form-group checkbox-group">
                            <input type="checkbox" name="new_is_task" id="new_is_task" value="1" checked>
                            <label for="new_is_task" style="margin: 0;">Is Task</label>
                        </div>
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="add_budget" name="add_budget" value="1" onchange="toggleBudgetSection()">
                            <label for="add_budget" style="margin: 0;">Add Budget</label>
                        </div>
                    </div>
                    
                    <div id="budget_section" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                        <h4 style="font-size: 1rem; margin-bottom: 1rem;">Budget Details</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="budget">Budget (€)</label>
                                <input type="number" id="budget" name="budget" class="form-control" onchange="calculateNewBudget('budget')">
                            </div>
                            <div class="form-group">
                                <label for="oop_spend">Operational Spending (€)</label>
                                <input type="number" id="oop_spend" name="oop_spend" class="form-control" onchange="calculateNewBudget('oop')">
                            </div>
                            <div class="form-group">
                                <label for="rate">Hour Rate (€)</label>
                                <input type="number" id="rate" name="rate" class="form-control" onchange="calculateNewBudget('rate')">
                            </div>
                            <div class="form-group">
                                <label for="hours">Budget Hours</label>
                                <input type="number" id="hours" name="hours" class="form-control" onchange="calculateNewBudget('hours')">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_activity" class="btn-add-activity" style="margin-top: 1rem;">
                        Add Activity
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
let modifiedActivityRows = new Set();
let modifiedBudgetRows = new Set();

function markRowModified(element, type) {
    const row = element.closest('tr');
    const rowIndex = Array.from(row.parentElement.children).indexOf(row);
    
    row.classList.add('modified');
    
    if (type === 'activity') {
        modifiedActivityRows.add(rowIndex);
        updateSaveButton('activity');
    } else if (type === 'budget') {
        modifiedBudgetRows.add(rowIndex);
        updateSaveButton('budget');
    }
}

function updateSaveButton(type) {
    if (type === 'activity') {
        const saveBtn = document.getElementById('saveActivitiesBtn');
        const indicator = document.getElementById('activityIndicator');
        const countSpan = document.getElementById('activityModifiedCount');
        
        if (modifiedActivityRows.size > 0) {
            saveBtn.disabled = false;
            indicator.classList.add('show');
            countSpan.textContent = modifiedActivityRows.size;
        } else {
            saveBtn.disabled = true;
            indicator.classList.remove('show');
        }
    } else if (type === 'budget') {
        const saveBtn = document.getElementById('saveBudgetsBtn');
        const indicator = document.getElementById('budgetIndicator');
        const countSpan = document.getElementById('budgetModifiedCount');
        
        if (modifiedBudgetRows.size > 0) {
            saveBtn.disabled = false;
            indicator.classList.add('show');
            countSpan.textContent = modifiedBudgetRows.size;
        } else {
            saveBtn.disabled = true;
            indicator.classList.remove('show');
        }
    }
}

function confirmDelete(activityId, activityKey) {
    if (confirm('Are you sure you want to delete this activity?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_activity" value="1">
            <input type="hidden" name="activity_id" value="${activityId}">
            <input type="hidden" name="activity_key" value="${activityKey}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleBudgetSection() {
    const addBudgetChecked = document.getElementById('add_budget').checked;
    const budgetSection = document.getElementById('budget_section');
    
    if (addBudgetChecked) {
        budgetSection.style.display = 'block';
    } else {
        budgetSection.style.display = 'none';
    }
}

function calculateBudget(element, changedField) {
    const row = element.closest('tr');
    const rowIndex = element.getAttribute('data-row-index');
    
    // Get all inputs in this row
    const inputs = row.querySelectorAll('input[type="number"]');
    let budget, oopSpend, rate, hours;
    
    inputs.forEach(input => {
        const name = input.name;
        if (name === 'budget[]') budget = input;
        else if (name === 'oop_spend[]') oopSpend = input;
        else if (name === 'rate[]') rate = input;
        else if (name === 'hours[]') hours = input;
    });
    
    const budgetValue = parseFloat(budget.value) || 0;
    const oopValue = parseFloat(oopSpend.value) || 0;
    const rateValue = parseFloat(rate.value) || 0;
    const hoursValue = parseFloat(hours.value) || 0;
    
    switch(changedField) {
        case 'budget':
            if (budgetValue > 0) {
                if (rateValue > 0) {
                    hours.value = Math.round((budgetValue - oopValue) / rateValue);
                } else if (hoursValue > 0) {
                    rate.value = Math.round((budgetValue - oopValue) / hoursValue);
                }
            }
            break;
        case 'oop':
            if (rateValue > 0 && budgetValue > 0) {
                hours.value = Math.round((budgetValue - oopValue) / rateValue);
            }
            break;
        case 'rate':
            if (rateValue > 0 && budgetValue > 0) {
                hours.value = Math.round((budgetValue - oopValue) / rateValue);
            }
            break;
        case 'hours':
            if (hoursValue > 0 && budgetValue > 0) {
                rate.value = Math.round((budgetValue - oopValue) / hoursValue);
            }
            break;
    }
}

function calculateNewBudget(changedField) {
    const budget = document.getElementById('budget');
    const oopSpend = document.getElementById('oop_spend');
    const rate = document.getElementById('rate');
    const hours = document.getElementById('hours');
    
    const budgetValue = parseFloat(budget.value) || 0;
    const oopValue = parseFloat(oopSpend.value) || 0;
    const rateValue = parseFloat(rate.value) || 0;
    const hoursValue = parseFloat(hours.value) || 0;
    
    switch(changedField) {
        case 'budget':
            if (budgetValue > 0) {
                if (rateValue > 0) {
                    hours.value = Math.round((budgetValue - oopValue) / rateValue);
                } else if (hoursValue > 0) {
                    rate.value = Math.round((budgetValue - oopValue) / hoursValue);
                }
            }
            break;
        case 'oop':
            if (rateValue > 0 && budgetValue > 0) {
                hours.value = Math.round((budgetValue - oopValue) / rateValue);
            }
            break;
        case 'rate':
            if (rateValue > 0 && budgetValue > 0) {
                hours.value = Math.round((budgetValue - oopValue) / rateValue);
            }
            break;
        case 'hours':
            if (hoursValue > 0 && budgetValue > 0) {
                rate.value = Math.round((budgetValue - oopValue) / hoursValue);
            }
            break;
    }
}
</script>

<?php 
// End output buffering and flush
ob_end_flush();
require 'includes/footer.php'; 
?>