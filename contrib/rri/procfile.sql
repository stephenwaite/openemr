DROP TABLE IF EXISTS `procfile`;
CREATE TABLE `procfile` (
  `proc_cdm` text(4) NOT NULL COMMENT 'RRMC charge key',
  `proc_cpt` text(5) NOT NULL,
  `proc_mod` text(2),
  `proc_type` text(1) DEFAULT '5' COMMENT '5 medical, 1 surgical',
  `proc_title` text(28) NOT NULL,
  `proc_amount` decimal(6,2) default 0,
  UNIQUE KEY (`proc_cdm`, `proc_cpt`, `proc_mod`)
) ENGINE=InnoDB;