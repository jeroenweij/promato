<?php
$pageSpecificCSS = ['mainmenu.css'];
require 'includes/header.php';
require 'includes/db.php';

// Group pages by menu category
$menuGroups = [
    'main' => [
        'title' => 'Main',
        'icon' => 'grid',
        'class' => 'bg-main',
        'auth_level' => 2
    ],
    'plan' => [
        'title' => 'Planning',
        'icon' => 'calendar',
        'class' => 'bg-plan',
        'auth_level' => 2
    ],
    'admin' => [
        'title' => 'Admin',
        'icon' => 'shield',
        'class' => 'bg-admin',
        'auth_level' => 4
    ],
    'file' => [
        'title' => 'File',
        'icon' => 'file',
        'class' => 'bg-file',
        'auth_level' => 5
    ]
];

// Define icons for specific pages
$pageIcons = [
    'dashboard.php' => 'home',
    'projects.php' => 'briefcase',
    'kanban.php' => 'trello',
    'capacity_planning.php' => 'bar-chart-2',
    'projects_edit.php' => 'edit',
    'personel.php' => 'users',
    'upload.php' => 'upload',
    'export.php' => 'download',
    'capacity.php' => 'cpu',
    'priority_planning.php' => 'list',
    'personel_order.php' => 'user-check',
    'wbso.php' => 'tag',
    'project_add.php' => 'file-plus'
];

// Function to get a suitable icon for a page
function getPageIcon($page, $pageIcons) {
    if (isset($pageIcons[$page])) {
        return $pageIcons[$page];
    }
    
    // Default icons based on partial name matches
    if (strpos($page, 'project') !== false) return 'briefcase';
    if (strpos($page, 'person') !== false) return 'user';
    if (strpos($page, 'capacity') !== false) return 'activity';
    if (strpos($page, 'dashboard') !== false) return 'grid';
    if (strpos($page, 'upload') !== false) return 'upload-cloud';
    if (strpos($page, 'export') !== false) return 'download-cloud';
    
    // Generic default
    return 'box';
}
?>

<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<section id="personal-dashboard">
    <div class="container py-5">
      
        <?php foreach ($menuGroups as $menuKey => $menuGroup): 
            if ($menuGroup['auth_level'] > $userAuthLevel) continue; ?>
            <div class="card mb-5">
                <div class="card-header <?= $menuGroup['class'] ?> text-white">
                    <h2 class="mb-0">
                        <i class="feather-icon" data-feather="<?= $menuGroup['icon'] ?>"></i>
                        <?= $menuGroup['title'] ?>
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        foreach ($pages as $pagePath => $page):
                            if ($page['menu'] === $menuKey && $page['auth_level'] <= $userAuthLevel):
                        ?>
                            <div class="col-md-3 col-sm-6 mb-4">
                                <a href="<?= $pagePath ?>" class="text-decoration-none">
                                    <div class="card menu-card h-100 text-center">
                                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                            <div class="icon-container mb-3 <?= $menuGroup['class'] ?>">
                                                <i class="feather-icon" data-feather="<?= getPageIcon($pagePath, $pageIcons) ?>"></i>
                                            </div>
                                            <h5 class="card-title"><?= $page['title'] ?></h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php 
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
// Initialize Feather icons
feather.replace();

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Feather Icons if available
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php require 'includes/footer.php'; ?>
