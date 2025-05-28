<?php
require 'includes/header.php';
require 'includes/db.php';

$errors = [];
$projectId = '';
$projectName = '';
$selectedStatus = '';
$selectedManager = '';

// Fetch statuses and project managers
$statusStmt = $pdo->query("SELECT Id, Status FROM Status");
$statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

$managerStmt = $pdo->query("SELECT Id, Shortname AS ManagerName FROM Personel WHERE Type>2");
$managers = $managerStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId = trim($_POST['project_id']);
    $projectName = trim($_POST['project_name']);
    $selectedStatus = $_POST['status'];
    $selectedManager = $_POST['manager'];

    // Basic validation
    if (empty($projectId)) {
        $errors[] = "Project ID is required.";
    }

    if (empty($projectName)) {
        $errors[] = "Project name is required.";
    }

    // Check uniqueness of Project ID
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Projects WHERE Id = ?");
        $stmt->execute([$projectId]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Project ID already exists. Please choose a different one.";
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        $insertStmt = $pdo->prepare("INSERT INTO Projects (Id, Name, Status, Manager) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$projectId, $projectName, $selectedStatus, $selectedManager]);
        header("Location: project_details.php?project_id=" . urlencode($projectId));
        exit;
    }
}
?>

<section class="container">
    <h2>Add New Project</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="project_id">Project ID (must be unique):</label>
            <input type="text" name="project_id" class="form-control" value="<?php echo htmlspecialchars($projectId); ?>" required>
        </div>

        <div class="form-group">
            <label for="project_name">Project Name:</label>
            <input type="text" name="project_name" class="form-control" value="<?php echo htmlspecialchars($projectName); ?>" required>
        </div>

        <div class="form-group">
            <label for="status">Status:</label>
            <select name="status" class="form-control" required>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status['Id']; ?>" <?php echo $selectedStatus == $status['Id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status['Status']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="manager">Project Manager:</label>
            <select name="manager" class="form-control" required>
                <?php foreach ($managers as $manager): ?>
                    <option value="<?php echo $manager['Id']; ?>" <?php echo $selectedManager == $manager['Id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($manager['ManagerName']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-success">Create Project</button>
    </form>
</section>

<?php require 'includes/footer.php'; ?>
