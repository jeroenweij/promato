<?php
$pages = [
    'index.php' => [
        'title' => 'Dashboard',
        'auth_level' => 1,
        'menu' => null,
    ],
    'login.php' => [
        'title' => 'Login',
        'auth_level' => 0,
        'menu' => null,
    ],
    'projects.php' => [
        'title' => 'Projects Overview',
        'auth_level' => 1,
        'menu' => 'main',
    ],
    'project_add.php' => [
        'title' => 'Add Project',
        'auth_level' => 3,
        'menu' => 'admin',
    ],
    'project_edit.php' => [
        'title' => 'Edit Project',
        'auth_level' => 2,
        'menu' => null, // usually linked from project details
    ],
    'projects_edit.php' => [
        'title' => 'Edit Projects',
        'auth_level' => 2,
        'menu' => 'admin',
    ],
    'project_details.php' => [
        'title' => 'Project Details',
        'auth_level' => 1,
        'menu' => null, // detail page, not in main nav
    ],
    'personel.php' => [
        'title' => 'Personnel Overview',
        'auth_level' => 4,
        'menu' => 'admin',
    ],
    'personel_edit.php' => [
        'title' => 'Edit Personnel',
        'auth_level' => 4,
        'menu' => null,
    ],
    'capacity.php' => [
        'title' => 'Capacity Overview',
        'auth_level' => 3,
        'menu' => 'main',
    ],
    'capacity_planning.php' => [
        'title' => 'Capacity Planning',
        'auth_level' => 3,
        'menu' => 'main',
    ],
    'priority_planning.php' => [
        'title' => 'Priority Planning',
        'auth_level' => 3,
        'menu' => 'main',
    ],
    'update_hours_plan.php' => [
        'title' => 'Update Hours Plan',
        'auth_level' => 2,
        'menu' => null,
    ],
    'update_priority.php' => [
        'title' => 'Update Priority',
        'auth_level' => 3,
        'menu' => null,
    ],
    'upload.php' => [
        'title' => 'Upload File',
        'auth_level' => 3,
        'menu' => 'admin',
    ],
    'upload_handler.php' => [
        'title' => 'Upload Handler',
        'auth_level' => 3,
        'menu' => null,
    ],
    'update_status.php' => [
        'title' => 'Status updater',
        'auth_level' => 1,
        'menu' => null,
    ],
    'kanban.php' => [
        'title' => 'Kanban',
        'auth_level' => 1,
        'menu' => 'main',
    ],
    'logout.php' => [
        'title' => 'Logout',
        'auth_level' => 1,
        'menu' => 'main',
    ],
    'test.php' => [
        'title' => 'test',
        'auth_level' => 0,
        'menu' => null,
    ],
];
