-- OpenEMR Database changes needed to add prescriptions import functionality
-- version 0.0.91

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `openemr`
--

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions_imports`
--

CREATE TABLE IF NOT EXISTS `prescriptions_imports` (  
 `id` int( 11  )  NOT  NULL  auto_increment ,
 `prescriptions_id` int( 11  )  default NULL ,
 `pi_batch_id` int( 11  )  NOT  NULL ,
 `pi_approver_id` int( 11  )  default NULL ,
 `pi_pid` int( 11  )  default NULL ,
 `patient_name` varchar( 100  )  NOT  NULL ,
 `drug_name` varchar( 100  )  NOT  NULL ,
 `pharmacy_name` varchar( 100  )  default NULL ,
 `provider_name` varchar( 100  )  default NULL ,
 `filled_by_name` varchar( 100  )  default NULL ,
 `initiator_name` varchar( 100  )  default NULL ,
 `order_date` varchar( 31  )  NOT  NULL ,
 `import_date` date  default NULL ,
 `quantity_desc` varchar( 31  )  default NULL ,
 `refills_desc` varchar( 31  )  default NULL ,
 `presc_count` varchar( 31  )  default NULL ,
 `pharmacy_note` varchar( 255  )  default NULL ,
 `pharmacy_status` varchar( 31  )  NOT  NULL ,
 `pi_status` smallint( 6  )  default NULL ,
 `pi_error` varchar( 10  )  default NULL ,
 PRIMARY  KEY (  `id`  ) ,
 KEY  `ix_batch_id` (  `pi_batch_id`  ) ,
 KEY  `ix_provider_name` (  `provider_name`  ) ,
 KEY  `ix_prescription_key` (  `patient_name` ,  `drug_name` ,  `order_date`  )  ) 
 ENGINE  = InnoDB  DEFAULT CHARSET  = utf8;

ALTER TABLE `lists` 
  ADD INDEX `ix_pid_type` (`pid`,`type`)
;

ALTER TABLE `prescriptions` 
  ADD INDEX `ix_filled_by_id` (`filled_by_id`)
;
