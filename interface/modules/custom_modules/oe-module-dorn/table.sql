CREATE TABLE IF NOT EXISTS `mod_dorn_routes`(
  `id` INT(11)  PRIMARY KEY AUTO_INCREMENT NOT NULL
  `ppid` bigint(20)
  `route_guid` uuid
  `lab_guid` uuid
  `lab_name` varchar(100)
);