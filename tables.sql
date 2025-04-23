CREATE TABLE Personel (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL UNIQUE,
    Email VARCHAR(255),
    Shortname VARCHAR(50),
    Startdate DATE,
    Enddate DATE,
    WBSO BOOLEAN DEFAULT FALSE,
    Fultime INT DEFAULT 100, -- full-time percentage
    Type INT,
    plan BOOLEAN DEFAULT FALSE,
    Ord INT DEFAULT 0,
    FOREIGN KEY (Type) REFERENCES Types(Id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

CREATE TABLE Types (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL
);

CREATE TABLE Projects (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Manager INT,
    Status INT DEFAULT 0,
    FOREIGN KEY (Manager) REFERENCES Personel(Id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);
CREATE TABLE Activities (
    Project INT NOT NULL,
    `Key` INT NOT NULL, -- task number or sub-identifier
    Name VARCHAR(255) NOT NULL,
    BudgetHours INT DEFAULT 0,
    `Show` BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (Project, `Key`),
    FOREIGN KEY (Project) REFERENCES Projects(Id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
CREATE TABLE Hours (
    Project INT NOT NULL,
    Activity INT NOT NULL,
    Person INT NOT NULL,
    Hours INT DEFAULT 0, -- stored as *100 for precision
    Plan INT DEFAULT 0,  -- also stored as *100
    PRIMARY KEY (Project, Activity, Person),
    FOREIGN KEY (Person) REFERENCES Personel(Id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (Project, Activity) REFERENCES Activities(Project, `Key`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


