<?php
$pageSpecificCSS = ['search.css'];
require 'includes/header.php';
require_once 'includes/db.php';

$query = trim($_GET['q'] ?? '');
$results = [
    'projects' => [],
    'activities' => [],
    'personnel' => [],
    'teams' => []
];
$totalResults = 0;

if (!empty($query)) {
    $searchTerm = '%' . $query . '%';

    // Search Projects
    $stmt = $pdo->prepare("
        SELECT
            p.Id,
            p.Name,
            s.Status,
            pe.Shortname as Manager
        FROM Projects p
        LEFT JOIN Status s ON p.Status = s.Id
        LEFT JOIN Personel pe ON p.Manager = pe.Id
        WHERE p.Id LIKE :term
           OR p.Name LIKE :term
        LIMIT 20
    ");
    $stmt->execute([':term' => $searchTerm]);
    $results['projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Search Activities (by task code or name)
    $stmt = $pdo->prepare("
        SELECT
            a.Id,
            a.Project,
            a.Key,
            a.Name,
            p.Name as ProjectName,
            a.StartDate,
            a.EndDate
        FROM Activities a
        JOIN Projects p ON a.Project = p.Id
        WHERE a.Name LIKE :term
           OR CONCAT(a.Project, '-', LPAD(a.Key, 3, '0')) LIKE :term
           OR a.Id LIKE :term
        ORDER BY a.Project, a.Key
        LIMIT 20
    ");
    $stmt->execute([':term' => $searchTerm]);
    $results['activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Search Personnel
    $stmt = $pdo->prepare("
        SELECT
            p.Id,
            p.Name,
            p.Shortname,
            p.Email,
            t.Name as TeamName
        FROM Personel p
        LEFT JOIN Teams t ON p.Team = t.Id
        WHERE p.Name LIKE :term
           OR p.Shortname LIKE :term
           OR p.Email LIKE :term
           OR p.Id LIKE :term
        LIMIT 20
    ");
    $stmt->execute([':term' => $searchTerm]);
    $results['personnel'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Search Teams
    $stmt = $pdo->prepare("
        SELECT
            Id,
            Name
        FROM Teams
        WHERE Name LIKE :term
        LIMIT 10
    ");
    $stmt->execute([':term' => $searchTerm]);
    $results['teams'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count total results
    $totalResults = count($results['projects']) + count($results['activities']) +
                    count($results['personnel']) + count($results['teams']);
}
?>

<section>
    <div class="container">
        <div class="search-header">
            <!-- Search Form -->
            <form method="GET" action="search.php" class="search-form-main">
                <div class="search-input-container">
                    <input
                        type="text"
                        name="q"
                        id="searchInput"
                        class="form-control search-input"
                        placeholder="Search projects, tasks, people..."
                        value="<?= htmlspecialchars($query) ?>"
                        autofocus>
                    <button type="submit" class="btn btn-primary search-btn">
                        <i class="icon ion-md-search"></i>
                        Search
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($query)): ?>
            <div class="search-results-summary">
                <h3>
                    <?php if ($totalResults > 0): ?>
                        Found <?= $totalResults ?> result<?= $totalResults !== 1 ? 's' : '' ?> for "<?= htmlspecialchars($query) ?>"
                    <?php else: ?>
                        No results found for "<?= htmlspecialchars($query) ?>"
                    <?php endif; ?>
                </h3>
            </div>

            <?php if ($totalResults > 0): ?>
                <div class="search-results">

                    <!-- Projects Results -->
                    <?php if (!empty($results['projects'])): ?>
                        <div class="result-section">
                            <h4 class="result-section-title">
                                <i class="icon ion-md-briefcase"></i>
                                Projects (<?= count($results['projects']) ?>)
                            </h4>
                            <div class="result-list">
                                <?php foreach ($results['projects'] as $project): ?>
                                    <a href="project_details.php?project_id=<?= $project['Id'] ?>" class="result-item">
                                        <div class="result-icon">
                                            <i class="icon ion-md-folder"></i>
                                        </div>
                                        <div class="result-content">
                                            <div class="result-title">
                                                <?= $project['Id'] ?> - <?= htmlspecialchars($project['Name']) ?>
                                            </div>
                                            <div class="result-meta">
                                                Status: <span class="badge badge-status"><?= htmlspecialchars($project['Status']) ?></span>
                                                <?php if ($project['Manager']): ?>
                                                    · Manager: <?= htmlspecialchars($project['Manager']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="result-arrow">
                                            <i class="icon ion-md-arrow-forward"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Activities Results -->
                    <?php if (!empty($results['activities'])): ?>
                        <div class="result-section">
                            <h4 class="result-section-title">
                                <i class="icon ion-md-list"></i>
                                Tasks/Activities (<?= count($results['activities']) ?>)
                            </h4>
                            <div class="result-list">
                                <?php foreach ($results['activities'] as $activity): ?>
                                    <?php
                                        $taskCode = $activity['Project'] . '-' . str_pad($activity['Key'], 3, '0', STR_PAD_LEFT);
                                    ?>
                                    <a href="project_details.php?project_id=<?= $activity['Project'] ?>" class="result-item">
                                        <div class="result-icon">
                                            <i class="icon ion-md-checkmark-circle-outline"></i>
                                        </div>
                                        <div class="result-content">
                                            <div class="result-title">
                                                <span class="task-code"><?= $taskCode ?></span>
                                                <?= htmlspecialchars($activity['Name']) ?>
                                            </div>
                                            <div class="result-meta">
                                                Project: <?= htmlspecialchars($activity['ProjectName']) ?>
                                                · <?= date('M j, Y', strtotime($activity['StartDate'])) ?> - <?= date('M j, Y', strtotime($activity['EndDate'])) ?>
                                            </div>
                                        </div>
                                        <div class="result-arrow">
                                            <i class="icon ion-md-arrow-forward"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Personnel Results -->
                    <?php if (!empty($results['personnel'])): ?>
                        <div class="result-section">
                            <h4 class="result-section-title">
                                <i class="icon ion-md-people"></i>
                                People (<?= count($results['personnel']) ?>)
                            </h4>
                            <div class="result-list">
                                <?php foreach ($results['personnel'] as $person): ?>
                                    <?php if ($userAuthLevel >= 5): ?>
                                        <a href="personel_edit.php?id=<?= $person['Id'] ?>" class="result-item">
                                    <?php else: ?>
                                        <div class="result-item result-item-readonly">
                                    <?php endif; ?>
                                        <div class="result-icon">
                                            <i class="icon ion-md-person"></i>
                                        </div>
                                        <div class="result-content">
                                            <div class="result-title">
                                                <?= htmlspecialchars($person['Name']) ?>
                                                <span class="text-muted">(<?= htmlspecialchars($person['Shortname']) ?>)</span>
                                            </div>
                                            <div class="result-meta">
                                                <?= htmlspecialchars($person['Email']) ?>
                                                <?php if ($person['TeamName']): ?>
                                                    · Team: <?= htmlspecialchars($person['TeamName']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($userAuthLevel >= 5): ?>
                                            <div class="result-arrow">
                                                <i class="icon ion-md-arrow-forward"></i>
                                            </div>
                                        <?php endif; ?>
                                    <?php if ($userAuthLevel >= 5): ?>
                                        </a>
                                    <?php else: ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Teams Results -->
                    <?php if (!empty($results['teams'])): ?>
                        <div class="result-section">
                            <h4 class="result-section-title">
                                <i class="icon ion-md-people"></i>
                                Teams (<?= count($results['teams']) ?>)
                            </h4>
                            <div class="result-list">
                                <?php foreach ($results['teams'] as $team): ?>
                                    <a href="capacity_planning.php?team=<?= $team['Id'] ?>" class="result-item">
                                        <div class="result-icon">
                                            <i class="icon ion-md-people"></i>
                                        </div>
                                        <div class="result-content">
                                            <div class="result-title">
                                                <?= htmlspecialchars($team['Name']) ?>
                                            </div>
                                        </div>
                                        <div class="result-arrow">
                                            <i class="icon ion-md-arrow-forward"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="icon ion-md-sad"></i>
                    <p>Try different keywords or check your spelling.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="search-suggestions">
                <h4>What can you search for?</h4>
                <ul>
                    <li><i class="icon ion-md-briefcase"></i> Projects by name or ID</li>
                    <li><i class="icon ion-md-list"></i> Tasks by code (e.g., "15-042") or activity name</li>
                    <li><i class="icon ion-md-person"></i> People by name, shortname, or email</li>
                    <li><i class="icon ion-md-people"></i> Teams by name</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
