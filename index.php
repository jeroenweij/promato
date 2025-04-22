<?php

require 'includes/header.php';
require 'includes/db.php';

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
  </script>
<?php

// Fetch personel
$stmt = $pdo->query("SELECT Shortname as Name, Id as Number FROM Personel WHERE plan = 1 ORDER BY Ord, Name;");
$personel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize person summary
$personSummary = [];
foreach ($personel as $p) {
    $id = $p['Number'];
    $personSummary[$id] = [
        'available' => round(($p['Fultime'] ?? 100) * 2080 / 100), // 2080 = full-time year
        'planned' => 0,
        'realised' => 0
    ];
}

// Fetch activities and projects
$sql = "SELECT Activities.Project, Activities.Key, Activities.Name, Activities.BudgetHours, 
               Projects.Name as ProjectName, Personel.Name as Manager 
        FROM Activities 
        LEFT JOIN Projects ON Activities.Project = Projects.Id 
        LEFT JOIN Personel ON Projects.Manager = Personel.Id 
        WHERE Projects.Status = 3 AND Activities.Show = 1 
        ORDER BY Projects.Id, Activities.Key;";

$stmt = $pdo->query($sql);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Collect hours across all activities
foreach ($activities as $activity) {
    $stmtHours = $pdo->prepare("SELECT Hours, Plan, Person FROM Hours WHERE Project = ? AND Activity = ?");
    $stmtHours->execute([$activity['Project'], $activity['Key']]);
    $hourData = $stmtHours->fetchAll(PDO::FETCH_ASSOC);

    foreach ($hourData as $h) {
        $pid = $h['Person'];
        if (!isset($personSummary[$pid])) continue;

        $personSummary[$pid]['planned'] += $h['Plan'] / 100;
        $personSummary[$pid]['realised'] += $h['Hours'] / 100;
    }
}

// Start output
echo '<section><div class="container"><div class="autoheight"><div class="horizontalscrol">';
echo '<table>' . PHP_EOL;

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

        echo '<td colspan="100%"><b>' . $value['Project'] . ' ' . htmlspecialchars($value['ProjectName']) . '</b> (' . htmlspecialchars($value['Manager']) . ')</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th class="text">TaskCode</th>';
        echo '<th class="text">Activity Name</th>';
        echo '<th class="text">Available</th>';
        echo '<th class="text">Planned</th>';
        echo '<th class="text">Realised</th>';

        foreach ($personel as $p) {
            echo '<th colspan="2" class="name">' . htmlspecialchars($p['Name']) . '</th>';
        }
        echo '</tr>' . PHP_EOL;

        $projectid = $value['Project'];
    }

    $taskCode = $value['Project'] . '-' . str_pad($value['Key'], 3, '0', STR_PAD_LEFT);
    echo '<tr>';
    echo '<td class="text">' . $taskCode . '</td>';
    echo '<td class="text">' . htmlspecialchars($value['Name']) . '</td>';
    echo '<td class="totals">' . $value['BudgetHours'] . '</td>';

    // Get Hours data
    $stmtHours = $pdo->prepare("SELECT Hours, Plan, Person FROM Hours WHERE Project = ? AND Activity = ?");
    $stmtHours->execute([$value['Project'], $value['Key']]);
    $hourData = $stmtHours->fetchAll(PDO::FETCH_ASSOC);

    // Planned hours (excluding Yoobi)
    $planned = 0;
    foreach ($hourData as $h) {
        if ($h['Person'] != 32750) {
            $planned += $h['Plan'] / 100;
        }
    }
    $plannedClass = $planned > $value['BudgetHours'] ? 'overbudget' : '';
    echo '<td class="totals ' . $plannedClass . '">' . $planned . '</td>';

    // Realised hours (Yoobi)
    $realised = 0;
    foreach ($hourData as $h) {
        if ($h['Person'] == 32750) {
            $realised = $h['Hours'] / 100;
            break;
        }
    }
    $realisedClass = $realised > $planned ? 'overbudget' : '';
    echo '<td class="totals ' . $realisedClass . '">' . $realised . '</td>';

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
        echo '<td class="editbudget"><input type="text" name="' . $value['Project'] . '#' . $value['Key'] . '#' . $p['Number'] . '" value="' . $plan . '" maxlength="4" size="3" class="hiddentext" onchange="UpdateValue(this)"></td>';
        $overbudget = ($hours>0 && $hours > $plan) ? 'overbudget' : '';
        echo '<td class="budget ' . $overbudget . '">' . $hours . '</td>';
    }
    echo '</tr>' . PHP_EOL;
}

echo '</table><br>&nbsp;<br>&nbsp;</div></div></div></section>';

?>
<script>
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

        // Update total planned for the specific person (summary header)
        let personPlanned = 0;
        document.querySelectorAll(`input[name$="#${person}"]`).forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) personPlanned += v;
        });

        const plannedCell = document.querySelector(`.planned-total[data-person="${person}"]`);
        if (plannedCell) {
            plannedCell.innerText = personPlanned
        }
        
        // Check against available
        const availableCell = document.querySelector(`.available-total[data-person="${person}"]`);
        if (availableCell && plannedCell) {
            const available = parseFloat(availableCell.innerText) || 0;
            if (personPlanned > available) {
                plannedCell.classList.add('overbudget');
            } else {
                plannedCell.classList.remove('overbudget');
            }
        }

        // Check realised vs planned
        const realisedCell = document.querySelector(`.realised-total[data-person="${person}"]`);
        if (realisedCell && plannedCell) {
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
