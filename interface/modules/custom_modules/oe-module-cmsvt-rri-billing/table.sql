-- RRI billing tables, created when the module is installed (Module Manager).
-- Every block is guarded, so running it again, or on a database moved from the
-- 7.0.2 module (which already has these tables), changes nothing it shouldn't.
--
-- Unlike the 7.0.2 module's table.sql, this one does not replace x12_partners
-- (sites share the server, each keeps its own partners) and does not insert
-- providers: users are set up per site and named in the module's settings.
-- cmsvt_garfile is no longer created; nothing reads it.

-- The old billing system's account file (one row per RRMC account), loaded from
-- the site's flat_actfile by the data install command.
#IfNotTable cmsvt_actfile
CREATE TABLE `cmsvt_actfile` (
    `actno`     VARCHAR(12) NOT NULL COMMENT 'RRMC mrn',
    `garname`   VARCHAR(35) NOT NULL COMMENT 'pt lname;fname mname',
    `billadd`   VARCHAR(30) COMMENT 'billing address',
    `street`    VARCHAR(50) COMMENT 'billing address street line 2',
    `city`      VARCHAR(25) COMMENT 'city',
    `state`     CHAR(2) COMMENT 'state',
    `zip5`      CHAR(5) COMMENT 'zip',
    `zip4`      CHAR(4) COMMENT 'plus 4',
    `collt`     TINYINT(1) COMMENT 'collection indicator',
    `phone`     VARCHAR(12) COMMENT 'phone',
    `sex`       CHAR(1) COMMENT 'sex',
    `relate`    CHAR(2) COMMENT 'relate code',
    `mstat`     CHAR(1) COMMENT 'marital status, unused',
    `dob`       CHAR(8) COMMENT 'pt dob',
    `dunning`   TINYINT(1) COMMENT 'dunning indicator',
    `acctstat`  TINYINT(1) COMMENT 'account status',
    `prmplr`    VARCHAR(4) COMMENT 'primary ins employer code',
    `prins`     VARCHAR(3) COMMENT 'primary ins code',
    `prassign`  CHAR(1) COMMENT 'primary ins assignment indicator',
    `proffice`  VARCHAR(4) COMMENT 'primary office code, unused',
    `prgroup`   VARCHAR(10) COMMENT 'primary ins group number',
    `pripol`    VARCHAR(20) COMMENT 'primary insurance policy number',
    `prname`    VARCHAR(35) COMMENT 'primary ins subscriber name',
    `prrelate`  CHAR(2) COMMENT 'primary insurance relate code',
    `semplr`    CHAR(4) COMMENT 'secondary ins employer code',
    `seins`     VARCHAR(3) COMMENT 'secondary ins code',
    `seassign`  CHAR(1) COMMENT 'secondary ins assignment indicator',
    `seoffice`  VARCHAR(4) COMMENT 'secondary office code, unused',
    `segroup`   VARCHAR(15) COMMENT 'secondary ins group number',
    `secpol`    VARCHAR(20) COMMENT 'secondary insurance policy number',
    `sename`    VARCHAR(35) COMMENT 'secondary ins subscriber name',
    `serelate`  CHAR(2) COMMENT 'secondary insurance relate code',
    `inspend`   VARCHAR(7) COMMENT 'insurance pending',
    `lastbill`  VARCHAR(8) COMMENT 'date of last bill',
    `assignm`   VARCHAR(1) COMMENT 'patient assignment, unused',
    `private`   VARCHAR(1) COMMENT 'private, unused',
    `billcycle` INT COMMENT 'billing cycle, 1 thru 4',
    `delete`    INT COMMENT 'delete indicator, unused',
    `garno`     VARCHAR(10) COMMENT 'legacy guarantor number',
    `prgrpname` VARCHAR(20) COMMENT 'primary ins employer group name',
    `segrpname` VARCHAR(20) COMMENT 'secondary ins employer group name',
    `namekey`   CHAR(3) COMMENT 'first 3 chars of last name',
    UNIQUE KEY (`actno`),
    KEY (`namekey`)
) ENGINE=InnoDB;
#EndIf

-- RRMC's referring physicians, by RRMC's three-character key.
#IfNotTable cmsvt_refphy
CREATE TABLE `cmsvt_refphy` (
    `refkey` CHAR(3) NOT NULL COMMENT 'referring physician id',
    `name`   VARCHAR(35) COMMENT 'referring physician name',
    `npi`    CHAR(10) COMMENT 'npi',
    UNIQUE KEY (`refkey`)
) ENGINE=InnoDB;
#EndIf

#IfNotTable cmsvt_compfile
CREATE TABLE `cmsvt_compfile` (
    `compkey` CHAR(20) NOT NULL COMMENT 'compkey',
    `medrec`  CHAR(8) COMMENT 'medrec',
    `date`    CHAR(8) COMMENT 'date',
    `proc`    CHAR(4) COMMENT 'proc',
    `name`    VARCHAR(25) COMMENT 'Insurance Name',
    `contact` VARCHAR(25) COMMENT 'Insurance Contact',
    `addr1`   VARCHAR(20) COMMENT 'Insurance Address 1',
    `addr2`   VARCHAR(15) COMMENT 'Insurance Address 2',
    `city`    VARCHAR(20) COMMENT 'Insurance City',
    `state`   CHAR(2) COMMENT 'Insurance State',
    `zip`     VARCHAR(10) COMMENT 'Insurance Zip',
    `phone`   VARCHAR(12) COMMENT 'Insurance Phone'
) ENGINE=InnoDB COMMENT='Miscellaneous Worker''s Comp File';
#EndIf

#IfNotTable cmsvt_emailauthfile
CREATE TABLE `cmsvt_emailauthfile` (
    `eakey`  INT NOT NULL COMMENT 'ea-key',
    `medrec` CHAR(8) COMMENT 'Medical Record Number',
    `name`   VARCHAR(24) COMMENT 'Name of Patient',
    `email`  VARCHAR(30) COMMENT 'Email address',
    `auth`   VARCHAR(20) COMMENT 'Authorization Code',
    `date`   CHAR(8) COMMENT 'Date',
    `ssn`    CHAR(9) COMMENT 'SSN of patient',
    INDEX `cmsvt_emailauthfile_idx_eakey` (`eakey`)
) ENGINE=InnoDB COMMENT='Email Authorization File';
#EndIf

-- Which lookup files the data install command has loaded, so an unchanged file
-- is not loaded twice.
#IfNotTable cmsvt_import_file_hash
CREATE TABLE `cmsvt_import_file_hash` (
    `filename`    VARCHAR(255) NOT NULL COMMENT 'File name of the import file',
    `hash`        VARCHAR(255) NOT NULL COMMENT 'Hash of the file',
    `algorithm`   VARCHAR(50) NOT NULL COMMENT 'Algorithm used for the hashing',
    `import_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`filename`)
) ENGINE=InnoDB COMMENT='CMSVT History of File Imports';
#EndIf

-- RRMC payer codes that are billed on paper.
#IfNotTable cmsvt_hospfile
CREATE TABLE `cmsvt_hospfile` (
    `hospkey`       CHAR(5) NOT NULL COMMENT 'hosp-key',
    `insurancekey`  CHAR(3) COMMENT 'insurance key',
    `insurancename` VARCHAR(18) COMMENT 'Insurance Name',
    PRIMARY KEY (`hospkey`)
) ENGINE=InnoDB COMMENT='Hospital Non-Electronic Insurance File';
#EndIf

#IfNotTable cmsvt_insfile
CREATE TABLE `cmsvt_insfile` (
    `inskey`       INT(11) NOT NULL COMMENT 'Insurance Key, matches pk in insurance_companies',
    `insname`      VARCHAR(18) COMMENT 'Insurance Name',
    `insstreet`    VARCHAR(20) COMMENT 'Insurance Address 1',
    `inscity`      VARCHAR(20) COMMENT 'Insurance City',
    `insstate`     CHAR(2) COMMENT 'Insurance State',
    `inszip`       VARCHAR(10) COMMENT 'Insurance Zip',
    `inszipfive`   VARCHAR(10) COMMENT 'Insurance Zip Five',
    `inszipfour`   VARCHAR(10) COMMENT 'Insurance Zip Four',
    `insassign`    CHAR(1) COMMENT 'Insurance Assign',
    `insclaimtype` CHAR(1) COMMENT 'Insurance Claim Type',
    `insneic`      VARCHAR(5) COMMENT 'Insurance NEIC',
    PRIMARY KEY (`inskey`)
) ENGINE=InnoDB COMMENT='Insurance File';
#EndIf

-- RRMC's charge master (COBOL copybook PROCFILE01: CDM, CPT/HCPCS, modifier,
-- type, title, amount). Step 1 checks every charge's CDM against it. The 7.0.2
-- module created it from an untracked SQL file; this matches trisquel's table
-- with typed columns in place of its TINYTEXT ones.
#IfNotTable cmsvt_procfile
CREATE TABLE `cmsvt_procfile` (
    `proc_cdm`    CHAR(4) NOT NULL COMMENT 'PROC-CDM, RRMC charge key',
    `proc_cpt`    VARCHAR(5) NOT NULL COMMENT 'PROC-CPT, CPT or HCPCS',
    `proc_mod`    VARCHAR(2) DEFAULT NULL COMMENT 'PROC-MOD',
    `proc_type`   CHAR(1) DEFAULT '5' COMMENT 'PROC-TYPE: 5 medical, 1 surgical',
    `proc_title`  VARCHAR(28) NOT NULL COMMENT 'PROC-TITLE',
    `proc_amount` DECIMAL(6,2) DEFAULT 0.00 COMMENT 'PROC-AMOUNT in dollars',
    UNIQUE KEY `proc_cdm` (`proc_cdm`, `proc_cpt`, `proc_mod`),
    KEY `cmsvt_procfile_idx_cdm_cpt` (`proc_cdm`, `proc_cpt`)
) ENGINE=InnoDB COMMENT='RRMC charge master';
#EndIf

-- One row per ordfile written by step 3.
#IfNotTable cmsvt_ordfile
CREATE TABLE `cmsvt_ordfile` (
    `ordfile_id`   INT NOT NULL AUTO_INCREMENT,
    `date_created` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `filename`     VARCHAR(255) NOT NULL,
    PRIMARY KEY (`ordfile_id`)
) ENGINE=InnoDB COMMENT='Ord File';
#EndIf

-- One row per charge in an ordfile. accession holds the RRMC accession, or the
-- diagnosis for sites that send one there (NULL when the file has none); the
-- file's field is 8 characters.
#IfNotTable cmsvt_ordfile_record
CREATE TABLE `cmsvt_ordfile_record` (
    `ordfile_record_id` INT NOT NULL AUTO_INCREMENT,
    `ordfile_id`        INT NOT NULL,
    `accession`         CHAR(8) DEFAULT NULL,
    `ord8`              CHAR(8) NOT NULL,
    `ord3`              CHAR(3) NOT NULL,
    `proc`              CHAR(4) NOT NULL,
    `ind`               CHAR(1),
    `ref`               CHAR(3) NOT NULL,
    `iopat`             CHAR(1) NOT NULL,
    `dateA`             CHAR(8) NOT NULL,
    `dateT`             CHAR(8) NOT NULL,
    `dateAdmit`         CHAR(8) NOT NULL,
    `order`             CHAR(4) NOT NULL,
    `clinical`          VARCHAR(38),
    `docp`              CHAR(2),
    `admitDiag`         VARCHAR(17),
    `visitNo`           VARCHAR(17),
    `mod2`              CHAR(2),
    `mod3`              CHAR(2),
    `mod4`              CHAR(2),
    `dateE`             CHAR(8),
    `cpt`               CHAR(5),
    PRIMARY KEY (`ordfile_record_id`),
    UNIQUE KEY `uc_ordfile_ordkey` (`ordfile_id`, `ord8`, `ord3`),
    INDEX `idx_ordfile_chargekey` (`accession`, `proc`),
    CONSTRAINT `fk_record_ordfile_id` FOREIGN KEY (`ordfile_id`) REFERENCES `cmsvt_ordfile` (`ordfile_id`)
) ENGINE=InnoDB COMMENT='Ord File Record Item';
#EndIf

-- The 7.0.2 module's table.sql made accession CHAR(7) NOT NULL, which cut an
-- 8-character accession or diagnosis short and refused an empty one. trisquel's
-- production table was already changed by hand to this definition.
#IfNotColumnType cmsvt_ordfile_record accession char(8)
ALTER TABLE `cmsvt_ordfile_record` MODIFY `accession` CHAR(8) DEFAULT NULL;
#EndIf

-- Quality measure answers per charge. The radiology measure pages are not part of
-- this port, but the billing join table below references these tables, and
-- databases moved from the 7.0.2 module have them.
#IfNotTable cmsvt_ordfile_record_measure
CREATE TABLE `cmsvt_ordfile_record_measure` (
    `id`                INT(11) NOT NULL AUTO_INCREMENT,
    `ordfile_record_id` INT(11) NOT NULL,
    `pid`               BIGINT(20) NOT NULL COMMENT 'patient_data pid value',
    `paycode`           CHAR(3) NOT NULL,
    `measure_code`      VARCHAR(10) NOT NULL,
    `measure_value`     VARCHAR(255) NOT NULL,
    `date_created`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `measure_type`      VARCHAR(20) NOT NULL DEFAULT 'QCDR' COMMENT 'Type of measure to include ie claims, QCDR, MIPS, etc',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unq_ordfile_record_mea_code` (`ordfile_record_id`, `measure_code`),
    KEY `fk_measure_patient_data_pid` (`pid`),
    CONSTRAINT `fk_measure_ordfile_record_id` FOREIGN KEY (`ordfile_record_id`) REFERENCES `cmsvt_ordfile_record` (`ordfile_record_id`),
    CONSTRAINT `fk_measure_patient_data_pid` FOREIGN KEY (`pid`) REFERENCES `patient_data` (`pid`)
) ENGINE=InnoDB COMMENT='Tracks the Quality Measures used in billing';
#EndIf

#IfNotTable cmsvt_ordfile_record_measure_code
CREATE TABLE `cmsvt_ordfile_record_measure_code` (
    `id`                        INT(11) NOT NULL AUTO_INCREMENT,
    `ordfile_record_measure_id` INT(11) NOT NULL,
    `code`                      VARCHAR(20) NOT NULL,
    `code_type`                 VARCHAR(15) NOT NULL,
    `date_created`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unq_ordfile_record_mea_code` (`ordfile_record_measure_id`, `code`),
    CONSTRAINT `fk_ordfile_record_measure_id` FOREIGN KEY (`ordfile_record_measure_id`) REFERENCES `cmsvt_ordfile_record_measure` (`id`)
) ENGINE=InnoDB COMMENT='Tracks the Quality Measure claims reporting codes used in billing';
#EndIf

-- Which billing lines step 6 made from which charge, without touching billing.
#IfNotTable cmsvt_ordfile_record_billing
CREATE TABLE `cmsvt_ordfile_record_billing` (
    `ordfile_record_id`              INT NOT NULL,
    `billing_id`                     INT(11) NOT NULL,
    `record_type`                    ENUM('source', 'primary', 'secondary', 'measure') NOT NULL DEFAULT 'source',
    `ordfile_record_measure_code_id` INT NULL DEFAULT NULL,
    PRIMARY KEY (`ordfile_record_id`, `billing_id`),
    UNIQUE INDEX `corb_idx_unq` (`ordfile_record_id`, `billing_id`, `record_type`),
    CONSTRAINT `fk_join_corb_ordfile_record_id` FOREIGN KEY (`ordfile_record_id`) REFERENCES `cmsvt_ordfile_record` (`ordfile_record_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_join_corb_billing_id` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_join_corb_ordfile_record_measure_code_id` FOREIGN KEY (`ordfile_record_measure_code_id`) REFERENCES `cmsvt_ordfile_record_measure_code` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB COMMENT='Links ordfile records to the billing lines made from them';
#EndIf

-- Encounter categories step 6 files charges under, by place of service.
#IfNotRow openemr_postcalendar_categories pc_constant_id emergency_visit
INSERT INTO `openemr_postcalendar_categories` (
    `pc_constant_id`, `pc_catname`, `pc_catcolor`, `pc_catdesc`,
    `pc_recurrtype`, `pc_enddate`, `pc_recurrspec`, `pc_recurrfreq`, `pc_duration`,
    `pc_end_date_flag`, `pc_end_date_type`, `pc_end_date_freq`, `pc_end_all_day`,
    `pc_dailylimit`, `pc_cattype`, `pc_active`, `pc_seq`, `aco_spec`
) VALUES (
    'emergency_visit', 'Emergency Room Visit', '#a2d9e2', 'Emergency Room Visit', '0', NULL,
    'a:5:{s:17:"event_repeat_freq";s:1:"0";s:22:"event_repeat_freq_type";s:1:"0";s:19:"event_repeat_on_num";s:1:"1";s:19:"event_repeat_on_day";s:1:"0";s:20:"event_repeat_on_freq";s:1:"0";}',
    '0', '1800', '0', NULL, '0', '0', '0', 0, '1', '10', 'encounters|notes'
);
#EndIf

#IfNotRow openemr_postcalendar_categories pc_constant_id inpatient_visit
INSERT INTO `openemr_postcalendar_categories` (
    `pc_constant_id`, `pc_catname`, `pc_catcolor`, `pc_catdesc`,
    `pc_recurrtype`, `pc_enddate`, `pc_recurrspec`, `pc_recurrfreq`, `pc_duration`,
    `pc_end_date_flag`, `pc_end_date_type`, `pc_end_date_freq`, `pc_end_all_day`,
    `pc_dailylimit`, `pc_cattype`, `pc_active`, `pc_seq`, `aco_spec`
) VALUES (
    'inpatient_visit', 'Inpatient Hospital Visit', '#a2d9e2', 'Inpatient Hospital Visit', '0', NULL,
    'a:5:{s:17:"event_repeat_freq";s:1:"0";s:22:"event_repeat_freq_type";s:1:"0";s:19:"event_repeat_on_num";s:1:"1";s:19:"event_repeat_on_day";s:1:"0";s:20:"event_repeat_on_freq";s:1:"0";}',
    '0', '1800', '0', NULL, '0', '0', '0', 0, '1', '10', 'encounters|notes'
);
#EndIf

#IfNotRow openemr_postcalendar_categories pc_constant_id outpatient_visit
INSERT INTO `openemr_postcalendar_categories` (
    `pc_constant_id`, `pc_catname`, `pc_catcolor`, `pc_catdesc`,
    `pc_recurrtype`, `pc_enddate`, `pc_recurrspec`, `pc_recurrfreq`, `pc_duration`,
    `pc_end_date_flag`, `pc_end_date_type`, `pc_end_date_freq`, `pc_end_all_day`,
    `pc_dailylimit`, `pc_cattype`, `pc_active`, `pc_seq`, `aco_spec`
) VALUES (
    'outpatient_visit', 'Outpatient Hospital Visit', '#a2d9e2', 'Outpatient Hospital Visit', '0', NULL,
    'a:5:{s:17:"event_repeat_freq";s:1:"0";s:22:"event_repeat_freq_type";s:1:"0";s:19:"event_repeat_on_num";s:1:"1";s:19:"event_repeat_on_day";s:1:"0";s:20:"event_repeat_on_freq";s:1:"0";}',
    '0', '1800', '0', NULL, '0', '0', '0', 0, '1', '10', 'encounters|notes'
);
#EndIf
