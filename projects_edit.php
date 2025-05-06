<?php
require 'includes/header.php';
require 'includes/db.php';

// Get project data with status and project manager
$sql = "SELECT Projects.Id AS ProjectId, Projects.Name AS ProjectName, 
               Personel.Shortname AS ProjectManager, Status.Status
        FROM Projects
        LEFT JOIN Status ON Projects.Status = Status.Id
        LEFT JOIN Personel ON Projects.Manager = Personel.Id
        ORDER BY Status.Status, Projects.Id";

$stmt = $pdo->query($sql);
if (!$stmt) {
    die("Query failed: " . print_r($pdo->errorInfo(), true));
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Render the table
echo '<section id="projects"><div class="container">';

echo '<div class="row mb-4">';
echo '  <div class="col">';
echo '    <a href="project_add.php" class="btn btn-primary">➕ Add Project</a>';
echo '  </div>';
echo '</div>';

echo '<table class="table table-bordered">';
echo '<thead>';
echo '<tr>';
echo '<th>Project ID</th>';
echo '<th>Project Name</th>';
echo '<th>Status</th>';
echo '<th>Project Manager</th>';
echo '<th>Project Manager</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($rows as $project) {
    echo '<tr>';
    echo '<td><a href="project_edit.php?project_id=' . htmlspecialchars($project['ProjectId']) . '">' . htmlspecialchars($project['ProjectId']) . '</a></td>';
    echo '<td><a href="project_edit.php?project_id=' . htmlspecialchars($project['ProjectId']) . '">' . htmlspecialchars($project['ProjectName']) . '</a></td>';
    echo '<td>' . htmlspecialchars($project['Status']) . '</td>';
    echo '<td>' . htmlspecialchars($project['ProjectManager'] ?? '') . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></section>';

require 'includes/footer.php';
?>
