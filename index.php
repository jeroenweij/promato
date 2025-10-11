<?php
$pageSpecificCSS = ['mainmenu.css'];
require 'includes/header.php';

// Fetch menus with their accessible pages in one go
// User can access a page if:
// 1. Their auth level is sufficient (p.Auth <= :authLevel), OR
// 2. They have explicit access granted in PageAccess table
$stmt = $pdo->prepare("
    SELECT 
        m.Id AS menu_id,
        m.Name AS menu_name,
        m.Icon AS menu_icon,
        p.Id AS page_id,
        p.Name AS page_name,
        p.Path AS page_path,
        p.Icon AS page_icon,
        p.Auth AS page_auth
    FROM Menus m
    LEFT JOIN Pages p ON p.Menu = m.Id 
    LEFT JOIN PageAccess pa ON pa.PageId = p.Id AND pa.UserId = :userId
    WHERE p.Id IS NOT NULL 
        AND (p.Auth <= :authLevel OR pa.UserId IS NOT NULL)
    ORDER BY m.Id, p.Id
");

$stmt->execute([
    ':authLevel' => $userAuthLevel,
    ':userId' => $userId
]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group results by menu (single pass through data)
$menuData = [];
foreach ($results as $row) {
    $menuId = $row['menu_id'];
    
    // Initialize menu if not exists
    if (!isset($menuData[$menuId])) {
        $menuData[$menuId] = [
            'name' => ucfirst($row['menu_name']),
            'icon' => $row['menu_icon'] ?: 'folder',
            'class' => 'bg-' . strtolower($row['menu_name']),
            'pages' => []
        ];
    }
    
    // Add page to menu
    $menuData[$menuId]['pages'][] = [
        'name' => $row['page_name'],
        'path' => $row['page_path'],
        'icon' => $row['page_icon'] ?: 'box'
    ];
}
?>

<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

<section id="personal-dashboard">
    <div class="container py-5">
        <?php foreach ($menuData as $menu): ?>
            <div class="card mb-5">
                <div class="card-header <?= htmlspecialchars($menu['class']) ?> text-white">
                    <h2 class="mb-0">
                        <i class="feather-icon" data-feather="<?= htmlspecialchars($menu['icon']) ?>"></i>
                        <?= htmlspecialchars($menu['name']) ?>
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($menu['pages'] as $page): ?>
                            <div class="col-md-3 col-sm-6 mb-4">
                                <a href="<?= htmlspecialchars($page['path']) ?>" class="text-decoration-none">
                                    <div class="card menu-card h-100 text-center">
                                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                            <div class="icon-container mb-3 <?= htmlspecialchars($menu['class']) ?>">
                                                <i class="feather-icon" data-feather="<?= htmlspecialchars($page['icon']) ?>"></i>
                                            </div>
                                            <h5 class="card-title"><?= htmlspecialchars($page['name']) ?></h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') feather.replace();
});
</script>

<?php require 'includes/footer.php'; ?>