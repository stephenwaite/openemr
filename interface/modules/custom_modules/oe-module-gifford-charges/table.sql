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
    `status` VARCHAR(16) NOT NULL COMMENT 'staged: ready to post; held: has problems; posted; duplicate: already posted from an earlier file; skipped: a person chose not to post it',
    `released` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1: a person chose to post a line held as a corrected re-send or a repeat procedure',
    `added_modifier` VARCHAR(2) NOT NULL DEFAULT '' COMMENT 'repeat procedure modifier (76 or 77) a person chose to post the line with',
    `pid` BIGINT NULL COMMENT 'patient_data.pid, once posted',
    `encounter` BIGINT NULL COMMENT 'form_encounter.encounter, once posted',
    `billing_id` INT NULL COMMENT 'billing.id of the procedure line, once posted',
    `posted_at` DATETIME NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `batch_id` (`batch_id`),
    KEY `visit_number` (`visit_number`),
    KEY `accession` (`accession`),
    KEY `status` (`status`)
) ENGINE=InnoDB;

#IfMissingColumn gifford_charge_line added_modifier
ALTER TABLE `gifford_charge_line` ADD COLUMN `added_modifier` VARCHAR(2) NOT NULL DEFAULT '' COMMENT 'repeat procedure modifier (76 or 77) a person chose to post the line with' AFTER `released`;
#EndIf

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

-- A charge line that needs the coder's quality measure answers (Fees > Quality Measures)
-- before it posts. The line is held until answered or released; the answers go with it
-- when it posts (G-codes on the claim, registry answers to the ACR export).
CREATE TABLE IF NOT EXISTS `gifford_measure` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `line_id` INT NOT NULL COMMENT 'gifford_charge_line.id',
    `measures` VARCHAR(32) NOT NULL COMMENT 'comma list: QMM26, 364, 405, 406',
    `answers_json` TEXT NOT NULL COMMENT 'measure => the option chosen',
    `status` VARCHAR(16) NOT NULL COMMENT 'pending; answered; released: let go with no answer',
    `created_at` DATETIME NOT NULL,
    `answered_at` DATETIME NULL,
    `answered_by` INT NULL COMMENT 'users.id',
    `pid` BIGINT NULL COMMENT 'once posted',
    `encounter` BIGINT NULL COMMENT 'once posted',
    `billing_id` INT NULL COMMENT 'billing.id of the charge, once posted',
    `posted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `line_id` (`line_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB;

-- NCCI procedure-to-procedure edits, loaded by `bin/console gifford:cci-import` from the
-- billing system's unloaded CCIFILE. Posting adds modifier 59 to the column 2 code of a
-- pair billed for one patient on one day when the indicator allows it.
CREATE TABLE IF NOT EXISTS `gifford_cci_edit` (
    `column1` CHAR(5) NOT NULL COMMENT 'the comprehensive code',
    `column2` CHAR(5) NOT NULL COMMENT 'the component code, which carries the modifier',
    `indicator` CHAR(1) NOT NULL COMMENT '0: not billed together; 1: billed together with modifier 59; 9: edit deleted',
    PRIMARY KEY (`column1`, `column2`),
    KEY `column2` (`column2`)
) ENGINE=InnoDB;

-- The diagnoses each accession posts with (Dx\DxRepository). The suggestion is read from the
-- radiologist's report; it posts as it is when DxRule finds no reason for a coder to look
-- (agreed), and otherwise waits for the coder on Fees > Gifford Diagnoses (pending, then
-- confirmed). A coder can confirm an accession that has no suggestion.
CREATE TABLE IF NOT EXISTS `gifford_dx` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `accession` VARCHAR(64) NOT NULL COMMENT 'chg_accession',
    `suggested_json` MEDIUMTEXT NOT NULL COMMENT 'JSON list in claim order: code, confidence, rationale',
    `reasons_json` TEXT NOT NULL COMMENT 'JSON list: why a coder confirms; empty when agreed',
    `status` VARCHAR(16) NOT NULL COMMENT 'pending: waits for a coder; agreed: the suggestion posts as it is; confirmed: a coder chose the codes',
    `codes` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'comma list in claim order, once agreed or confirmed',
    `source` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'where the suggestion came from',
    `created_at` DATETIME NOT NULL,
    `confirmed_at` DATETIME NULL,
    `confirmed_by` INT NULL COMMENT 'users.id',
    PRIMARY KEY (`id`),
    UNIQUE KEY `accession` (`accession`),
    KEY `status` (`status`)
) ENGINE=InnoDB;

-- The measure answers read from an accession's report, loaded by
-- `bin/console gifford:measure-import`. A sure "no finding" answers the question by itself;
-- anything else is shown to the coder on Fees > Quality Measures beside the question.
CREATE TABLE IF NOT EXISTS `gifford_measure_suggestion` (
    `accession` VARCHAR(64) NOT NULL COMMENT 'chg_accession',
    `answers_json` MEDIUMTEXT NOT NULL COMMENT 'measure => answer, confidence, evidence, rationale',
    `source` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'where the answers came from',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`accession`)
) ENGINE=InnoDB;
