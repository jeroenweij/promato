<?php
require 'includes/header.php';
require 'includes/db.php';

// Handle leave calculation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_leave'])) {
    
    // Get all active personnel
    $stmt = $pdo->query("SELECT Id, StartDate, EndDate, Fultime FROM Personel");
    $allPersonnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $errors = [];
    
    foreach ($allPersonnel as $person) {
        try {
            $leaveHours = calculateLeaveForYear(
                $person['StartDate'], 
                $person['EndDate'], 
                $person['Fultime'],
                $selectedYear
            );
            
            if ($leaveHours > 0) {
                $stmt = $pdo->prepare("INSERT INTO Hours (Project, Activity, Person, Plan, Hours, `Year`) 
                    VALUES (10, 1, :person, :hours, 0, :year)
                    ON DUPLICATE KEY UPDATE Plan = GREATEST(Hours, :hours)");
                $stmt->execute([
                    ':person' => $person['Id'],
                    ':hours' => $leaveHours * 100,
                    ':year' => $selectedYear,
                ]);
            }
        } catch (Exception $e) {
            $errors[] = "Person ID {$person['Id']}: " . $e->getMessage();
        }
    }
    
    $message = "Leave hours calculated for {$selectedYear}.";
    if (!empty($errors)) {
        $message .= " Errors: " . implode(', ', $errors);
    }
}

function calculateLeaveForYear($startDate, $endDate, $fulltimePercent, $targetYear) {
    $fullLeaveHours = 248;
    
    $startDate = $startDate ?: "$targetYear-01-01";
    $endDate = $endDate ?: "$targetYear-12-31";
    
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    if ($start > $end) return 0;
    
    $yearStart = new DateTime("$targetYear-01-01");
    $yearEnd = new DateTime("$targetYear-12-31");
    
    // Check if person works during this year at all
    if ($end < $yearStart || $start > $yearEnd) {
        return 0;
    }
    
    // Determine actual work period in this year
    $workStart = ($start > $yearStart) ? $start : $yearStart;
    $workEnd = ($end < $yearEnd) ? $end : $yearEnd;
    
    // Calculate the fraction of the year worked
    $daysInYear = $yearStart->format('L') == 1 ? 366 : 365;
    $daysWorked = $workStart->diff($workEnd)->days + 1;
    $yearFraction = $daysWorked / $daysInYear;
    
    // Calculate pro-rated leave hours
    return round($fullLeaveHours * $yearFraction * ($fulltimePercent / 100));
}

// Check if leave calculation is needed for current year
$checkStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT p.Id) as total_personnel,
           COUNT(DISTINCT h.Person) as personnel_with_leave
    FROM Personel p
    LEFT JOIN Hours h ON h.Person = p.Id 
        AND h.Project = 10 
        AND h.Activity = 1 
        AND h.Year = :year
    WHERE (p.EndDate IS NULL OR p.EndDate >= :year_start)
      AND p.StartDate <= :year_end
");
$checkStmt->execute([
    ':year' => $selectedYear,
    ':year_start' => "$selectedYear-01-01",
    ':year_end' => "$selectedYear-12-31"
]);
$leaveCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);
$missingLeave = $leaveCheck['total_personnel'] - $leaveCheck['personnel_with_leave'];

// Define sortable columns and their database mappings
$sortableColumns = [
    'Name' => 'Personel.Name',
    'Email' => 'Personel.Email', 
    'Shortname' => 'Personel.Shortname',
    'LastLogin' => 'Personel.LastLogin',
    'StartDate' => 'Personel.StartDate',
    'EndDate' => 'Personel.EndDate',
    'Department' => 'Departments.name',
    'Type' => 'Types.Name',
    'Plan' => 'Personel.plan'
];

// Get sort parameters from URL
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$sortOrder = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

// Build ORDER BY clause
$orderClause = '';
if ($sortBy === 'default') {
    $orderClause = 'ORDER BY Departments.Ord, Personel.Ord, Personel.Name';
} else {
    $sortColumns = explode(',', $sortBy);
    $validSorts = [];
    
    foreach ($sortColumns as $column) {
        $column = trim($column);
        if (isset($sortableColumns[$column])) {
            $validSorts[] = $sortableColumns[$column] . ' ' . $sortOrder;
        }
    }
    
    if (!empty($validSorts)) {
        $orderClause = 'ORDER BY ' . implode(', ', $validSorts);
    } else {
        $orderClause = 'ORDER BY Departments.Ord, Personel.Ord, Personel.Name';
    }
}

// Fetch all personnel with dynamic sorting
$sql = "SELECT Personel.*, Types.Name AS TypeName, Departments.name AS DepName 
        FROM Personel 
        LEFT JOIN Types ON Personel.Type = Types.Id 
        LEFT JOIN Departments ON Personel.Department = Departments.Id 
        " . $orderClause;

$stmt = $pdo->query($sql);
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to generate sort URLs
function getSortUrl($column, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    return '?sort=' . urlencode($column) . '&order=' . $newOrder;
}

// Helper function to get sort icon
function getSortIcon($column, $currentSort, $currentOrder) {
    if ($currentSort === $column) {
        return $currentOrder === 'ASC' ? ' ↑' : ' ↓';
    }
    return '';
}
?>

<section class="white"><div class="container"><h2>Personel Overview</h2>

<?php if (isset($message)): ?>
<div class="alert alert-success" role="alert">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Action buttons -->
<div class="mb-3">
    <a href="personel_edit.php" class="btn btn-primary">+ Add Person</a>
    <a href="personel_order.php" class="btn btn-primary">Change order</a>
    
    <?php if ($missingLeave > 0): ?>
    <form method="post" style="display: inline;" onsubmit="return confirm('Calculate leave hours for <?= $leaveCheck['total_personnel'] ?> personnel for year <?= $selectedYear ?>?');">
        <button type="submit" name="calculate_leave" class="btn btn-warning">
            ⚠ Calculate Leave Hours (<?= $missingLeave ?> missing)
        </button>
    </form>
    <?php else: ?>
    <form method="post" style="display: inline;" onsubmit="return confirm('Recalculate leave hours for all <?= $leaveCheck['total_personnel'] ?> personnel for year <?= $selectedYear ?>? This will update existing values if the new calculation is higher.');">
        <button type="submit" name="calculate_leave" class="btn btn-secondary">
            🔄 Recalculate Leave Hours for <?= $selectedYear ?>
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- Results info -->
<div class="mb-2">
    <small class="text-muted">
        Showing <?= count($personnel) ?> users 
        <?php if ($sortBy !== 'default'): ?>
            - sorted by <?= htmlspecialchars($sortBy) ?> (<?= $sortOrder ?>)
        <?php else: ?>
            - default order
        <?php endif; ?>
        | Year: <?= $selectedYear ?>
    </small>
</div>

<table class="table table-striped">
  <thead>
    <tr>
      <th><a href="<?= getSortUrl('Name', $sortBy, $sortOrder) ?>" class="text-decoration-none">Name<?= getSortIcon('Name', $sortBy, $sortOrder) ?></a></th>
      <th><a href="<?= getSortUrl('Email', $sortBy, $sortOrder) ?>" class="text-decoration-none">Email<?= getSortIcon('Email', $sortBy, $sortOrder) ?></a></th>
      <th><a href="<?= getSortUrl('Shortname', $sortBy, $sortOrder) ?>" class="text-decoration-none">Shortname<?= getSortIcon('Shortname', $sortBy, $sortOrder) ?></a></th>
      <th><a href="<?= getSortUrl('LastLogin', $sortBy, $sortOrder) ?>" class="text-decoration-none">LastLogin<?= getSortIcon('LastLogin', $sortBy, $sortOrder) ?></a></th>
      <th><a href="<?= getSortUrl('StartDate', $sortBy, $sortOrder) ?>" class="text-decoration-none">Start Date<?= getSortIcon('StartDate', $sortBy, $sortOrder) ?></a></th>
      <th><a href="<?= getSortUrl('EndDate', $sortBy, $sortOrder) ?>" class="text-decoration-none">End Date<?= getSortIcon('EndDate', $sortBy, $sortOrder) ?></a></th>
      <th>WBSO</th>
      <th>Fulltime %</th>
      <th><a href="<?= getSortUrl('Department', $sortBy, $sortOrder) ?>" class="text-decoration-none">Department<?= getSortIcon('Department', $sortBy, $sortOrder) ?></a></th>
      <th><a href="<?= getSortUrl('Type', $sortBy, $sortOrder) ?>" class="text-decoration-none">Type<?= getSortIcon('Type', $sortBy, $sortOrder) ?></a></th>
      <th><a href="<?= getSortUrl('Plan', $sortBy, $sortOrder) ?>" class="text-decoration-none">Plan<?= getSortIcon('Plan', $sortBy, $sortOrder) ?></a></th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($personnel as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['Name']) ?></td>
      <td><?= htmlspecialchars($p['Email']) ?></td>
      <td><?= htmlspecialchars($p['Shortname']) ?></td>
      <?php
      $LastLogin = 'N/A';
      if (!empty($p['LastLogin'])) {
          $loginTime = new DateTime($p['LastLogin']);
          $now = new DateTime();

          $loginDate = $loginTime->format('Y-m-d');
          $nowDate = $now->format('Y-m-d');

          if ($loginDate == $nowDate) {
              $LastLogin = 'Today ' . $loginTime->format('H:i');
          } elseif ($loginDate == $now->modify('-1 day')->format('Y-m-d')) {
              $LastLogin = 'Yesterday ' . $loginTime->format('H:i');
          } else {
              $LastLogin = $loginTime->format('d/m/Y H:i');
          }
      }
      ?>
      <td><?= $LastLogin ?></td>
      <td><?= !empty($p['StartDate']) ? (new DateTime($p['StartDate']))->format('d/m/Y') : '' ?></td>
      <td><?= !empty($p['EndDate']) ? (new DateTime($p['EndDate']))->format('d/m/Y') : '' ?></td>
      <td><?= $p['WBSO'] ? 'Yes' : 'No' ?></td>
      <td><?= htmlspecialchars($p['Fultime']) ?></td>
      <td><?= htmlspecialchars($p['DepName'] ?? '') ?></td>
      <td><?= htmlspecialchars($p['TypeName'] ?? '') ?></td>
      <td><?= $p['plan'] ? 'Yes' : 'No' ?></td>
      <td><a href="personel_edit.php?id=<?= $p['Id'] ?>" class="btn btn-sm btn-secondary">Edit</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</div></section>

<?php require 'includes/footer.php'; ?>