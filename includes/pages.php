<?php

// 2	User
// 3	Project manager
// 4	Elevated
// 5	Administrator

$pages = [
    'index.php' => [
        'title' => 'Promato',
        'auth_level' => 2,
        'menu' => null,
        'inhead' => false
    ],
    'login.php' => [
        'title' => 'Login',
        'auth_level' => 0,
        'menu' => null,
        'inhead' => false
    ],
    'project_edit.php' => [
        'title' => 'Edit Project',
        'auth_level' => 4,
        'menu' => null,
        'inhead' => false
    ],
    'project_details.php' => [
        'title' => 'Project Details',
        'auth_level' => 1,
        'menu' => null,
        'inhead' => false
    ],
    'personel_edit.php' => [
        'title' => 'Edit Personnel',
        'auth_level' => 5,
        'menu' => null,
        'inhead' => false
    ],
    'update_hours_plan.php' => [
        'title' => 'Update Hours Plan',
        'auth_level' => 3,
        'menu' => null,
        'inhead' => false
    ],
    'update_team_plan.php' => [
        'title' => 'Update Team Plan',
        'auth_level' => 3,
        'menu' => null,
        'inhead' => false
    ],
    'update_priority.php' => [
        'title' => 'Update Priority',
        'auth_level' => 3,
        'menu' => null,
        'inhead' => false
    ],
    'upload_handler.php' => [
        'title' => 'Upload Handler',
        'auth_level' => 4,
        'menu' => null,
        'inhead' => false
    ],
    'update_status.php' => [
        'title' => 'Status updater',
        'auth_level' => 1,
        'menu' => null,
        'inhead' => false
    ],
    'update_user_order.php' => [
        'title' => 'User order updater',
        'auth_level' => 5,
        'menu' => null,
        'inhead' => false
    ],
    'update_task_status.php' => [
        'title' => 'User task status',
        'auth_level' => 3,
        'menu' => null,
        'inhead' => false
    ],
    'test.php' => [
        'title' => 'test',
        'auth_level' => 0,
        'menu' => null,
        'inhead' => false
    ],
    'tmp.php' => [
        'title' => 'tmp',
        'auth_level' => 5,
        'menu' => null,
        'inhead' => false
    ],
    'tmp_handler.php' => [
        'title' => 'Upload Handler',
        'auth_level' => 5,
        'menu' => null,
        'inhead' => false
    ],
    'export_generate.php' => [
        'title' => 'Export generator',
        'auth_level' => 5,
        'menu' => null,
        'inhead' => false
    ],
    'kroketto.php' => [
        'title' => 'Kroketto',
        'auth_level' => 2,
        'menu' => 'file',
        'inhead' => false
    ],
    'kroketto_admin.php' => [
        'title' => 'Kroketto Admin',
        'auth_level' => 5,
        'menu' => null,
        'inhead' => false
    ],


    // MAIN Menu
    'dashboard.php' => [
        'title' => 'Dashboard',
        'auth_level' => 2,
        'menu' => 'main',
        'inhead' => true
    ],
    'projects.php' => [
        'title' => 'Projects',
        'auth_level' => 2,
        'menu' => 'main',
        'inhead' => true
    ],
    'kanban.php' => [
        'title' => 'Kanban',
        'auth_level' => 2,
        'menu' => 'main',
        'inhead' => true
    ],
    'project_dashboard.php' => [
        'title' => 'Project dashboard',
        'auth_level' => 4,
        'menu' => 'main',
        'inhead' => true
    ],

    // ADMIN Menu
    'project_add.php' => [
        'title' => 'Add Project',
        'auth_level' => 4,
        'menu' => 'admin',
        'inhead' => false
    ],
    'projects_edit.php' => [
        'title' => 'Edit Projects',
        'auth_level' => 4,
        'menu' => 'admin',
        'inhead' => true
    ],
    'personel.php' => [
        'title' => 'Users',
        'auth_level' => 5,
        'menu' => 'admin',
        'inhead' => false
    ],
    'wbso.php' => [
        'title' => 'Wbso labels',
        'auth_level' => 4,
        'menu' => 'admin',
        'inhead' => false
    ],

    // PLAN Menu
    'capacity_planning.php' => [
        'title' => 'Capacity Planning',
        'auth_level' => 2,
        'menu' => 'plan',
        'inhead' => true
    ],
    'team_planning.php' => [
        'title' => 'Team Planning',
        'auth_level' => 2,
        'menu' => 'plan',
        'inhead' => true
    ],
    'capacity.php' => [
        'title' => 'Capacity Overview',
        'auth_level' => 3,
        'menu' => 'plan',
        'inhead' => true
    ],
    'priority_planning.php' => [
        'title' => 'Priority Planning',
        'auth_level' => 3,
        'menu' => 'plan',
        'inhead' => true
    ],
    'personel_order.php' => [
        'title' => 'Personel order',
        'auth_level' => 5,
        'menu' => 'plan',
        'inhead' => false
    ],

    // FILE Menu
    'upload.php' => [
        'title' => 'Upload Hours',
        'auth_level' => 4,
        'menu' => 'file',
        'inhead' => true
    ],
    'export.php' => [
        'title' => 'Export to Yoobi',
        'auth_level' => 5,
        'menu' => 'file',
        'inhead' => false
    ],
    'backup.php' => [
        'title' => 'Data backup',
        'auth_level' => 5,
        'menu' => 'file',
        'inhead' => false
    ],
];
