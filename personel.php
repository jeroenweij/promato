<?php
require 'includes/header.php';
require 'includes/db.php';

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
    // Default sorting
    $orderClause = 'ORDER BY Departments.Ord, Personel.Ord, Personel.Name';
} else {
    // Parse multiple sort columns (comma-separated)
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
        // Fallback to default if no valid columns
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

// Render table
?>
<section class="white"><div class="container"><h2>Personel Overview</h2>

<!-- Action buttons -->
<div class="mb-3">
    <a href="personel_edit.php" class="btn btn-primary">+ Add Person</a>
    <a href="personel_order.php" class="btn btn-primary">Change order</a>
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