<?php
$pageSpecificCSS = ['form-styles.css'];
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
        echo "<section><div class='container'><div class='alert alert-danger'>Person not found.</div></div></section>";
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
        echo '</ul><a href="javascript:history.back()" class="btn btn-secondary">Go Back</a></div></div></section>';
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
        window.location.href = "personel.php";
    </script>
    <section>
    <div class="container">
        <div class="alert alert-success">
            <h3>Changes saved successfully!</h3>
            <a href="personel.php" class="btn btn-primary">Return to Personnel</a>
        </div>
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

<section class="white">
    <div class="container" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?= $editing ? 'Edit Person' : 'Add New Person' ?></h1>
            <a href="personel.php" class="btn btn-secondary">
                <i class="lucide-arrow-left"></i> Back to Personnel
            </a>
        </div>

        <?php if ($editing): ?>
        <?php
        // Check if user can change team (auth level 4+ or project manager)
        $canChangeTeam = $userAuthLevel >= 4;
        if (!$canChangeTeam) {
            // Check if user is a manager of any projects where this person is assigned
            $managerCheckStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM Projects p
                WHERE p.Manager = :userId
                AND EXISTS (
                    SELECT 1 FROM Hours h
                    WHERE h.Person = :personId
                    AND h.Project = p.Id
                )
            ");
            $managerCheckStmt->execute([
                ':userId' => $userId,
                ':personId' => $person['Id']
            ]);
            $canChangeTeam = $managerCheckStmt->fetchColumn() > 0;
        }
        ?>
        <?php if ($canChangeTeam): ?>
        <div class="card mb-4 border-warning">
            <div class="card-body bg-light">
                <h5 class="card-title text-warning">
                    <i class="lucide-alert-triangle"></i> Change Team
                </h5>
                <p class="card-text">Moving a person to a different team requires careful consideration. This will affect:</p>
                <ul class="mb-3">
                    <li>Team capacity planning</li>
                    <li>Project assignments</li>
                    <li>Resource allocation reports</li>
                </ul>
                <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#changeTeamModal">
                    <i class="lucide-users"></i> Change Team Assignment
                </button>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="lucide-user"></i> Personnel Information
                </h5>
            </div>
            <div class="card-body">
                <form method="post" id="personelForm">
                    <?php csrf_field(); ?>

                    <!-- Basic Information Section -->
                    <div class="mb-4">
                        <h6 class="text-muted border-bottom pb-2 mb-3">
                            <i class="lucide-id-card"></i> Basic Information
                        </h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="Email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="lucide-mail"></i></span>
                                    <input type="email" class="form-control" id="Email" name="Email" required
                                           value="<?= htmlspecialchars($person['Email']) ?>"
                                           placeholder="user@example.com">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="Shortname" class="form-label">Short Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="lucide-user"></i></span>
                                    <input type="text" class="form-control" id="Shortname" name="Shortname" required
                                           value="<?= htmlspecialchars($person['Shortname']) ?>"
                                           placeholder="JDoe">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="Name" class="form-label">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="lucide-user-circle"></i></span>
                                <input type="text" class="form-control" id="Name" name="Name"
                                       value="<?= htmlspecialchars($person['Name']) ?>"
                                       placeholder="John Doe">
                            </div>
                        </div>
                    </div>

                    <!-- Employment Details Section -->
                    <div class="mb-4">
                        <h6 class="text-muted border-bottom pb-2 mb-3">
                            <i class="lucide-calendar"></i> Employment Details
                        </h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="Startdate" class="form-label">Start Date</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="lucide-calendar-plus"></i></span>
                                    <input type="date" class="form-control" id="Startdate" name="Startdate"
                                           value="<?= htmlspecialchars($person['StartDate'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="Enddate" class="form-label">End Date</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="lucide-calendar-minus"></i></span>
                                    <input type="date" class="form-control" id="Enddate" name="Enddate"
                                           value="<?= htmlspecialchars($person['EndDate'] ?? '') ?>">
                                </div>
                                <small class="form-text text-muted">Leave empty if currently employed</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="Fultime" class="form-label">Full-time Percentage</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="lucide-clock"></i></span>
                                    <input type="number" class="form-control" id="Fultime" name="Fultime"
                                           value="<?= htmlspecialchars($person['Fultime']) ?>"
                                           min="0" max="100" step="1">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="form-text text-muted">100% = full-time, 50% = half-time</small>
                            </div>
                        </div>
                    </div>

                    <!-- Organization Section -->
                    <div class="mb-4">
                        <h6 class="text-muted border-bottom pb-2 mb-3">
                            <i class="lucide-building"></i> Organization
                        </h6>

                        <div class="row">
                            <?php if (!$editing): ?>
                            <div class="col-md-6 mb-3">
                                <label for="Deparment" class="form-label">Team</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="lucide-users"></i></span>
                                    <select class="form-select" id="Deparment" name="Deparment">
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?= $team['Id'] ?>" <?= ($team['Id'] == $person['Team']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($team['Name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Team</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="lucide-users"></i></span>
                                    <input type="text" class="form-control" readonly
                                           value="<?php
                                               $currentTeam = array_filter($teams, function($t) use ($person) {
                                                   return $t['Id'] == $person['Team'];
                                               });
                                               echo htmlspecialchars(reset($currentTeam)['Name'] ?? 'Unknown');
                                           ?>">
                                    <input type="hidden" name="Deparment" value="<?= $person['Team'] ?>">
                                </div>
                                <small class="form-text text-muted">Use "Change Team" button above to modify</small>
                            </div>
                            <?php endif; ?>

                            <div class="col-md-6 mb-3">
                                <label for="Type" class="form-label">User Type / Permission Level</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="lucide-shield"></i></span>
                                    <select class="form-select" id="Type" name="Type">
                                        <?php foreach ($types as $type): ?>
                                            <option value="<?= $type['Id'] ?>" <?= ($type['Id'] == $person['Type']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['Name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Options Section -->
                    <div class="mb-4">
                        <h6 class="text-muted border-bottom pb-2 mb-3">
                            <i class="lucide-settings"></i> Options
                        </h6>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="plan" name="plan"
                                   <?= $person['plan'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="plan">
                                <strong>Plannable</strong> - Show this person in planning views
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="WBSO" name="WBSO"
                                   <?= $person['WBSO'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="WBSO">
                                <strong>WBSO Eligible</strong> - Eligible for Dutch R&D tax credit
                            </label>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between pt-3 border-top">
                        <a href="personel.php" class="btn btn-outline-secondary">
                            <i class="lucide-x"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="lucide-save"></i> Save Personnel Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if ($editing): ?>
<!-- Change Team Modal -->
<div class="modal fade" id="changeTeamModal" tabindex="-1" aria-labelledby="changeTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="changeTeamModalLabel">
                    <i class="lucide-alert-triangle"></i> Change Team Assignment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="personel_change_team.php">
                <?php csrf_field(); ?>
                <input type="hidden" name="person_id" value="<?= $person['Id'] ?>">

                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> Changing teams will affect capacity planning and resource allocation.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Current Team</label>
                        <input type="text" class="form-control" readonly
                               value="<?php
                                   $currentTeam = array_filter($teams, function($t) use ($person) {
                                       return $t['Id'] == $person['Team'];
                                   });
                                   echo htmlspecialchars(reset($currentTeam)['Name'] ?? 'Unknown');
                               ?>">
                    </div>

                    <div class="mb-3">
                        <label for="new_team" class="form-label">New Team <span class="text-danger">*</span></label>
                        <select class="form-select" id="new_team" name="new_team" required>
                            <option value="">-- Select New Team --</option>
                            <?php foreach ($teams as $team): ?>
                                <?php if ($team['Id'] != $person['Team']): ?>
                                    <option value="<?= $team['Id'] ?>">
                                        <?= htmlspecialchars($team['Name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <h6 class="text-primary"><i class="lucide-calendar-clock"></i> Planned Hours Transfer</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="move_hours" name="move_hours" checked>
                            <label class="form-check-label" for="move_hours">
                                <strong>Move planned hours to new team</strong>
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            Transfer this person's planned hours from the old team to the new team.
                            Only hours that don't make the old team negative will be moved.
                        </small>
                    </div>

                    <div class="alert alert-info">
                        <strong><i class="lucide-info"></i> Note:</strong> Hours will be moved from TeamHours entries for the old team to the new team.
                        Individual Hours entries (per-person planning) are not affected.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="lucide-users"></i> Change Team
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.input-group-text {
    background-color: #f8f9fa;
    border-right: none;
}

.input-group .form-control,
.input-group .form-select {
    border-left: none;
}

.input-group-text i {
    width: 16px;
    height: 16px;
}

.card {
    border-radius: 8px;
}

.card-header {
    border-radius: 8px 8px 0 0 !important;
}

.form-check-input:checked {
    background-color: #0066cc;
    border-color: #0066cc;
}

.border-warning {
    border-color: #ffc107 !important;
    border-width: 2px !important;
}

h6.text-muted i {
    width: 16px;
    height: 16px;
    vertical-align: text-bottom;
}
</style>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
// Form validation
document.getElementById('personelForm')?.addEventListener('submit', function(e) {
    const email = document.getElementById('Email').value;
    const shortname = document.getElementById('Shortname').value;
    const fultime = parseInt(document.getElementById('Fultime').value);

    if (!email || !shortname) {
        e.preventDefault();
        alert('Email and Shortname are required fields.');
        return false;
    }

    if (fultime < 0 || fultime > 100) {
        e.preventDefault();
        alert('Full-time percentage must be between 0 and 100.');
        return false;
    }
});

// Initialize Lucide icons
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<?php require 'includes/footer.php'; ?>
