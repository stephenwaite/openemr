<?php
/**
 *
 * @package OpenEMR
 * @link    http://www.open-emr.org
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

 require_once "../../../../globals.php";

 use OpenEMR\Common\Acl\AclMain;
 use OpenEMR\Common\Csrf\CsrfUtils;
 use OpenEMR\Common\Twig\TwigContainer;
 use OpenEMR\Modules\Dorn\ConnectorApi;
 use OpenEMR\Core\Header; //this is needed along with setupHeader() to get the pop up to appear

$tab = "orders";

if (!AclMain::aclCheckCore('admin', 'users')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Edit/Add Procedure Provider")]);
    exit;
}

if (!empty($_POST)) {
    if (isset($_POST['SubmitButton'])) { //check if form was submitted
        $datas = ConnectorApi::searchLabs($_POST['form_labName'], $_POST['form_phone'], $_POST['form_fax'], $_POST['form_city'], $_POST['form_state'], $_POST['form_zip'], $_POST['form_active'], $_POST['form_connected']);
        if ($datas == null) {
            $datas = [];
        }
    }
}
?>
<html>
<head>
        <?php Header::setupHeader(); ?>
        <link rel="stylesheet" href="../../../../../public/assets/bootstrap/dist/css/bootstrap.min.css">
    </head>
<title> <?php echo xlt("DORN Orders"); ?>  </title>
<script>

</script>
<body>
    <div class="row"> 
        <div class="col">
            <?php
                require '../templates/navbar.php';
            ?>
        </div>
    </div>
    <div class="row"> 
        <div class="col">
            <h1><?php echo xlt("DORN - Lab Orders"); ?></h1>        
        </div>
    </div>


</body>
</html>
