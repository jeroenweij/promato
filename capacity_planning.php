<?php
$pageSpecificCSS = ['plantable.css'];
require 'includes/header.php';
require 'includes/db.php';

// ---- DATA PREPARATION ----
// Get team filter from URL parameter
$teamFilter = isset($_GET['team']) ? (int)$_GET['team'] : null;

// Fetch all teams for the filter buttons
$teamsQuery = $pdo->query("
    SELECT Id, Name, Ord 
    FROM Teams 
    WHERE Id IN (
        SELECT DISTINCT Team 
        FROM Personel 
        WHERE plan = 1 
        AND YEAR(StartDate) <= $selectedYear 
        AND (EndDate IS NULL OR YEAR(EndDate) >= $selectedYear)
    )
    ORDER BY Ord, Name
");
$teams = $teamsQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch personnel with available hours - doing a more efficient query with proper joins
$whereClause = "WHERE p.plan = 1
    AND YEAR(p.StartDate) <= :selectedYear 
    AND (p.EndDate IS NULL OR YEAR(p.EndDate) >= :selectedYear)";
$params = [':selectedYear' => $selectedYear];

// Add team filter if specified
if ($teamFilter) {
    $whereClause .= " AND p.Team = :teamFilter";
    $params[':teamFilter'] = $teamFilter;
}

$stmt = $pdo->prepare("
    SELECT 
        p.Shortname AS Name, 
        p.Id AS Number, 
        p.Fultime, 
        p.Team,
        COALESCE(h.Plan, 0) AS AvailableHours,
        d.Ord AS DeptOrder,
        d.Name AS TeamName,
        p.Ord AS PersonOrder
    FROM Personel p 
    LEFT JOIN Hours h ON h.Person = p.Id AND h.Project = 0 AND h.Activity = 0 AND `Year`= :selectedYear
    LEFT JOIN Teams d ON p.Team = d.Id
    $whereClause
    ORDER BY d.Ord, p.Ord, p.Name
");

// Execute the prepared statement
$stmt->execute($params);

$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
$personnelById = []; // For quicker lookup

// Initialize person summary - calculate available hours once
foreach ($personnel as $p) {
    $id = $p['Number'];
    $available = $p['AvailableHours'] > 0
        ? round($p['AvailableHours'] / 100)  // Stored as hundredths in DB
        : round(($p['Fultime'] ?? 100) * 2080 / 100); // Fallback to estimate

    $personnelById[$id] = $p;
    $personnelById[$id]['available'] = $available;
    $personnelById[$id]['planned'] = 0;
    $personnelById[$id]['realised'] = 0;
}

// Single query to fetch all hours data grouped by person
$stmtPersonHours = $pdo->query(
    "SELECT 
        Person,
        SUM(CASE WHEN Project > 0 THEN Plan ELSE 0 END) AS TotalPlanned,
        SUM(CASE WHEN Project > 0 THEN Hours ELSE 0 END) - 
        SUM(CASE WHEN Project = 10 AND Activity = 7 THEN Hours ELSE 0 END) AS TotalRealised
    FROM Hours WHERE `Year` = $selectedYear
    GROUP BY Person"
);

// Update person summaries with planned and realized hours
while ($row = $stmtPersonHours->fetch(PDO::FETCH_ASSOC)) {
    $pid = $row['Person'];
    if (!isset($personnelById[$pid])) continue;

    $personnelById[$pid]['planned'] = $row['TotalPlanned'] / 100;
    $personnelById[$pid]['realised'] = $row['TotalRealised'] / 100;
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

// Fetch TeamHours data if a team filter is active
$teamHoursData = [];
if ($teamFilter) {
    $teamHoursQuery = $pdo->prepare("
        SELECT 
            Project,
            Activity,
            Plan
        FROM TeamHours
        WHERE Team = :teamFilter
        AND `Year` = :selectedYear
    ");
    $teamHoursQuery->execute([
        ':teamFilter' => $teamFilter,
        ':selectedYear' => $selectedYear
    ]);
    
    while ($row = $teamHoursQuery->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['Project'] . '-' . $row['Activity'];
        $teamHoursData[$key] = $row['Plan'] / 100;
    }
}

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
$allHoursQuery = $pdo->prepare("
    SELECT 
        CONCAT(Project, '-', Activity) AS ProjectActivity,
        Person, 
        Hours, 
        Plan
    FROM Hours 
    WHERE `Year` = $selectedYear AND CONCAT(Project, '-', Activity) IN ($placeholders)
");

// We need to execute with flattened array values
$params = [];
foreach ($activities as $a) {
    $params[] = $a['Project'] . '-' . $a['Key'];
}
$allHoursQuery->execute(array_unique($params));

// Organize hours data for quick access
$hoursData = [];
while ($row = $allHoursQuery->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['ProjectActivity'];
    if (!isset($hoursData[$key])) {
        $hoursData[$key] = [];
    }
    $hoursData[$key][$row['Person']] = [
        'Hours' => $row['Hours'],
        'Plan' => $row['Plan']
    ];
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

// Get team name for page title if filtering
$teamName = '';
if ($teamFilter && !empty($personnel)) {
    $teamName = $personnel[0]['TeamName'] ?? '';
}

// Start output with CSS optimization
?>
<section class="white">
    <div class="container" style="max-width: 20000px;">
        <?php if (!$teamFilter && count($teams) > 0): ?>
            <div style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #d0d0d0; border-radius: 5px;">
                <h3 style="margin: 0 0 10px 0;">Filter by Team:</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php foreach ($teams as $team): ?>
                        <a href="?team=<?= $team['Id'] ?>" 
                           style="padding: 8px 16px; background-color: #0066cc; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">
                            <?= htmlspecialchars($team['Name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($teamFilter): ?>
            <div style="margin-bottom: 20px; padding: 10px; background-color: #f0f8ff; border: 1px solid #d0d0d0; border-radius: 5px;">
                <h3 style="margin: 0; display: inline-block;">
                    Showing: <?= htmlspecialchars($teamName) ?> Team
                </h3>
                <a href="capacity_planning.php" style="float: right; color: #0066cc; text-decoration: none;">
                    [Show All Teams]
                </a>
                <div style="clear: both;"></div>
            </div>
        <?php endif; ?>
        
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
                                
                                // Determine available hours based on team filter
                                $availableHours = '';
                                if ($teamFilter) {
                                    // Use TeamHours.Plan if team filter is active
                                    // TeamHours uses Project + Activity (Key) as composite key
                                    if (isset($teamHoursData[$activityKey])) {
                                        $availableHours = $teamHoursData[$activityKey];
                                    }
                                } else {
                                    // Use budget hours when no team filter
                                    $availableHours = $activity['BudgetHours'];
                                }
                                
                                // Calculate totals for this activity (only for filtered personnel if applicable)
                                $planned = 0;
                                $realised = 0;
                                
                                if (isset($hoursData[$activityKey])) {
                                    foreach ($hoursData[$activityKey] as $personId => $data) {
                                        // If team filter is active, only count hours from personnel in that team
                                        if ($teamFilter && isset($personnelById[$personId])) {
                                            // Only include if person is in our filtered personnel list
                                            if ($personId != 0) {
                                                $planned += $data['Plan'] / 100;
                                            } else {
                                                $realised = $data['Hours'] / 100;
                                            }
                                        } elseif (!$teamFilter) {
                                            // No filter - include all hours
                                            if ($personId != 0) {
                                                $planned += $data['Plan'] / 100;
                                            } else {
                                                $realised = $data['Hours'] / 100;
                                            }
                                        }
                                    }
                                }
                                
                                $plannedClass = ($availableHours !== '' && $planned > $availableHours) ? 'overbudget' : '';
                                $realisedClass = ($planned > 0 && $realised > $planned) ? 'overbudget' : '';
                                ?>
                                <tr>
                                    <td class="text fixedheigth"><?= $taskCode ?></td>
                                    <td class="text fixedheigth"><?= htmlspecialchars($activity['ActivityName']) ?></td>
                                    <td class="totals fixedheigth"><?= $availableHours ?></td>
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
                            <?php foreach ($personnel as $p): ?>
                                <th colspan="2" class="name fixedheigth"><?= htmlspecialchars($p['Name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($personnel as $p): ?>
                                <td colspan="2" class="totals available-total fixedheigth" data-person="<?= $p['Number'] ?>">
                                    <?= $personnelById[$p['Number']]['available'] ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($personnel as $p): 
                                $pid = $p['Number'];
                                $planned = $personnelById[$pid]['planned'];
                                $realised = $personnelById[$pid]['realised'];
                                $available = $personnelById[$pid]['available'];
                                
                                $plannedClass = $planned > $available ? 'overbudget' : '';
                                $realisedClass = $realised > $planned ? 'overbudget' : '';
                            ?>
                                <td class="totals fixedheigth planned-total <?= $plannedClass ?>" data-person="<?= $pid ?>">
                                    <?= round($planned, 2) ?>
                                </td>
                                <td class="totals fixedheigth realised-total <?= $realisedClass ?>" data-person="<?= $pid ?>">
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
                                <?php foreach ($personnel as $p): ?>
                                    <th colspan="2" class="name fixedheigth"><?= htmlspecialchars($p['Name']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                            
                            <?php foreach ($project['activities'] as $activity): ?>
                                <?php $activityKey = $activity['Project'] . '-' . $activity['Key']; ?>
                                <tr>
                                    <?php foreach ($personnel as $p): 
                                        $personId = $p['Number'];
                                        $plan = '';
                                        $hours = '&nbsp;';
                                        
                                        if (isset($hoursData[$activityKey][$personId])) {
                                            $entry = $hoursData[$activityKey][$personId];
                                            $hoursVal = $entry['Hours'] / 100;
                                            $planVal = $entry['Plan'] / 100;
                                            if ($hoursVal > 0) $hours = $hoursVal;
                                            if ($planVal > 0) $plan = $planVal;
                                        }
                                        
                                        $overbudget = ($hours != '&nbsp;' && $hours > $plan) ? 'overbudget' : '';
                                        $isEditable = $userAuthLevel >= 4 || ($_SESSION['user_id'] ?? 0) == $activity['ManagerId'];
                                    ?>
                                        <?php if ($isEditable): ?>
                                            <td class="editbudget fixedheigth">
                                                <input type="text" 
                                                      name="<?= $activity['Project'] ?>#<?= $activity['Key'] ?>#<?= $personId ?>" 
                                                      value="<?= $plan ?>" 
                                                      maxlength="5" 
                                                      size="4" 
                                                      class="hiddentext editbudget" 
                                                      onchange="UpdateValue(this)">
                                            </td>
                                        <?php else: ?>
                                            <td class="editbudget fixedheigth"><?= $plan ?></td>
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

    // Handle updating values
    window.UpdateValue = function(input) {
        const [project, activity, person] = input.name.split('#');
        const value = parseFloat(input.value) || 0;

        // Update UI immediately for better UX
        updateUIForChangedValue(input);

        // Send data to server
        fetch('update_hours_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `project=${project}&activity=${activity}&person=${person}&year=<?= $selectedYear ?>&plan=${value}`
        }).then(res => {
            if (!res.ok) {
                alert(`Failed to save: project=${project}, activity=${activity}, person=${person}, plan=${value}`);
                return;
            }
        }).catch(err => {
            console.error('Error saving data:', err);
            alert('Failed to save data. Please try again.');
        });
    }

    // Update UI without waiting for server response
    function updateUIForChangedValue(input) {
        const [project, activity, person] = input.name.split('#');
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
                    
                    // Check if planned is over budget (only if budget exists)
                    const budgetText = budgetCell.textContent.trim();
                    if (budgetText !== '') {
                        const budget = parseFloat(budgetText) || 0;
                        plannedCell.classList.toggle('overbudget', totalPlanned > budget);
                    } else {
                        plannedCell.classList.remove('overbudget');
                    }
                    
                    // Check if logged is over planned
                    if (loggedCell) {
                        const logged = parseFloat(loggedCell.textContent) || 0;
                        loggedCell.classList.toggle('overbudget', logged > totalPlanned);
                    }
                }
                break;
            }
        }

        // 4. Update person total planned hours
        let personPlanned = 0;
        document.querySelectorAll(`input[name$="#${person}"]`).forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) personPlanned += v;
        });

        const personPlannedCell = document.querySelector(`.planned-total[data-person="${person}"]`);
        const availableCell = document.querySelector(`.available-total[data-person="${person}"]`);
        const realisedCell = document.querySelector(`.realised-total[data-person="${person}"]`);
        
        if (personPlannedCell) {
            personPlannedCell.innerText = personPlanned;
            
            // Check against available hours
            if (availableCell) {
                const available = parseFloat(availableCell.innerText) || 0;
                personPlannedCell.classList.toggle('overbudget', personPlanned > available);
            }
            
            // Check realised vs planned
            if (realisedCell) {
                const realised = parseFloat(realisedCell.innerText) || 0;
                realisedCell.classList.toggle('overbudget', realised > personPlanned);
            }
        }
    }
})();
</script>

<?php require 'includes/footer.php'; ?>