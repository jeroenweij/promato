<?php
require 'includes/header.php';
require 'includes/db.php';

// Check if the project ID is provided in the URL
if (isset($_GET['project_id'])) {
    $projectId = $_GET['project_id'];

    // Fetch the project details
    $projectStmt = $pdo->prepare("SELECT * FROM Projects WHERE Id = ?");
    $projectStmt->execute([$projectId]);
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

    // If project is not found
    if (!$project) {
        echo 'Project not found.';
        require 'includes/footer.php';
        exit;
    }

    // Fetch the activities for the project
    $activityStmt = $pdo->prepare("SELECT * FROM Activities WHERE Project = ?");
    $activityStmt->execute([$projectId]);
    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch status options
    $statusStmt = $pdo->query("SELECT * FROM Status");
    $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch project managers (assuming they are in a Users or similar table)
    $managerStmt = $pdo->query("SELECT Id, Shortname AS Name FROM Personel WHERE Type>1");
    $managers = $managerStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission to update the project status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];

    // Update the project status
    $updateStatusStmt = $pdo->prepare("UPDATE Projects SET Status = ? WHERE Id = ?");
    $updateStatusStmt->execute([$newStatus, $projectId]);

    // Reload the page to reflect the changes
    header("Location: project_edit.php?project_id=" . $projectId);
    exit;
}

// Handle form submission to update the project manager
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_manager'])) {
    $newManager = $_POST['manager'];

    // Update the project manager
    $updateManagerStmt = $pdo->prepare("UPDATE Projects SET Manager = ? WHERE Id = ?");
    $updateManagerStmt->execute([$newManager, $projectId]);

    // Reload the page to reflect the changes
    header("Location: project_edit.php?project_id=" . $projectId);
    exit;
}

// Handle form submission to update or add activities (same as previous)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_activity'])) {
        $activityId = $_POST['activity_id'];
        $name = $_POST['activity_name'];
        $budgetHours = $_POST['budget_hours'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $wbso = $_POST['wbso'];

        // Handle BudgetHours: default to 0 if not set or not numeric
        if (!is_numeric($budgetHours)) {
            $budgetHours = 0;
        }

        $updateStmt = $pdo->prepare("UPDATE Activities SET Name = ?, BudgetHours = ?, StartDate = ?, EndDate = ?, WBSO = ?, Export=1 WHERE Id = ?");
        $updateStmt->execute([$name, $budgetHours, $startDate, $endDate, $wbso, $activityId]);
    }
    // Add a new activity
    elseif (isset($_POST['add_activity'])) {
        $activityName = $_POST['new_activity_name'];
        $budgetHours = $_POST['new_budget_hours'];
        $startDate = $_POST['new_start_date'];
        $endDate = $_POST['new_end_date'];
        $wbso = $_POST['new_wbso'] ?? null;

        // Handle BudgetHours: default to 0 if not set or not numeric
        if (!is_numeric($budgetHours)) {
            $budgetHours = 0;
        }

        // Find the next available Key for the project
        $keyStmt = $pdo->prepare("SELECT MAX(`Key`) as MaxKey FROM Activities WHERE Project = ?");
        $keyStmt->execute([$projectId]);
        $maxKeyRow = $keyStmt->fetch(PDO::FETCH_ASSOC);
        $nextKey = $maxKeyRow && $maxKeyRow['MaxKey'] !== null ? $maxKeyRow['MaxKey'] + 1 : 1;

        // Insert new activity
        $insertStmt = $pdo->prepare("
            INSERT INTO Activities (Project, `Key`, Name, BudgetHours, StartDate, EndDate, WBSO, Export)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $insertStmt->execute([
            $projectId,
            $nextKey,
            $activityName,
            $budgetHours,
            $startDate,
            $endDate,
            $wbso !== '' ? $wbso : null
        ]);
    }

    header("Location: project_edit.php?project_id=" . $projectId);
    exit;
}

?>

<section id="project-details">
    <div class="container">

        <!-- Project Information -->
        <h1>Project Details: <?php echo htmlspecialchars($project['Name']); ?></h1>

        <!-- Status Dropdown (Auto-save) -->
        <form method="POST" class="form-inline mb-3">
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

        <!-- Project Manager Dropdown (Auto-save) -->
        <form method="POST" class="form-inline mb-3">
            <label for="manager">Project Manager: </label>
            <select name="manager" id="manager" class="form-control ml-2" onchange="this.form.submit()">
                <?php foreach ($managers as $manager): ?>
                    <option value="<?php echo $manager['Id']; ?>" <?php echo $manager['Id'] == $project['Manager'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($manager['Name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="update_manager" value="1">
        </form>

        <hr>

        <!-- Edit Activities -->
        <h2>Edit Activities</h2>

        <!-- Activities List -->
        <table class="table table-striped">
            <thead>
            <tr>
                <th>TaskCode</th>
                <th>Activity Name</th>
                <th>WBSO Label</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Budget Hours</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($activities as $activity): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="activity_id" value="<?php echo $activity['Id']; ?>">
                        <td><?php echo $activity['Project'] . '-' . str_pad($activity['Key'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td><input type="text" name="activity_name" value="<?php echo htmlspecialchars($activity['Name']); ?>" class="form-control"></td>
                        <td><input type="text" name="wbso" value="<?php echo htmlspecialchars($activity['WBSO'] ?? ''); ?>" class="form-control"></td>
                        <td><input type="date" name="start_date" value="<?php echo $activity['StartDate']; ?>" class="form-control"></td>
                        <td><input type="date" name="end_date" value="<?php echo $activity['EndDate']; ?>" class="form-control"></td>
                        <td><input type="number" name="budget_hours" value="<?php echo $activity['BudgetHours']; ?>" class="form-control"></td>
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
                <label for="new_budget_hours">Budget Hours:</label>
                <input type="number" name="new_budget_hours" class="form-control" required>
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
                <label for="new_wbso">WBSO Label:</label>
                <input type="text" name="new_wbso" class="form-control">
            </div>
            <button type="submit" name="add_activity" class="btn btn-success">Add Activity</button>
        </form>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
