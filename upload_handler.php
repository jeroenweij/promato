<?php
require 'includes/auth.php';
require 'includes/db.php';

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
  $file = $_FILES['csv']['tmp_name'];
  $selectedYear = $_POST['year'];

  // Count total lines for progress (excluding header)
  $totalRows = max(1, count(file($file)) - 2); // minus 2 headers
  
  if (($handle = fopen($file, "r")) !== false) {
    fgetcsv($handle); // skip empty header
    $header = fgetcsv($handle);

    $stmt = $pdo->query("SELECT Id, Name FROM Personel");
    $personMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $personMap[strtolower(trim($row['Name']))] = $row['Id'];
    }
    $personMap[strtolower('Totaal Som van Uren')] = 0;

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
          if (strtolower(trim($row[0])) === 'eindtotaal') {
              $currentProject = 0;
              $currentProjectName = 'Totals';
              $row[2]=0;
          }
      }
      if (!empty($row[1])) $currentProjectName = $row[1];
      if (!isset($row[2])) continue;
      $activity = (int)$row[2];

      foreach ($colMap as $colIndex => $personId) {
        $val = trim(str_replace('.', '', $row[$colIndex] ?? '')); // Remove thousands separator (dot)
        $val = str_replace(',', '.', $val);  // Replace comma with dot for decimal point
        $hours = floatval($val);  // Convert to float

        if ($hours > 0) {
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
          
          $count++;
        }
      }
      
      $percent = round(($rowIndex / $totalRows) * 100);
      logmsg("Progress: {$percent}%");
      logmsg("✅ Imported hours for {$currentProjectName} {$row[3]}.");
    }

    fclose($handle);
    logmsg("✅ Imported {$count} entries.");
    $stmt = $pdo->prepare("UPDATE Hours SET Plan = Hours WHERE Project = :project AND Activity = :activity AND `Year` = :year");
    $stmt->execute([
        ':project' => 10,
        ':activity' => 2,
        ':year' => $selectedYear
    ]);
    $stmt = $pdo->prepare("UPDATE Hours SET Plan = Hours WHERE Plan < Hours AND Project = :project AND Activity = :activity AND `Year` = :year");
    $stmt->execute([
        ':project' => 10,
        ':activity' => 1,
        ':year' => $selectedYear
    ]);
    logmsg("📆 Set planned hours for holidays.");
  } else {
    logmsg("❌ Failed to open uploaded file.");
  }
} else {
  logmsg("❌ Invalid request.");
}

