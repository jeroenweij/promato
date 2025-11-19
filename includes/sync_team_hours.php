<?php
/**
 * Sync Team Hours from Individual Hours
 *
 * This function aggregates individual person hours from the Hours table
 * and updates the TeamHours table with the sum per team using efficient SQL.
 *
 * @param PDO $pdo Database connection
 * @param int $project Project ID
 * @param int $activityKey Activity Key (not ID)
 * @param int $year Year to sync
 * @return bool True on success, false on failure
 */
function syncTeamHours($pdo, $project, $activityKey, $year) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO TeamHours (Project, Activity, Team, Plan, Year, Hours)
            SELECT
                :project AS Project,
                :activityKey AS Activity,
                p.Team,
                SUM(h.Plan) AS Plan,
                :year AS Year,
                SUM(h.Hours) AS Hours
            FROM Hours h
            JOIN Personel p ON h.Person = p.Id
            WHERE h.Project = :project
            AND h.Activity = :activityKey
            AND h.Year = :year
            GROUP BY p.Team
            ON DUPLICATE KEY UPDATE Plan = VALUES(Plan)
        ");

        $stmt->execute([
            ':project' => $project,
            ':activityKey' => $activityKey,
            ':year' => $year
        ]);

        return true;

    } catch (PDOException $e) {
        error_log("Error syncing team hours: " . $e->getMessage());
        return false;
    }
}
