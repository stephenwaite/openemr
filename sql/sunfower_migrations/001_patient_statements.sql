CREATE TABLE IF NOT EXISTS `patient_statements` (
    `id`              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `pid`             BIGINT(20) NOT NULL,
    `encounter`       BIGINT(20) DEFAULT NULL,
    `statement_date`  DATE NOT NULL,
    `method`          ENUM('mail','email','portal','other') NOT NULL DEFAULT 'mail',
    `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `document_id`     BIGINT(20) UNSIGNED DEFAULT NULL,
    `created_by`      BIGINT(20) NOT NULL,
    `created_date`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pid_date`      (`pid`, `statement_date`),
    KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Sunflower statement send audit log';
