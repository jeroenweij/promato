<?php
$pageSpecificCSS = ['kanban.css', 'dashboard.css'];
require 'includes/header.php';
require_once 'includes/db.php';

// Get user information
$userStmt = $pdo->prepare("
    SELECT 
        p.Id, p.Name, p.Email, p.Shortname, p.Fultime, p.Team, p.StartDate, p.WBSO,
        d.Name AS TeamName
    FROM Personel p
    JOIN Teams d ON p.Team = d.Id
    JOIN Types t ON p.Type = t.Id
    WHERE p.Id = :userid
");
$userStmt->execute([':userid' => $userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

$currentDate = new DateTime();

// Get total planned vs logged hours for the year
$totalHoursStmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN Project > 0 THEN Plan ELSE 0 END) AS TotalPlanned,
    SUM(CASE WHEN Project = 0 THEN Hours ELSE 0 END) AS TotalLogged
    FROM Hours 
    WHERE Person = :userid AND Year = :selectedYear"
    );
$totalHoursStmt->execute([
    ':userid' => $userId,
    ':selectedYear' => $selectedYear
]);
$totalHours = $totalHoursStmt->fetch(PDO::FETCH_ASSOC);
$totalPlanned = $totalHours['TotalPlanned'] / 100;
$totalLogged = $totalHours['TotalLogged'] / 100;
$yearProgress = min(100, round((date('z') / 365) * 100));

// Fetch activities per project where user is planned
$tasksStmt = $pdo->prepare("
    SELECT 
        h.Plan AS PlannedHours, 
        h.Hours AS LoggedHours,
        COALESCE(th.Prio, 0) AS Priority,
        h.Status AS StatusId,
        h.Person AS PersonId,
        a.Name AS ActivityName, 
        a.Key AS ActivityId, 
        a.StartDate AS ActivityStart,
        a.EndDate AS ActivityEnd,
        p.Id AS ProjectId, 
        p.Name AS ProjectName,
        s.Name AS StatusName
    FROM Hours h 
    JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
    JOIN Projects p ON a.Project = p.Id AND p.Status = 3
    JOIN TeamHours th ON th.Activity = h.Activity AND h.Project = th.Project AND h.Year = th.Year AND th.Team = :team
    LEFT JOIN HourStatus s ON h.Status = s.Id
    WHERE h.Person = :userid AND h.Plan > 0 AND a.IsTask = 1 AND h.Status < 5 AND h.Year = :selectedYear
    ORDER BY Priority, h.Status
");
$tasksStmt->execute([
    ':team' => $userInfo['Team'],
    ':selectedYear' => $selectedYear,
    ':userid' => $userId,
]);
$tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);

// Get hours distribution by project
$projectHoursStmt = $pdo->prepare("
    SELECT 
        p.Id AS ProjectId,
        p.Name AS ProjectName,
        SUM(h.Hours) AS LoggedHours
    FROM Hours h
    JOIN Projects p ON h.Project = p.Id
    WHERE h.Person = :userid AND h.Hours>0 AND h.Year = :selectedYear
    GROUP BY p.Id, p.Name
    ORDER BY LoggedHours DESC
");
$projectHoursStmt->execute([
    ':userid' => $userId,
    ':selectedYear' => $selectedYear
]);
$projectHours = $projectHoursStmt->fetchAll(PDO::FETCH_ASSOC);

// Get projects managed by this user
$managedProjectsStmt = $pdo->prepare("
    SELECT 
        p.Id AS ProjectId,
        p.Name AS ProjectName,
        s.Status AS ProjectStatus
    FROM Projects p
    JOIN Status s ON p.Status = s.Id
    WHERE p.Manager = :userid
");
$managedProjectsStmt->execute([':userid' => $userId]);
$managedProjects = $managedProjectsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get tasks in each status
$tasksByStatusStmt = $pdo->prepare("
    SELECT 
        s.Name AS StatusName,
        COUNT(*) AS TaskCount
    FROM Hours h
    JOIN HourStatus s ON h.Status = s.Id
    JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
    JOIN Projects p on a.Project = p.Id AND p.Status = 3
    WHERE h.Person = :userid AND h.Plan>0 AND h.Year = :selectedYear AND h.Status < 5 AND a.IsTask = 1
    GROUP BY s.Name
    ORDER BY s.Id
");
$tasksByStatusStmt->execute([
    ':userid' => $userId,
    ':selectedYear' => $selectedYear
]);
$tasksByStatus = $tasksByStatusStmt->fetchAll(PDO::FETCH_ASSOC);

// Get WBSO activities
$wbsoStmt = $pdo->prepare("
    SELECT 
        a.Name AS ActivityName,
        w.Name AS WBSOName,
        w.Description AS WBSODescription,
        h.Hours AS LoggedHours,
        h.Plan AS PlannedHours
    FROM Hours h
    JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
    JOIN Wbso w ON a.Wbso = w.Id
    WHERE h.Person = :userid AND h.Year = :selectedYear AND a.Wbso IS NOT NULL
");
$wbsoStmt->execute([
    ':userid' => $userId,
    ':selectedYear' => $selectedYear
]);
$wbsoActivities = $wbsoStmt->fetchAll(PDO::FETCH_ASSOC);

// Format for display
$formatHours = function($hours) {
    return number_form($hours / 100, 2);
};

// Calculate percent complete
$calculateProgress = function($logged, $planned) {
    $logged = $logged / 100;
    $planned = $planned / 100;
    $percent = $planned > 0 ? round(($logged / $planned) * 100) : 0;
    return [
        'real' => $percent,
        'display' => min(100, $percent),
        'overshoot' => $percent > 100 ? 'overshoot' : ''
    ];
};

// Get upcoming deadlines
$upcomingDeadlinesStmt = $pdo->prepare("
    SELECT 
        a.Name AS ActivityName,
        p.Name AS ProjectName,
        a.EndDate AS Deadline
    FROM Activities a
    JOIN Projects p ON a.Project = p.Id
    JOIN Hours h ON h.Activity = a.Key AND h.Project = a.Project
    WHERE h.Person = :userid AND a.EndDate >= CURDATE() AND a.EndDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY a.EndDate ASC
    LIMIT 5
");
$upcomingDeadlinesStmt->execute([':userid' => $userId]);
$upcomingDeadlines = $upcomingDeadlinesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section id="personal-dashboard">
    <div class="container">
        <!-- User Profile Summary -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card profile-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 text-center">
                                <div class="profile-image">
                                    <?= strtoupper(substr($userInfo['Shortname'], 0, 2)) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h2><?= htmlspecialchars($userInfo['Name']) ?></h2>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($userInfo['Email']) ?></p>
                                <p><i class="fas fa-briefcase"></i> <?= htmlspecialchars($userInfo['TeamName']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <div class="yearly-hours-summary">
                                    <h4>Total Hours VS Planned</h4>
                                    <div class="progress-container">
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                style="width: <?= min(100, ($totalLogged / max($totalPlanned, 1)) * 100) ?>%;" 
                                                aria-valuenow="<?= $totalLogged ?>" aria-valuemin="0" aria-valuemax="<?= $totalPlanned ?>">
                                                <?= round(($totalLogged / max($totalPlanned, 1)) * 100) ?>%
                                            </div>
                                        </div>
                                        <div class="text-center"><?= number_form($totalLogged, 1) ?> / <?= number_form($totalPlanned, 1) ?> hours</div>
                                    </div>
                                    <div class="mt-3">
                                        <p>Year progress: <?= $yearProgress ?>%</p>
                                        <div class="progress">
                                            <div class="progress-bar bg-info" role="progressbar" 
                                                style="width: <?= $yearProgress ?>%;" 
                                                aria-valuenow="<?= $yearProgress ?>" aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-md-4">
                <!-- Task Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-chart-pie"></i> Task Overview</h4>
                    </div>
                    <div class="card-body">
                        <div class="task-status-summary">
                            <?php foreach ($tasksByStatus as $status): ?>
                                <div class="status-item">
                                    <div class="status-label"><?= htmlspecialchars($status['StatusName']) ?></div>
                                    <div class="status-count"><a href="/kanban.php" class="hidden-link"><?= $status['TaskCount'] ?></a></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Projects Distribution -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-project-diagram"></i> Hour Distribution</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($projectHours as $project): ?>
                            <?php 
                                $hours = $project['LoggedHours'] / 100;
                                $percentage = $totalLogged > 0 ? round(($hours / $totalLogged) * 100) : 0; 
                            ?>
                            <div class="project-hours-item">
                                <div class="project-name">
                                    <a href="/project_details.php?project_id=<?= $project['ProjectId'] ?>" class="hidden-link"><?= htmlspecialchars($project['ProjectName']) ?></a>
                                </div>
                                <div class="project-hours"><?= number_form($hours, 1) ?> hrs</div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" 
                                        style="width: <?= $percentage ?>%;" 
                                        aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?= $percentage ?>%
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Middle Column - Tasks -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-tasks"></i> Task Priorities</h4>
                    </div>
                    <div class="card-body tasks-container">
                        <?php if (empty($tasks)): ?>
                            <div class="alert alert-info">No tasks currently assigned to you.</div>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                    $progress = $calculateProgress($task['LoggedHours'], $task['PlannedHours']);
                                    
                                    // Calculate days until deadline
                                    $endDate = new DateTime($task['ActivityEnd']);
                                    $daysLeft = $currentDate->diff($endDate)->format("%R%a");
                                    $deadlineClass = '';
                                    
                                    if ($daysLeft < 0) {
                                        $deadlineClass = 'task-overdue';
                                    } elseif ($daysLeft <= 7) {
                                        $deadlineClass = 'task-urgent';
                                    }
                                ?>
                                <div class="task-card <?= $deadlineClass ?>">
                                    <div class="task-header bg-primary text-white">
                                        <span class="project-name">
                                            <a href="/project_details.php?project_id=<?= $task['ProjectId'] ?>" class="hidden-link"><?= htmlspecialchars($task['ProjectName']) ?></a>
                                        </span>
                                        <span class="task-priority"><?= ($task['Priority']>0 && $task['Priority']<250) ? $task['Priority'] : '' ?></span>
                                    </div>
                                    <div class="task-body">
                                        <div class="task-name"><?= htmlspecialchars($task['ActivityName']) ?></div>
                                        <div class="task-details">
                                            <div class="task-status">
                                                <span class="status-indicator status-<?= strtolower($task['StatusName']) ?>"></span>
                                                <a href="/kanban.php" class="hidden-link">
                                                    <?= htmlspecialchars($task['StatusName']) ?>
                                                </a>
                                            </div>
                                            <div class="task-deadline">
                                                Due: <?= date('M j, Y', strtotime($task['ActivityEnd'])) ?>
                                                <?php if ($daysLeft < 0): ?>
                                                    <span class="overdue-tag">Overdue</span>
                                                <?php elseif ($daysLeft <= 7): ?>
                                                    <span class="urgent-tag">Soon</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="task-progress">
                                            <div class="hours-info">
                                                <?= $formatHours($task['LoggedHours']) ?> / <?= $formatHours($task['PlannedHours']) ?> hours
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar <?= $progress['overshoot'] ?>" 
                                                    role="progressbar" 
                                                    style="width: <?= $progress['display'] ?>%;" 
                                                    aria-valuenow="<?= $progress['display'] ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                    <?= $progress['real'] ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div> <!-- Close the card -->
            </div> <!-- Close the col-md-4 -->

            <!-- Right Column -->
            <div class="col-md-4">
                <!-- Upcoming Deadlines -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-clock"></i> Upcoming Deadlines</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingDeadlines)): ?>
                            <div class="alert alert-info">No upcoming deadlines in the next 30 days.</div>
                        <?php else: ?>
                            <ul class="deadline-list">
                                <?php foreach ($upcomingDeadlines as $deadline): ?>
                                    <?php
                                        $deadlineDate = new DateTime($deadline['Deadline']);
                                        $daysUntil = $currentDate->diff($deadlineDate)->format("%a");
                                        $urgencyClass = $daysUntil <= 7 ? 'urgent-deadline' : '';
                                    ?>
                                    <li class="deadline-item <?= $urgencyClass ?>">
                                        <div class="deadline-project"><?= htmlspecialchars($deadline['ProjectName']) ?></div>
                                        <div class="deadline-activity"><?= htmlspecialchars($deadline['ActivityName']) ?></div>
                                        <div class="deadline-date">
                                            <i class="far fa-calendar-alt"></i>
                                            <?= date('M j, Y', strtotime($deadline['Deadline'])) ?>
                                            <span class="days-left">(<?= $daysUntil ?> days left)</span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Managed Projects (if any) -->
                <?php if (!empty($managedProjects)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-user-tie"></i> Projects You Manage</h4>
                    </div>
                    <div class="card-body">
                        <ul class="managed-projects-list">
                            <?php foreach ($managedProjects as $project): ?>
                                <li class="managed-project">
                                    <div class="project-name">
                                        <a href="/project_details.php?project_id=<?= $project['ProjectId'] ?>" class="hidden-link"><?= htmlspecialchars($project['ProjectName']) ?></a>
                                    </div>
                                    <div class="project-status">
                                        <span class="status-badge status-<?= strtolower($project['ProjectStatus']) ?>">
                                            <?= htmlspecialchars($project['ProjectStatus']) ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <!-- WBSO Activities (if applicable) -->
                <?php if (!empty($wbsoActivities) && $userInfo['WBSO'] == 1): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-flask"></i> WBSO Activities</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($wbsoActivities as $wbso): ?>
                            <?php $progress = $calculateProgress($wbso['LoggedHours'], $wbso['PlannedHours']); ?>
                            <div class="wbso-item">
                                <div class="wbso-name"><?= htmlspecialchars($wbso['WBSOName']) ?></div>
                                <div class="wbso-activity"><?= htmlspecialchars($wbso['ActivityName']) ?></div>
                                <div class="wbso-description"><?= htmlspecialchars($wbso['WBSODescription']) ?></div>
                                <div class="wbso-progress">
                                    <div class="hours-info">
                                        <?= $formatHours($wbso['LoggedHours']) ?> / <?= $formatHours($wbso['PlannedHours']) ?> hours
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?= $progress['overshoot'] ?>" 
                                            role="progressbar" 
                                            style="width: <?= $progress['display'] ?>%;" 
                                            aria-valuenow="<?= $progress['display'] ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100">
                                            <?= $progress['real'] ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>