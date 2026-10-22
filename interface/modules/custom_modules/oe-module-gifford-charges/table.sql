-- Gifford charge import tables, created when the module is installed (Module Manager).
-- Every statement is guarded so installing again changes nothing.

-- One row per charge file seen in the drop directory. A file is recognised again by
-- the SHA-256 of its bytes, so a re-sent identical file is not staged twice.
CREATE TABLE IF NOT EXISTS `gifford_charge_batch` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `file_name` VARCHAR(255) NOT NULL COMMENT 'name in the drop directory when it was read',
    `file_sha256` CHAR(64) NOT NULL,
    `received_at` DATETIME NOT NULL COMMENT 'when the import read the file',
    `status` VARCHAR(16) NOT NULL COMMENT 'staged: lines saved; failed: the file could not be read',
    `failure` TEXT NULL COMMENT 'why the file could not be read (no patient data)',
    `line_count` INT NOT NULL DEFAULT 0,
    `held_count` INT NOT NULL DEFAULT 0 COMMENT 'lines with problems, not to be posted',
    PRIMARY KEY (`id`),
    UNIQUE KEY `file_sha256` (`file_sha256`)
) ENGINE=InnoDB;

-- One row per charge line of a staged file. The raw row is kept so a later run can
-- parse it again under updated rules; the key fields are copied out for lookups.
CREATE TABLE IF NOT EXISTS `gifford_charge_line` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `batch_id` INT NOT NULL,
    `line_number` INT NOT NULL COMMENT 'row in the file; the header is row 1',
    `visit_number` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'encounter_num',
    `mrn` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'pat_mrn',
    `accession` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'chg_accession',
    `date_of_service` DATE NULL COMMENT 'chg_dos, when it parsed',
    `code` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'chg_cpt or chg_hcpcs',
    `raw_json` MEDIUMTEXT NOT NULL COMMENT 'the row as read: column name => value',
    `problems_json` TEXT NOT NULL COMMENT 'JSON list of problems; empty list when none',
    `status` VARCHAR(16) NOT NULL COMMENT 'staged: ready to post; held: has problems',
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `batch_id` (`batch_id`),
    KEY `visit_number` (`visit_number`),
    KEY `accession` (`accession`),
    KEY `status` (`status`)
) ENGINE=InnoDB;

-- Which insurance company each Gifford payer mnemonic (ins1_mnemonic ...) is billed to.
-- Filled by `bin/console gifford:payer-import` from the payer crosswalk CSV.
CREATE TABLE IF NOT EXISTS `gifford_payer_map` (
    `mnemonic` VARCHAR(32) NOT NULL COMMENT 'Gifford payer mnemonic, upper case',
    `insurance_company_id` INT NOT NULL COMMENT 'insurance_companies.id',
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`mnemonic`)
) ENGINE=InnoDB;

-- The hourly import, run by the site's background services cron. Inactive until the
-- module is enabled (ModuleManagerListener turns it on and off). The function is
-- namespaced; CHAR(92) is the backslash, whatever the server's SQL mode.
#IfNotRow background_services name Gifford_Charge_Import
INSERT INTO `background_services` (`name`, `title`, `active`, `running`, `next_run`, `execute_interval`, `function`, `require_once`, `sort_order`) VALUES
('Gifford_Charge_Import', 'Gifford Charge Import', 0, 0, current_timestamp(), 60,
 CONCAT_WS(CHAR(92 USING utf8mb4), 'Cmsvt', 'OpenEMR', 'Modules', 'GiffordCharges', 'runBackgroundService'),
 '/interface/modules/custom_modules/oe-module-gifford-charges/public/background_service.php', 100);
#EndIf
