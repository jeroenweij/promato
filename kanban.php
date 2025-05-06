<?php
$pageSpecificCSS = ['kanban.css'];

require 'includes/header.php';
require 'includes/db.php';

$userId = $_SESSION['user_id'] ?? null;

// Fetch activities per project and status from joined status table
$stmt = $pdo->prepare("
SELECT 
    h.Plan AS PlannedHours, 
    h.Hours AS LoggedHours,
    hs.Name AS Status,
    hs.Id AS StatusId,
    h.Prio AS Priority,
    a.Name AS ActivityName, 
    a.Key AS ActivityId, 
    p.Id AS ProjectId, 
    p.Name AS ProjectName 
FROM Hours h 
JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
JOIN Projects p ON a.Project = p.Id
LEFT JOIN HourStatus hs ON h.StatusId = hs.Id
WHERE h.Person = ? AND h.Plan>0 AND a.IsTask=1
ORDER BY hs.Name, h.Prio DESC
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by status
$kanban = [];
foreach ($rows as $row) {
    $kanban[$row['StatusId']][] = $row;
}
?>

<section id="kanban-board">
    <div class="container">
        <div class="row">
            <?php
            $statusStmt = $pdo->query("SELECT Id, Name FROM HourStatus ORDER BY Id ASC");
            $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($statuses as $status):
                $statusId = $status['Id'];
            ?>
                <div class="col-md-3" class="kanban-column">
                    <div class="card mb-3">
                        <div class="card-header bg-secondary text-white text-center">
                            <?= $status['Name'] ?>
                        </div>
                    </div>
                    <div id="status-<?= $statusId ?>" class="kanban-cards">
                    <?php if (!empty($kanban[$statusId])): ?>
                        <?php foreach ($kanban[$statusId] as $item):
                            $planned = $item['PlannedHours'] / 100;
                            $logged = $item['LoggedHours'] / 100;
                            $realpercent = $planned > 0 ? round(($logged / $planned) * 100) : 0;
                            $percent = min(100, $realpercent);
                            ?>
                            <div class="card mb-3" data-project-id="<?= $item['ProjectId'] ?>" data-activity-id="<?= $item['ActivityId'] ?>" data-person-id="<?= $userId ?>">
                                <div class="card-body">
                                    <h6 class="card-title"><?= htmlspecialchars($item['ActivityName']) ?></h6>
                                    <p class="small text-muted"><?= htmlspecialchars($item['ProjectName']) ?></p>
                                    <div class="text"><?= $logged ?> / <?= $planned ?></div>
                                    <div class="progress">
                                        <?php $overshoot = $realpercent>100 ? 'overshoot' : '' ?>
                                        <div class="progress-bar <?= $overshoot ?>" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= $realpercent ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    const statuses = <?= json_encode($statuses) ?>;

    statuses.forEach(status => {
        new Sortable(document.getElementById('status-' + status.Id), {
            group: 'kanban',
            animation: 150,
            onEnd: function (evt) {
                const activityId = evt.item.dataset.activityId;
                const projectId = evt.item.dataset.projectId;
                const personId = evt.item.dataset.personId;
                const newStatusId = evt.to.id.replace('status-', '');

                fetch('update_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        activityId: activityId,
                        projectId: projectId,
                        personId: personId,
                        newStatusId: newStatusId
                    })
                }).then(res => res.json()).then(data => {
                    console.log('Status updated', data);
                });
            }
        });
    });
</script>


<?php require 'includes/footer.php'; ?>
