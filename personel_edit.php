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
    csrf_protect(); // Verify CSRF token

    // Input validation
    $errors = [];

    // Validate email
    $email = filter_var($_POST['Email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $errors[] = "Invalid email address";
    }

    // Validate name and shortname
    $name = trim($_POST['Name'] ?? '');
    $shortname = trim($_POST['Shortname'] ?? '');
    if (empty($shortname)) {
        $errors[] = "Shortname is required";
    }

    // Validate dates
    $startdate = $_POST['Startdate'] ?? '';
    $enddate = $_POST['Enddate'] ?? null;
    if (!empty($startdate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startdate)) {
        $errors[] = "Invalid start date format";
    }
    if (!empty($enddate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $enddate)) {
        $errors[] = "Invalid end date format";
    }
    if (!empty($startdate) && !empty($enddate) && $enddate < $startdate) {
        $errors[] = "End date cannot be before start date";
    }

    // Validate fulltime percentage
    $fultime = filter_var($_POST['Fultime'] ?? 100, FILTER_VALIDATE_INT);
    if ($fultime === false || $fultime < 0 || $fultime > 100) {
        $errors[] = "Fulltime percentage must be between 0 and 100";
    }

    // Validate type
    $type = filter_var($_POST['Type'] ?? 1, FILTER_VALIDATE_INT);
    if ($type === false || $type < 1 || $type > 7) {
        $errors[] = "Invalid user type";
    }

    // Validate team
    $team = filter_var($_POST['Deparment'] ?? 0, FILTER_VALIDATE_INT);
    if ($team === false) {
        $errors[] = "Invalid team selection";
    }

    // If there are validation errors, display them
    if (!empty($errors)) {
        echo '<section><div class="container"><div class="alert alert-danger">';
        echo '<h4>Validation Errors:</h4><ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul><a href="javascript:history.back()">Go Back</a></div></div></section>';
        require 'includes/footer.php';
        exit;
    }

    $data = [
        $email,
        $name,
        $startdate,
        $enddate ?: null,
        isset($_POST['WBSO']) ? 1 : 0,
        $fultime,
        $type,
        $team,
        isset($_POST['plan']) ? 1 : 0,
        $shortname
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
        $stmt = $pdo->prepare("INSERT INTO Availability (Person, Hours, `Year`) 
            VALUES (:person, :hours, :year)
            ON DUPLICATE KEY UPDATE Hours = :hours");
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
            <?php csrf_field(); ?>
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