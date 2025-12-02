<?php
$pageSpecificCSS = ['kanban.css', 'priority.css'];

require 'includes/header.php';
require_once 'includes/db.php';

// Fetch teams that have tasks assigned
$teamStmt = $pdo->query("
    SELECT DISTINCT t.Id, t.Name, t.Ord
    FROM Teams t 
    JOIN TeamHours th ON th.Team = t.Id
    WHERE t.Planable AND th.Plan > 0 AND th.`Year` = $selectedYear
    ORDER BY t.Ord, t.Name
");
$teams = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

// Auto-update status to Done (4) for all TeamHours where project is closed (Status = 4)
$autoUpdateStmt = $pdo->prepare("
    UPDATE TeamHours th
    JOIN Projects p ON th.Project = p.Id
    SET th.Status = 4
    WHERE p.Status = 4 AND th.Status != 4 AND th.`Year` = :selectedYear
");
$autoUpdateStmt->execute([':selectedYear' => $selectedYear]);

// Fetch activities grouped by team and project
$stmt = $pdo->prepare("
SELECT
    th.Plan AS PlannedHours,
    th.Hours AS LoggedHours,
    th.Prio AS Priority,
    th.Team AS TeamId,
    th.Status AS Status,
    th.Project AS ProjectId,
    th.Activity AS ActivityKey,
    a.Name AS ActivityName,
    p.Name AS ProjectName,
    p.Status AS ProjectStatus
FROM TeamHours th
JOIN Activities a ON th.Activity = a.Key AND th.Project = a.Project
JOIN Projects p ON a.Project = p.Id
WHERE th.Plan > 0 AND a.IsTask = 1 AND th.`Year` = :selectedYear
ORDER BY th.Team, th.Prio, p.Id, a.Key
");

$stmt->execute([
    ':selectedYear' => $selectedYear
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by team, status, and project
$activeProjects = [];
$doneProjects = [];
$hiddenProjects = [];

foreach ($rows as $row) {
    $teamId = $row['TeamId'];
    $projectId = $row['ProjectId'];
    
    // Initialize project structure if it doesn't exist
    $projectKey = $teamId . '-' . $projectId;
    
    if ($row['Status'] == 4) { // Done
        if (!isset($doneProjects[$projectKey])) {
            $doneProjects[$projectKey] = [
                'teamId' => $teamId,
                'projectId' => $projectId,
                'projectName' => $row['ProjectName'],
                'priority' => $row['Priority'],
                'activities' => []
            ];
        }
        $doneProjects[$projectKey]['activities'][] = $row;
    } else if ($row['Status'] == 5) { // Hidden
        if (!isset($hiddenProjects[$projectKey])) {
            $hiddenProjects[$projectKey] = [
                'teamId' => $teamId,
                'projectId' => $projectId,
                'projectName' => $row['ProjectName'],
                'priority' => $row['Priority'],
                'activities' => []
            ];
        }
        $hiddenProjects[$projectKey]['activities'][] = $row;
    } else { // Active
        if (!isset($activeProjects[$projectKey])) {
            $activeProjects[$projectKey] = [
                'teamId' => $teamId,
                'projectId' => $projectId,
                'projectName' => $row['ProjectName'],
                'priority' => $row['Priority'],
                'activities' => []
            ];
        }
        $activeProjects[$projectKey]['activities'][] = $row;
    }
}

// Sort active projects by priority within each team
foreach ($teams as $team) {
    $teamActiveProjects = array_filter($activeProjects, function($project) use ($team) {
        return $project['teamId'] == $team['Id'];
    });
    
    usort($teamActiveProjects, function($a, $b) {
        return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
    });
}
?>

<section id="priority-planning">
    <div class="container">
        <h1>Team Priority Planning</h1>
        <div class="row">
            <?php foreach ($teams as $team): ?>
                <div class="col-md-3 bottom-margin">
                    <!-- Active Projects -->
                    <div class="card card-full mb-3">
                        <div class="card-header bg-secondary text-white text-center">
                            <strong><?= htmlspecialchars($team['Name']) ?></strong>
                        </div>
                        <div class="card-body tasks-container">
                            <div id="team-<?= $team['Id'] ?>" class="kanban-cards active-tasks" data-team-id="<?= $team['Id'] ?>">
                                <?php 
                                $teamActiveProjects = array_filter($activeProjects, function($project) use ($team) {
                                    return $project['teamId'] == $team['Id'];
                                });
                                
                                if (!empty($teamActiveProjects)): 
                                    foreach ($teamActiveProjects as $project): 
                                        // Calculate totals for the project
                                        $totalPlanned = 0;
                                        $totalLogged = 0;
                                        foreach ($project['activities'] as $activity) {
                                            $totalPlanned += $activity['PlannedHours'] / 100;
                                            $totalLogged += $activity['LoggedHours'] / 100;
                                        }
                                        $realpercent = $totalPlanned > 0 ? round(($totalLogged / $totalPlanned) * 100) : 0;
                                        $percent = min(100, $realpercent);
                                        ?>
                                        <div class="card mb-3 task-card"
                                            data-project-id="<?= $project['projectId'] ?>"
                                            data-team-id="<?= $project['teamId'] ?>"
                                            data-status="2">
                                            <div class="task-header bg-primary text-white project-header d-flex justify-content-between align-items-center">
                                                <span class="project-name flex-grow-1">
                                                    <?= htmlspecialchars($project['projectName']) ?>
                                                </span>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-light btn-action"
                                                            onclick="moveToTop(<?= $project['projectId'] ?>, <?= $project['teamId'] ?>)"
                                                            title="Move to top">
                                                        <i class="icon ion-ios-arrow-up"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-light btn-action"
                                                            onclick="moveToBottom(<?= $project['projectId'] ?>, <?= $project['teamId'] ?>)"
                                                            title="Move to bottom">
                                                        <i class="icon ion-ios-arrow-down"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-light btn-action"
                                                            onclick="hideProject(<?= $project['projectId'] ?>, <?= $project['teamId'] ?>)"
                                                            title="Hide">
                                                        <i class="icon ion-ios-eye-off"></i>
                                                    </button>
                                                </div>
                                                <span class="item-priority ml-2">
                                                    <?= ($project['priority'] > 0 && $project['priority'] < 250) ? $project['priority'] : '' ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <div class="hours-info" style="margin-top: 10px; font-weight: bold;">
                                                    Total: <?= number_form($totalLogged, 1) ?> / <?= number_form($totalPlanned, 1) ?> hours
                                                </div>
                                                <div class="progress">
                                                    <?php $overshoot = $realpercent > 100 ? 'overshoot' : '' ?>
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
                                            No active projects
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    
                        <!-- Done Projects -->
                        <div class="done-header card-header nospacing text-center">
                            Done Projects
                        </div>
                        <div class="card-body tasks-container nospacing">
                            <div id="team-done-<?= $team['Id'] ?>" class="kanban-cards done-tasks" data-team-id="<?= $team['Id'] ?>">
                                <?php 
                                $teamDoneProjects = array_filter($doneProjects, function($project) use ($team) {
                                    return $project['teamId'] == $team['Id'];
                                });
                                
                                if (!empty($teamDoneProjects)): 
                                    foreach ($teamDoneProjects as $project): ?>
                                        <div class="card mb-2 done-card task-card"
                                            data-project-id="<?= $project['projectId'] ?>"
                                            data-team-id="<?= $project['teamId'] ?>"
                                            data-status="4">
                                            <div class="card-body">
                                                <small><strong><?= htmlspecialchars($project['projectName']) ?></strong></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Hidden Projects -->
                        <div class="done-header card-header nospacing text-center">
                            Hidden Projects
                        </div>
                        <div class="card-body tasks-container nospacing">
                            <div id="team-hidden-<?= $team['Id'] ?>" class="kanban-cards done-tasks" data-team-id="<?= $team['Id'] ?>">
                                <?php 
                                $teamHiddenProjects = array_filter($hiddenProjects, function($project) use ($team) {
                                    return $project['teamId'] == $team['Id'];
                                });
                                
                                if (!empty($teamHiddenProjects)): 
                                    foreach ($teamHiddenProjects as $project): ?>
                                        <div class="card mb-2 done-card task-card"
                                            data-project-id="<?= $project['projectId'] ?>"
                                            data-team-id="<?= $project['teamId'] ?>"
                                            data-status="5">
                                            <div class="card-body">
                                                <small><strong><?= htmlspecialchars($project['projectName']) ?></strong></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    const teams = <?= json_encode($teams) ?>;

    // Helper function to update project status for all activities in a project
    function updateProjectStatus(data) {
        console.log("Updating project status:", data);
        return fetch('update_team_project_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        }).then(res => res.json()).then(response => {
            console.log('Project status updated', response);
            return response;
        }).catch(error => {
            console.error('Error updating project status', error);
            alert('Error updating project status');
            throw error;
        });
    }

    // Move project to top
    function moveToTop(projectId, teamId) {
        const container = document.getElementById('team-' + teamId);
        const cards = Array.from(container.querySelectorAll('.task-card'));
        const targetCard = cards.find(card =>
            card.dataset.projectId == projectId && card.dataset.teamId == teamId
        );

        if (targetCard) {
            container.insertBefore(targetCard, container.firstChild);
            updatePriorities(teamId);
        }
    }

    // Move project to bottom
    function moveToBottom(projectId, teamId) {
        const container = document.getElementById('team-' + teamId);
        const cards = Array.from(container.querySelectorAll('.task-card'));
        const targetCard = cards.find(card =>
            card.dataset.projectId == projectId && card.dataset.teamId == teamId
        );

        if (targetCard) {
            container.appendChild(targetCard);
            updatePriorities(teamId);
        }
    }

    // Hide project (move to hidden section)
    function hideProject(projectId, teamId) {
        updateProjectStatus({
            projectId: projectId,
            teamId: teamId,
            status: 5 // Set to hidden
        }).then(() => {
            window.location.reload();
        });
    }

    // Update priorities for all cards in a team container
    function updatePriorities(teamId) {
        const container = document.getElementById('team-' + teamId);
        const cards = container.querySelectorAll('.task-card');

        const projectIds = [];
        cards.forEach((card, index) => {
            projectIds.push({
                projectId: card.dataset.projectId,
                teamId: card.dataset.teamId,
                priority: index + 1
            });
        });

        // Send updated priorities to backend
        fetch('update_priority.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(projectIds)
        }).then(res => res.json()).then(data => {
            console.log('Priorities updated', data);
            // Update priority display
            cards.forEach((card, index) => {
                const prioritySpan = card.querySelector('.item-priority');
                if (prioritySpan) {
                    prioritySpan.textContent = index + 1;
                }
            });
        }).catch(error => {
            console.error('Error updating priorities', error);
            alert('Error updating priorities');
        });
    }

    // Initialize Sortable for all active, done, and hidden project containers
    teams.forEach(team => {
        // Active projects sortable
        const activeContainer = document.getElementById('team-' + team.Id);
        new Sortable(activeContainer, {
            group: 'projects-' + team.Id,
            animation: 150,
            onAdd: function(evt) {
                // Project moved from Done/Hidden to Active
                const card = evt.item;
                const projectId = card.dataset.projectId;
                const teamId = card.dataset.teamId;
                                
                // Collect project IDs in new order
                const cards = evt.to.querySelectorAll('.task-card');
                const projectIds = [];
                cards.forEach((cardx, index) => {
                    projectIds.push({
                        projectId: cardx.dataset.projectId,
                        teamId: cardx.dataset.teamId,
                        priority: index + 1
                    });
                });

                // First update status in database
                updateProjectStatus({
                    projectId: projectId,
                    teamId: teamId,
                    status: 2 // Set to active
                }).then(() => {
                    // Then update priority
                    return fetch('update_priority.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(projectIds)
                    });
                }).then(res => res.json()).then(data => {
                    console.log('Priorities updated', data);
                    // Reload page to refresh card UI
                    window.location.reload();
                });
            },
            onEnd: function(evt) {
                // Update priorities if within the same container
                if (evt.from === evt.to && !evt.to.classList.contains('done-tasks')) {
                    const container = evt.to;
                    const cards = container.querySelectorAll('.task-card');

                    // Collect project IDs in new order
                    const projectIds = [];
                    cards.forEach((card, index) => {
                        projectIds.push({
                            projectId: card.dataset.projectId,
                            teamId: card.dataset.teamId,
                            priority: index + 1
                        });
                    });

                    // Send updated priorities to backend
                    fetch('update_priority.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(projectIds)
                    }).then(res => res.json()).then(data => {
                        console.log('Priorities updated', data);
                    });
                }
            }
        });

        // Done projects sortable
        const doneContainer = document.getElementById('team-done-' + team.Id);
        new Sortable(doneContainer, {
            group: 'projects-' + team.Id,
            animation: 150,
            onAdd: function(evt) {
                // Project moved to Done
                const card = evt.item;
                const projectId = card.dataset.projectId;
                const teamId = card.dataset.teamId;
                
                // Update status in database
                updateProjectStatus({
                    projectId: projectId,
                    teamId: teamId,
                    status: 4 // Set to done
                }).then(() => {
                    // Reload page to refresh card UI
                    window.location.reload();
                });
            }
        });
        
        // Hidden projects sortable
        const hiddenContainer = document.getElementById('team-hidden-' + team.Id);
        new Sortable(hiddenContainer, {
            group: 'projects-' + team.Id,
            animation: 150,
            onAdd: function(evt) {
                // Project moved to Hidden
                const card = evt.item;
                const projectId = card.dataset.projectId;
                const teamId = card.dataset.teamId;
                
                // Update status in database
                updateProjectStatus({
                    projectId: projectId,
                    teamId: teamId,
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