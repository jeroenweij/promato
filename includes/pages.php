<?php

// 2	User
// 3	Project manager
// 4	Elevated
// 5	Administrator

$pages = [
    'index.php' => [
        'title' => 'Dashboard',
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
    'test.php' => [
        'title' => 'test',
        'auth_level' => 0,
        'menu' => null,
        'inhead' => false
    ],
    'tmp.php' => [
        'title' => 'tmp',
        'auth_level' => 0,
        'menu' => null,
        'inhead' => false
    ],
    'tmp_handler.php' => [
        'title' => 'Upload Handler',
        'auth_level' => 3,
        'menu' => null,
        'inhead' => false
    ],


    // MAIN Menu
    'projects.php' => [
        'title' => 'Projects',
        'auth_level' => 1,
        'menu' => 'main',
        'inhead' => true
    ],
    'kanban.php' => [
        'title' => 'Kanban',
        'auth_level' => 1,
        'menu' => 'main',
        'inhead' => true
    ],
    'capacity_planning.php' => [
        'title' => 'Capacity Planning',
        'auth_level' => 1,
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
        'title' => 'Personnel',
        'auth_level' => 5,
        'menu' => 'admin',
        'inhead' => false
    ],
    'personel_order.php' => [
        'title' => 'Personel order',
        'auth_level' => 5,
        'menu' => 'admin',
        'inhead' => false
    ],
    'upload.php' => [
        'title' => 'Upload File',
        'auth_level' => 4,
        'menu' => 'admin',
        'inhead' => true
    ],


    // PLAN Menu
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
];
