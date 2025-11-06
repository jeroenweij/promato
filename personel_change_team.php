<?php
require 'includes/header.php';
require_once 'includes/db.php';

// Authorization: Only allow auth level 4+ (Elevated/Admin) or project managers
// Get person ID early to check if user is their manager
$personIdCheck = filter_var($_POST['person_id'] ?? 0, FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: personel.php");
    exit;
}

csrf_protect(); // Verify CSRF token

// Validate inputs
$personId = filter_var($_POST['person_id'] ?? 0, FILTER_VALIDATE_INT);
$newTeam = filter_var($_POST['new_team'] ?? 0, FILTER_VALIDATE_INT);
$moveHours = isset($_POST['move_hours']);

$errors = [];

if (!$personId) {
    $errors[] = "Invalid person ID";
}

if (!$newTeam) {
    $errors[] = "Please select a new team";
}

// Get person details
$stmt = $pdo->prepare("SELECT * FROM Personel WHERE Id = ?");
$stmt->execute([$personId]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$person) {
    $errors[] = "Person not found";
}

// Verify the new team exists
$stmt = $pdo->prepare("SELECT Id, Name FROM Teams WHERE Id = ?");
$stmt->execute([$newTeam]);
$newTeamData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$newTeamData) {
    $errors[] = "New team not found";
}

// Check if it's actually a change
if ($person && $person['Team'] == $newTeam) {
    $errors[] = "Person is already in this team";
}

if (!empty($errors)) {
    echo '<section><div class="container"><div class="alert alert-danger">';
    echo '<h4>Cannot Change Team:</h4><ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul><a href="personel_edit.php?id=' . $personId . '" class="btn btn-secondary">Go Back</a></div></div></section>';
    require 'includes/footer.php';
    exit;
}

// Get old team name
$stmt = $pdo->prepare("SELECT Name FROM Teams WHERE Id = ?");
$stmt->execute([$person['Team']]);
$oldTeamData = $stmt->fetch(PDO::FETCH_ASSOC);
$oldTeamName = $oldTeamData['Name'] ?? 'Unknown';

$oldTeamId = $person['Team'];
$hoursMovedCount = 0;
$hoursSkippedCount = 0;
$hoursMovedDetails = [];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // If moveHours is checked, transfer planned hours from old team to new team
    if ($moveHours) {
        // Get all Hours entries for this person
        $hoursStmt = $pdo->prepare("
            SELECT Project, Activity, Plan, Hours, Year
            FROM Hours
            WHERE Person = :personId
            AND Year >= YEAR(CURDATE()) - 1
            ORDER BY Year, Project, Activity
        ");
        $hoursStmt->execute([':personId' => $personId]);
        $personHours = $hoursStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($personHours as $hourEntry) {
            $project = $hourEntry['Project'];
            $activity = $hourEntry['Activity'];
            $year = $hourEntry['Year'];
            $personPlan = $hourEntry['Plan'];

            // Skip if person has no planned hours for this activity
            if ($personPlan <= 0) {
                continue;
            }

            // Check if old team has hours planned for this activity
            $oldTeamStmt = $pdo->prepare("
                SELECT Plan, Hours
                FROM TeamHours
                WHERE Team = :oldTeam
                AND Project = :project
                AND Activity = :activity
                AND Year = :year
            ");
            $oldTeamStmt->execute([
                ':oldTeam' => $oldTeamId,
                ':project' => $project,
                ':activity' => $activity,
                ':year' => $year
            ]);
            $oldTeamHours = $oldTeamStmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldTeamHours) {
                // Old team has no hours planned for this activity, skip
                $hoursSkippedCount++;
                continue;
            }

            $oldTeamPlan = $oldTeamHours['Plan'];
            $oldTeamActual = $oldTeamHours['Hours'];

            // Calculate how much we can safely move
            // We can't make the old team's plan go negative
            // Also, we shouldn't move more than the old team has planned
            $maxCanMove = $oldTeamPlan;

            // If old team has logged hours, we need to keep at least that much in the plan
            if ($oldTeamActual > 0) {
                $maxCanMove = max(0, $oldTeamPlan - $oldTeamActual);
            }

            // The amount to transfer is the minimum of what person has planned and what we can safely remove
            $amountToMove = min($personPlan, $maxCanMove);

            if ($amountToMove <= 0) {
                // Can't move any hours for this activity
                $hoursSkippedCount++;
                continue;
            }

            // Update old team hours (reduce plan)
            $newOldTeamPlan = $oldTeamPlan - $amountToMove;
            if ($newOldTeamPlan > 0 || $oldTeamActual > 0) {
                // Keep the entry if there are still planned hours or actual hours
                $updateOldStmt = $pdo->prepare("
                    UPDATE TeamHours
                    SET Plan = :newPlan
                    WHERE Team = :oldTeam
                    AND Project = :project
                    AND Activity = :activity
                    AND Year = :year
                ");
                $updateOldStmt->execute([
                    ':newPlan' => $newOldTeamPlan,
                    ':oldTeam' => $oldTeamId,
                    ':project' => $project,
                    ':activity' => $activity,
                    ':year' => $year
                ]);
            } else {
                // Remove the entry if no hours left
                $deleteOldStmt = $pdo->prepare("
                    DELETE FROM TeamHours
                    WHERE Team = :oldTeam
                    AND Project = :project
                    AND Activity = :activity
                    AND Year = :year
                ");
                $deleteOldStmt->execute([
                    ':oldTeam' => $oldTeamId,
                    ':project' => $project,
                    ':activity' => $activity,
                    ':year' => $year
                ]);
            }

            // Check if new team already has hours for this activity
            $newTeamStmt = $pdo->prepare("
                SELECT Plan, Hours
                FROM TeamHours
                WHERE Team = :newTeam
                AND Project = :project
                AND Activity = :activity
                AND Year = :year
            ");
            $newTeamStmt->execute([
                ':newTeam' => $newTeam,
                ':project' => $project,
                ':activity' => $activity,
                ':year' => $year
            ]);
            $newTeamHours = $newTeamStmt->fetch(PDO::FETCH_ASSOC);

            if ($newTeamHours) {
                // Add to existing entry
                $newTeamPlan = $newTeamHours['Plan'] + $amountToMove;
                $updateNewStmt = $pdo->prepare("
                    UPDATE TeamHours
                    SET Plan = :newPlan
                    WHERE Team = :newTeam
                    AND Project = :project
                    AND Activity = :activity
                    AND Year = :year
                ");
                $updateNewStmt->execute([
                    ':newPlan' => $newTeamPlan,
                    ':newTeam' => $newTeam,
                    ':project' => $project,
                    ':activity' => $activity,
                    ':year' => $year
                ]);
            } else {
                // Create new entry
                $insertNewStmt = $pdo->prepare("
                    INSERT INTO TeamHours (Team, Project, Activity, Plan, Hours, Year)
                    VALUES (:newTeam, :project, :activity, :plan, 0, :year)
                ");
                $insertNewStmt->execute([
                    ':newTeam' => $newTeam,
                    ':project' => $project,
                    ':activity' => $activity,
                    ':plan' => $amountToMove,
                    ':year' => $year
                ]);
            }

            // Track what was moved
            $hoursMovedCount++;
            $hoursMovedDetails[] = [
                'project' => $project,
                'activity' => $activity,
                'year' => $year,
                'amount' => $amountToMove / 100 // Convert back to display format
            ];
        }
    }

    // Update the person's team
    $stmt = $pdo->prepare("UPDATE Personel SET Team = ? WHERE Id = ?");
    $stmt->execute([$newTeam, $personId]);

    // Commit transaction
    $pdo->commit();

    // Success message
    ?>
    <section class="white">
        <div class="container" style="max-width: 900px;">
            <div class="alert alert-success shadow-sm">
                <h4 class="alert-heading">
                    <i class="lucide-check-circle"></i> Team Changed Successfully!
                </h4>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Person:</strong><br>
                        <?= htmlspecialchars($person['Name']) ?> (<?= htmlspecialchars($person['Shortname']) ?>)
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>From Team:</strong><br>
                        <span class="badge badge-secondary"><?= htmlspecialchars($oldTeamName) ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>To Team:</strong><br>
                        <span class="badge badge-success"><?= htmlspecialchars($newTeamData['Name']) ?></span>
                    </div>
                </div>

                <?php if ($moveHours): ?>
                <hr>
                <div class="row mt-3">
                    <div class="col-12">
                        <h5><i class="lucide-calendar-clock"></i> Hours Transfer Summary</h5>
                        <?php if ($hoursMovedCount > 0): ?>
                            <div class="alert alert-info">
                                <strong>Successfully moved:</strong> <?= $hoursMovedCount ?> activity hour allocations
                                <?php if ($hoursSkippedCount > 0): ?>
                                    <br><strong>Skipped:</strong> <?= $hoursSkippedCount ?> activity allocations (would make old team negative or no hours to move)
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($hoursMovedDetails)): ?>
                            <details>
                                <summary style="cursor: pointer; color: #0066cc;"><strong>View Details</strong></summary>
                                <table class="table table-sm table-striped mt-2">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Activity</th>
                                            <th>Year</th>
                                            <th class="text-right">Hours Moved</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($hoursMovedDetails as $detail): ?>
                                        <tr>
                                            <td><?= $detail['project'] ?></td>
                                            <td><?= $detail['activity'] ?></td>
                                            <td><?= $detail['year'] ?></td>
                                            <td class="text-right"><?= number_form($detail['amount'], 1) ?> hrs</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <strong>No hours were moved.</strong> Either the person had no planned hours, or moving them would have made the old team's plan negative.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <hr>
                <div class="d-flex justify-content-between mt-3">
                    <a href="personel.php" class="btn btn-primary">
                        <i class="lucide-list"></i> Back to Personnel List
                    </a>
                    <a href="personel_edit.php?id=<?= $personId ?>" class="btn btn-outline-primary">
                        <i class="lucide-edit"></i> Continue Editing
                    </a>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="lucide-info"></i> What Happens Next?
                    </h5>
                </div>
                <div class="card-body">
                    <p>The team change has been applied. Please note the following:</p>
                    <ul>
                        <li><strong>Capacity Planning:</strong> This person's hours will now count towards the new team's capacity</li>
                        <li><strong>Team Reports:</strong> Future reports will show this person under the new team</li>
                        <?php if ($moveHours && $hoursMovedCount > 0): ?>
                        <li><strong>Planned Hours:</strong> <?= $hoursMovedCount ?> activity hour allocations have been transferred from <?= htmlspecialchars($oldTeamName) ?> to <?= htmlspecialchars($newTeamData['Name']) ?></li>
                        <?php endif; ?>
                        <li><strong>Individual Assignments:</strong> Personal hour assignments (Hours table) remain unchanged</li>
                        <li><strong>Past Hours:</strong> Previously logged hours remain associated with their original team</li>
                    </ul>
                    <div class="alert alert-info mt-3">
                        <strong>Recommendation:</strong> Review team planning views to verify the hour transfers and ensure proper resource allocation.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
    .alert-success {
        border-left: 4px solid #28a745;
    }
    details summary {
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 10px;
    }
    details[open] summary {
        margin-bottom: 10px;
    }
    </style>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Auto-redirect after 15 seconds
    setTimeout(function() {
        if (confirm('Redirect to personnel list?')) {
            window.location.href = 'personel.php';
        }
    }, 15000);
    </script>
    <?php

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo '<section><div class="container"><div class="alert alert-danger">';
    echo '<h4>Error Changing Team:</h4>';
    echo '<p>An error occurred while changing the team assignment. Please try again.</p>';
    if ($userAuthLevel >= 5) {
        echo '<p class="text-muted"><small>Technical details: ' . htmlspecialchars($e->getMessage()) . '</small></p>';
    }
    echo '<a href="personel_edit.php?id=' . $personId . '" class="btn btn-secondary">Go Back</a></div></div></section>';
}

require 'includes/footer.php';
?>
