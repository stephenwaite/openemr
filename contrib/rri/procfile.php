<?php
/**
 * takes unload of procfile and imports into codes table
 *
 */

$ignoreAuth = true;
$_GET['site'] = $argv[1];
$file = $argv[2];

require_once(dirname(__FILE__) . "/../../interface/globals.php");

sqlStatement("truncate codes");
sqlStatement("truncate prices");
sqlStatement("truncate procfile");


// w1 is unload of procfile
$handle1 = fopen($file, "r");

while (($lin = fgets($handle1)) !== false) {
    // process the line read.
    $cdm = substr($lin, 0, 4);
    echo "cdm is $cdm \n";
    $cpt = substr($lin, 4, 5);
    if (trim($cpt) != '') {
      echo "cpt is $cpt \n";
      $mod = substr($lin, 9, 2);
      $typ = substr($lin, 11, 1);
      $des = substr($lin, 12, 28);
      // will need to add medicare fee
      $amt = substr($lin, 40, 6);
      echo "amount is $amt \n";
      $div = 100;
      $fee = $amt/$div;
      echo "fee is $fee \n";

      $proc_res = sqlStatement("insert into procfile set proc_cdm =?, proc_cpt =?, proc_mod=?, proc_type=?, proc_title=?, proc_amount=?", array($cdm, $cpt, $mod, $typ, $des, $fee));

      $rec = sqlQuery("select code from codes where code = ?", array($cpt));
      if (!$rec) {
          $res = sqlStatement("insert into codes set code_text =?, code_text_short =?, code=?, code_type=?, modifier=?, fee=?, active=?", array($des, $des, $cpt, "1", $mod, $fee, "1"));
          $rez = sqlQuery("select id from codes where code = ? and modifier = ?", array($cpt, $mod));
          $rep = sqlQuery("select pr_price from prices where pr_id = ? and pr_level = ? and pr_price = ?", array($rez['id'], "standard", $fee));
          if (!$rep) {
              echo "going to insert into prices \n";
              $pri = sqlStatement("insert into prices set pr_id=?, pr_level=?, pr_price=?", array($rez['id'], "standard", $fee));
          }
      }
    }
}
