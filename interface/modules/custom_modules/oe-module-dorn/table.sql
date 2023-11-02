CREATE TABLE IF NOT EXISTS `mod_dorn_routes`(
  `lab_guid` varchar(100)
  ,`route_guid` varchar(100)
  ,`ppid` bigint(20) default NULL
  ,`uid` bigint(20) default NULL
  ,`lab_name` varchar(100) default NULL
  ,`text_line_break_character` varchar(100) default NULL
  ,PRIMARY KEY (`lab_guid`, `route_guid`)
);