REPLACE INTO `patient_data` 
  SET fname = "#ORPHAN#", 
  lname = "#QUESTLABS#",
  pid = 999999999999999;

REPLACE INTO `users`
  SET username = "quest",
  password = "NONE",
  fname = "Quest",
  lname = "Interface",
  active = 1,
  authorized = 0,
  info = "Quest batch processing";

CREATE TABLE IF NOT EXISTS `form_quest_order` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `pid` bigint(20) NOT NULL,
  `user` varchar(255) NOT NULL,
  `groupname` varchar(255) NOT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `status` varchar(16) DEFAULT NULL,
  `priority` varchar(16) DEFAULT NULL,
  `pat_first` varchar(255) DEFAULT NULL,
  `pat_middle` varchar(255) DEFAULT NULL,
  `pat_last` varchar(255) DEFAULT NULL,
  `pat_DOB` date DEFAULT NULL,
  `pat_ss` varchar(15) DEFAULT NULL,
  `pat_age` varchar(255) DEFAULT NULL,
  `pat_sex` varchar(255) DEFAULT NULL,
  `pat_email` varchar(255) DEFAULT NULL,
  `pat_mobile` varchar(255) DEFAULT NULL,
  `pat_phone` varchar(255) DEFAULT NULL,
  `pat_street` varchar(255) DEFAULT NULL,
  `pat_city` varchar(255) DEFAULT NULL,
  `pat_state` varchar(255) DEFAULT NULL,
  `pat_zip` varchar(255) DEFAULT NULL,
  `ins_first` varchar(255) DEFAULT NULL,
  `ins_middle` varchar(255) DEFAULT NULL,
  `ins_last` varchar(255) DEFAULT NULL,
  `ins_DOB` date DEFAULT NULL,
  `ins_sex` varchar(2) DEFAULT NULL,
  `ins_ss` varchar(15) DEFAULT NULL,
  `ins_relation` varchar(255) DEFAULT NULL,
  `ins_primary` varchar(255) DEFAULT NULL,
  `ins_primary_id` varchar(255) DEFAULT NULL,
  `ins_primary_plan` varchar(255) DEFAULT NULL,
  `ins_primary_policy` varchar(255) DEFAULT NULL,
  `ins_primary_group` varchar(255) DEFAULT NULL,
  `ins_secondary` varchar(255) DEFAULT NULL,
  `ins_secondary_id` varchar(255) DEFAULT NULL,
  `ins_secondary_plan` varchar(255) DEFAULT NULL,
  `ins_secondary_policy` varchar(255) DEFAULT NULL,
  `ins_secondary_group` varchar(255) DEFAULT NULL,
  `ins_tertiary` varchar(255) DEFAULT NULL,
  `ins_tertiary_id` varchar(255) DEFAULT NULL,
  `ins_tertiary_plan` varchar(255) DEFAULT NULL,
  `ins_tertiary_policy` varchar(255) DEFAULT NULL,
  `ins_tertiary_group` varchar(255) DEFAULT NULL,
  `guarantor_first` varchar(255) DEFAULT NULL,
  `guarantor_middle` varchar(255) DEFAULT NULL,
  `guarantor_last` varchar(255) DEFAULT NULL,
  `guarantor_relation` varchar(255) DEFAULT NULL,
  `guarantor_street` varchar(255) DEFAULT NULL,
  `guarantor_city` varchar(255) DEFAULT NULL,
  `guarantor_state` varchar(255) DEFAULT NULL,
  `guarantor_zip` varchar(255) DEFAULT NULL,
  `guarantor_ss` varchar(255) DEFAULT NULL,
  `guarantor_phone` varchar(255) DEFAULT NULL,
  `dx0_code` varchar(255) DEFAULT NULL,
  `dx0_text` varchar(255) DEFAULT NULL,
  `dx1_code` varchar(255) DEFAULT NULL,
  `dx1_text` varchar(255) DEFAULT NULL,
  `dx2_code` varchar(255) DEFAULT NULL,
  `dx2_text` varchar(255) DEFAULT NULL,
  `dx3_code` varchar(255) DEFAULT NULL,
  `dx3_text` varchar(255) DEFAULT NULL,
  `dx4_code` varchar(255) DEFAULT NULL,
  `dx4_text` varchar(255) DEFAULT NULL,
  `dx5_code` varchar(255) DEFAULT NULL,
  `dx5_text` varchar(255) DEFAULT NULL,
  `dx6_code` varchar(255) DEFAULT NULL,
  `dx6_text` varchar(255) DEFAULT NULL,
  `dx7_code` varchar(255) DEFAULT NULL,
  `dx7_text` varchar(255) DEFAULT NULL,
  `dx8_code` varchar(255) DEFAULT NULL,
  `dx8_text` varchar(255) DEFAULT NULL,
  `dx9_code` varchar(255) DEFAULT NULL,
  `dx9_text` varchar(255) DEFAULT NULL,
  `order0_number` varchar(225) NOT NULL,
  `order0_datetime` datetime DEFAULT NULL,
  `order0_done` tinyint(4) DEFAULT NULL,
  `order0_type` varchar(255) DEFAULT NULL,
  `order0_psc` tinyint(4) DEFAULT NULL,
  `order0_pending` datetime DEFAULT NULL,
  `order0_fasting` tinyint(4) DEFAULT NULL,
  `order0_duration` varchar(255) DEFAULT NULL,
  `order0_req_id` varchar(255) DEFAULT NULL,
  `order0_abn_id` varchar(255) DEFAULT NULL,
  `order0_notes` text,
  `request_provider` varchar(255) DEFAULT NULL,
  `request_facility` varchar(255) DEFAULT NULL,
  `request_handling` varchar(255) DEFAULT NULL,
  `request_datetime` datetime DEFAULT NULL,
  `request_processed` datetime DEFAULT NULL,
  `request_notes` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `form_quest_order_item` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) NOT NULL,
  `date` datetime NOT NULL,
  `pid` bigint(20) NOT NULL,
  `user` varchar(255) NOT NULL,
  `groupname` varchar(255) NOT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `status` varchar(16) DEFAULT NULL,
  `priority` varchar(16) DEFAULT NULL,
  `test_code` varchar(255) NOT NULL,
  `test_text` varchar(255) NOT NULL,
  `test_profile` tinyint(4) DEFAULT NULL,
  `aoe0_code` varchar(255) DEFAULT NULL,
  `aoe0_unit` varchar(255) DEFAULT NULL,
  `aoe0_label` varchar(255) DEFAULT NULL,
  `aoe0_text` varchar(255) DEFAULT NULL,
  `aoe1_code` varchar(255) DEFAULT NULL,
  `aoe1_label` varchar(255) DEFAULT NULL,
  `aoe1_text` varchar(255) DEFAULT NULL,
  `aoe2_code` varchar(255) DEFAULT NULL,
  `aoe2_label` varchar(255) DEFAULT NULL,
  `aoe2_text` varchar(255) DEFAULT NULL,
  `aoe3_code` varchar(255) DEFAULT NULL,
  `aoe3_label` varchar(255) DEFAULT NULL,
  `aoe3_text` varchar(255) DEFAULT NULL,
  `aoe4_code` varchar(255) DEFAULT NULL,
  `aoe4_label` varchar(255) DEFAULT NULL,
  `aoe4_text` varchar(255) DEFAULT NULL,
  `aoe5_code` varchar(255) DEFAULT NULL,
  `aoe5_label` varchar(255) DEFAULT NULL,
  `aoe5_text` varchar(255) DEFAULT NULL,
  `aoe6_code` varchar(255) DEFAULT NULL,
  `aoe6_label` varchar(255) DEFAULT NULL,
  `aoe6_text` varchar(255) DEFAULT NULL,
  `aoe7_code` varchar(255) DEFAULT NULL,
  `aoe7_label` varchar(255) DEFAULT NULL,
  `aoe7_text` varchar(255) DEFAULT NULL,
  `aoe8_code` varchar(255) DEFAULT NULL,
  `aoe8_label` varchar(255) DEFAULT NULL,
  `aoe8_text` varchar(255) DEFAULT NULL,
  `aoe9_code` varchar(255) DEFAULT NULL,
  `aoe9_label` varchar(255) DEFAULT NULL,
  `aoe9_text` varchar(255) DEFAULT NULL,
  `aoe10_code` varchar(255) DEFAULT NULL,
  `aoe10_label` varchar(255) DEFAULT NULL,
  `aoe10_text` varchar(255) DEFAULT NULL,
  `aoe11_code` varchar(255) DEFAULT NULL,
  `aoe11_label` varchar(255) DEFAULT NULL,
  `aoe11_text` varchar(255) DEFAULT NULL,
  `aoe12_code` varchar(255) DEFAULT NULL,
  `aoe12_label` varchar(255) DEFAULT NULL,
  `aoe12_text` varchar(255) DEFAULT NULL,
  `aoe13_code` varchar(255) DEFAULT NULL,
  `aoe13_label` varchar(255) DEFAULT NULL,
  `aoe13_text` varchar(255) DEFAULT NULL,
  `aoe14_code` varchar(255) DEFAULT NULL,
  `aoe14_label` varchar(255) DEFAULT NULL,
  `aoe14_text` varchar(255) DEFAULT NULL,
  `aoe15_code` varchar(255) DEFAULT NULL,
  `aoe15_label` varchar(255) DEFAULT NULL,
  `aoe15_text` varchar(255) DEFAULT NULL,
  `aoe16_code` varchar(255) DEFAULT NULL,
  `aoe16_label` varchar(255) DEFAULT NULL,
  `aoe16_text` varchar(255) DEFAULT NULL,
  `aoe17_code` varchar(255) DEFAULT NULL,
  `aoe17_label` varchar(255) DEFAULT NULL,
  `aoe17_text` varchar(255) DEFAULT NULL,
  `aoe18_code` varchar(255) DEFAULT NULL,
  `aoe18_label` varchar(255) DEFAULT NULL,
  `aoe18_text` varchar(255) DEFAULT NULL,
  `aoe19_code` varchar(255) DEFAULT NULL,
  `aoe19_label` varchar(255) DEFAULT NULL,
  `aoe19_text` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `cdc_order_aoe` (
  `legal_entity` varchar(3) NOT NULL,
  `toplab_performing_site` varchar(3) NOT NULL,
  `unit_cd` varchar(10) NOT NULL,
  `test_cd` varchar(16) NOT NULL,
  `analyte_cd` varchar(11) NOT NULL,
  `aoe_question` varchar(30) NOT NULL,
  `active_ind` varchar(1) NOT NULL,
  `profile_component` varchar(15) NOT NULL,
  `insert_datetime` datetime NOT NULL,
  `aoe_question_desc` varchar(50) NOT NULL,
  `suffix` varchar(8) NOT NULL,
  `result_filter` varchar(250) NOT NULL,
  `test_cd_mnemonic` varchar(16) NOT NULL,
  `test_flag` varchar(1) NOT NULL,
  `update_datetime` datetime NOT NULL,
  `update_user` varchar(8) NOT NULL,
  `component_name` varchar(200) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `cdc_order_codes` (
  `legal_entity` varchar(3) NOT NULL,
  `test_cd` varchar(16) NOT NULL,
  `state` varchar(2) NOT NULL,
  `unit_cd` varchar(10) NOT NULL,
  `active_ind` varchar(1) NOT NULL,
  `insert_datetime` datetime NOT NULL,
  `description` varchar(175) NOT NULL,
  `specimen_type` varchar(30) NOT NULL,
  `nbs_service_code` varchar(10) NOT NULL,
  `toplab_performing_site` varchar(3) NOT NULL,
  `update_datetime` datetime NOT NULL,
  `update_user` varchar(8) NOT NULL,
  `suffix` varchar(8) NOT NULL,
  `profile_ind` varchar(1) NOT NULL,
  `selectest_ind` varchar(1) NOT NULL,
  `nbs_performing_site` varchar(4) NOT NULL,
  `test_flag` varchar(1) NOT NULL,
  `no_bill_indicator` varchar(1) NOT NULL,
  `bill_only_indicator` varchar(1) NOT NULL,
  `send_out_reflex_count` varchar(2) NOT NULL,
  `conforming_ind` varchar(1) NOT NULL,
  `alternate_temp` varchar(1) NOT NULL,
  `pap_ind` varchar(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `cdc_profiles` (
  `legal_entity` varchar(3) NOT NULL,
  `toplab_performing_site` varchar(3) NOT NULL,
  `test_cd` varchar(16) NOT NULL,
  `component_test_cd` varchar(50) NOT NULL,
  `component_unit_cd` varchar(10) NOT NULL,
  `description` varchar(175) NOT NULL,
  `specimen_type` varchar(130) NOT NULL,
  `specimen_state` varchar(2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `cdc_dos_info` (
  `record_type` varchar(2) NOT NULL,
  `toplab_performing_site` varchar(3) NOT NULL,
  `test_cd` varchar(16) NOT NULL,
  `sequence_no` int(2) NOT NULL,
  `comment_text` varchar(60) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
