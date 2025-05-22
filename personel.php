<?php
require 'includes/header.php';
require 'includes/db.php';

// Fetch all personel
$stmt = $pdo->query("SELECT Personel.*, Types.Name AS TypeName, Departments.name AS DepName 
  FROM Personel 
  LEFT JOIN Types ON Personel.Type = Types.Id 
  LEFT JOIN Departments ON Personel.Department = Departments.Id 
  ORDER BY Departments.Ord, Ord, Name");
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Render table
?>
<section class="white"><div class="container"><h2>Personel Overview</h2>
<a href="personel_edit.php" class="btn btn-primary mb-3">+ Add Person</a>
<a href="personel_order.php" class="btn btn-primary mb-3">Change order</a>
<table class="table table-striped">
  <thead>
    <tr>
      <th>Name</th>
      <th>Email</th>
      <th>Shortname</th>
      <th>LastLogin</th>
      <th>Start Date</th>
      <th>End Date</th>
      <th>WBSO</th>
      <th>Fulltime %</th>
      <th>Department</th>
      <th>Type</th>
      <th>Plan</th>
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
          $diff = $now->diff($loginTime);
          
          if ($diff->days == 0) {
              $LastLogin = 'Today ' . $loginTime->format('H:i');
          } elseif ($diff->days == 1) {
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

