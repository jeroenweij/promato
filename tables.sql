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
  `Wbso` smallint DEFAULT NULL
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

CREATE TABLE `Departments` (
  `Id` tinyint NOT NULL,
  `Name` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Ord` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Departments` (`Id`, `Name`, `Ord`) VALUES
(1, 'Software', 1),
(2, 'Hardware', 2),
(3, 'Management', 3),
(4, 'Other', 4);

CREATE TABLE `Hours` (
  `Project` smallint DEFAULT NULL,
  `Activity` smallint DEFAULT NULL,
  `Person` smallint DEFAULT NULL,
  `Hours` int DEFAULT NULL,
  `Plan` int DEFAULT '0',
  `Prio` int NOT NULL DEFAULT '0',
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
  `Department` tinyint NOT NULL DEFAULT '1',
  `LastLogin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Projects` (
  `Id` smallint NOT NULL,
  `Name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Status` tinyint DEFAULT '0',
  `Manager` smallint DEFAULT NULL
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

CREATE TABLE `Types` (
  `Id` tinyint NOT NULL,
  `Name` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Types` (`Id`, `Name`) VALUES
(1, 'Inactive'),
(2, 'User'),
(3, 'Project Manager'),
(4, 'Elevated'),
(5, 'Administrator');

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

ALTER TABLE `Budgets`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Budgets_ibfk_1` (`Activity`);

ALTER TABLE `Departments`
  ADD PRIMARY KEY (`Id`);

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
  ADD KEY `fk_department` (`Department`);

ALTER TABLE `Projects`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Status` (`Status`),
  ADD KEY `Manager` (`Manager`);

ALTER TABLE `Status`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `Types`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `Wbso`
  ADD PRIMARY KEY (`Id`);


ALTER TABLE `Activities`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Budgets`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Departments`
  MODIFY `Id` tinyint NOT NULL AUTO_INCREMENT;

ALTER TABLE `HourStatus`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Personel`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Projects`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Status`
  MODIFY `Id` tinyint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Types`
  MODIFY `Id` tinyint NOT NULL AUTO_INCREMENT;

ALTER TABLE `Wbso`
  MODIFY `Id` smallint NOT NULL AUTO_INCREMENT;


ALTER TABLE `Activities`
  ADD CONSTRAINT `Activities_ibfk_1` FOREIGN KEY (`Project`) REFERENCES `Projects` (`Id`),
  ADD CONSTRAINT `fk_activities_wbso` FOREIGN KEY (`Wbso`) REFERENCES `Wbso` (`Id`);

ALTER TABLE `Budgets`
  ADD CONSTRAINT `Budgets_ibfk_1` FOREIGN KEY (`Activity`) REFERENCES `Activities` (`Id`);

ALTER TABLE `Hours`
  ADD CONSTRAINT `fk_hours_status` FOREIGN KEY (`Status`) REFERENCES `HourStatus` (`Id`);

ALTER TABLE `Personel`
  ADD CONSTRAINT `fk_department` FOREIGN KEY (`Department`) REFERENCES `Departments` (`Id`),
  ADD CONSTRAINT `Personel_ibfk_1` FOREIGN KEY (`Type`) REFERENCES `Types` (`Id`);

ALTER TABLE `Projects`
  ADD CONSTRAINT `Projects_ibfk_1` FOREIGN KEY (`Status`) REFERENCES `Status` (`Id`),
  ADD CONSTRAINT `Projects_ibfk_2` FOREIGN KEY (`Manager`) REFERENCES `Personel` (`Id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
