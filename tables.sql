CREATE TABLE `Activities` (
  `Id` smallint NOT NULL,
  `Key` smallint NOT NULL,
  `Project` smallint NOT NULL,
  `Name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `Visible` tinyint(1) DEFAULT '1',
  `IsTask` tinyint(1) NOT NULL DEFAULT '1',
  `Export` tinyint(1) DEFAULT '1',
  `IsExported` tinyint(1) NOT NULL DEFAULT '0',
  `Wbso` smallint DEFAULT NULL,
  `Active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Availability` (
  `Person` smallint DEFAULT NULL,
  `Hours` int DEFAULT NULL,
  `Year` smallint NOT NULL DEFAULT (year(curdate()))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Budgets` (
  `Id` smallint NOT NULL,
  `Activity` smallint NOT NULL,
  `Budget` int NOT NULL,
  `OopSpend` int NOT NULL,
  `Hours` smallint NOT NULL,
  `Rate` smallint NOT NULL,
  `Year` smallint NOT NULL DEFAULT (year(curdate()))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Hours` (
  `Project` smallint DEFAULT NULL,
  `Activity` smallint DEFAULT NULL,
  `Person` smallint DEFAULT NULL,
  `Hours` int DEFAULT NULL,
  `Plan` int DEFAULT '0',
  `Status` int DEFAULT '1',
  `Year` smallint NOT NULL DEFAULT (year(curdate()))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `HourStatus` (
  `Id` int NOT NULL,
  `Name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `HourStatus` (`Id`, `Name`) VALUES
(1, 'Backlog'),
(4, 'Done'),
(5, 'Hidden'),
(3, 'In Progress'),
(2, 'Todo');

CREATE TABLE `Menus` (
  `Id` tinyint NOT NULL,
  `Name` varchar(16) COLLATE utf8mb4_general_ci NOT NULL,
  `Icon` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Menus` (`Id`, `Name`, `Icon`) VALUES
(1, 'Main', 'menu'),
(2, 'Planning', 'calendar'),
(3, 'Kroketto', 'chef-hat'),
(4, 'Admin', 'shield'),
(5, 'File', 'file');

CREATE TABLE `PageAccess` (
  `UserId` smallint NOT NULL,
  `PageId` tinyint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Pages` (
  `Id` tinyint NOT NULL,
  `Name` varchar(32) COLLATE utf8mb4_general_ci NOT NULL,
  `Path` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `Auth` tinyint NOT NULL,
  `Menu` tinyint DEFAULT '0',
  `InHead` tinyint(1) DEFAULT '0',
  `Icon` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Pages` (`Id`, `Name`, `Path`, `Auth`, `Menu`, `InHead`, `Icon`) VALUES
(35, 'Promato', 'index.php', 2, NULL, 0, NULL),
(36, 'Login', 'login.php', 1, NULL, 0, NULL),
(37, 'Edit Project', 'project_edit.php', 4, NULL, 0, NULL),
(38, 'Project Details', 'project_details.php', 2, NULL, 0, NULL),
(39, 'Edit Personnel', 'personel_edit.php', 5, NULL, 0, NULL),
(40, 'Update Hours Plan', 'update_hours_plan.php', 3, NULL, 0, NULL),
(41, 'Update Team Plan', 'update_team_plan.php', 3, NULL, 0, NULL),
(42, 'Update Priority', 'update_priority.php', 3, NULL, 0, NULL),
(43, 'Upload Handler', 'upload_handler.php', 4, NULL, 0, NULL),
(44, 'Status updater', 'update_status.php', 2, NULL, 0, NULL),
(45, 'User order updater', 'update_user_order.php', 5, NULL, 0, NULL),
(46, 'Team task status', 'update_team_project_status.php', 3, NULL, 0, NULL),
(47, 'Upload Handler', 'tmp_handler.php', 5, NULL, 0, NULL),
(48, 'Export generator', 'export_generate.php', 5, NULL, 0, NULL),
(49, 'Kroketto', 'kroketto.php', 2, 3, 0, 'croissant'),
(50, 'Kroketto Admin', 'kroketto_admin.php', 5, 3, 0, 'utensils'),
(51, 'Dashboard', 'dashboard.php', 2, 1, 0, 'home'),
(52, 'Projects', 'projects.php', 2, 1, 1, 'briefcase'),
(53, 'Kanban', 'kanban.php', 2, 1, 1, 'trello'),
(54, 'Project dashboard', 'project_dashboard.php', 4, 1, 1, NULL),
(55, 'Add Project', 'project_add.php', 4, 4, 0, 'file-plus'),
(56, 'Edit Projects', 'projects_edit.php', 4, 4, 1, 'edit'),
(57, 'Users', 'personel.php', 5, 4, 0, 'users'),
(58, 'Wbso labels', 'wbso.php', 4, 4, 0, 'tag'),
(59, 'Capacity Planning', 'capacity_planning.php', 2, 2, 1, 'bar-chart-2'),
(60, 'Team Planning', 'team_planning.php', 2, 2, 1, 'bar-chart'),
(61, 'Capacity Overview', 'capacity.php', 3, 2, 1, 'cpu'),
(62, 'Priority Planning', 'priority_planning.php', 3, 2, 1, 'list'),
(63, 'Personel order', 'personel_order.php', 5, 2, 0, 'user-check'),
(64, 'Upload Hours', 'upload.php', 4, 5, 1, 'upload'),
(65, 'Export to Yoobi', 'export.php', 5, 5, 0, 'download'),
(66, 'Data backup', 'backup.php', 5, 5, 0, 'download-cloud'),
(67, 'Financial dashboard', 'finance.php', 5, 1, 0, 'dollar-sign'),
(68, 'Project finance', 'project_finance.php', 5, NULL, 0, NULL),
(69, 'Update page access', 'update_page_access.php', 6, NULL, 0, NULL),
(70, 'Page access', 'access_admin.php', 6, 4, 0, 'shield-user'),
(71, 'Team Admin', 'team_admin.php', 6, 4, 0, 'group'),
(72, 'Report', 'report.php', 5, 1, 0, 'file-text'),
(73, 'Database Admin', 'db_admin.php', 7, 4, 0, 'database'),
(74, 'Change Personnel Team', 'personel_change_team.php', 5, NULL, 0, NULL);

CREATE TABLE `Personel` (
  `Id` smallint NOT NULL,
  `Email` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `Name` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Shortname` varchar(32) COLLATE utf8mb4_general_ci NOT NULL,
  `StartDate` date NOT NULL DEFAULT '2023-01-01',
  `EndDate` date DEFAULT NULL,
  `WBSO` tinyint(1) DEFAULT '0',
  `Fultime` tinyint DEFAULT '100',
  `Type` tinyint DEFAULT '1',
  `Ord` smallint DEFAULT '100',
  `plan` tinyint(1) NOT NULL DEFAULT '1',
  `Team` tinyint NOT NULL DEFAULT '1',
  `LastLogin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Projects` (
  `Id` smallint NOT NULL,
  `Name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Status` tinyint DEFAULT '0',
  `Manager` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `snack_options` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `available_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `snack_orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `snack_id` int NOT NULL,
  `order_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Status` (
  `Id` tinyint NOT NULL,
  `Status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Status` (`Id`, `Status`) VALUES
(1, 'Lead'),
(2, 'Quote'),
(3, 'Active'),
(4, 'Closed');

CREATE TABLE `TeamHours` (
  `Project` smallint DEFAULT NULL,
  `Activity` smallint DEFAULT NULL,
  `Team` tinyint DEFAULT NULL,
  `Hours` int DEFAULT NULL,
  `Plan` int DEFAULT '0',
  `Prio` int NOT NULL DEFAULT '250',
  `Year` smallint NOT NULL DEFAULT (year(curdate())),
  `Status` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Teams` (
  `Id` tinyint NOT NULL,
  `Name` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Planable` tinyint(1) NOT NULL DEFAULT '1',
  `Ord` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Types` (
  `Id` tinyint NOT NULL,
  `Name` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Types` (`Id`, `Name`) VALUES
(1, 'Inactive'),
(2, 'User'),
(3, 'Project Manager'),
(4, 'Elevated'),
(5, 'Administrator'),
(6, 'Total Admin'),
(7, 'God');

CREATE TABLE `Wbso` (
  `Id` smallint NOT NULL,
  `Name` varchar(16) COLLATE utf8mb4_general_ci NOT NULL,
  `Description` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Hours` smallint DEFAULT NULL,
  `Date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `Activities`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Project` (`Project`),
  ADD KEY `Key` (`Key`),
  ADD KEY `fk_activities_wbso` (`Wbso`);

ALTER TABLE `Availability`
  ADD UNIQUE KEY `personYearIndex` (`Person`,`Year`),
  ADD KEY `Person` (`Person`);

ALTER TABLE `Budgets`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Budgets_ibfk_1` (`Activity`);

ALTER TABLE `Hours`
  ADD UNIQUE KEY `hoursIndex` (`Project`,`Activity`,`Person`,`Year`),
  ADD KEY `Activity` (`Activity`),
  ADD KEY `Person` (`Person`),
  ADD KEY `fk_hours_status` (`Status`);

ALTER TABLE `HourStatus`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Name` (`Name`);

ALTER TABLE `Menus`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `PageAccess`
  ADD PRIMARY KEY (`UserId`,`PageId`),
  ADD KEY `PageId` (`PageId`);

ALTER TABLE `Pages`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Path` (`Path`),
  ADD KEY `fk_pages_types` (`Auth`),
  ADD KEY `fk_pages_menus` (`Menu`) USING BTREE;

ALTER TABLE `Personel`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Personel_UNIQUE` (`Email`),
  ADD KEY `Type` (`Type`),
  ADD KEY `fk_department` (`Team`);

ALTER TABLE `Projects`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Status` (`Status`),
  ADD KEY `Manager` (`Manager`);

ALTER TABLE `snack_options`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `snack_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_week` (`user_id`,`order_date`),
  ADD KEY `snack_id` (`snack_id`),
  ADD KEY `idx_order_date` (`order_date`),
  ADD KEY `idx_user_id` (`user_id`);

ALTER TABLE `Status`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `TeamHours`
  ADD UNIQUE KEY `teamHoursIndex` (`Project`,`Activity`,`Team`,`Year`),
  ADD KEY `k_Team` (`Team`);

ALTER TABLE `Teams`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `Types`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `Wbso`
  ADD PRIMARY KEY (`Id`);


ALTER TABLE `Activities`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Budgets`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `HourStatus`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Menus`
  MODIFY `Id` tinyint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Pages`
  MODIFY `Id` tinyint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Personel`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Projects`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `snack_options`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `snack_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Status`
  MODIFY `Id` tinyint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Teams`
  MODIFY `Id` tinyint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Types`
  MODIFY `Id` tinyint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Wbso`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;


ALTER TABLE `Activities`
  ADD CONSTRAINT `Activities_ibfk_1` FOREIGN KEY (`Project`) REFERENCES `Projects` (`Id`),
  ADD CONSTRAINT `fk_activities_wbso` FOREIGN KEY (`Wbso`) REFERENCES `Wbso` (`Id`);

ALTER TABLE `Availability`
  ADD CONSTRAINT `fk_avail_person` FOREIGN KEY (`Person`) REFERENCES `Personel` (`Id`);

ALTER TABLE `Budgets`
  ADD CONSTRAINT `Budgets_ibfk_1` FOREIGN KEY (`Activity`) REFERENCES `Activities` (`Id`);

ALTER TABLE `Hours`
  ADD CONSTRAINT `fk_hours_person` FOREIGN KEY (`Person`) REFERENCES `Personel` (`Id`),
  ADD CONSTRAINT `fk_hours_project` FOREIGN KEY (`Project`) REFERENCES `Projects` (`Id`),
  ADD CONSTRAINT `fk_hours_status` FOREIGN KEY (`Status`) REFERENCES `HourStatus` (`Id`);

ALTER TABLE `PageAccess`
  ADD CONSTRAINT `PageAccess_ibfk_1` FOREIGN KEY (`UserId`) REFERENCES `Personel` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `PageAccess_ibfk_2` FOREIGN KEY (`PageId`) REFERENCES `Pages` (`Id`) ON DELETE CASCADE;

ALTER TABLE `Pages`
  ADD CONSTRAINT `fk_pages_menus` FOREIGN KEY (`Menu`) REFERENCES `Menus` (`Id`),
  ADD CONSTRAINT `fk_pages_types` FOREIGN KEY (`Auth`) REFERENCES `Types` (`Id`);

ALTER TABLE `Personel`
  ADD CONSTRAINT `fk_department` FOREIGN KEY (`Team`) REFERENCES `Teams` (`Id`),
  ADD CONSTRAINT `fk_team` FOREIGN KEY (`Team`) REFERENCES `Teams` (`Id`),
  ADD CONSTRAINT `Personel_ibfk_1` FOREIGN KEY (`Type`) REFERENCES `Types` (`Id`);

ALTER TABLE `Projects`
  ADD CONSTRAINT `Projects_ibfk_1` FOREIGN KEY (`Status`) REFERENCES `Status` (`Id`),
  ADD CONSTRAINT `Projects_ibfk_2` FOREIGN KEY (`Manager`) REFERENCES `Personel` (`Id`);

ALTER TABLE `snack_orders`
  ADD CONSTRAINT `snack_orders_ibfk_1` FOREIGN KEY (`snack_id`) REFERENCES `snack_options` (`id`);

ALTER TABLE `TeamHours`
  ADD CONSTRAINT `fk_team_hours_status` FOREIGN KEY (`Project`) REFERENCES `Projects` (`Id`),
  ADD CONSTRAINT `fk_team_hours_team` FOREIGN KEY (`Team`) REFERENCES `Teams` (`Id`);
