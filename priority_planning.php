<?php
$pageSpecificCSS = ['kanban.css'];

require 'includes/header.php';
require 'includes/db.php';

// Fetch users (limit to users with tasks if needed)
$userStmt = $pdo->query("
    SELECT DISTINCT u.Id, u.Name 
    FROM Personel u 
    JOIN Hours h ON h.Person = u.Id
    WHERE h.Project > 10 AND u.Plan=1
    ORDER BY u.Department, u.Ord
");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch activities grouped by person
$stmt = $pdo->query("
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
WHERE h.Plan>0 AND a.IsTask=1
ORDER BY h.Person, h.Prio DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by person
$tasks = [];
foreach ($rows as $row) {
    $tasks[$row['PersonId']][] = $row;
}
?>

<section id="priority-planning">
    <div class="container">
        <h1>Priority Planning</h1>
        <div class="row">
            <?php foreach ($users as $user): ?>
                <div class="col-md-3">
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white text-center">
                            <?= htmlspecialchars($user['Name']) ?>
                        </div>
                    </div>
                    <div id="person-<?= $user['Id'] ?>" class="kanban-cards">
                        <?php if (!empty($tasks[$user['Id']])): ?>
                            <?php foreach ($tasks[$user['Id']] as $item):
                                $planned = $item['PlannedHours'] / 100;
                                $logged = $item['LoggedHours'] / 100;
                                $realpercent = $planned > 0 ? round(($logged / $planned) * 100) : 0;
                                $percent = min(100, $realpercent);
                                ?>
                                <div class="card mb-3"
                                     data-project-id="<?= $item['ProjectId'] ?>"
                                     data-activity-id="<?= $item['ActivityId'] ?>"
                                     data-person-id="<?= $item['PersonId'] ?>">
                                    <div class="card-body">
                                        <h6 class="card-title"><?= htmlspecialchars($item['ProjectName']) ?></h6>
                                        <p class="small text-muted"><?= htmlspecialchars($item['ActivityName']) ?></p>
                                        <div class="text center"><?= $logged ?> / <?= $planned ?></div>
                                        <div class="kanban-progress">
                                            <?php $overshoot = $realpercent>100 ? 'overshoot' : '' ?>
                                            <div class="progress-bar <?= $overshoot ?>" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= $realpercent ?>%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="card mb-3">
                                <div class="card-body text-muted text-center">
                                    No tasks
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    const users = <?= json_encode($users) ?>;

    users.forEach(user => {
        new Sortable(document.getElementById('person-' + user.Id), {
            group: 'none', // no cross-column dragging
            animation: 150,
            onEnd: function (evt) {
                const container = evt.to;
                const cards = container.querySelectorAll('.card');

                // Collect activity IDs in new order (highest prio at top)
                const activityIds = [];
                cards.forEach((card, index) => {
                    activityIds.push({
                        activityId: card.dataset.activityId,
                        projectId: card.dataset.projectId,
                        personId: card.dataset.personId,
                        priority: cards.length - index // highest priority at top
                    });
                });

                // Send updated priorities to backend
                fetch('update_priority.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(activityIds)
                }).then(res => res.json()).then(data => {
                    console.log('Priorities updated', data);
                });
            }
        });
    });
</script>

<?php require 'includes/footer.php'; ?>
