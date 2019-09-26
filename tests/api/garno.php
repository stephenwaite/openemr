<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 6/11/19
 * Time: 5:12 PM
 */

// d1out is output of die-38
use OpenEMR\Services\PatientService;
use OpenEMR\Services\InsuranceService;

require_once(dirname(__FILE__) . "/../../interface/globals.php");
//require_once (dirname(__FILE__) . "/../../library/patient.inc")

//echo "<b>pt service call:</b><br>";
$pat = new PatientService();
$ins = new InsuranceService();

//$handle = fopen("/tmp/d1out", "r");
//if ($handle) {
//    while (($line = fgets($handle)) !== false) {
//        // process the line read.
//        $pid = substr($line, 0, 5);
//        echo "pid is $pid";
//        echo "<br>";
//        $garno = substr($line, 5, 8);
//        //echo $rest . "<br>";
//        $pat->setPid("$pid");
//        $pat_array = $pat->getOne();
//        echo "garno is $garno ";
//        echo "<br>";
//        echo "pid is in database as ";
//        echo $pat_array['pid'];
//        echo "<br>";
//        $pubpid = $pat_array['pubpid'];
//        echo "pubpid is $pubpid";
//        echo "<br>";
//        if ($pubpid == $pid) {
//            echo "going to update to $garno<br>";
//            $pat_array['pubpid'] = $garno;
//            //var_dump($pat_array);
//            $pat->update($pid, $pat_array);
//        } else {
//            echo "not going to update since pubpid is not pid <br>";
//        }
//
//    }
//
//    fclose($handle);
//} else {
//    // error opening the file.
//    echo "couldn't open file";
//}
//
//exit();

$handle = fopen("/tmp/errfile2", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        $pieces = explode(",", $line);
        //$pid = substr($line, 0, 5);
        //$pid = $pieces[5];
        $pid = "1";
        $pat->setPid($pid);
        //echo "pid is $pid";
        //echo "<br>";
//        $garno = substr($line, 5, 8);
        $pat_array = $pat->getOne();
//        $pat->getAll($search['dob'] = "1956-03-23");

//        $search = array();
//        $search['dob'] = "1956-03-23";
//        $pat_array = $pat->getAll($search);
        var_dump($pat_array);
        echo "<br>";
//        $pat_array = $pat->getOne();
//        $gender = $pieces[4];
//        echo "gender is $gender";
//        echo "<br>";
//        if ($gender == "Female") {
//            echo "need to change to Male";
//            echo "<br>";
//            $pat_array['sex'] = 'Male';
//            $pat->update($pid, $pat_array);
//            $pat_array = $pat->getOne();
//            var_dump($pat_array);

        //} else {
        //    echo "need to change to Female";
        //    echo "<br>";
        //}
        exit();
//        echo "pid is in database as ";
//        echo $pat_array['pid'];
//        echo "<br>";
//        $pubpid = $pat_array['pubpid'];
//        echo "pubpid is $pubpid";
//        echo "<br>";
//        if ($pubpid == $pid) {
//            echo "going to update to $garno<br>";
//            $pat_array['pubpid'] = $garno;
            //var_dump($pat_array);
//            $pat->update($pid, $pat_array);
//        } else {
//            echo "not going to update since pubpid is not pid <br>";
//        }

    }
    fclose($handle);
} else {
    // error opening the file.
    echo "couldn't open file";
}
