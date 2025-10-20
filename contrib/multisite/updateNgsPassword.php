<?php

/*
* php updateNgsPassword <site> <password> <username>
*/
// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_NGS_PASSWORD')) {
    die('Set OPENEMR_ENABLE_NGS_PASSWORD=1 environment variable to enable this script');
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default login password \n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../../interface/globals.php";

use League\Csv\Reader;

use OpenEMR\Common\Crypto\CryptoGen;

$ngsLogin = $argv[2];
$newPassword = $argv[3];

try {
    $autoSftpFlag = sqlQuery("SELECT `gl_value` FROM `globals` WHERE `gl_name` = 'auto_sftp_claims_to_x12_partner'");
} catch (PDOException $e) {
    $autoSftpFlag = false;
}

if (!empty($autoSftpFlag)) {
    try {
        $cryptoGen = new CryptoGen();
        $encryptedPassword = $cryptoGen->encryptStandard($newPassword);
        $x12PartnerId = sqlQuery("SELECT `id` FROM `x12_partners` WHERE `x12_sftp_login` = ?", [$ngsLogin])['id'];
        $sqlStatement = "UPDATE `x12_partners` SET `x12_sftp_pass` = ? WHERE `id` = ?";
        if (sqlStatement($sqlStatement, [$encryptedPassword, $x12PartnerId])) {
            echo "updated NGS Password :) \n";
        } else {
            echo "errored :( \n";
        }
    } catch (Exception $e) {
        // error stuff goes here
        echo ("exception: failed to update NGS Password \n");
    }
} else {
    echo ("autoSftpFlag is not set \n");
}
