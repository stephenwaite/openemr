<?php
/**
 * Testing script for the local/internal use of the api
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


// comment below exit command to run this test script
//  (when done, remember to uncomment it again)
//exit;


require_once(dirname(__FILE__) . "/../../interface/globals.php");
?>
<html>
<head>
    <script src="../../public/assets/jquery/dist/jquery.min.js"></script>

    <script language="JavaScript">
        //function testAjaxApi(fid) {
        //    //alert(fid);
        //
        //    $.ajax({
        //        type: 'GET',
        //        url: '../../apis/api/facility/'+fid,
        //        dataType: 'json',
        //        headers: {
        //            'apicsrftoken': <?php //echo js_escape($_SESSION['api_csrf_token']); ?>
        //        },
        //        success: function(thedata){
        //            let thedataJSON = JSON.stringify(thedata);
        //            $("#ajaxapi").html(thedataJSON);
        //        },
        //        error:function(){
        //            alert (' bad');
        //        }
        //    });
        //}
        //
        //function testFetchApi() {
        //    fetch('../../apis/api/facility', {
        //        credentials: 'same-origin',
        //        method: 'GET',
        //        headers: new Headers({
        //            'apicsrftoken': <?php //echo js_escape($_SESSION['api_csrf_token']); ?>
        //        })
        //    })
        //    .then(response => response.json())
        //    .then(data => {
        //        let dataJSON = JSON.stringify(data);
        //        document.getElementById('fetchapi').innerHTML = dataJSON;
        //    })
        //    .catch(error => console.error(error))
        //}
        //
        //function testFetchPtApi(pid) {
        //    fetch('../../apis/api/patient/'+pid+'/insurance/primary', {
        //        credentials: 'same-origin',
        //        method: 'GET',
        //        headers: new Headers({
        //            'apicsrftoken': <?php //echo js_escape($_SESSION['api_csrf_token']); ?>
        //        })
        //    })
        //        .then(response => response.json())
        //        .then(data => {
        //            let dataJSON = JSON.stringify(data);
        //            document.getElementById('fetchptapi').innerHTML = dataJSON;
        //        })
        //        .catch(error => console.error(error))
        //}
        //
        //$(function (){
        //    testAjaxApi('3'); // first facility in openemr has id 3 :)
        //    testFetchApi();
        //    testFetchPtApi(1);
        //});
    </script>


</head>

<?php

// CALL the api via a local jquery ajax call
//  See above testAjaxApi() function for details.
//echo "<b>local jquery ajax call:</b><br>";
//echo "<div id='ajaxapi'></div>";
//echo "<br><br>";


// CALL the api via a local fetch call
//  See above testFetchApi() function for details.
//echo "<b>local facility fetch call:</b><br>";
//echo "<div id='fetchapi'></div>";
//echo "<br><br>";

// CALL the api via a local fetch call
//  See above testFetchPtApi() function for details.
//echo "<b>local pt fetch call:</b><br>";
//echo "<div id='fetchptapi'></div>";
//echo "<br><br>";


// CALL the api via route handler
//  This allows same notation as the calls in the api (ie. '/api/facility'), but
//  is limited to get requests at this time.
//use OpenEMR\Common\Http\HttpRestRouteHandler;
//
//require_once(dirname(__FILE__) . "/../../_rest_config.php");
//$gbl = RestConfig::GetInstance();
//$gbl::setNotRestCall();
//// below will return as json
//echo "<b>api via route handler call returning json:</b><br>";
//echo HttpRestRouteHandler::dispatch($gbl::$ROUTE_MAP, '/api/facility', "GET", 'direct-json');
//echo "<br><br>";
//// below will return as php array
//echo "<b>api via route handler call returning php array:</b><br>";
//echo print_r(HttpRestRouteHandler::dispatch($gbl::$ROUTE_MAP, '/api/facility', "GET", 'direct'));
//echo "<br><br>";


// CALL the underlying service that is used by the api
//use OpenEMR\Services\FacilityService;
//
//echo "<b>fac service call:</b><br>";
//echo json_encode((new FacilityService())->getAll());
//echo "<br><br>";

// CALL the underlying service that is used by the api
use OpenEMR\Services\PatientService;

echo "<b>pt service call:</b><br>";
$pat = new PatientService();


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
//echo json_encode($ins);
//var_dump($ins);
//
//$ins_id = array();

//


// CALL the underlying service that is used by the api
use OpenEMR\Services\InsuranceService;

echo "<b>ins service call:</b><br>";
//echo json_encode(new InsuranceService())->getAll(1)));

//$ins = [];
$ins = new InsuranceService();
//echo json_encode($ins);
//var_dump($ins);
//
//$ins_id = array();
$ins_array = $ins->getOne(16295, 'primary');
//
echo "provider ";
echo $ins_array['provider'];
echo "<br>";
echo "policy number ";
echo $ins_array['policy_number'];
echo "<br>";



// CALL the underlying controller that is used by the api
//use OpenEMR\RestControllers\FacilityRestController;
//
//echo "<b>controller call:</b><br>";
//echo json_encode((new FacilityRestController())->getAll());
//echo "<br><br>";
?>
</html>
