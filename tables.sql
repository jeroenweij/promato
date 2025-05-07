SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
  
CREATE TABLE `Activities` (
  `Id` smallint(6) NOT NULL,
  `Key` smallint(6) NOT NULL,
  `Project` smallint(6) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `WBSO` varchar(20) DEFAULT NULL,
  `Visible` tinyint(1) DEFAULT 1,
  `IsTask` tinyint(1) NOT NULL DEFAULT 1,
  `Export` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Departments` (
  `Id` tinyint(4) NOT NULL,
  `Name` varchar(16) DEFAULT NULL,
  `Ord` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Departments` (`Id`, `Name`, `Ord`) VALUES
(1, 'Software', 1),
(2, 'Hardware', 2),
(3, 'Management', 3),
(4, 'Other', 4);

CREATE TABLE `Hours` (
  `Project` smallint(6) DEFAULT NULL,
  `Activity` smallint(6) DEFAULT NULL,
  `Person` smallint(6) DEFAULT NULL,
  `Hours` int(11) DEFAULT NULL,
  `Plan` int(11) DEFAULT 0,
  `Prio` int(11) NOT NULL DEFAULT 0,
  `StatusId` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `HourStatus` (
  `Id` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `HourStatus` (`Id`, `Name`) VALUES
(1, 'Backlog'),
(4, 'Done'),
(3, 'In Progress'),
(2, 'Todo');

CREATE TABLE `Personel` (
  `Id` smallint(6) NOT NULL,
  `Email` varchar(64) NOT NULL,
  `Name` varchar(32) DEFAULT NULL,
  `Shortname` varchar(32) NOT NULL,
  `StartDate` date NOT NULL DEFAULT '2023-01-01',
  `EndDate` date DEFAULT NULL,
  `WBSO` tinyint(1) DEFAULT 0,
  `Fultime` tinyint(4) DEFAULT 100,
  `Type` tinyint(4) DEFAULT 1,
  `Ord` smallint(6) DEFAULT 100,
  `plan` tinyint(1) NOT NULL DEFAULT 1,
  `Department` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Projects` (
  `Id` smallint(6) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Status` tinyint(4) DEFAULT 0,
  `Manager` smallint(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Status` (
  `Id` tinyint(4) NOT NULL,
  `Status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Status` (`Id`, `Status`) VALUES
(1, 'Lead'),
(2, 'Quote'),
(3, 'Active'),
(4, 'Closed');

CREATE TABLE `Types` (
  `Id` tinyint(4) NOT NULL,
  `Name` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Types` (`Id`, `Name`) VALUES
(1, 'Inactive'),
(2, 'User'),
(3, 'Project Manager'),
(4, 'Elevated'),
(5, 'Administrator');

ALTER TABLE `Activities`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Project` (`Project`),
  ADD KEY `Key` (`Key`);

ALTER TABLE `Departments`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `Hours`
  ADD UNIQUE KEY `hoursIndex` (`Project`,`Activity`,`Person`),
  ADD KEY `Activity` (`Activity`),
  ADD KEY `Person` (`Person`),
  ADD KEY `fk_hours_status` (`StatusId`);

ALTER TABLE `HourStatus`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Name` (`Name`);

ALTER TABLE `Personel`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Personel_UNIQUE` (`Email`),
  ADD KEY `Type` (`Type`),
  ADD KEY `fk_department` (`Department`);

ALTER TABLE `Projects`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Status` (`Status`),
  ADD KEY `Manager` (`Manager`);

ALTER TABLE `Status`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `Types`
  ADD PRIMARY KEY (`Id`);


ALTER TABLE `Activities`
  MODIFY `Id` smallint(6) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Departments`
  MODIFY `Id` tinyint(4) NOT NULL AUTO_INCREMENT;

ALTER TABLE `HourStatus`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Personel`
  MODIFY `Id` smallint(6) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Projects`
  MODIFY `Id` smallint(6) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Status`
  MODIFY `Id` tinyint(4) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Types`
  MODIFY `Id` tinyint(4) NOT NULL AUTO_INCREMENT;


ALTER TABLE `Activities`
  ADD CONSTRAINT `Activities_ibfk_1` FOREIGN KEY (`Project`) REFERENCES `Projects` (`Id`) ON UPDATE NO ACTION;

ALTER TABLE `Hours`
  ADD CONSTRAINT `fk_hours_status` FOREIGN KEY (`StatusId`) REFERENCES `HourStatus` (`Id`);

ALTER TABLE `Personel`
  ADD CONSTRAINT `Personel_ibfk_1` FOREIGN KEY (`Type`) REFERENCES `Types` (`Id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_department` FOREIGN KEY (`Department`) REFERENCES `Departments` (`Id`);

ALTER TABLE `Projects`
  ADD CONSTRAINT `Projects_ibfk_1` FOREIGN KEY (`Status`) REFERENCES `Status` (`Id`) ON UPDATE NO ACTION,
  ADD CONSTRAINT `Projects_ibfk_2` FOREIGN KEY (`Manager`) REFERENCES `Personel` (`Id`) ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

CREATE TABLE `Budgets` (
  `Id` smallint(6) NOT NULL,
  `Activity` smallint(6) NOT NULL,
  `Budget` int(11) NOT NULL,
  `OopSpend` int(11) NOT NULL,
  `Hours` smallint(6) NOT NULL,
  `Rate` smallint(6) NOT NULL,
  `Year` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Budgets`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `Budgets`
  MODIFY `Id` smallint(6) NOT NULL AUTO_INCREMENT;
  
ALTER TABLE `Budgets`
  ADD CONSTRAINT `Budgets_ibfk_1` FOREIGN KEY (`Activity`) REFERENCES `Activities` (`Id`) ON UPDATE NO ACTION;

INSERT INTO Budgets (Activity, Budget, OopSpend, Hours, Rate, Year) SELECT Id AS Activity, 0 AS Budget, 0 AS OopSpend, BudgetHours AS Hours, 0 AS Rate, 2025 AS Year FROM Activities WHERE BudgetHours IS NOT NULL AND BudgetHours > 0;
ALTER TABLE `Activities` DROP `BudgetHours`;
