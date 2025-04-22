<?php
require 'includes/header.php';
require 'includes/db.php';
?>
<section id="pricing"><div class="container">

  <h2>Upload Realised Hours (CSV)</h2>

  <?php
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $file = $_FILES['csv']['tmp_name'];
    if (($handle = fopen($file, "r")) !== false) {
      // Skip initial header rows
      fgetcsv($handle); // empty line with commas
      $header = fgetcsv($handle);

      // Load Personel names
      $stmt = $pdo->query("SELECT Id, Name FROM Personel");
      $personMap = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $personMap[strtolower(trim($row['Name']))] = $row['Id'];
      }

      // Map CSV columns to Personel IDs
      $colMap = [];
      foreach ($header as $i => $name) {
        $name = trim($name, "\" \t\n\r\0\x0B");
        if (!$name) continue;
        $key = strtolower($name);
        if (isset($personMap[$key])) {
          $colMap[$i] = $personMap[$key];
        } else {
          echo "<div class='alert alert-warning'>Name not found in database: <strong>{$name}</strong></div>";
        }
      }

      $currentProject = null;
      while (($row = fgetcsv($handle)) !== false) {
        if (empty($row[0]) && empty($row[1]) && empty($row[2])) continue;
        if (!empty($row[0])) {
          $currentProject = (int)$row[0];
        }

        $activity = isset($row[2]) ? (int)$row[2] : null;
        if (!$activity) continue;

        foreach ($colMap as $colIndex => $personId) {
          $val = trim(str_replace(',', '.', $row[$colIndex] ?? ''));
          $hours = floatval($val);
          if ($hours > 0) {
            $stmt = $pdo->prepare("INSERT INTO Hours (Project, Activity, Person, Hours)
              VALUES (:project, :activity, :person, :hours)
              ON DUPLICATE KEY UPDATE Hours = :hours");
            $stmt->execute([
              ':project' => $currentProject,
              ':activity' => $activity,
              ':person' => $personId,
              ':hours' => round($hours * 100),
            ]);
          }
        }
      }

      fclose($handle);
      echo "<div class='alert alert-success mt-3'>CSV file imported successfully.</div>";
    } else {
      echo "<div class='alert alert-danger'>Failed to open uploaded file.</div>";
    }
  }
  ?>

  <form method="post" enctype="multipart/form-data" class="mt-4">
    <div class="mb-3">
      <label for="csv" class="form-label">Select CSV File</label>
      <input type="file" name="csv" id="csv" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
  
</div></section>

<?php
require 'includes/footer.php';
?>

