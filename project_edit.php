<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Validate project ID and redirect if not provided
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    header('Location: index.php');
    exit;
}

require 'includes/header.php';
require 'includes/db.php';

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
    ob_end_flush(); // Flush the buffer and end it
    exit;
}

// Fetch the activities for the project with budget information
$activityStmt = $pdo->prepare("
    SELECT Activities.*, Budgets.Hours AS BudgetHours, Budgets.Budget, Budgets.OopSpend, Budgets.Rate,
            Wbso.Name AS WbsoName
    FROM Activities 
    LEFT JOIN Budgets ON Activities.Id = Budgets.Activity AND Budgets.`Year` = ? 
    LEFT JOIN Wbso ON Activities.Wbso = Wbso.Id
    WHERE Project = ?
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

    // Update the project status
    $updateStatusStmt = $pdo->prepare("UPDATE Projects SET Status = ? WHERE Id = ?");
    $updateStatusStmt->execute([$newStatus, $projectId]);

    $updateStatusStmt = $pdo->prepare("UPDATE Activities SET Export = 1 WHERE Project = ?");
    $updateStatusStmt->execute([$projectId]);

    // Set redirect flag instead of immediate redirect
    $redirectNeeded = true;
    $redirectUrl = "project_edit.php?project_id=" . $projectId;
}

// Handle form submission to update the project manager
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_manager'])) {
    $newManager = $_POST['manager'];

    // Update the project manager
    $updateManagerStmt = $pdo->prepare("UPDATE Projects SET Manager = ? WHERE Id = ?");
    $updateManagerStmt->execute([$newManager, $projectId]);

    // Set redirect flag instead of immediate redirect
    $redirectNeeded = true;
    $redirectUrl = "project_edit.php?project_id=" . $projectId;
}

// Handle form submission to update or add activities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_activity'])) {
        $activityId = $_POST['activity_id'];
        $name = $_POST['activity_name'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $wbso = $_POST['wbso'] !== '' ? $_POST['wbso'] : null;
        $visible = isset($_POST['visible']) ? 1 : 0;
        $isTask = isset($_POST['is_task']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $updateStmt = $pdo->prepare("UPDATE Activities SET Name = ?, StartDate = ?, EndDate = ?, Wbso = ?, Visible = ?, IsTask = ?, Active = ?, Export = 1 WHERE Id = ?");
        $updateStmt->execute([$name, $startDate, $endDate, $wbso, $visible, $isTask, $isActive, $activityId]);

        // Set redirect flag instead of immediate redirect
        $redirectNeeded = true;
        $redirectUrl = "project_edit.php?project_id=" . $projectId;
    }
    // Update budget
    elseif (isset($_POST['update_budget'])) {
        $activityId = $_POST['activity_id'];
        $newYear = $_POST['year'];
        $budget = isset($_POST['budget']) && is_numeric($_POST['budget']) && $_POST['budget'] !== '' ? (int)$_POST['budget'] : 0;
        $oopSpend = isset($_POST['oop_spend']) && is_numeric($_POST['oop_spend']) && $_POST['oop_spend'] !== '' ? (int)$_POST['oop_spend'] : 0;
        $rate = isset($_POST['rate']) && is_numeric($_POST['rate']) && $_POST['rate'] !== '' ? (int)$_POST['rate'] : 0;
        $hours = isset($_POST['hours']) && is_numeric($_POST['hours']) && $_POST['hours'] !== '' ? (int)$_POST['hours'] : 0;

        // Check if a budget record exists for this activity
        $checkBudgetStmt = $pdo->prepare("SELECT Id FROM Budgets WHERE Activity = ? AND `Year` = ?");
        $checkBudgetStmt->execute([$activityId, $newYear]);
        $existingBudget = $checkBudgetStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingBudget) {
            // Update existing budget
            $updateBudgetStmt = $pdo->prepare("
                UPDATE Budgets SET Budget = ?, OopSpend = ?, Hours = ?, Rate = ?
                WHERE Activity = ? AND `Year` = ?
            ");
            $updateBudgetStmt->execute([$budget, $oopSpend, $hours, $rate, $activityId, $newYear]);
        } else {
            // Insert new budget
            $insertBudgetStmt = $pdo->prepare("
                INSERT INTO Budgets (Activity, Budget, OopSpend, Hours, Rate, `Year`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertBudgetStmt->execute([$activityId, $budget, $oopSpend, $hours, $rate, $newYear]);
        }
        
        // Set redirect flag instead of immediate redirect
        $redirectNeeded = true;
        $redirectUrl = "project_edit.php?project_id=" . $projectId;
    }
    // Add a new activity
    elseif (isset($_POST['add_activity'])) {
        $activityName = $_POST['new_activity_name'];
        $startDate = $_POST['new_start_date'];
        $endDate = $_POST['new_end_date'];
        $wbso = $_POST['new_wbso'] !== '' ? $_POST['new_wbso'] : null;
        $visible = isset($_POST['new_visible']) ? 1 : 0;
        $newIsTask = isset($_POST['new_is_task']) ? 1 : 0;
        $addBudget = isset($_POST['add_budget']) ? 1 : 0;

        // Find the next available Key for the project
        $keyStmt = $pdo->prepare("SELECT MAX(`Key`) as MaxKey FROM Activities WHERE Project = ?");
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
        
        // Get the newly inserted activity ID
        $newActivityId = $pdo->lastInsertId();
        
        // Add budget if checkbox is checked
        if ($addBudget) {
            $budget = $_POST['budget'] ?? 0;
            $oopSpend = $_POST['oop_spend'] ?? 0;
            $rate = $_POST['rate'] ?? 0;
            $hours = $_POST['hours'] ?? 0;
            
            // Insert into Budgets table
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
        
        // Set redirect flag instead of immediate redirect
        $redirectNeeded = true;
        $redirectUrl = "project_edit.php?project_id=" . $projectId;
    }
}

// Perform redirect if needed and it's before any output
if ($redirectNeeded && ob_get_length() === 0) {
    header("Location: " . $redirectUrl);
    exit;
}

// If we couldn't redirect with header (output already sent), we'll use JavaScript later
?>

<section class="white">
    <div class="container">
        <?php if ($redirectNeeded): ?>
        <script>
            // JavaScript redirect as fallback if PHP header redirect fails
            window.location.href = "<?php echo htmlspecialchars($redirectUrl); ?>";
        </script>
        <?php endif; ?>

        <!-- Project Information -->
        <h1>Project Details: <?php echo htmlspecialchars($project['Name']); ?></h1>

        <!-- Status and Project Manager Dropdowns (side by side) -->
        <div class="row mb-3">
            <div class="col-md-6">
                <form method="POST" class="form-inline">
                    <label for="status">Status: </label>
                    <select name="status" id="status" class="form-control ml-2" onchange="this.form.submit()">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status['Id']; ?>" <?php echo $status['Id'] == $project['Status'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['Status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="update_status" value="1">
                </form>
            </div>
            <div class="col-md-6">
                <form method="POST" class="form-inline">
                    <label for="manager">Project Manager: </label>
                    <select name="manager" id="manager" class="form-control ml-2" onchange="this.form.submit()">
                        <?php if ($project['Manager'] == null): ?>
                            <option value="null" selected >Not set</option>
                        <?php endif; ?>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?php echo $manager['Id']; ?>" <?php echo $manager['Id'] == $project['Manager'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($manager['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="update_manager" value="1">
                </form>
            </div>
        </div>

        <hr>

        <!-- Edit Activities -->
        <h2>Edit Activities</h2>

        <!-- Activities List -->
        <table class="table table-striped">
            <thead>
            <tr>
                <th>TaskCode</th>
                <th>Activity Name</th>
                <th>WBSO</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Budget Hours</th>
                <th>Visible</th>
                <th>Is Task</th>
                <th>Active</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($activities as $activity): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="activity_id" value="<?php echo $activity['Id']; ?>">
                        <td><?php echo $activity['Project'] . '-' . str_pad($activity['Key'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td><input type="text" name="activity_name" value="<?php echo htmlspecialchars($activity['Name']); ?>" class="form-control"></td>
                        <td>
                            <select name="wbso" class="form-control">
                                <option value="">-- None --</option>
                                <?php foreach ($wbsoOptions as $wbsoOption): ?>
                                    <option value="<?php echo $wbsoOption['Id']; ?>" 
                                            <?php echo $activity['Wbso'] == $wbsoOption['Id'] ? 'selected' : ''; ?>
                                            title="<?php echo htmlspecialchars($wbsoOption['Description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($wbsoOption['Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="date" name="start_date" value="<?php echo $activity['StartDate']; ?>" class="form-control"></td>
                        <td><input type="date" name="end_date" value="<?php echo $activity['EndDate']; ?>" class="form-control"></td>
                        <td>
                                <a href="#" class="budget-link" data-activity-id="<?php echo $activity['Id']; ?>" 
                                   data-year="<?= $selectedYear ?>" 
                                   data-budget="<?php echo $activity['Budget'] ?? 0; ?>" 
                                   data-oopspend="<?php echo $activity['OopSpend'] ?? 0; ?>" 
                                   data-rate="<?php echo $activity['Rate'] ?? 0; ?>" 
                                   data-hours="<?php echo $activity['BudgetHours'] ?? 0; ?>"
                                   onclick="showBudgetModal(this)">
                                   <?php echo $activity['BudgetHours'] ?? 'Add budget'; ?>
                                </a>
                        </td>
                        <td><input type="checkbox" name="visible" value="1" <?php echo $activity['Visible'] ? 'checked' : ''; ?>></td>
                        <td><input type="checkbox" name="is_task" value="1" <?php echo $activity['IsTask'] ? 'checked' : ''; ?>></td>
                        <td><input type="checkbox" name="is_active" value="1" <?php echo $activity['Active'] ? 'checked' : ''; ?>></td>
                        <td><button type="submit" name="edit_activity" class="btn btn-primary">Save</button></td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <hr>

        <!-- Add New Activity -->
        <h2>Add New Activity</h2>
        <form method="POST">
            <div class="form-group">
                <label for="new_activity_name">Activity Name:</label>
                <input type="text" name="new_activity_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="new_start_date">Start Date:</label>
                <input type="date" name="new_start_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="new_end_date">End Date:</label>
                <input type="date" name="new_end_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="new_wbso">WBSO:</label>
                <select name="new_wbso" class="form-control">
                    <option value="">-- None --</option>
                    <?php foreach ($wbsoOptions as $wbsoOption): ?>
                        <option value="<?php echo $wbsoOption['Id']; ?>" 
                                title="<?php echo htmlspecialchars($wbsoOption['Description'] ?? ''); ?>">
                            <?php echo htmlspecialchars($wbsoOption['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="new_visible">Visible:</label>
                <input type="checkbox" name="new_visible" value="1" checked>
            </div>
            <div class="form-group">
                <label for="new_is_task">Is Task:</label>
                <input type="checkbox" name="new_is_task" value="1" checked>
            </div>
            
            <!-- New Budget section -->
            <div class="form-group">
                <label for="add_budget">Add Budget:</label>
                <input type="checkbox" id="add_budget" name="add_budget" value="1" onchange="toggleBudgetSection()">
            </div>
            
            <div id="budget_section" style="display: none;">
                <h4>Budget Details</h4>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="budget">Budget (€):</label>
                            <input type="number" id="budget" name="budget" class="form-control budget-field" onchange="calculateBudget('budget')">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="oop_spend">Operational Spending (€):</label>
                            <input type="number" id="oop_spend" name="oop_spend" class="form-control budget-field" onchange="calculateBudget('oop')">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="rate">Hour Rate (€):</label>
                            <input type="number" id="rate" name="rate" class="form-control budget-field" onchange="calculateBudget('rate')">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="hours">Budget Hours:</label>
                            <input type="number" id="hours" name="hours" class="form-control budget-field" onchange="calculateBudget('hours')">
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="add_activity" class="btn btn-success mt-3">Add Activity</button>
        </form>
    </div>
</section>

<script>
function toggleBudgetSection() {
    const addBudgetChecked = document.getElementById('add_budget').checked;
    const budgetSection = document.getElementById('budget_section');
    
    if (addBudgetChecked) {
        budgetSection.style.display = 'block';
    } else {
        budgetSection.style.display = 'none';
    }
}

// Budget modal handling
function showBudgetModal(element) {
    event.preventDefault();
    
    // Get data from the clicked element
    const activityId = element.getAttribute('data-activity-id');
    const budget = element.getAttribute('data-budget');
    const oopSpend = element.getAttribute('data-oopspend');
    const rate = element.getAttribute('data-rate');
    const hours = element.getAttribute('data-hours');
    const year = element.getAttribute('data-year');
    
    // Fill the modal form with these values
    document.getElementById('modal_activity_id').value = activityId;
    document.getElementById('modal_year').value = year;
    document.getElementById('modal_budget').value = budget;
    document.getElementById('modal_oop_spend').value = oopSpend;
    document.getElementById('modal_rate').value = rate;
    document.getElementById('modal_hours').value = hours;
    
    // Show the modal
    $('#budgetModal').modal('show');
}

function calculateBudget(changedField, prefix='') {
    const budget = document.getElementById(prefix+'budget');
    const oopSpend = document.getElementById(prefix+'oop_spend');
    const rate = document.getElementById(prefix+'rate');
    const hours = document.getElementById(prefix+'hours');
    
    // Ensure values are numbers and default to 0 if not
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
            if (budgetValue > 0 && budgetValue > 0) {
                rate.value = Math.round((budgetValue - oopValue) / hoursValue);
            }
            break;
    }
}
</script>

<!-- Budget Edit Modal -->
<div class="modal fade" id="budgetModal" tabindex="-1" role="dialog" aria-labelledby="budgetModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="budgetForm" method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="budgetModalLabel">Edit Budget</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <input type="hidden" name="update_budget">
                    <input type="hidden" id="modal_year" name="year">
                    <input type="hidden" id="modal_activity_id" name="activity_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_budget">Budget (€):</label>
                                <input type="number" id="modal_budget" name="budget" class="form-control" 
                                       onchange="calculateBudget('budget', 'modal_')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_oop_spend">Operational Spending (€):</label>
                                <input type="number" id="modal_oop_spend" name="oop_spend" class="form-control" 
                                       onchange="calculateBudget('oop', 'modal_')">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_rate">Hour Rate (€):</label>
                                <input type="number" id="modal_rate" name="rate" class="form-control" 
                                       onchange="calculateBudget('rate', 'modal_')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_hours">Budget Hours:</label>
                                <input type="number" id="modal_hours" name="hours" class="form-control" 
                                       onchange="calculateBudget('hours', 'modal_')">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// End output buffering and flush
ob_end_flush();
require 'includes/footer.php'; 
?>