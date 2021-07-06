<?php
/**
 * takes unload of garfile and imports into insurance_data table
 *
 * Created by PhpStorm.
 * User: stee
 * Date: 6/12/19
 * Time: 3:37 PM
 */

$ignoreAuth = true;
$_GET['site'] = $argv[1];

require_once(dirname(__FILE__) . "/../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../library/patient.inc");
require_once(dirname(__FILE__) . "/../../library/forms.inc");

$res = sqlStatement("delete from `codes` where code_type=?", array("1"));
sqlStatement("truncate prices");

// w1 is unload of procfile
$handle1 = fopen("/tmp/w1", "r");

while (($lin = fgets($handle1)) !== false) {
    // process the line read.
    $cpt = substr($lin, 0, 5);
    if (trim($cpt) != '') {
      error_log("cpt is $cpt \n");
      $mod = substr($lin, 5, 2);
      $des = substr($lin, 19, 28);
      // will need to add medicare fee
      $fee = substr($lin, 47, 6);
      echo "fee is $fee \n";
      $div = 100;
      $fee = $fee/$div;
      echo "fee is $fee \n";

      $res = sqlStatement("insert into codes set code_text =?, code_text_short =?, code=?, code_type=?, modifier=?, fee=?, active=?", array($des, $des, $cpt, "1", $mod, $fee, "1"));
      $rez = sqlQuery("select id from codes where code = ? and modifier = ?", array($cpt, $mod));
      var_dump($rez);
      $pri = sqlStatement("insert into prices set pr_id=?, pr_level=?, pr_price=?", array($rez['id'], "standard", $fee));
    }


}


        
       