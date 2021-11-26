DROP TABLE IF EXISTS `hospfile`;
CREATE TABLE `hospfile` (
  `hosp_key` text(5) NOT NULL COMMENT 'RRMC ins key',
  `h_ins_key` text(3) NOT NULL,
  `h_ins_name` text(18),
  UNIQUE KEY (`hosp_key`),
  KEY (`h_ins_key`)
) ENGINE=InnoDB;