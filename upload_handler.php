<?php
require 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/csrf.php';

header('Content-Type: text/plain');
set_time_limit(0); // Important for long uploads
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

// Write helper
function logmsg($msg) {
  echo $msg . "\n";
  @ob_flush();
  @flush();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
  csrf_protect(); // Verify CSRF token

  // Validate file upload
  if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    logmsg("❌ File upload error");
    exit;
  }

  // Validate file size (max 10MB)
  $maxSize = 10 * 1024 * 1024; // 10MB
  if ($_FILES['csv']['size'] > $maxSize) {
    logmsg("❌ File too large. Maximum size is 10MB");
    exit;
  }

  // Validate file type - must be CSV
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $_FILES['csv']['tmp_name']);
  finfo_close($finfo);

  $allowedMimeTypes = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/x-csv', 'text/comma-separated-values', 'text/x-comma-separated-values'];

  // Also check file extension
  $fileExtension = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));

  if (!in_array($mimeType, $allowedMimeTypes) && $fileExtension !== 'csv') {
    logmsg("❌ Invalid file type. Only CSV files are allowed. Detected: $mimeType");
    exit;
  }

  $file = $_FILES['csv']['tmp_name'];

  // Validate year parameter
  $selectedYear = filter_var($_POST['year'] ?? 0, FILTER_VALIDATE_INT);
  if (!$selectedYear || $selectedYear < 2000 || $selectedYear > 2100) {
    logmsg("❌ Invalid year parameter");
    exit;
  }

  // Count total lines for progress (excluding header)
  $totalRows = max(1, count(file($file)) - 2); // minus 2 headers
  
  if (($handle = fopen($file, "r")) !== false) {
    fgetcsv($handle); // skip empty header
    $header = fgetcsv($handle);

    $stmt = $pdo->query("SELECT Id, Name, Team FROM Personel");
    $personMap = [];
    $teamMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $personMap[strtolower(trim($row['Name']))] = $row['Id'];
      $teamMap[$row['Id']] = $row['Team']; // Map person ID to their team
    }
    $personMap[strtolower('Totaal Som van Uren')] = 0;
    $personMap[strtolower('Total Sum of Uren')] = 0;
    $teamMap[0] = null;

    $colMap = [];
    $skipColumns = ['projectcode', 'project', 'activiteitscode', 'activiteit'];
    foreach ($header as $i => $name) {
      $name = trim($name, "\" \t\n\r\0\x0B");
      if (!$name) continue;
      $key = strtolower($name);
      if (in_array($key, $skipColumns)) continue;
      if (isset($personMap[$key])) {
        $colMap[$i] = $personMap[$key];
      } else {
        logmsg("⚠️ Name not found: {$name}");
      }
    }

    $stmt = $pdo->prepare("UPDATE Hours SET Hours = 0 WHERE `Year` = :year");
    $stmt->execute([':year' => $selectedYear]);
    $stmt = $pdo->prepare("UPDATE TeamHours SET Hours = 0 WHERE `Year` = :year");
    $stmt->execute([':year' => $selectedYear]);
  
    logmsg("🔄 Cleared existing hours...");

    $currentProject = null;
    $currentProjectName = null;
    $count = 0;
    $rowIndex = 0;
    while (($row = fgetcsv($handle)) !== false) {
      $rowIndex++;
      if (empty($row[0]) && empty($row[1]) && empty($row[2])) continue;
      if (!empty($row[0])) {
          $currentProject = (int)$row[0];
          if (strtolower(trim($row[0])) === 'eindtotaal' || strtolower(trim($row[0])) === 'grand total') {
              $currentProject = 0;
              $currentProjectName = 'Totals';
              $row[2]=0;
          }
      }
      if (!empty($row[1])) $currentProjectName = $row[1];
      logmsg("Start Import hours for {$currentProjectName} {$row[3]}.");
      if (!isset($row[2])) continue;
      $activity = (int)$row[2];

      foreach ($colMap as $colIndex => $personId) {
        $val = trim(str_replace('.', '', $row[$colIndex] ?? '')); // Remove thousands separator (dot)
        $val = str_replace(',', '.', $val);  // Replace comma with dot for decimal point
        $hours = floatval($val);  // Convert to float
      
        if ($hours > 0 && $personId > 0 && $currentProject > 0) {
          $stmt = $pdo->prepare("INSERT INTO Hours (Project, Activity, Person, Hours, `Year`)
            VALUES (:project, :activity, :person, :hours, :year)
            ON DUPLICATE KEY UPDATE Hours = :hours");
          $stmt->execute([
            ':project' => $currentProject,
            ':activity' => $activity,
            ':person' => $personId,
            ':hours' => round($hours * 100),
            ':year' => $selectedYear
          ]);
      
          $team = $teamMap[$personId]; // Get the team for this person
          $stmt = $pdo->prepare("INSERT INTO TeamHours (Project, Activity, Team, Hours, `Year`)
            VALUES (:project, :activity, :team, :hours, :year)
            ON DUPLICATE KEY UPDATE Hours = Hours + :hours");
          $stmt->execute([
            ':project' => $currentProject,
            ':activity' => $activity,
            ':team' => $team,
            ':hours' => round($hours * 100),
            ':year' => $selectedYear
          ]);
          $count++;
        }
      }
      
      $percent = round(($rowIndex / $totalRows) * 100);
      logmsg("Progress: {$percent}%");
      logmsg("✅ Imported hours for {$currentProjectName} {$row[3]}.");
    }

    fclose($handle);
    logmsg("✅ Imported {$count} entries.");

    // Update national hollidays
    $stmt = $pdo->prepare("UPDATE Hours SET Plan = Hours WHERE Project = :project AND Activity = :activity AND `Year` = :year");
    $stmt->execute([
        ':project' => 10,
        ':activity' => 2,
        ':year' => $selectedYear
    ]);

    // Update planned leave if leave > planned
    $stmt = $pdo->prepare("UPDATE Hours SET Plan = Hours WHERE Plan < Hours AND Project = :project AND Activity = :activity AND `Year` = :year");
    $stmt->execute([
        ':project' => 10,
        ':activity' => 1,
        ':year' => $selectedYear
    ]);
    logmsg("📆 Set planned hours for holidays.");

    $stmt = $pdo->prepare("UPDATE TeamHours AS th JOIN Teams AS t ON th.Team = t.Id SET th.Plan = th.Hours WHERE t.Planable = 0 AND th.`Year` = :year");
    $stmt->execute([
        ':year' => $selectedYear
    ]);
    
    $stmt = $pdo->prepare("UPDATE Hours AS h JOIN Personel AS p ON h.Person = p.Id JOIN Teams AS t ON p.Team = t.Id SET h.Plan = h.Hours WHERE t.Planable = 0 AND h.`Year` = :year");
    $stmt->execute([
        ':year' => $selectedYear
    ]);

  } else {
    logmsg("❌ Failed to open uploaded file.");
  }
} else {
  logmsg("❌ Invalid request.");
}

