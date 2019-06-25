<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 6/11/19
 * Time: 5:12 PM
 */

// d1out is output of die-38?

$handle = fopen("/tmp/d1out", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        $pid = substr($line, 0, 5);
        echo "pid is $pid";
        echo "<br>";
        $garno = substr($line, 5, 8);
        //echo $rest . "<br>";
        $pat->setPid("$pid");
        $pat_array = $pat->getOne();
        echo "garno is $garno ";
        echo "<br>";
        echo "pid is in database as ";
        echo $pat_array['pid'];
        echo "<br>";
        $pubpid = $pat_array['pubpid'];
        echo "pubpid is $pubpid";
        echo "<br>";
        if ($pubpid == $pid) {
            echo "going to update to $garno<br>";
            $pat_array['pubpid'] = $garno;
            //var_dump($pat_array);
            $pat->update($pid, $pat_array);
        } else {
            echo "not going to update since pubpid is not pid <br>";
        }

    }

    fclose($handle);
} else {
    // error opening the file.
    echo "couldn't open file";
}