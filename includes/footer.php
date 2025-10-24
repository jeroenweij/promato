<?php
// Fetch footer menu pages from database
$footerMenusStmt = $pdo->prepare("
    SELECT 
        m.Id AS menu_id,
        m.Name AS menu_name,
        p.Path,
        p.Name AS page_name
    FROM Menus m
    LEFT JOIN Pages p ON p.Menu = m.Id
    LEFT JOIN PageAccess pa ON pa.PageId = p.Id AND pa.UserId = :userId
    WHERE p.Id IS NOT NULL 
        AND (p.Auth <= :authLevel OR pa.UserId IS NOT NULL)
    ORDER BY m.Id, p.Id
");

$footerMenusStmt->execute([
    ':userId' => $userId ?? 0,
    ':authLevel' => $userAuthLevel ?? 1
]);

$footerResults = $footerMenusStmt->fetchAll(PDO::FETCH_ASSOC);

// Group pages by menu
$footerMenus = [];
foreach ($footerResults as $row) {
    $menuId = $row['menu_id'];
    if (!isset($footerMenus[$menuId])) {
        $footerMenus[$menuId] = [
            'name' => $row['menu_name'],
            'pages' => []
        ];
    }
    $footerMenus[$menuId]['pages'][] = [
        'path' => $row['Path'],
        'name' => $row['page_name']
    ];
}
?>
  <!--  F O O T E R  -->
  <footer>
    <div class="container">
      <div class="row">
        <?php foreach ($footerMenus as $menu): ?>
          <div class="col-md-2">
            <h5><?= htmlspecialchars($menu['name']) ?></h5>
            <ul>
              <?php foreach ($menu['pages'] as $page): 
                // highlight active page
                $activeClass = ($page['path'] === $currentPage) ? ' active' : '';
              ?>
                <li class="<?= $activeClass ?>">
                  <a href="/<?= htmlspecialchars($page['path']) ?>"><?= htmlspecialchars($page['name']) ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
        
        <?php 
        // Calculate remaining columns for description
        $usedColumns = count($footerMenus);
        $remainingColumns = 6 - $usedColumns;
        if ($remainingColumns > 0): 
        ?>
          <div class="col-md-2">
            <h5>Promato</h5>
            <p>Say hello to Promato! your ultimate sidekick! From project dashboards to personal Kanban board, it keeps tabs on tasks, tracks capacity, and makes sure nothing slips through the cracks. Work smarter, not harder—and have a little fun while you're at it!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </footer>
  <!--  E N D  F O O T E R  -->
    
    <!-- External JavaScripts -->
    <!-- jQuery already loaded in header.php, then Popper.js, then Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js" integrity="sha384-smHYKdLADwkXOn1EmN1qk/HfnUcbVRZyYmZ4qpPea6sjB/pTJ0euyQp0Mk8ck+5T" crossorigin="anonymous"></script>
  </body>
</html>