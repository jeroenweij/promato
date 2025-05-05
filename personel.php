<?php
require 'includes/header.php';
require 'includes/db.php';

// Fetch all personel
$stmt = $pdo->query("SELECT Personel.*, Types.Name AS TypeName FROM Personel LEFT JOIN Types ON Personel.Type = Types.Id ORDER BY Ord, Name");
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Render table
?>
<section><div class="container"><h2>Personel Overview</h2>
<a href="personel_edit.php" class="btn btn-primary mb-3">+ Add Person</a>
<table class="table table-striped">
  <thead>
    <tr>
      <th>Name</th>
      <th>Email</th>
      <th>Shortname</th>
      <th>Start Date</th>
      <th>End Date</th>
      <th>WBSO</th>
      <th>Fulltime %</th>
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
      <td><?= htmlspecialchars($p['StartDate']) ?></td>
      <td><?= htmlspecialchars($p['EndDate'] ?? '') ?></td>
      <td><?= $p['WBSO'] ? 'Yes' : 'No' ?></td>
      <td><?= htmlspecialchars($p['Fultime']) ?></td>
      <td><?= htmlspecialchars($p['TypeName'] ?? '') ?></td>
      <td><?= $p['plan'] ? 'Yes' : 'No' ?></td>
      <td><a href="personel_edit.php?id=<?= $p['Id'] ?>" class="btn btn-sm btn-secondary">Edit</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></section>

<?php require 'includes/footer.php'; ?>

