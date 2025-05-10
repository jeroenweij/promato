/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `Activities` (
  `Id` smallint NOT NULL,
  `Key` smallint NOT NULL,
  `Project` smallint NOT NULL,
  `Name` varchar(50) NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `WBSO` varchar(20) DEFAULT NULL,
  `Visible` tinyint(1) DEFAULT '1',
  `IsTask` tinyint(1) NOT NULL DEFAULT '1',
  `Export` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Budgets` (
  `Id` smallint NOT NULL,
  `Activity` smallint NOT NULL,
  `Budget` int NOT NULL,
  `OopSpend` int NOT NULL,
  `Hours` smallint NOT NULL,
  `Rate` smallint NOT NULL,
  `Year` smallint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Departments` (
  `Id` tinyint NOT NULL,
  `Name` varchar(16) DEFAULT NULL,
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
  `Status` int DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `HourStatus` (
  `Id` int NOT NULL,
  `Name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `HourStatus` (`Id`, `Name`) VALUES
(1, 'Backlog'),
(4, 'Done'),
(3, 'In Progress'),
(2, 'Todo');

CREATE TABLE `Personel` (
  `Id` smallint NOT NULL,
  `Email` varchar(64) NOT NULL,
  `Name` varchar(32) DEFAULT NULL,
  `Shortname` varchar(32) NOT NULL,
  `StartDate` date NOT NULL DEFAULT '2025-01-01',
  `EndDate` date DEFAULT NULL,
  `WBSO` tinyint(1) DEFAULT '0',
  `Fultime` tinyint DEFAULT '100',
  `Type` tinyint DEFAULT '1',
  `Ord` smallint DEFAULT '100',
  `plan` tinyint(1) NOT NULL DEFAULT '1',
  `Department` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Projects` (
  `Id` smallint NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Status` tinyint DEFAULT '0',
  `Manager` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Status` (
  `Id` tinyint NOT NULL,
  `Status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `Status` (`Id`, `Status`) VALUES
(1, 'Lead'),
(2, 'Quote'),
(3, 'Active'),
(4, 'Closed');

CREATE TABLE `Types` (
  `Id` tinyint NOT NULL,
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

ALTER TABLE `Budgets`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Budgets_ibfk_1` (`Activity`);

ALTER TABLE `Departments`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `Hours`
  ADD UNIQUE KEY `hoursIndex` (`Project`,`Activity`,`Person`),
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


ALTER TABLE `Activities`
  ADD CONSTRAINT `Activities_ibfk_1` FOREIGN KEY (`Project`) REFERENCES `Projects` (`Id`);

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

-- 1. Add new nullable Wbso field to Activities
ALTER TABLE `Activities` CHANGE `WBSO` `WBSOO` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE Activities ADD COLUMN Wbso SMALLINT DEFAULT NULL;

-- 2. Create Wbso table
CREATE TABLE Wbso (
    Id SMALLINT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(16) NOT NULL,
    Description VARCHAR(64) DEFAULT NULL,
    Hours SMALLINT NULL, 
    `Date` DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Insert all distinct non-null, non-empty WBSO values from Activities into Wbso
INSERT INTO Wbso (Name)
SELECT DISTINCT WBSOO
FROM Activities
WHERE WBSOO IS NOT NULL AND WBSOO <> '';

-- 4. Update Activities.Wbso to match new Wbso.Id where WBSO value is set
UPDATE Activities a
JOIN Wbso w ON a.WBSOO = w.Name
SET a.Wbso = w.Id
WHERE a.WBSOO IS NOT NULL AND a.WBSOO <> '';

-- 5. Drop the old string-based WBSO column
ALTER TABLE Activities DROP COLUMN WBSOO;

-- 6. Add optional foreign key constraint (allows NULLs)
ALTER TABLE Activities
ADD CONSTRAINT fk_activities_wbso FOREIGN KEY (Wbso) REFERENCES Wbso(Id);

