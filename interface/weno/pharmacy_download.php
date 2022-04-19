<?php
// WENO Pharmacy Directory Download
// on $url for production site where your credentials are use 
//"https://live.wenoexchange.com/WENOx/GetListResponse.aspx?PharmacyDirectory=yes&EZUser=1";
// provide your WENO Online admin user email and password MD5 hash

require_once (dirname(__FILE__) . '/../globals.php'); 

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Core\Header;

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

$url = "https://live.wenoexchange.com/WENOx/GetListResponse.aspx?PharmacyDirectory=yes&EZUser=1";
$cryptogen = new CryptoGen();
$weno_email = $GLOBALS['weno_online_admin_email'];
$weno_admin_pass = $cryptogen->decryptStandard($GLOBALS['weno_online_admin_password']);
$weno_admin_pass_hash = md5($weno_admin_pass);

$path_to_file = '/tmp/PharmacyDirectory.zip';
unlink($path_to_file);
$out = fopen($path_to_file, 'wb');
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FILE, $out);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "{$weno_email}:{$weno_admin_pass_hash}");
curl_exec($ch);
curl_close($ch);
fclose($out);
$za = new ZipArchive();
$za->open($path_to_file);
$unzipped_file_name = $za->statIndex(0)['name'];
$pos = strpos($unzipped_file_name, 'Error_Status.txt');

?>
<html>
<head>
    <title><?php echo xlt('WENO Pharmacy Download'); ?></title>
    <?php Header::setupHeader(); ?>
</head>
<body class="body_top">
<div class="container"><br><br>
    <h1><?php if ($pos === false) {
        echo "<div class='text-success'><p>" . xlt("WENO Pharmacies downloaded") . "</p></div>";
     } else {
        echo "<div class='text-danger'><p>" . xlt("WENO Pharmacies not downloaded") . "</p></div>";
     }    ?></h1>
</div>
</body>
</html>
