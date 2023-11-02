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
 use OpenEMR\Core\Header;
 use OpenEMR\Modules\Dorn\ClaimRevDornApiConector;
 use OpenEMR\Modules\Dorn\models\CreateRouteFromPrimaryViewModel;
 use OpenEMR\Modules\Dorn\DisplayHelper;
 use OpenEMR\Modules\Dorn\LabRouteSetup;

 $labGuid = "";
if (!empty($_GET)) {
    if (!CsrfUtils::verifyCsrfToken($_GET["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    $labGuid = $_POST["form_labGuid"];
    $routeData = CreateRouteFromPrimaryViewModel::loadByPost($_POST);
    $apiResponse =  ClaimRevDornApiConector::CreateRoute($routeData);
    $ppid = LabRouteSetup::createProcedureProviders($apiResponse->labName, $routeData->npi, $routeData->labGuid);
    if ($ppid > 0) {
        $isLabSetup = LabRouteSetup::CreateDornRoute($apiResponse->labName, $apiResponse->routeGuid, $apiResponse->labGuid, $ppid);
    }
} else {
    if (!empty($_GET)) {
        $labGuid = $_REQUEST['labGuid'];
    }
}

if (!AclMain::aclCheckCore('admin', 'users')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Edit/Add Procedure Provider")]);
    exit;
}
$primaryInfos = ClaimRevDornApiConector::GetPrimaryInfos("");

?>
<html>
<head>
        <?php Header::setupHeader(['opener']);?>
        <link rel="stylesheet" href="../../../../../public/assets/bootstrap/dist/css/bootstrap.min.css">
    </head>
    <body>
    <form method='post' name='theform' action="route_edit.php?labGuid=<?php echo attr_url($labGuid); ?>&csrf_token_form=<?php echo attr_url(CsrfUtils::collectCsrfToken()); ?>">
        <div class="row">
            <div class="col-sm-6">
                <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                <input type="hidden" name="form_labGuid" value="<?php echo attr($labGuid); ?>" />
            </div>
        </div>        

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="form_primaries"><?php echo xlt("Select NPI") ?>:</label>
                    <select id="form_primaries" name="form_primaries">
                        <?php
                        foreach ($primaryInfos as $pInfo) {
                            ?>
                            <option <?php echo DisplayHelper::SelectOption(attr($_POST['form_primaries']), attr($pInfo->npi)) ?>  value='<?php echo attr($pInfo->npi) ?>' ><?php echo text($pInfo->primaryName); ?> (<?php echo text($pInfo->npi); ?>)</option>
                            <?php
                        }
                        ?>
                    </select>
                </div>
            </div>

       </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="form_labAcctNumber"><?php echo xlt("Lab Account Number") ?>:</label>
                    <input type="text" class="form-control" id="form_labAcctNumber" name="form_labAcctNumber" value="<?php echo isset($_POST['form_labAcctNumber']) ? attr($_POST['form_labAcctNumber']) : '' ?>"/>
                </div>              
            </div>
       </div>
       <div class="row">
            <div class="col-sm-6">
                <button type="submit" name="SubmitButton" class="btn btn-primary"><?php echo xlt("Save") ?></button>
                <?php
                    echo $apiResponse->responseMesssage;
                ?>
            </div>
        </div>
     
    </body>
</html>
