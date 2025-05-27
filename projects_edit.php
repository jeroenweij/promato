<?php
$pageSpecificCSS = ['kanban.css'];
require 'includes/header.php';
require 'includes/db.php';

// Get project data with status and project manager
$stmt = $pdo->prepare("SELECT 
    Projects.Id AS ProjectId, 
    Projects.Name AS ProjectName, 
    Projects.Status AS StatusId, 
    Personel.Shortname AS ProjectManager, 
    Status.Status
    FROM Projects
    LEFT JOIN Status ON Projects.Status = Status.Id
    LEFT JOIN Personel ON Projects.Manager = Personel.Id
    ORDER BY Status.Status, Projects.Id");

$stmt->execute();
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
            $statusStmt = $pdo->query("SELECT Id, Status AS Name FROM Status ORDER BY Id ASC");
            $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($statuses as $status):
                $statusId = $status['Id'];
            ?>
                <div class="col-md-3" class="kanban-column">
                    <div class="card card-full mb-3">
                        <div class="card-header bg-secondary text-white text-center">
                            <?= $status['Name'] ?>
                        </div>
                    <div id="status-<?= $statusId ?>" class="card-body">
                    <?php if (!empty($kanban[$statusId])): ?>
                        <?php foreach ($kanban[$statusId] as $item):
                            ?>
                            <div class="task-card" data-project-id="<?= $item['ProjectId'] ?>">
                                <div class="task-header bg-primary text-white project-header">
                                    <span class="project-name">
                                        <a href="project_edit.php?project_id=<?= htmlspecialchars($item['ProjectId']) ?>" class="hidden-link">
                                            <strong><?= htmlspecialchars($item['ProjectId']) ?></strong> <?= htmlspecialchars($item['ProjectName']) ?>
                                        </a>
                                    </span>
                                </div> 
                                <div class="card-body">
                                    <div class="task-name"><?= htmlspecialchars($item['ProjectManager']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
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
                const projectId = evt.item.dataset.projectId;
                const newStatusId = evt.to.id.replace('status-', '');

                fetch('update_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        projectId: projectId,
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
