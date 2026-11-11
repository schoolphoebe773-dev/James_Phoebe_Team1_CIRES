-- Create a separate demo database.
CREATE DATABASE IF NOT EXISTS cires_m4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Use the demo database for the tables below.
USE cires_m4;

-- Each sector can contain several cells.
CREATE TABLE IF NOT EXISTS sector (
    sector_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Sector identifier.
    sector_name VARCHAR(80) NOT NULL UNIQUE -- Display name.
) ENGINE=InnoDB;

-- Each cell belongs to one sector.
CREATE TABLE IF NOT EXISTS cell (
    cell_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Cell identifier.
    sector_id INT UNSIGNED NOT NULL, -- Parent sector.
    cell_name VARCHAR(80) NOT NULL, -- Display name.
    UNIQUE (sector_id, cell_name), -- A name is unique within its sector.
    FOREIGN KEY (sector_id) REFERENCES sector(sector_id) -- Require an existing sector.
) ENGINE=InnoDB;

-- Store each issue category once.
CREATE TABLE IF NOT EXISTS category (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Category identifier.
    category_name VARCHAR(80) NOT NULL UNIQUE -- Form label.
) ENGINE=InnoDB;

-- Store local demo accounts and staff assignments.
CREATE TABLE IF NOT EXISTS user_account (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Account identifier.
    full_name VARCHAR(100) NOT NULL, -- Display name.
    login_name VARCHAR(80) NOT NULL UNIQUE, -- Login identifier.
    password_hash VARCHAR(255) NOT NULL, -- PHP password hash.
    role ENUM('citizen','cell_executive','sector_executive') NOT NULL, -- Access role.
    cell_id INT UNSIGNED NULL, -- Cell Executive assignment.
    sector_id INT UNSIGNED NULL, -- Sector Executive assignment.
    FOREIGN KEY (cell_id) REFERENCES cell(cell_id), -- Validate the cell link.
    FOREIGN KEY (sector_id) REFERENCES sector(sector_id) -- Validate the sector link.
) ENGINE=InnoDB;

-- Store each citizen report and its routing information.
CREATE TABLE IF NOT EXISTS issue (
    issue_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Internal identifier.
    reference_code VARCHAR(24) NOT NULL UNIQUE, -- Citizen-facing reference.
    citizen_id INT UNSIGNED NOT NULL, -- Owner of the report.
    cell_id INT UNSIGNED NOT NULL, -- Responsible cell.
    category_id INT UNSIGNED NOT NULL, -- Selected issue type.
    location_details VARCHAR(180) NOT NULL, -- Location entered by the citizen.
    description VARCHAR(1500) NOT NULL, -- Description of the issue.
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Creation time.
    follow_up_due_at DATETIME NOT NULL, -- Follow-up deadline from the M3 design.
    FOREIGN KEY (citizen_id) REFERENCES user_account(user_id), -- Validate the owner.
    FOREIGN KEY (cell_id) REFERENCES cell(cell_id), -- Validate the cell.
    FOREIGN KEY (category_id) REFERENCES category(category_id) -- Validate the category.
) ENGINE=InnoDB;

-- Keep the full history and derive the current state from the latest row.
CREATE TABLE IF NOT EXISTS status_update (
    update_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Update identifier.
    issue_id INT UNSIGNED NOT NULL, -- Report being updated.
    updated_by INT UNSIGNED NOT NULL, -- Actor who recorded the update.
    status ENUM('Received','In Progress','Resolved') NOT NULL, -- Workflow step.
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Time of the change.
    completion_note VARCHAR(800) NULL, -- Citizen-visible reply at resolution.
    INDEX (issue_id, update_id), -- Find a report's updates efficiently.
    FOREIGN KEY (issue_id) REFERENCES issue(issue_id), -- Validate the report.
    FOREIGN KEY (updated_by) REFERENCES user_account(user_id) -- Validate the actor.
) ENGINE=InnoDB;

-- Preserve the approved escalation relationship in the schema.
CREATE TABLE IF NOT EXISTS escalation (
    issue_id INT UNSIGNED PRIMARY KEY, -- At most one escalation per issue.
    sector_executive_id INT UNSIGNED NOT NULL, -- Executive receiving the escalation.
    escalated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Handoff time.
    FOREIGN KEY (issue_id) REFERENCES issue(issue_id), -- Validate the report.
    FOREIGN KEY (sector_executive_id) REFERENCES user_account(user_id) -- Validate the executive.
) ENGINE=InnoDB;

-- Seed fictional areas and selectable categories. Reimporting keeps existing reports.
INSERT INTO sector (sector_id, sector_name) VALUES (1, 'Demo Sector') ON DUPLICATE KEY UPDATE sector_id = sector_id;
INSERT INTO cell (cell_id, sector_id, cell_name) VALUES (1, 1, 'Demo Cell') ON DUPLICATE KEY UPDATE cell_id = cell_id;
INSERT INTO category (category_name) VALUES ('Drainage'), ('Roads'), ('Waste collection'), ('Other') ON DUPLICATE KEY UPDATE category_id = category_id;
-- Seed two local demo logins using a bcrypt hash of the password in README.md.
INSERT INTO user_account (full_name, login_name, password_hash, role, cell_id) VALUES
    ('Demo Citizen', 'citizen', '$2y$10$FfXk1H.q0I43LbMZxVFJK.zAP8XSNziPeAi2XHfaRtJfuJRbjX5tS', 'citizen', NULL),
    ('Demo Cell Executive', 'cell', '$2y$10$FfXk1H.q0I43LbMZxVFJK.zAP8XSNziPeAi2XHfaRtJfuJRbjX5tS', 'cell_executive', 1)
ON DUPLICATE KEY UPDATE user_id = user_id;
