-- =====================================================
-- WBSO System Upgrade
-- Adds start/end dates to WBSO items and yearly budgets
-- =====================================================

-- Step 1: Rename existing Date column to StartDate and add EndDate
ALTER TABLE `Wbso`
  CHANGE COLUMN `Date` `StartDate` DATE NOT NULL,
  ADD COLUMN `EndDate` DATE NULL AFTER `StartDate`;

-- Step 2: Set EndDate to one year after StartDate for existing records
UPDATE `Wbso`
SET `EndDate` = DATE_ADD(`StartDate`, INTERVAL 1 YEAR);

-- Step 3: Create WbsoBudget table for yearly WBSO hour budgets
CREATE TABLE `WbsoBudget` (
  `Id` int NOT NULL AUTO_INCREMENT,
  `WbsoId` smallint NOT NULL,
  `Year` smallint NOT NULL,
  `Hours` int DEFAULT NULL COMMENT 'Budget hours for this WBSO in this year',
  PRIMARY KEY (`Id`),
  UNIQUE KEY `unique_wbso_year` (`WbsoId`, `Year`),
  KEY `fk_wbsobudget_wbso` (`WbsoId`),
  CONSTRAINT `fk_wbsobudget_wbso` FOREIGN KEY (`WbsoId`) REFERENCES `Wbso` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 4: Migrate existing Hours from Wbso table to WbsoBudget
-- This creates budget entries for each WBSO item for years it's active
INSERT INTO `WbsoBudget` (`WbsoId`, `Year`, `Hours`)
SELECT
    w.Id,
    2025,
    w.Hours
FROM `Wbso` w;

-- Step 6: Optional - Remove Hours column from Wbso table
-- (Keeping it for now in case you want to keep legacy data)
-- Uncomment the line below if you want to remove it:
-- ALTER TABLE `Wbso` DROP COLUMN `Hours`;

-- Step 7: Add wbso_overview.php page to Pages table
INSERT INTO `Pages` (`Id`, `Name`, `Path`, `Auth`, `Menu`, `InHead`, `Icon`) VALUES
(78, 'WBSO Overview', 'wbso_overview.php', 4, 2, 1, 'activity');

-- =====================================================
-- Verification queries (run these to check the results)
-- =====================================================
-- SELECT * FROM Wbso;
-- SELECT * FROM WbsoBudget ORDER BY WbsoId, Year;
