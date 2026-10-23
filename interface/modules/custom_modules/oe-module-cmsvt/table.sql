
-- Statements sent to patients, one row per statement and encounter.
-- The statements screen (sl_eob_search.php, "Due Pt") counts email statements
-- that went out 21+ days ago with no payment posted since, and suggests
-- printing instead for patients with two or more.
-- Same definition as rel-800-sunflower's sql/sunfower_migrations/001_patient_statements.sql
-- (28104198ad), so installing the module where the table exists is a no-op.
CREATE TABLE IF NOT EXISTS `patient_statements` (
`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
`pid` bigint(20) NOT NULL,
`encounter` bigint(20) DEFAULT NULL,
`statement_date` date NOT NULL,
`method` enum('mail','email','portal','other') NOT NULL DEFAULT 'mail',
`amount` decimal(12,2) NOT NULL DEFAULT 0.00,
`document_id` bigint(20) UNSIGNED DEFAULT NULL,
`created_by` bigint(20) NOT NULL,
`created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`id`),
KEY `idx_pid_date` (`pid`, `statement_date`),
KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB COMMENT='Patient statements sent, by method';
