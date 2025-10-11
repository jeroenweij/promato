<?php
$pageSpecificCSS = ['kanban.css', 'priority.css'];

require 'includes/header.php';
require 'includes/db.php';

$userStmt = $pdo->query("
    SELECT DISTINCT u.Id, u.Name, u.Team, u.Ord
    FROM Personel u 
    JOIN Hours h ON h.Person = u.Id
    WHERE h.Project > 10 AND u.Plan=1 AND u.Type>1
    ORDER BY u.Team, u.Ord
");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch activities grouped by person
$stmt = $pdo->prepare("
SELECT 
    h.Plan AS PlannedHours, 
    h.Hours AS LoggedHours,
    h.Prio AS Priority,
    h.Person AS PersonId,
    h.Status AS Status,
    a.Name AS ActivityName, 
    a.Key AS ActivityId, 
    p.Id AS ProjectId, 
    p.Name AS ProjectName 
FROM Hours h 
JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
JOIN Projects p ON a.Project = p.Id AND p.Status = 3
WHERE h.Plan>0 AND a.IsTask=1 AND h.`Year` = :selectedYear
ORDER BY h.Person, h.Prio");

// Execute the prepared statement
$stmt->execute([
    ':selectedYear' => $selectedYear
]);


$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by person and status
$activeTasks = [];
$doneTasks = [];
$hiddenTasks = [];
foreach ($rows as $row) {
    if ($row['Status'] == 4) { // Done tasks
        $doneTasks[$row['PersonId']][] = $row;
    } else if ($row['Status'] == 5) { // Hidden tasks
        $hiddenTasks[$row['PersonId']][] = $row;
    } else { // Active tasks
        $activeTasks[$row['PersonId']][] = $row;
    }
}
?>

<section id="priority-planning">
    <div class="container">
        <h1>Priority Planning</h1>
        <div class="row">
            <?php foreach ($users as $user): ?>
                <div class="col-md-3 bottom-margin">
                    <!-- Active Tasks -->
                    <div class="card card-full mb-3">
                        <div class="card-header bg-secondary text-white text-center">
                            <strong><?= htmlspecialchars($user['Name']) ?></strong>
                        </div>
                        <div class="card-body tasks-container">
                            <div id="person-<?= $user['Id'] ?>" class="kanban-cards active-tasks" data-person-id="<?= $user['Id'] ?>">
                                <?php if (!empty($activeTasks[$user['Id']])): ?>
                                    <?php foreach ($activeTasks[$user['Id']] as $item):
                                        $planned = $item['PlannedHours'] / 100;
                                        $logged = $item['LoggedHours'] / 100;
                                        $realpercent = $planned > 0 ? round(($logged / $planned) * 100) : 0;
                                        $percent = min(100, $realpercent);
                                        ?>
                                        <div class="card mb-3 task-card"
                                            data-project-id="<?= $item['ProjectId'] ?>"
                                            data-activity-id="<?= $item['ActivityId'] ?>"
                                            data-person-id="<?= $item['PersonId'] ?>"
                                            data-status="<?= $item['Status'] ?>">
                                            <div class="task-header bg-primary text-white project-header">
                                                <span class="project-name">
                                                    <?= htmlspecialchars($item['ProjectName']) ?>
                                                </span>
                                                <span class="item-priority">
                                                    <?= ($item['Priority']>0 && $item['Priority']<250) ? $item['Priority'] : '' ?>
                                                </span>
                                            </div> 
                                            <div class="card-body">
                                            <div class="task-name"><?= htmlspecialchars($item['ActivityName']) ?></div>
                                            <div class="hours-info">
                                                <?= $logged ?> / <?= $planned ?> hours
                                            </div>
                                            <div class="progress">
                                                <?php $overshoot = $realpercent>100 ? 'overshoot' : '' ?>
                                                <div class="progress-bar <?= $overshoot ?>" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?= $realpercent ?>%
                                                </div>
                                            </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="card mb-3 empty-placeholder">
                                        <div class="card-body text-muted text-center">
                                            No active tasks
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    
                        <!-- Done Tasks -->
                        <!-- <div class="card"> -->
                            <div class="done-header card-header nospacing text-center">
                                    Done Tasks
                            </div>
                            <div class="card-body tasks-container nospacing">
                                <div id="person-done-<?= $user['Id'] ?>" class="kanban-cards done-tasks" data-person-id="<?= $user['Id'] ?>">
                                    <?php if (!empty($doneTasks[$user['Id']])): ?>
                                        <?php foreach ($doneTasks[$user['Id']] as $item): ?>
                                            <div class="card mb-2 done-card task-card"
                                                data-project-id="<?= $item['ProjectId'] ?>"
                                                data-activity-id="<?= $item['ActivityId'] ?>"
                                                data-person-id="<?= $item['PersonId'] ?>"
                                                data-status="<?= $item['Status'] ?>">
                                                <div class="card-body">
                                                    <small><strong><?= htmlspecialchars($item['ProjectName']) ?></strong> - <?= htmlspecialchars($item['ActivityName']) ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <!-- </div> -->
                        
                        <!-- Hidden Tasks -->
                        <!-- <div class="card"> -->
                            <div class="done-header card-header nospacing text-center">
                                    Hidden Tasks
                            </div>
                            <div class="card-body tasks-container nospacing">
                                <div id="person-hidden-<?= $user['Id'] ?>" class="kanban-cards done-tasks" data-person-id="<?= $user['Id'] ?>">
                                    <?php if (!empty($hiddenTasks[$user['Id']])): ?>
                                        <?php foreach ($hiddenTasks[$user['Id']] as $item): ?>
                                            <div class="card mb-2 done-card task-card"
                                                data-project-id="<?= $item['ProjectId'] ?>"
                                                data-activity-id="<?= $item['ActivityId'] ?>"
                                                data-person-id="<?= $item['PersonId'] ?>"
                                                data-status="<?= $item['Status'] ?>">
                                                <div class="card-body">
                                                    <small><strong><?= htmlspecialchars($item['ProjectName']) ?></strong> - <?= htmlspecialchars($item['ActivityName']) ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <!-- </div> -->
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    const users = <?= json_encode($users) ?>;

    // Helper function to update task status
    function updateTaskStatus(data) {
        console.log("Updating status:", data);
        return fetch('update_task_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        }).then(res => res.json()).then(response => {
            console.log('Status updated', response);
            return response;
        }).catch(error => {
            console.error('Error updating status', error);
            throw error;
        });
    }

    // Initialize Sortable for all active, done, and hidden task containers
    users.forEach(user => {
        // Active tasks sortable
        const activeContainer = document.getElementById('person-' + user.Id);
        new Sortable(activeContainer, {
            group: 'tasks-' + user.Id, // Simple group name to allow cross-container dragging
            animation: 150,
            onAdd: function(evt) {
                // Task moved from Done/Hidden to Active
                const card = evt.item;
                const projectId = card.dataset.projectId;
                const activityId = card.dataset.activityId;
                const personId = card.dataset.personId;
                                
                // Collect activity IDs in new order
                const cards = evt.to.querySelectorAll('.task-card');
                const activityIds = [];
                cards.forEach((cardx, index) => {
                    activityIds.push({
                        activityId: cardx.dataset.activityId,
                        projectId: cardx.dataset.projectId,
                        personId: cardx.dataset.personId,
                        priority: index + 1
                    });
                });

                // First update status in database
                updateTaskStatus({
                    projectId: projectId,
                    activityId: activityId,
                    personId: personId,
                    status: 2 // Set to todo
                }).then(() => {
                    // Then update priority
                    return fetch('update_priority.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(activityIds)
                    });
                }).then(res => res.json()).then(data => {
                    console.log('Priorities updated', data);
                });

                // update status in database
                updateTaskStatus({
                    projectId: projectId,
                    activityId: activityId,
                    personId: personId,
                    status: 2 // Set to todo
                }).then(() => {
                    // Reload page to refresh card UI
                    window.location.reload();
                });
            },
            onEnd: function(evt) {
                // Update priorities if within the same container
                if (evt.from === evt.to && !evt.to.classList.contains('done-tasks')) {
                    const container = evt.to;
                    const cards = container.querySelectorAll('.task-card');

                    // Collect activity IDs in new order
                    const activityIds = [];
                    cards.forEach((card, index) => {
                        activityIds.push({
                            activityId: card.dataset.activityId,
                            projectId: card.dataset.projectId,
                            personId: card.dataset.personId,
                            priority: index + 1
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
            }
        });

        // Done tasks sortable
        const doneContainer = document.getElementById('person-done-' + user.Id);
        new Sortable(doneContainer, {
            group: 'tasks-' + user.Id, // Same group name to allow cross-container dragging
            animation: 150,
            onAdd: function(evt) {
                // Task moved to Done
                const card = evt.item;
                const projectId = card.dataset.projectId;
                const activityId = card.dataset.activityId;
                const personId = card.dataset.personId;
                
                // Update status in database
                updateTaskStatus({
                    projectId: projectId,
                    activityId: activityId,
                    personId: personId,
                    status: 4 // Set to done
                }).then(() => {
                    // Reload page to refresh card UI
                    window.location.reload();
                });
            }
        });
        
        // Hidden tasks sortable
        const hiddenContainer = document.getElementById('person-hidden-' + user.Id);
        new Sortable(hiddenContainer, {
            group: 'tasks-' + user.Id, // Same group name to allow cross-container dragging
            animation: 150,
            onAdd: function(evt) {
                // Task moved to Hidden
                const card = evt.item;
                const projectId = card.dataset.projectId;
                const activityId = card.dataset.activityId;
                const personId = card.dataset.personId;
                
                // Update status in database
                updateTaskStatus({
                    projectId: projectId,
                    activityId: activityId,
                    personId: personId,
                    status: 5 // Set to hidden
                }).then(() => {
                    // Reload page to refresh card UI
                    window.location.reload();
                });
            }
        });
        
        // Remove empty placeholders when dragging starts
        const removeEmptyPlaceholders = function(evt) {
            const container = evt.target.closest('.kanban-cards');
            const emptyPlaceholders = container.querySelectorAll('.empty-placeholder');
            emptyPlaceholders.forEach(placeholder => {
                placeholder.style.display = 'none';
            });
        };
        
        // Show empty placeholders when container is empty
        const showEmptyPlaceholdersIfEmpty = function(evt) {
            const container = evt.target.closest('.kanban-cards');
            const cards = container.querySelectorAll('.task-card');
            const emptyPlaceholders = container.querySelectorAll('.empty-placeholder');
            
            if (cards.length === 0) {
                emptyPlaceholders.forEach(placeholder => {
                    placeholder.style.display = 'block';
                });
            }
        };
        
        // Add event listeners
        activeContainer.addEventListener('dragstart', removeEmptyPlaceholders);
        activeContainer.addEventListener('dragend', showEmptyPlaceholdersIfEmpty);
        doneContainer.addEventListener('dragstart', removeEmptyPlaceholders);
        doneContainer.addEventListener('dragend', showEmptyPlaceholdersIfEmpty);
        hiddenContainer.addEventListener('dragstart', removeEmptyPlaceholders);
        hiddenContainer.addEventListener('dragend', showEmptyPlaceholdersIfEmpty);
    });
</script>

<?php require 'includes/footer.php'; ?>