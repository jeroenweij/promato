<?php
$pageSpecificCSS = ['plantable.css'];
require 'includes/header.php';
require 'includes/db.php';

// ---- DATA PREPARATION ----
// Fetch teams first
$deptStmt = $pdo->prepare("SELECT Id, Name, Ord FROM Teams ORDER BY Ord");
$deptStmt->execute();
$teams = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
$teamById = [];
foreach ($teams as $dept) {
    $teamById[$dept['Id']] = $dept;
}

// Fetch personnel with available hours - doing a more efficient query with proper joins
$stmt = $pdo->prepare("
    SELECT 
        p.Shortname AS Name, 
        p.Id AS Number, 
        p.Fultime, 
        p.Team,
        COALESCE(h.Plan, 0) AS AvailableHours,
        d.Ord AS DeptOrder,
        p.Ord AS PersonOrder
    FROM Personel p 
    LEFT JOIN Hours h ON h.Person = p.Id AND h.Project = 0 AND h.Activity = 0 AND `Year`= :selectedYear
    LEFT JOIN Teams d ON p.Team = d.Id
    WHERE p.plan = 1
    AND YEAR(p.StartDate) <= :selectedYear 
    AND (p.EndDate IS NULL OR YEAR(p.EndDate) >= :selectedYear)
    ORDER BY d.Ord, p.Ord, p.Name
");

// Execute the prepared statement
$stmt->execute([
    ':selectedYear' => $selectedYear
]);

$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
$personnelById = []; // For quicker lookup
$teamTotals = []; // Team aggregated data

// Initialize person summary - calculate available hours once
foreach ($personnel as $p) {
    $id = $p['Number'];
    $deptId = $p['Team'];
    
    $available = $p['AvailableHours'] > 0
        ? round($p['AvailableHours'] / 100)  // Stored as hundredths in DB
        : round(($p['Fultime'] ?? 100) * 2080 / 100); // Fallback to estimate

    $personnelById[$id] = $p;
    $personnelById[$id]['available'] = $available;
    $personnelById[$id]['planned'] = 0;
    $personnelById[$id]['realised'] = 0;
    
    // Initialize team totals if not exists
    if (!isset($teamTotals[$deptId])) {
        $teamTotals[$deptId] = [
            'id' => $deptId,
            'name' => $teamById[$deptId]['Name'] ?? 'Unknown',
            'available' => 0,
            'planned' => 0,
            'realised' => 0
        ];
    }
    
    // Add to team totals
    $teamTotals[$deptId]['available'] += $available;
}

// Filter out teams with no active personnel
$teams = array_filter($teams, function($dept) use ($teamTotals) {
    return isset($teamTotals[$dept['Id']]) && $teamTotals[$dept['Id']]['available'] > 0;
});

// Single query to fetch all hours data grouped by person
$stmtPersonHours = $pdo->query(
    "SELECT 
        Person,
        SUM(CASE WHEN Project > 0 THEN Plan ELSE 0 END) AS TotalPlanned,
        SUM(CASE WHEN Project = 0 THEN Hours ELSE 0 END) - 
        SUM(CASE WHEN Project = 10 AND Activity = 7 THEN Hours ELSE 0 END) AS TotalRealised
    FROM Hours WHERE `Year` = $selectedYear
    GROUP BY Person"
);

// Update person summaries with planned and realized hours
while ($row = $stmtPersonHours->fetch(PDO::FETCH_ASSOC)) {
    $pid = $row['Person'];
    if (!isset($personnelById[$pid])) continue;

    $plannedHours = $row['TotalPlanned'] / 100;
    $realisedHours = $row['TotalRealised'] / 100;
    
    $personnelById[$pid]['planned'] = $plannedHours;
    $personnelById[$pid]['realised'] = $realisedHours;
    
    // Add to team totals
    $deptId = $personnelById[$pid]['Team'];
    if (isset($teamTotals[$deptId])) {
        $teamTotals[$deptId]['planned'] += $plannedHours;
        $teamTotals[$deptId]['realised'] += $realisedHours;
    }
}

// Fetch all projects and activities in a single query with JOINs
$activitiesQuery = $pdo->prepare("
    SELECT 
        a.Project, 
        a.Key, 
        a.Name AS ActivityName, 
        b.Hours AS BudgetHours, 
        p.Name AS ProjectName, 
        pe.ShortName AS Manager, 
        p.Manager AS ManagerId,
        p.Status
    FROM Activities a
    JOIN Projects p ON a.Project = p.Id 
    LEFT JOIN Personel pe ON p.Manager = pe.Id 
    LEFT JOIN Budgets b ON a.Id = b.Activity
    WHERE p.Status > 2 
    AND a.Visible = 1 
    AND YEAR(a.StartDate) <= :selectedYear 
    AND YEAR(a.EndDate) >= :selectedYear
    ORDER BY p.Status, p.Id, a.Key
");

// Execute the prepared statement
$activitiesQuery->execute([
    ':selectedYear' => $selectedYear
]);

$activities = $activitiesQuery->fetchAll(PDO::FETCH_ASSOC);

// Efficiently fetch all hours data for all activities at once
$projectActivities = [];
foreach ($activities as $a) {
    $key = $a['Project'] . '-' . $a['Key'];
    $projectActivities[$key] = true;
}

$activityKeys = array_keys($projectActivities);
$placeholders = implode(',', array_fill(0, count($activityKeys), '?'));
if (empty($placeholders)){
    $placeholders="''";
}

// Get both planned and actual hours for activities from real people
$allHoursQuery = $pdo->prepare("
    SELECT 
        CONCAT(h.Project, '-', h.Activity) AS ProjectActivity,
        h.Person, 
        h.Hours, 
        h.Plan,
        COALESCE(p.Team, 0) AS Team
    FROM Hours h
    LEFT JOIN Personel p ON h.Person = p.Id
    WHERE h.`Year` = $selectedYear 
    AND CONCAT(h.Project, '-', h.Activity) IN ($placeholders)
    AND h.Person > 0
");

// We need to execute with flattened array values
$params = [];
foreach ($activities as $a) {
    $params[] = $a['Project'] . '-' . $a['Key'];
}
$allHoursQuery->execute(array_unique($params));

// Organize hours data for quick access grouped by team
$teamHoursData = [];

// Process both planned and actual hours by team
while ($row = $allHoursQuery->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['ProjectActivity'];
    $personId = $row['Person'];
    $deptId = $row['Team'];
    
    // Aggregate both planned and actual hours by team
    if (!isset($teamHoursData[$key])) {
        $teamHoursData[$key] = [];
    }
    if (!isset($teamHoursData[$key][$deptId])) {
        $teamHoursData[$key][$deptId] = [
            'Plan' => 0,
            'Hours' => 0
        ];
    }
    $teamHoursData[$key][$deptId]['Plan'] += $row['Plan'];
    $teamHoursData[$key][$deptId]['Hours'] += $row['Hours'];
}

// Additional preparation for project grouping
$projectGroups = [];
foreach ($activities as $activity) {
    $projectId = $activity['Project'];
    if (!isset($projectGroups[$projectId])) {
        $projectGroups[$projectId] = [
            'id' => $projectId,
            'Status' => $activity['Status'],
            'name' => $activity['ProjectName'],
            'manager' => $activity['Manager'],
            'managerId' => $activity['ManagerId'],
            'activities' => []
        ];
    }
    $projectGroups[$projectId]['activities'][] = $activity;
}

$currentStatus = 3;

// Start output with CSS optimization
?>
<section class="white">
    <div class="container" style="max-width: 20000px;">
        <div class="autoheight">
            <div class="budget-table-wrapper">
                <!-- Scrollable area with fixed and scrollable columns -->
                <div class="scrollable-area verticalscrol">
                <!-- Fixed left columns -->
                <div class="fixed-columns">
                    <table class="plantable">
                        <tr>
                            <th colspan="5" class="text fixedheigth">&nbsp;</th>
                        </tr>
                        <tr><td colspan="5" class="text fixedheigth"><b>Available (hrs)</b></td></tr>
                        <tr><td colspan="5" class="text fixedheigth"><b>Planned / realised</b></td></tr>
                        
                        <?php foreach ($projectGroups as $project): 
                            if ($currentStatus != $project["Status"]): 
                                $currentStatus = $project["Status"];
                                ?>
                                <tr>
                                <td colspan="100%" class="headerspacer"> 
                                <div class="spacer" style="height: 128px;">
                                    &nbsp;
                                </div>
                                    <div style="display: flex; align-items: center; gap: 10px; height: 64px;">
                                        <h4 style="margin: 0;">
                                        Closed Projects
                                        </h4>
                                    </div>
                                </td>
                                </tr>
                            
                            <?php endif; ?>
                            <tr>
                                <td colspan="100%" class="headerspacer">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <h4 style="margin: 0;">
                                            <a href="project_details.php?project_id=<?= htmlspecialchars($project['id']) ?>">
                                                <b><?= $project['id'] ?> <?= htmlspecialchars($project['name']) ?></b> 
                                                (<?= htmlspecialchars($project['manager'] ?? '') ?>)
                                            </a>
                                        </h4>
                                        <?php if ($userAuthLevel >= 4): ?>
                                            <a href="project_edit.php?project_id=<?= htmlspecialchars($project['id']) ?>">Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th class="text fixedheigth">TaskCode</th>
                                <th class="text fixedheigth">Activity Name</th>
                                <th class="text fixedheigth">Available</th>
                                <th class="text fixedheigth">Planned</th>
                                <th class="text fixedheigth">Realised</th>
                            </tr>
                            
                            <?php foreach ($project['activities'] as $activity): ?>
                                <?php
                                $taskCode = $activity['Project'] . '-' . str_pad($activity['Key'], 3, '0', STR_PAD_LEFT);
                                $activityKey = $activity['Project'] . '-' . $activity['Key'];
                                
                                // Calculate totals for this activity across all teams
                                $planned = 0;
                                $realised = 0;
                                
                                if (isset($teamHoursData[$activityKey])) {
                                    foreach ($teamHoursData[$activityKey] as $deptId => $data) {
                                        $planned += $data['Plan'] / 100;
                                        $realised += $data['Hours'] / 100;
                                    }
                                }
                                
                                $plannedClass = ($activity['BudgetHours'] && $planned > $activity['BudgetHours']) ? 'overbudget' : '';
                                $realisedClass = ($planned > 0 && $realised > $planned) ? 'overbudget' : '';
                                ?>
                                <tr>
                                    <td class="text fixedheigth"><?= $taskCode ?></td>
                                    <td class="text fixedheigth"><?= htmlspecialchars($activity['ActivityName']) ?></td>
                                    <td class="totals fixedheigth"><?= $activity['BudgetHours'] ?></td>
                                    <td class="totals <?= $plannedClass ?> fixedheigth"><?= $planned ?></td>
                                    <td class="totals <?= $realisedClass ?>"><?= $realised ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </table>
                    <br>&nbsp;<br>&nbsp;
                </div><!-- fixed-columns -->
                
                <!-- Scrollable right columns -->
                <div class="scrollable-columns">
                    <table class="plantable">
                        <tr>
                            <?php foreach ($teams as $dept): ?>
                                <th colspan="2" class="name fixedheigth">
                                    <a href="capacity_planning.php?team=<?= urlencode($dept['Id']) ?>" style="color: inherit; text-decoration: none;">
                                        <?= htmlspecialchars($dept['Name']) ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($teams as $dept): ?>
                                <td colspan="2" class="totals available-total fixedheigth" data-team="<?= $dept['Id'] ?>">
                                    <?= $teamTotals[$dept['Id']]['available'] ?? 0 ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($teams as $dept): 
                                $deptId = $dept['Id'];
                                $planned = $teamTotals[$deptId]['planned'] ?? 0;
                                $realised = $teamTotals[$deptId]['realised'] ?? 0;
                                $available = $teamTotals[$deptId]['available'] ?? 0;
                                
                                $plannedClass = $planned > $available ? 'overbudget' : '';
                                $realisedClass = $realised > $planned ? 'overbudget' : '';
                            ?>
                                <td class="totals fixedheigth planned-total <?= $plannedClass ?>" data-team="<?= $deptId ?>">
                                    <?= round($planned, 2) ?>
                                </td>
                                <td class="totals fixedheigth realised-total <?= $realisedClass ?>" data-team="<?= $deptId ?>">
                                    <?= round($realised, 2) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <?php 
                        $currentStatus = 3;
                        foreach ($projectGroups as $project):  
                            if ($currentStatus != $project["Status"]): 
                                $currentStatus = $project["Status"];
                                ?>
                                <tr>
                                <td colspan="100%" class="headerspacer"> 
                                    <div class="spacer" style="height: 128px;">
                                        &nbsp;
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px; height: 64px;">
                                        &nbsp;
                                    </div>
                                </td>
                                </tr>
                            
                            <?php endif; ?>
                            <tr>
                                <td colspan="100%" class="headerspacer">&nbsp;</td>
                            </tr>
                            <tr>
                                <?php foreach ($teams as $dept): ?>
                                    <th colspan="2" class="name fixedheigth">
                                        <a href="capacity_planning.php?team=<?= urlencode($dept['Id']) ?>" style="color: inherit; text-decoration: none;">
                                            <?= htmlspecialchars($dept['Name']) ?>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                            
                            <?php foreach ($project['activities'] as $activity): ?>
                                <?php $activityKey = $activity['Project'] . '-' . $activity['Key']; ?>
                                <tr>
                                    <?php foreach ($teams as $dept): 
                                        $deptId = $dept['Id'];
                                        $plan = '';
                                        $hours = '&nbsp;';
                                        
                                        // Get both planned and actual hours for this team
                                        if (isset($teamHoursData[$activityKey][$deptId])) {
                                            $entry = $teamHoursData[$activityKey][$deptId];
                                            $planVal = $entry['Plan'] / 100;
                                            $hoursVal = $entry['Hours'] / 100;
                                            
                                            if ($planVal > 0) $plan = $planVal;
                                            if ($hoursVal > 0) $hours = $hoursVal;
                                        }
                                        
                                        $overbudget = ($hours != '&nbsp;' && $plan > 0 && $hours > $plan) ? 'overbudget' : '';
                                        $isEditable = false; // Team view is read-only for now
                                    ?>
                                        <td class="editbudget fixedheigth"><?= $plan ?></td>
                                        <td class="budget <?= $overbudget ?> fixedheigth"><?= $hours ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </table>
                    <br>&nbsp;<br>&nbsp;
                </div><!-- scrollable-columns -->
                </div><!-- Scrollable area with fixed and scrollable columns -->
            </div><!-- budget-table-wrapper -->
        </div><!-- autoheight -->
            <!-- Fixed horizontal scrollbar at bottom -->
            <div class="horizontal-scroll-container" id="horizontal-scrollbar">
                <div class="horizontal-scroll-content" id="scrollbar-content"></div>
            </div>
    </div><!-- container -->
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scrollableContent = document.querySelector('.scrollable-columns');
    const horizontalScrollbar = document.getElementById('horizontal-scrollbar');
    const scrollbarContent = document.getElementById('scrollbar-content');

    // Sync scrollbar width with content width
    function updateScrollbarWidth() {
        const tableWidth = scrollableContent.querySelector('table').offsetWidth;
        scrollbarContent.style.width = tableWidth + 'px';
    }
    
    // Initial setup and on resize
    updateScrollbarWidth();
    window.addEventListener('resize', updateScrollbarWidth);

    // Flag to prevent infinite loop of scroll events
    let isScrolling = false;
    
    // Sync scrolling between content and scrollbar  
    horizontalScrollbar.addEventListener('scroll', function() {
        if (isScrolling) return;
        isScrolling = true;
        
        // Calculate the scroll position as a ratio
        const maxScroll = horizontalScrollbar.scrollWidth - horizontalScrollbar.clientWidth;
        const currentRatio = horizontalScrollbar.scrollLeft / maxScroll;
        
        // Apply the same ratio to the content
        const contentMaxScroll = scrollableContent.scrollWidth - scrollableContent.clientWidth;
        scrollableContent.scrollLeft = currentRatio * contentMaxScroll;
        
        isScrolling = false;
    });
});

// More efficient JavaScript with debouncing
(function() {
    // Set initial height
    function setElementHeight() {
        var height = window.innerHeight - 130;
        document.querySelectorAll('.autoheight').forEach(el => {
            el.style.minHeight = height + 'px';
            el.style.maxHeight = height + 'px';
        });
    }

    // Debounce function to limit resize events
    function debounce(func, wait) {
        let timeout;
        return function() {
            clearTimeout(timeout);
            timeout = setTimeout(func, wait);
        };
    }

    // Initial setup
    window.addEventListener('DOMContentLoaded', setElementHeight);
    window.addEventListener('resize', debounce(setElementHeight, 100));
})();
</script>

<?php require 'includes/footer.php'; ?>