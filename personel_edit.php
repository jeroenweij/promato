<?php
require 'includes/header.php';
require_once 'includes/db.php';

$editing = isset($_GET['id']);
$person = [
    'Email' => '',
    'Name' => '',
    'Startdate' => date('Y-m-d'),
    'Enddate' => '',
    'WBSO' => 0,
    'Fultime' => 100,
    'Type' => 1,
    'Ord' => 250,
    'plan' => 1,
    'Shortname' => '',
    'Team' => 0
];

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM Personel WHERE Id = ?");
    $stmt->execute([$_GET['id']]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$person) {
        echo "<p>Person not found.</p>";
        require 'includes/footer.php';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        $_POST['Email'],
        $_POST['Name'],
        $_POST['Startdate'],
        $_POST['Enddate'] ?: null,
        isset($_POST['WBSO']) ? 1 : 0,
        $_POST['Fultime'],
        $_POST['Type'],
        $_POST['Deparment'],
        isset($_POST['plan']) ? 1 : 0,
        $_POST['Shortname']
    ];

    if ($editing) {
        $data[] = $_GET['id'];
        $sql = "UPDATE Personel SET Email=?, Name=?, StartDate=?, EndDate=?, WBSO=?, Fultime=?, Type=?, Team=?, plan=?, Shortname=? WHERE Id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $personId = $_GET['id'];
    } else {
        $sql = "INSERT INTO Personel (Email, Name, StartDate, EndDate, WBSO, Fultime, Type, Team, Plan, Shortname) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $personId = $pdo->lastInsertId();
    }

    function calculateAvailableHoursByYear($startDate, $endDate, $fulltimePercent) {
        $results = [];
        $startDate = $startDate ?: date('Y') . '-01-01';
        $endDate = $endDate ?: date('Y') . '-12-31';
        
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        if ($start > $end) return $results;
        
        $startYear = (int)$start->format('Y');
        $endYear = (int)$end->format('Y');
        
        $hoursPerDay = 8;
        
        // Calculate hours for each year in the range
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearStart = new DateTime(max($startDate, "$year-01-01"));
            $yearEnd = new DateTime(min($endDate, "$year-12-31"));
            
            $workdays = 0;
            $currentDate = clone $yearStart;
            
            while ($currentDate <= $yearEnd) {
                // Check if it's a weekday (1-5 = Monday-Friday)
                if (in_array($currentDate->format('N'), [1, 2, 3, 4, 5])) {
                    $workdays++;
                }
                $currentDate->modify('+1 day');
            }
            
            $yearHours = round($workdays * $hoursPerDay * ($fulltimePercent / 100));
            if ($yearHours > 0) {
                $results[$year] = $yearHours;
            }
        }
        
        return $results;
    }
    
    function calculateLeaveHoursByYear($startDate, $endDate, $fulltimePercent) {
        $results = [];
        $fullLeaveHours = 248; // Annual leave for fulltime employee
        
        $startDate = $startDate ?: date('Y') . '-01-01';
        $endDate = $endDate ?: date('Y') . '-12-31';
        
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        if ($start > $end) return $results;
        
        $startYear = (int)$start->format('Y');
        $endYear = (int)$end->format('Y');
        
        // Calculate leave for each year in the range
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearStart = new DateTime("$year-01-01");
            $yearEnd = new DateTime("$year-12-31");
            
            // Determine actual work period in this year
            $workStart = ($start > $yearStart) ? $start : $yearStart;
            $workEnd = ($end < $yearEnd) ? $end : $yearEnd;
            
            // Calculate the fraction of the year worked
            $daysInYear = 365;
            if ($yearStart->format('L') == 1) {
                $daysInYear = 366; // Leap year
            }
            
            $daysWorked = $workStart->diff($workEnd)->days + 1;
            $yearFraction = $daysWorked / $daysInYear;
            
            // Calculate pro-rated leave hours
            $leaveHours = round($fullLeaveHours * $yearFraction * ($fulltimePercent / 100));
            
            if ($leaveHours > 0) {
                $results[$year] = $leaveHours;
            }
        }
        
        return $results;
    }
    
    // Calculate and update available work hours
    $hoursByYear = calculateAvailableHoursByYear($_POST['Startdate'], $_POST['Enddate'], $_POST['Fultime']);
    
    // Insert a record for each year
    foreach ($hoursByYear as $year => $hours) {
        $stmt = $pdo->prepare("INSERT INTO Hours (Project, Activity, Person, Plan, `Year`) 
            VALUES (0, 0, :person, :hours, :year)
            ON DUPLICATE KEY UPDATE Plan = :hours");
        $stmt->execute([
            ':person' => $personId,
            ':hours' => $hours * 100, // stored as hundredths
            ':year' => $year,
        ]);
    }
    
    // Calculate and update leave hours
    $leaveByYear = calculateLeaveHoursByYear($_POST['Startdate'], $_POST['Enddate'], $_POST['Fultime']);
    
    // Insert/update leave hours for each year
    foreach ($leaveByYear as $year => $leaveHours) {
        // First, insert or update the Hours record
        $stmt = $pdo->prepare("INSERT INTO Hours (Project, Activity, Person, Plan, Hours, `Year`) 
            VALUES (10, 1, :person, :hours, 0, :year)
            ON DUPLICATE KEY UPDATE Plan = GREATEST(Hours, :hours)");
        $stmt->execute([
            ':person' => $personId,
            ':hours' => $leaveHours * 100, // stored as hundredths
            ':year' => $year,
        ]);
    }

    header("Location: personel.php");
    ?>
    <meta http-equiv="refresh" content="0;url=personel.php">
    <script>
        // JavaScript fallback
        window.location.href = "personel.php";
    </script>
    <section>
    <div class="container">
    <h3>Changes saved</h3>
    <a href="personel.php">Return</a>
    </div>
    </section>
    <?php 
    require 'includes/footer.php';
    exit;
}

// Get types
$types = $pdo->query("SELECT Id, Name FROM Types ORDER BY Id")->fetchAll(PDO::FETCH_ASSOC);
// Get teams
$teams = $pdo->query("SELECT Id, Name FROM Teams ORDER BY Ord")->fetchAll(PDO::FETCH_ASSOC);
?>

<section>
    <div class="container">
        <h2><?= $editing ? 'Edit' : 'Add' ?> Person</h2>
        <form method="post">
            <label>Email:<br><input type="email" name="Email" required value="<?= htmlspecialchars($person['Email']) ?>"></label><br><br>
            <label>Name:<br><input type="text" name="Name" value="<?= htmlspecialchars($person['Name']) ?>"></label><br><br>
            <label>Shortname:<br><input type="text" name="Shortname" required value="<?= htmlspecialchars($person['Shortname']) ?>"></label><br><br>
            <label>Start Date:<br><input type="date" name="Startdate" value="<?= htmlspecialchars($person['StartDate'] ?? '') ?>"></label><br><br>
            <label>End Date:<br><input type="date" name="Enddate" value="<?= htmlspecialchars($person['EndDate'] ?? '') ?>"></label><br><br>
            <label>Fulltime %:<br><input type="number" name="Fultime" value="<?= htmlspecialchars($person['Fultime']) ?>" min="0" max="100"></label><br><br>
            <label>Team:<br>
                <select name="Deparment">
                        <?php foreach ($teams as $team): ?>
                            <option value="<?= $team['Id'] ?>" <?= ($team['Id'] == $person['Team']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($team['Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label><br><br>
            <label>Type:<br>
                <select name="Type">
                    <?php foreach ($types as $type): ?>
                        <option value="<?= $type['Id'] ?>" <?= ($type['Id'] == $person['Type']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label><br><br>

            <label><input type="checkbox" name="plan" <?= $person['plan'] ? 'checked' : '' ?>> Plan-able (Show in Planning)</label><br>
            <label><input type="checkbox" name="WBSO" <?= $person['WBSO'] ? 'checked' : '' ?>> WBSO-eligible</label><br><br>

            <button type="submit">Save</button>
            <a href="personel.php" class="button">Cancel</a>
        </form>
    </div>
</section>

<?php require 'includes/footer.php'; ?>