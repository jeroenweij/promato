<?php
require 'includes/header.php';
require 'includes/db.php';

// Check if the project ID is provided in the URL
if (isset($_GET['project_id'])) {
    $projectId = $_GET['project_id'];

    // Fetch the project details along with the status and manager
    $projectStmt = $pdo->prepare("
        SELECT 
            Projects.*, 
            Status.Status AS Status, 
            Personel.Shortname AS Manager
        FROM Projects
        LEFT JOIN Status ON Projects.Status = Status.Id
        LEFT JOIN Personel ON Projects.Manager = Personel.Id
        WHERE Projects.Id = ?
    ");
    $projectStmt->execute([$projectId]);
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

    // If project is not found
    if (!$project) {
        echo 'Project not found.';
        require 'includes/footer.php';
        exit;
    }

    // Fetch the activities for the project
    $activityStmt = $pdo->prepare("SELECT * FROM Activities WHERE Project = ?");
    $activityStmt->execute([$projectId]);
    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

}
?>

<section id="project-details">
    <div class="container">

        <!-- Project Information -->
        <h1><?php echo htmlspecialchars($project['Name']); ?></h1>

        <div class="mb-3">
            <strong>Status:</strong>
            <?php
            echo htmlspecialchars($project['Status']);
            ?>
        </div>

        <div class="mb-3">
            <strong>Project Manager:</strong>
            <?php
            echo htmlspecialchars($project['Manager']);
            ?>
        </div>

        <hr>

        <!-- Gantt Chart -->
        <style>
            #project-details {
                font-family: Arial, sans-serif;
                margin: 20px;
            }

            #ganttChart {
                position: relative;
                width: 100%;
                height: 400px;
                border: 1px solid #ccc;
                margin-top: 20px;
            }

            .activity-bar {
                position: absolute;
                height: 30px;
                background-color: #4CAF50;
                color: white;
                text-align: center;
                line-height: 30px;
                border-radius: 5px;
            }

            .current-date-line {
                position: absolute;
                top: 0;
                bottom: 0;
                width: 2px;
                background-color: red;
                z-index: 10;
            }

            .date-labels {
                display: flex;
                justify-content: space-between;
                position: absolute;
                top: -20px;
                width: 100%;
                font-size: 12px;
            }
        </style>
        <style>
            #progressChart {
                margin-top: 30px;
                font-family: Arial, sans-serif;
            }

            .progress-row {
                display: flex;
                align-items: center;
                margin-bottom: 24px; /* more space between rows */
             }

            .progress-label {
                width: 200px;
                font-weight: bold;
                font-size: 14px;
            }

            .progress-bar-container {
                position: relative;
                flex-grow: 1;
                height: 25px;
                background: #eee;
                border-radius: 5px;
                overflow: visible;
                margin-left: 10px;
            }

            .progress-bar {
                height: 100%;
                background-color: #2196F3;
                transition: width 0.3s ease-in-out;
            }

            .progress-markers {
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                font-size: 10px;
                display: flex;
                justify-content: space-between;
                padding-top: 4px;
            }

            .progress-markers span {
                position: absolute;
                transform: translateX(-50%);
            }
        </style>
        <h3>Project Timeline</h3>
        <div id="ganttChart"></div>

        <h3>Hours Progress</h3>
        <div id="progressChart"></div>

        <?php
        // Map each row to only the properties the Gantt needs:
        $jsActivities = array_map(function($a) {
            return [
                'name'      => $a['Name'],
                'startDate' => $a['StartDate'],
                'endDate'   => $a['EndDate'],
            ];
        }, $activities);
        ?>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const activities = <?php echo json_encode($jsActivities, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
                const ganttChart = document.getElementById('ganttChart');
                const currentDate = new Date();

                // 1) Convert activity dates to Date objects
                activities.forEach(a => {
                    a.startDate = new Date(a.startDate);
                    a.endDate   = new Date(a.endDate);
                });

                // 2) Compute earliest & latest
                const earliestDate = new Date(Math.min(...activities.map(a => a.startDate)));
                const latestDate   = new Date(Math.max(...activities.map(a => a.endDate)));
                const totalDays    = Math.ceil((latestDate - earliestDate) / (1000*60*60*24));

                // 3) Create at most 10 date-labels
                const maxLabels = 10;
                const step = Math.max(1, Math.floor(totalDays / (maxLabels - 1)));
                const dateLabels = document.createElement('div');
                dateLabels.classList.add('date-labels');
                for (let i = 0; i <= totalDays; i += step) {
                    const d = new Date(earliestDate);
                    d.setDate(d.getDate() + i);
                    const lbl = document.createElement('div');
                    lbl.textContent = d.toISOString().slice(0,10);
                    dateLabels.appendChild(lbl);
                }
                ganttChart.appendChild(dateLabels);

                // 4) Assign each activity to the lowest free “track” (row) to avoid overlap
                activities.sort((a, b) => a.startDate - b.startDate);
                const tracks = []; // will hold the endDate of last activity in each track
                activities.forEach(a => {
                    let t = tracks.findIndex(endDate => a.startDate > endDate);
                    if (t === -1) {
                        t = tracks.length;
                        tracks.push(a.endDate);
                    } else {
                        tracks[t] = a.endDate;
                    }
                    a.track = t;
                });

                // 5) Render bars
                activities.forEach(a => {
                    const bar = document.createElement('div');
                    const leftPct   = (a.startDate - earliestDate) / (latestDate - earliestDate) * 100;
                    const widthPct  = (a.endDate   - a.startDate)   / (latestDate - earliestDate) * 100;

                    bar.classList.add('activity-bar');
                    bar.style.left = `${leftPct}%`;
                    bar.style.width = `${widthPct}%`;
                    bar.style.top   = `${a.track * 40 + 30}px`; // 40px per track + 30px padding for labels
                    bar.textContent = a.name;
                    ganttChart.appendChild(bar);
                });

                // 6) Draw current-date line
                const currentOffset = (currentDate - earliestDate) / (latestDate - earliestDate) * 100;
                const line = document.createElement('div');
                line.classList.add('current-date-line');
                line.style.left = `${currentOffset}%`;
                ganttChart.appendChild(line);

                // 7) Finally, set container height to fit all tracks + label area
                const barHeight   = 30;  // each activity row is 30 px tall
                const rowSpacing  = 10;  // 10 px gap between rows
                const labelOffset = 30;  // reserve 30 px at the top for the date labels
                const totalHeight = labelOffset + tracks.length * (barHeight + rowSpacing) + rowSpacing;
                ganttChart.style.height = `${totalHeight}px`;
            });
        </script>

        <?php
        // Fetch hours spent per activity
        $activityIds = array_column($activities, 'Id');
        $spentMap = [];
        if (count($activityIds) > 0) {
            $hoursStmt = $pdo->prepare("
                SELECT Activity, Hours as SpentHours 
                FROM Hours 
                WHERE Person=32750 AND Project = ?
            ");
            
            $hoursStmt->execute([$projectId]);

            $spentMap = [];
            foreach ($hoursStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $spentMap[$row['Activity']] = $row['SpentHours'];
            }
        }

        // Add spent hours to activities array
        $totalBudget = 0;
        $totalSpent = array_sum($spentMap);
        foreach ($activities as &$a) {
            $totalBudget += $a['BudgetHours'];
        }
        unset($a); // break reference

        // Map each row to only the properties the hours bars needs:
        $jsActivities = array_map(function($a) use ($spentMap) {
            return [
                'name'         => $a['Name'],
                'SpentHours'   => ($spentMap[$a['Key']] ?? 0) / 100,
                'BudgetHours'  => $a['BudgetHours'],
            ];
        }, $activities);
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const activities = <?php echo json_encode($jsActivities, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
                const totalBudget = <?php echo $totalBudget; ?>;
                const totalSpent  = <?php echo $totalSpent/100; ?>;

                const progressChart = document.getElementById('progressChart');

                // Compute max ratio to scale bars (if overspent)
                const maxRatio = Math.max(1, ...activities.map(a => a.SpentHours / a.BudgetHours || 0), totalSpent / totalBudget);

                const createBar = (label, spent, budget) => {
                    const ratio = budget > 0 ? spent / budget : 0;
                    const scaled = ratio / maxRatio * 100;
                    const row = document.createElement('div');
                    row.classList.add('progress-row');

                    row.innerHTML = `
            <div class="progress-label">${label}</div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width:${scaled}%"></div>
                <div class="progress-markers">
                    <span style="left: 0%;">0%</span>
                    <span style="left: 25%;">25%</span>
                    <span style="left: 50%;">50%</span>
                    <span style="left: 75%;">75%</span>
                    <span style="left: 100%;">100%</span>
                </div>
            </div>
        `;
                    progressChart.appendChild(row);
                };

                createBar("Total Project", totalSpent, totalBudget);
                activities.forEach(a => {
                    createBar(a.name, a.SpentHours, a.BudgetHours);
                });
            });
        </script>

        <hr>

        <!-- Activities List -->
        <h3>Activities</h3>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Task Code</th>
                <th>Activity Name</th>
                <th>WBSO Label</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Budget Hours</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?php echo $activity['Project'] . '-' . str_pad($activity['Key'], 3, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars($activity['Name']); ?></td>
                    <td><?php echo htmlspecialchars($activity['WBSO'] ?? ''); ?></td>
                    <td><?php echo $activity['StartDate']; ?></td>
                    <td><?php echo $activity['EndDate']; ?></td>
                    <td><?php echo $activity['BudgetHours']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</section>

<?php require 'includes/footer.php'; ?>