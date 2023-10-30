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

 namespace OpenEMR\Modules\Dorn\models;

 class CustomerPrimaryInfoView
 {
    public $accountNumber = "";
    public $npi = "";
    public $primaryId = "";
    public $primaryName = "";
    public $primaryPhone = "";
    public $primaryEmail = "";
    public $primaryAddress1 = "";
    public $primaryAddress2 = "";
    public $primaryCity = "";
    public $primaryState = "";
    public $primaryZipCode = "";

    public function __construct()
    {
    }

    public static function loadByPost($postData)
    {
        $model = new CustomerPrimaryInfoView();
        $model->npi = $postData["form_npi"];
        $model->primaryName = $postData["form_name"];
        $model->primaryPhone = $postData["form_phone"];
        return $model;
    }

 }
?>