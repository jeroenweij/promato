/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


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
  `Wbso` smallint DEFAULT NULL,
  `Active` tinyint(1) NOT NULL DEFAULT '1'
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
  `Prio` int NOT NULL DEFAULT '250',
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
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `available_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `snack_orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `snack_id` int NOT NULL,
  `order_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Year` smallint NOT NULL DEFAULT (year(curdate()))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Teams` (
  `Id` tinyint NOT NULL,
  `Name` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Ord` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Teams` (`Id`, `Name`, `Ord`) VALUES
(1, 'Software 1', 1),
(2, 'Hardware', 3),
(3, 'Management + Sup', 4),
(4, 'Production', 5),
(5, 'Software 2', 2);

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
CREATE TABLE `weekly_snack_summary` (
`week_year` int
,`week_start` date
,`snack_name` varchar(100)
,`order_count` bigint
);


ALTER TABLE `Activities`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Project` (`Project`),
  ADD KEY `Key` (`Key`),
  ADD KEY `fk_activities_wbso` (`Wbso`);

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
DROP TABLE IF EXISTS `weekly_snack_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`promb3_site`@`%` SQL SECURITY DEFINER VIEW `weekly_snack_summary`  AS SELECT yearweek(`so`.`order_date`,1) AS `week_year`, cast((`so`.`order_date` - interval weekday(`so`.`order_date`) day) as date) AS `week_start`, `snack`.`name` AS `snack_name`, count(0) AS `order_count` FROM (`snack_orders` `so` join `snack_options` `snack` on((`so`.`snack_id` = `snack`.`id`))) GROUP BY yearweek(`so`.`order_date`,1), `snack`.`id`, `snack`.`name` ORDER BY `week_year` DESC, `snack`.`name` ASC ;


ALTER TABLE `Activities`
  ADD CONSTRAINT `Activities_ibfk_1` FOREIGN KEY (`Project`) REFERENCES `Projects` (`Id`),
  ADD CONSTRAINT `fk_activities_wbso` FOREIGN KEY (`Wbso`) REFERENCES `Wbso` (`Id`);

ALTER TABLE `Budgets`
  ADD CONSTRAINT `Budgets_ibfk_1` FOREIGN KEY (`Activity`) REFERENCES `Activities` (`Id`);

ALTER TABLE `Hours`
  ADD CONSTRAINT `fk_hours_status` FOREIGN KEY (`Status`) REFERENCES `HourStatus` (`Id`);

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

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;