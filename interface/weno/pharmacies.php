<?php

/*
 *  @package OpenEMR
 *  @link    http://www.open-emr.org
 *  @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  @copyright Copyright (c) 2020 Sherwin Gaddis <sherwingaddis@gmail.com>
 *  @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Rx\Weno\PharmaciesImport;

//ensure user has proper access
if (!AclMain::aclCheckCore('admin', 'super')) {
    echo xlt('Not Authorized');
    exit;
}

if ($_POST) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token"])) {
        CsrfUtils::csrfNotVerified();
    }
    
}

// todo: make pharmacy service
$pharmacies = sqlStatement("SELECT d.id, d.name, d.ncpdp, a.line1, a.city, a.state, a.zip, " .
"p.area_code, p.prefix, p.number FROM pharmacies AS d " .
"LEFT OUTER JOIN addresses AS a ON a.foreign_id = d.id " .
"LEFT OUTER JOIN phone_numbers AS p ON p.foreign_id = d.id " .
"AND p.type = 2 " .
"ORDER BY a.state, a.city, d.name, p.area_code, p.prefix, p.number");

?>
<html>
<head>
    <title><?php echo xlt('WENO Pharmacies'); ?></title>
    <?php Header::setupHeader(); ?>
</head>
<body class="body_top">
<div class="container"><br><br>
    <h1><?php print xlt("WENO Pharmacies") ?></h1>
    <form name="pharmacyinfo" method="post" action="pharmacies.php" onsubmit="return top.restoreSession()">
        <input type="hidden" name="csrf_token" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>">
        <input type="submit" value="<?php echo xla('Import Pharmacies'); ?>" id="import_weno" class="btn_primary">
    <table class="table">
        <thead>
            <th></th>
            <th><?php print xlt('Name'); ?></th>
            <th><?php print xlt('Address'); ?></th>
            <th><?php print xlt('City'); ?></th>
            <th><?php print xlt('State'); ?></th>
            <th><?php print xlt('Zip'); ?></th>
            <th><?php print xlt('NCPDP'); ?></th>
        </thead>
        <?php
        $i = 0;
        foreach ($pharmacies as $pharmacy) {
              print "<tr>";
              print "<td><input type='hidden' name='location" . $i . "[]' value='" . attr($pharmacy['id']) . "'></td>";
              print "<td>" . text($pharmacy["name"]) . "</td><td>" . text($pharmacy['line1'])
                   . "</td><td>" . text($pharmacy['city']) . "</td><td>" . text($pharmacy['state'])
                   . "</td><td>" . text($pharmacy['zip']) . "</td><td>" . text($pharmacy['ncpdp']);
              print "</tr>";
              ++$i;
        }
        ?>
    </table>
    </form>
</div>

<script>
    function() {
        $("button").click(function(){
            $(".oe-spinner").css("visibility", "visible");
        });

        $('.wait').click(function(){
                $('.wait').addClass('button-wait');
        });
    }
</script>
</body>
</html>
