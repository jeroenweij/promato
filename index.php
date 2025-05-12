<?php
$pageSpecificCSS = ['kanban.css'];

require 'includes/header.php';
require 'includes/db.php';

$userId = $_SESSION['user_id'] ?? null;

// Fetch activities per project where user is planned
$stmt = $pdo->prepare("
SELECT 
    h.Plan AS PlannedHours, 
    h.Hours AS LoggedHours,
    h.Prio AS Priority,
    h.Person AS PersonId,
    a.Name AS ActivityName, 
    a.Key AS ActivityId, 
    p.Id AS ProjectId, 
    p.Name AS ProjectName 
FROM Hours h 
JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
JOIN Projects p ON a.Project = p.Id
WHERE h.Person = :userid AND h.Plan > 0 AND a.IsTask = 1 AND h.`Year` = :selectedYear
ORDER BY h.Person, h.Prio");

// Execute the prepared statement
$stmt->execute([
    ':selectedYear' => $selectedYear,
    ':userid' => $userId,
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section id="personal-dashboard">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h4>Task priorities:</h4>
                <?php foreach ($rows as $item): ?>
                    <?php
                        $planned = $item['PlannedHours'] / 100;
                        $logged = $item['LoggedHours'] / 100;
                        $realpercent = $planned > 0 ? round(($logged / $planned) * 100) : 0;
                        $percent = min(100, $realpercent);
                        $overshoot = $realpercent > 100 ? 'overshoot' : '';
                    ?>
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white text-center">
                            <?= htmlspecialchars($item['ProjectName']) ?>
                        </div>    
                        <div class="card-body">
                            <p class="card-title"><?= htmlspecialchars($item['ActivityName']) ?></p>
                            <div class="text-center"><?= $logged ?> / <?= $planned ?> hours</div>
                            <div class="kanban-progress">
                                <div class="progress-bar <?= $overshoot ?>" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?= $realpercent ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
