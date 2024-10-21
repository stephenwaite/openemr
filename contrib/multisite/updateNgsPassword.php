<?php

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";

use OpenEMR\Common\Crypto\CryptoGen;

try {
    $autoSftpFlag = sqlQuery("SELECT `gl_value` FROM `globals` WHERE `gl_name` = 'auto_sftp_claims_to_x12_partner'");
} catch (PDOException $e) {
    $autoSftpFlag = false;
}

if (!empty($autoSftpFlag)) {
    try {
        $cryptoGen = new CryptoGen();
        $encryptedPassword = $cryptoGen->encryptStandard($argv[2]);
        $sqlStatement = "UPDATE `x12_partners` SET `x12_sftp_pass` = ? WHERE `x12_sftp_login` = 'N532@N532'";
        sqlStatement($sqlStatement, [$encryptedPassword]);
        echo ("updated NGS Password \n");
    } catch (Exception $e) {
        // error stuff goes here
        echo ("failed to update NGS Password \n");
    }
} else {
    echo ("autoSftpFlag is not set \n");
}
