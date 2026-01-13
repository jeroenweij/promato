<?php
$pageSpecificCSS = ['plantable.css'];
$pageSpecificJS = ['/js/table-export.js'];  // Use absolute path from web root
require 'includes/header.php';
require_once 'includes/db.php';

// ---- DATA PREPARATION ----
// Fetch teams first
$teamStmt = $pdo->prepare("SELECT Id, Name, Planable, Ord FROM Teams ORDER BY Ord");
$teamStmt->execute();
$teams = $teamStmt->fetchAll(PDO::FETCH_ASSOC);
$teamById = [];
foreach ($teams as $team) {
    $teamById[$team['Id']] = $team;
}

// Fetch personnel with available hours - for calculating team available capacity
$stmt = $pdo->prepare("
    SELECT 
        p.Team,
        COALESCE(h.Hours, 0) AS AvailableHours,
        p.Fultime
    FROM Personel p 
    LEFT JOIN Availability h ON h.Person = p.Id AND h.`Year` = :selectedYear
    WHERE p.plan = 1
    AND YEAR(p.StartDate) <= :selectedYear 
    AND (p.EndDate IS NULL OR YEAR(p.EndDate) >= :selectedYear)
");

$stmt->execute([':selectedYear' => $selectedYear]);
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize team totals
$teamTotals = [];
foreach ($personnel as $p) {
    $teamId = $p['Team'];
    
    $available = $p['AvailableHours'] > 0
        ? round($p['AvailableHours'] / 100)
        : round(($p['Fultime'] ?? 100) * 2080 / 100);
    
    if (!isset($teamTotals[$teamId])) {
        $teamTotals[$teamId] = [
            'id' => $teamId,
            'name' => $teamById[$teamId]['Name'] ?? 'Unknown',
            'available' => 0,
            'planned' => 0,
            'realised' => 0
        ];
    }
    
    $teamTotals[$teamId]['available'] += $available;
}

// Fetch planned and realised hours from TeamHours table
$stmtTeamHours = $pdo->prepare("
    SELECT 
        Team,
        SUM(Plan) AS TotalPlanned,
        SUM(Hours) - 
        SUM(CASE WHEN Project = 10 AND Activity = 7 THEN Hours ELSE 0 END) AS TotalRealised
    FROM TeamHours 
    WHERE `Year` = :selectedYear
    GROUP BY Team
");

$stmtTeamHours->execute([':selectedYear' => $selectedYear]);

// Update team totals with planned and realised hours
while ($row = $stmtTeamHours->fetch(PDO::FETCH_ASSOC)) {
    $teamId = $row['Team'];
    
    if (!isset($teamTotals[$teamId])) {
        $teamTotals[$teamId] = [
            'id' => $teamId,
            'name' => $teamById[$teamId]['Name'] ?? 'Unknown',
            'available' => 0,
            'planned' => 0,
            'realised' => 0
        ];
    }
    
    $teamTotals[$teamId]['planned'] = $row['TotalPlanned'] / 100;
    $teamTotals[$teamId]['realised'] = $row['TotalRealised'] / 100;
}

// Filter out teams with no active personnel
$teams = array_filter($teams, function($team) use ($teamTotals) {
    return isset($teamTotals[$team['Id']]) && $teamTotals[$team['Id']]['available'] > 0;
});

// Fetch all projects and activities
$activitiesQuery = $pdo->prepare("
    SELECT
        a.Project,
        a.Key,
        a.Name AS ActivityName,
        a.EndDate,
        a.Active,
        b.Hours AS BudgetHours,
        p.Name AS ProjectName,
        pe.ShortName AS Manager,
        p.Manager AS ManagerId,
        p.Status
    FROM Activities a
    JOIN Projects p ON a.Project = p.Id
    LEFT JOIN Personel pe ON p.Manager = pe.Id
    LEFT JOIN Budgets b ON a.Id = b.Activity AND b.Year = :selectedYear
    WHERE p.Status > 2
    AND a.Visible = 1
    AND YEAR(a.StartDate) <= :selectedYear
    AND YEAR(a.EndDate) >= :selectedYear
    ORDER BY p.Status, p.Id, a.Key
");

$activitiesQuery->execute([':selectedYear' => $selectedYear]);
$activities = $activitiesQuery->fetchAll(PDO::FETCH_ASSOC);

// Build list of activity keys for efficient query
$projectActivities = [];
foreach ($activities as $a) {
    $key = $a['Project'] . '-' . $a['Key'];
    $projectActivities[$key] = true;
}

$activityKeys = array_keys($projectActivities);
$placeholders = implode(',', array_fill(0, count($activityKeys), '?'));
if (empty($placeholders)){
    $placeholders = "''";
}

// Fetch team hours directly from TeamHours table
$allHoursQuery = $pdo->prepare("
    SELECT 
        CONCAT(Project, '-', Activity) AS ProjectActivity,
        Team,
        Hours,
        Plan
    FROM TeamHours
    WHERE `Year` = ?
    AND CONCAT(Project, '-', Activity) IN ($placeholders)
");

// Build params array with year first, then activity keys
$params = array_merge([$selectedYear], array_unique($activityKeys));
$allHoursQuery->execute($params);

// Organize hours data by team
$teamHoursData = [];
while ($row = $allHoursQuery->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['ProjectActivity'];
    $teamId = $row['Team'];
    
    if (!isset($teamHoursData[$key])) {
        $teamHoursData[$key] = [];
    }
    
    $teamHoursData[$key][$teamId] = [
        'Plan' => $row['Plan'],
        'Hours' => $row['Hours']
    ];
}

// Group activities by project
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
?>
<section class="white">
    <div class="container" style="max-width: 20000px;">
        <div class="autoheight">
            <div class="budget-table-wrapper">
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
                                    foreach ($teamHoursData[$activityKey] as $teamId => $data) {
                                        $planned += $data['Plan'] / 100;
                                        $realised += $data['Hours'] / 100;
                                    }
                                }
                                
                                $plannedClass = ($activity['BudgetHours'] && $planned > $activity['BudgetHours']) ? 'overbudget' : '';
                                $realisedClass = ($realised > $planned) ? 'overbudget' : '';

                                // Check if activity is closed (!Active or end date has passed)
                                $isClosed = ($activity['Status'] == 4 || !$activity['Active'] || (strtotime($activity['EndDate']) < strtotime('today')));
                                $closedClass = $isClosed ? 'closed' : '';
                                ?>
                                <tr>
                                    <td class="text fixedheigth <?= $closedClass ?>"><?= $taskCode ?></td>
                                    <td class="text fixedheigth <?= $closedClass ?>" ><?= htmlspecialchars($activity['ActivityName']) ?></td>
                                    <td class="totals fixedheigth <?= $closedClass ?>"><?= number_form($activity['BudgetHours']) ?></td>
                                    <td class="totals <?= $plannedClass ?> fixedheigth <?= $closedClass ?>"><?= number_form($planned) ?></td>
                                    <td class="totals <?= $realisedClass ?> <?= $closedClass ?>"><?= number_form($realised) ?></td>
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
                            <?php foreach ($teams as $team): ?>
                                <th colspan="2" class="name fixedheigth">
                                    <a href="capacity_planning.php?team=<?= urlencode($team['Id']) ?>" style="color: inherit; text-decoration: none;">
                                        <?= htmlspecialchars($team['Name']) ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($teams as $team): ?>
                                <td colspan="2" class="totals available-total fixedheigth" data-team="<?= $team['Id'] ?>">
                                    <?= number_form($teamTotals[$team['Id']]['available'] ?? 0) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($teams as $team): 
                                $teamId = $team['Id'];
                                $planned = $teamTotals[$teamId]['planned'] ?? 0;
                                $realised = $teamTotals[$teamId]['realised'] ?? 0;
                                $available = $teamTotals[$teamId]['available'] ?? 0;
                                
                                $plannedClass = $planned > $available ? 'overbudget' : '';
                                $realisedClass = $realised > $planned ? 'overbudget' : '';
                            ?>
                                <td class="totals fixedheigth planned-total <?= $plannedClass ?>" data-team="<?= $teamId ?>">
                                    <?= number_form($planned) ?>
                                </td>
                                <td class="totals fixedheigth realised-total <?= $realisedClass ?>" data-team="<?= $teamId ?>">
                                    <?= number_form($realised) ?>
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
                                <?php foreach ($teams as $team): ?>
                                    <th colspan="2" class="name fixedheigth">
                                        <a href="capacity_planning.php?team=<?= urlencode($team['Id']) ?>" style="color: inherit; text-decoration: none;">
                                            <?= htmlspecialchars($team['Name']) ?>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                            
                            <?php foreach ($project['activities'] as $activity): ?>
                                <?php $activityKey = $activity['Project'] . '-' . $activity['Key']; ?>
                                <tr>
                                    <?php foreach ($teams as $team): 
                                        $teamId = $team['Id'];
                                        $plan = '';
                                        $hours = '&nbsp;';
                                        
                                        // Get both planned and actual hours for this team
                                        if (isset($teamHoursData[$activityKey][$teamId])) {
                                            $entry = $teamHoursData[$activityKey][$teamId];
                                            $planVal = $entry['Plan'] / 100;
                                            $hoursVal = $entry['Hours'] / 100;
                                            
                                            if ($planVal > 0) $plan = $planVal;
                                            if ($hoursVal > 0) $hours = $hoursVal;
                                        }
                                        
                                        $overbudget = ($hours != '&nbsp;' && $plan > 0 && $hours > $plan) ? 'overbudget' : '';
                                        $isEditable = $userAuthLevel >= 4 || ($_SESSION['user_id'] ?? 0) == $activity['ManagerId'];
                                        ?>
                                            <?php if ($isEditable && $team['Planable']): ?>
                                                <td class="editbudget fixedheigth">
                                                    <input type="text" 
                                                          name="<?= $activity['Project'] ?>#<?= $activity['Key'] ?>#<?= $teamId ?>" 
                                                          value="<?= $plan ?>" 
                                                          maxlength="10" 
                                                          size="5" 
                                                          class="hiddentext editbudget" 
                                                          onchange="UpdateValue(this)">
                                                </td>
                                            <?php else: ?>
                                                <td class="fixedbudget fixedheigth"><?= $plan ?></td>
                                            <?php endif; ?>
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

        <?php if ($userAuthLevel >= 5): ?>
        <hr>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">    
            
            <div>
                <button class="btn btn-success" onclick="exportTeamPlanningToCSV()">
                    <i class="icon ion-md-download"></i> Export to CSV
                </button>
                <button class="btn btn-primary" onclick="exportTeamPlanningToExcel()">
                    <i class="icon ion-md-download"></i> Export to Excel
                </button>
            </div>
        </div>
        <?php endif; ?>

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
        var height = window.innerHeight - 150;
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

    // Handle updating values
    window.UpdateValue = function(input) {
        const [project, activity, team] = input.name.split('#');
        const value = parseFloat(input.value) || 0;

        // Update UI immediately for better UX
        updateUIForChangedValue(input);

        // Send data to server
        fetch('update_team_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `project=${project}&activity=${activity}&team=${team}&year=<?= $selectedYear ?>&plan=${value}`
        }).then(async res => {
        const text = await res.text(); // 🔹 read response body as text
            if (!res.ok) {
                alert(`Failed to save: project=${project}, activity=${activity}, team=${team}, plan=${value}`);
                alert(`${text}`);
                return;
            }
        }).catch(err => {
            console.error('Error saving data:', err);
            alert('Failed to save data. Please try again.');
        });
    }

    // Update UI without waiting for server response
    function updateUIForChangedValue(input) {
        const [project, activity, team] = input.name.split('#');
        const value = parseFloat(input.value) || 0;
        const row = input.closest('tr');
        
        // 1. Update row totals
        let totalPlanned = 0;
        row.querySelectorAll('input').forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) totalPlanned += v;
        });

        // 2. Check overbudget statuses for each cell in row
        row.querySelectorAll('input').forEach(inp => {
            const planVal = parseFloat(inp.value) || 0;
            const tdInput = inp.closest('td');
            const tdBudget = tdInput.nextElementSibling;

            if (tdBudget && tdBudget.classList.contains('budget')) {
                const loggedVal = parseFloat(tdBudget.innerText) || 0;
                tdBudget.classList.toggle('overbudget', loggedVal > 0 && loggedVal > planVal);
            }
        });
        
        // 3. Find and update the corresponding row in the fixed left table
        const taskCode = `${project}-${activity.padStart(3, '0')}`;
        const fixedTable = document.querySelector('.fixed-columns table');
        const fixedRows = fixedTable.querySelectorAll('tr');
        
        for (let i = 0; i < fixedRows.length; i++) {
            const cells = fixedRows[i].querySelectorAll('td');
            // Find the row with the matching task code (first column)
            if (cells.length > 0 && cells[0].textContent === taskCode) {
                // Get the relevant cells from the fixed table
                const budgetCell = cells[2];    // Available hours
                const plannedCell = cells[3];   // Planned hours
                const loggedCell = cells[4];    // Realized hours
                
                if (plannedCell && budgetCell) {
                    // Update the planned hours cell
                    plannedCell.textContent = totalPlanned;

                    // Check if planned is over budget (only if budget exists and is > 0)
                    const budgetText = budgetCell.textContent.trim();
                    if (budgetText !== '' && budgetText !== '0') {
                        // Convert European number format (1.500,5) to standard (1500.5)
                        const budget = parseFloat(budgetText.replace(/\./g, '').replace(',', '.')) || 0;
                        plannedCell.classList.toggle('overbudget', budget > 0 && totalPlanned > budget);
                    } else {
                        plannedCell.classList.remove('overbudget');
                    }

                    // Check if logged is over planned
                    if (loggedCell) {
                        const logged = parseFloat(loggedCell.textContent.replace(/\./g, '').replace(',', '.')) || 0;
                        loggedCell.classList.toggle('overbudget', logged > totalPlanned);
                    }
                }
                break;
            }
        }

        // 4. Update team total planned hours
        let teamPlanned = 0;
        document.querySelectorAll(`input[name$="#${team}"]`).forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) teamPlanned += v;
        });

        const teamPlannedCell = document.querySelector(`.planned-total[data-team="${team}"]`);
        const availableCell = document.querySelector(`.available-total[data-team="${team}"]`);
        const realisedCell = document.querySelector(`.realised-total[data-team="${team}"]`);
        
        if (teamPlannedCell) {
            teamPlannedCell.innerText = teamPlanned;

            // Check against available hours
            if (availableCell) {
                // Convert European number format (1.500,5) to standard (1500.5)
                const available = parseFloat(availableCell.innerText.replace(/\./g, '').replace(',', '.')) || 0;
                teamPlannedCell.classList.toggle('overbudget', teamPlanned > available);
            }

            // Check realised vs planned
            if (realisedCell) {
                // Convert European number format (1.500,5) to standard (1500.5)
                const realised = parseFloat(realisedCell.innerText.replace(/\./g, '').replace(',', '.')) || 0;
                realisedCell.classList.toggle('overbudget', realised > teamPlanned);
            }
        }
    }
})();

// Export button handlers - use shared export utility
function exportTeamPlanningToCSV() {
    const data = collectTeamPlanningData();
    exportToCSV(data.headers, data.rows, 'team_planning_<?= $selectedYear ?>');
}

function exportTeamPlanningToExcel() {
    const data = collectTeamPlanningData();
    exportToExcel(data.headers, data.rows, 'team_planning_<?= $selectedYear ?>');
}

// Collect team planning table data
function collectTeamPlanningData() {
    const teams = <?= json_encode(array_values($teams)) ?>;
    const headers = ['Project ID', 'Project Name', 'Manager', 'Task Code', 'Activity Name', 'Budget Hours', 'Planned Hours', 'Realised Hours'];

    // Add team headers for Plan and Hours
    teams.forEach(team => {
        headers.push(team.Name + ' - Plan');
        headers.push(team.Name + ' - Hours');
    });

    const rows = [];

    // Get fixed table for activity details
    const fixedTable = document.querySelector('.fixed-columns table');
    const scrollTable = document.querySelector('.scrollable-columns table');

    if (!fixedTable || !scrollTable) {
        alert('Tables not found');
        return { headers: [], rows: [] };
    }

    const fixedRows = Array.from(fixedTable.querySelectorAll('tr'));
    const scrollRows = Array.from(scrollTable.querySelectorAll('tr'));

    let currentProject = '';
    let currentProjectName = '';
    let currentManager = '';

    for (let i = 0; i < fixedRows.length; i++) {
        const fixedRow = fixedRows[i];
        const scrollRow = scrollRows[i];

        // Check if this is a project header
        const projectHeader = fixedRow.querySelector('a[href*="project_details.php"]');
        if (projectHeader) {
            const text = projectHeader.textContent.trim();
            const match = text.match(/^(\d+)\s+(.+?)(?:\s+\((.+?)\))?$/);
            if (match) {
                currentProject = match[1];
                currentProjectName = match[2];
                currentManager = match[3] || '';
            }
            continue;
        }

        // Check if this is an activity row
        const cells = fixedRow.querySelectorAll('td');
        if (cells.length >= 5 && cells[0].textContent.trim().match(/^\d+-\d+$/)) {
            const row = [];

            // Project info
            row.push(currentProject);
            row.push(currentProjectName);
            row.push(currentManager);

            // Activity info from fixed columns
            row.push(cells[0].textContent.trim()); // Task Code
            row.push(cells[1].textContent.trim()); // Activity Name
            row.push(cells[2].textContent.trim()); // Budget Hours (Available)
            row.push(cells[3].textContent.trim()); // Planned Hours
            row.push(cells[4].textContent.trim()); // Realised Hours

            // Team hours from scrollable columns
            if (scrollRow) {
                const scrollCells = scrollRow.querySelectorAll('td');
                scrollCells.forEach(cell => {
                    if (cell.classList.contains('input-cell')) {
                        const input = cell.querySelector('input');
                        row.push(input ? input.value : '');
                    } else if (cell.classList.contains('budget')) {
                        row.push(cell.textContent.trim());
                    }
                });
            }

            rows.push(row);
        }
    }

    return { headers, rows };
}
</script>

<?php require 'includes/footer.php'; ?>