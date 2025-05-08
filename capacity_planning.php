<?php
require 'includes/header.php';
require 'includes/db.php';

// Fetch personel with available hours
$stmt = $pdo->query("SELECT p.Shortname AS Name, p.Id AS Number, p.Fultime, COALESCE(h.Plan, 0) AS AvailableHours 
    FROM Personel p 
    LEFT JOIN Hours h ON h.Person = p.Id AND h.Project = 0 AND h.Activity = 0
    LEFT JOIN Departments d ON p.Department = d.Id
    WHERE p.plan = 1
    ORDER BY d.Ord, p.Ord, p.Name;
");

$personel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize person summary
$personSummary = [];
foreach ($personel as $p) {
    $id = $p['Number'];
    $available = $p['AvailableHours'] > 0
        ? round($p['AvailableHours'] / 100)  // Stored as hundredths in DB
        : round(($p['Fultime'] ?? 100) * 2080 / 100); // Fallback to estimate

    $personSummary[$id] = [
        'available' => $available,
        'planned' => 0,
        'realised' => 0
    ];
}

// Collect hours across all activities
$stmtHours = $pdo->prepare(
    "SELECT 
        SUM(CASE WHEN Project > 0 THEN Plan ELSE 0 END) AS Plan,
        SUM(CASE WHEN Project = 0 THEN Hours ELSE 0 END) AS Hours,
        Person FROM Hours GROUP BY Person"
);
$stmtHours->execute();
$hourData = $stmtHours->fetchAll(PDO::FETCH_ASSOC);

foreach ($hourData as $h) {
    $pid = $h['Person'];
    if (!isset($personSummary[$pid])) continue;

    $personSummary[$pid]['planned'] = $h['Plan'] / 100;
    $personSummary[$pid]['realised'] = $h['Hours'] / 100;
}

// Fetch activities and projects
$sql = "SELECT Activities.Project, Activities.Key, Activities.Name, Budgets.Hours AS BudgetHours, 
               Projects.Name as ProjectName, Personel.Name as Manager, Projects.Manager AS ManagerId
        FROM Activities 
        LEFT JOIN Projects ON Activities.Project = Projects.Id 
        LEFT JOIN Personel ON Projects.Manager = Personel.Id 
        LEFT JOIN Budgets ON Activities.Id = Budgets.Activity
        WHERE Projects.Status = 3 AND Activities.Visible = 1 
        ORDER BY Projects.Id, Activities.Key;";

$stmt = $pdo->query($sql);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Start output
echo '<section><div class="container"><div class="autoheight"><div class="horizontalscrol verticalscrol">';
echo '<table class="plantable">' . PHP_EOL;

echo '<th colspan="5">&nbsp;</th>';
foreach ($personel as $p) {
    echo '<th colspan="2" class="name">' . htmlspecialchars($p['Name']) . '</th>';
}
echo '</tr>';

// Row: Available
echo '<tr><td colspan="5"><b>Available (hrs)</b></td>';
foreach ($personel as $p) {
    $available = $personSummary[$p['Number']]['available'];
    echo "<td colspan='2' class='totals available-total' data-person='{$p['Number']}'>$available</td>";
}
echo '</tr>';

// Row: Planned / realised
echo '<tr><td colspan="5"><b>Planned / realised</b></td>';
foreach ($personel as $p) {
    $available = $personSummary[$p['Number']]['available'];
    $planned = $personSummary[$p['Number']]['planned'];
    $realised = $personSummary[$p['Number']]['realised'];

    $plannedClass = $planned > $available ? 'overbudget' : '';
    $realisedClass = $realised > $planned ? 'overbudget' : '';

    // Planned
    echo "<td class='totals planned-total $plannedClass' data-person='{$p['Number']}'>" . round($planned, 2) . "</td>";
    
    // Realised
    echo "<td class='totals realised-total $realisedClass' data-person='{$p['Number']}'>" . round($realised, 2) . "</td>";
}
echo '</tr>';

$projectid = -1;

foreach ($activities as $value) {
    if ($projectid != $value['Project']) {
        echo '<tr class="spacer"><td colspan="100%">&nbsp;</td></tr><tr>';

        echo '<td colspan="100%"><a href="project_details.php?project_id=' . htmlspecialchars($value['Project']) . '"><h4><b>' . $value['Project'] . ' ' . htmlspecialchars($value['ProjectName']) . '</b> (' . htmlspecialchars($value['Manager'] ?? '') . ')</h4></a></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th class="text sticky">TaskCode</th>';
        echo '<th class="text sticky">Activity Name</th>';
        echo '<th class="text sticky">Available</th>';
        echo '<th class="text sticky">Planned</th>';
        echo '<th class="text sticky">Realised</th>';

        foreach ($personel as $p) {
            echo '<th colspan="2" class="name">' . htmlspecialchars($p['Name']) . '</th>';
        }
        echo '</tr>' . PHP_EOL;

        $projectid = $value['Project'];
    }

    $taskCode = $value['Project'] . '-' . str_pad($value['Key'], 3, '0', STR_PAD_LEFT);
    echo '<tr>';
    echo '<td class="text sticky">' . $taskCode . '</td>';
    echo '<td class="text sticky">' . htmlspecialchars($value['Name']) . '</td>';
    echo '<td class="totals sticky">' . $value['BudgetHours'] . '</td>';

    // Get Hours data
    $stmtHours = $pdo->prepare("SELECT Hours, Plan, Person FROM Hours WHERE Project = ? AND Activity = ?");
    $stmtHours->execute([$value['Project'], $value['Key']]);
    $hourData = $stmtHours->fetchAll(PDO::FETCH_ASSOC);

    // Planned hours
    $planned = 0;
    foreach ($hourData as $h) {
        if ($h['Person'] != 0) {
            $planned += $h['Plan'] / 100;
        }
    }
    $plannedClass = $planned > $value['BudgetHours'] ? 'overbudget' : '';
    echo '<td class="totals ' . $plannedClass . ' sticky">' . $planned . '</td>';

    // Realised hours (Yoobi)
    $realised = 0;
    foreach ($hourData as $h) {
        if ($h['Person'] == 0) {
            $realised = $h['Hours'] / 100;
            break;
        }
    }
    $realisedClass = $realised > $planned ? 'overbudget' : '';
    echo '<td class="totals ' . $realisedClass . ' sticky">' . $realised . '</td>';

    foreach ($personel as $p) {
        $found = array_filter($hourData, fn($x) => $x['Person'] == $p['Number']);
        $hours = '&nbsp;';
        $plan = '';
        if (!empty($found)) {
            $entry = array_values($found)[0];
            $hoursVal = $entry['Hours'] / 100;
            $planVal = $entry['Plan'] / 100;
            if ($hoursVal > 0) $hours = $hoursVal;
            if ($planVal > 0) $plan = $planVal;
        }
        if ($userAuthLevel >= 4 || $_SESSION['user_id'] == $value['ManagerId']) {
            echo '<td class="editbudget"><input type="text" name="' . $value['Project'] . '#' . $value['Key'] . '#' . $p['Number'] . '" value="' . $plan . '" maxlength="4" size="3" class="hiddentext" onchange="UpdateValue(this)"></td>';
        } else {
            echo '<td class="editbudget">' . $plan . '</td>';
        }
    $overbudget = ($hours>0 && $hours > $plan) ? 'overbudget' : '';
        echo '<td class="budget ' . $overbudget . '">' . $hours . '</td>';
    }
    echo '</tr>' . PHP_EOL;
}

echo '</table><br>&nbsp;<br>&nbsp;</div></div></div></section>';

?>
<script>
    var setElementHeight = function () {
      var height = $(window).height() - 120;
      $('.autoheight').css('min-height', (height));
    };

    $(window).on("resize", function () {
      setElementHeight();
    }).resize();

    $(window).on("load", function () {
      setElementHeight();
    }).resize();
    
function UpdateValue(input) {
    const [project, activity, person] = input.name.split('#');
    const value = parseFloat(input.value) || 0;

    fetch('update_hours_plan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `project=${project}&activity=${activity}&person=${person}&plan=${value}`
    }).then(res => {
        if (!res.ok) {
            alert('Failed to save. project=${project}&activity=${activity}&person=${person}&plan=${value}');
            return;
        }

        const row = input.closest('tr');
        const inputs = row.querySelectorAll('input');

        inputs.forEach(inp => {
            const [p, a, personId] = inp.name.split('#');
            const planVal = parseFloat(inp.value) || 0;
            const tdInput = inp.closest('td');
            const tdBudget = tdInput.nextElementSibling;

            if (tdBudget && tdBudget.classList.contains('budget')) {
                const loggedVal = parseFloat(tdBudget.innerText) || 0;
                if (loggedVal > 0 && loggedVal > planVal) {
                    tdBudget.classList.add('overbudget');
                } else {
                    tdBudget.classList.remove('overbudget');
                }
            }
        });

        // Update total planned for this activity (row)
        let totalPlanned = 0;
        inputs.forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) totalPlanned += v;
        });
        row.querySelectorAll('td')[3].innerText = totalPlanned;

        const tds = row.querySelectorAll('td');
        const budgetCell = tds[2];
        const plannedCell = tds[3];
        const loggedCell = tds[4];

        // Update Planned Hours column
        plannedCell.innerText = totalPlanned;

        // Check Planned Hours > Budget Hours
        const budget = parseFloat(budgetCell.innerText) || 0;
        if (totalPlanned > budget) {
            plannedCell.classList.add('overbudget');
        } else {
            plannedCell.classList.remove('overbudget');
        }

        // Check Logged Hours > Planned Hours
        const logged = parseFloat(loggedCell.innerText) || 0;
        if (logged > totalPlanned) {
            loggedCell.classList.add('overbudget');
        } else {
            loggedCell.classList.remove('overbudget');
        }

        // Update total planned for the specific person (summary header)
        let personPlanned = 0;
        document.querySelectorAll(`input[name$="#${person}"]`).forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) personPlanned += v;
        });

        const personPlannedCell = document.querySelector(`.planned-total[data-person="${person}"]`);
        if (personPlannedCell) {
            personPlannedCell.innerText = personPlanned
        }
        
        // Check against available
        const availableCell = document.querySelector(`.available-total[data-person="${person}"]`);
        if (availableCell && personPlannedCell) {
            const available = parseFloat(availableCell.innerText) || 0;
            if (personPlanned > available) {
                personPlannedCell.classList.add('overbudget');
            } else {
                personPlannedCell.classList.remove('overbudget');
            }
        }

        // Check realised vs planned
        const realisedCell = document.querySelector(`.realised-total[data-person="${person}"]`);
        if (realisedCell && personPlannedCell) {
            const realised = parseFloat(realisedCell.innerText) || 0;
            if (realised > personPlanned) {
                realisedCell.classList.add('overbudget');
            } else {
                realisedCell.classList.remove('overbudget');
            }
        }
    });
}
</script>

<?php
require 'includes/footer.php';
?>
