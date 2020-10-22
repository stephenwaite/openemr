<?php
/** **************************************************************************
 *	QUEST/DATALOADER.PHP
 *
 *	Copyright (c)2013 - Williams Medical Technology, Inc.
 *
 *	This program is licensed software: licensee is granted a limited nonexclusive
 *  license to install this Software on more than one computer system, as long as all
 *  systems are used to support a single licensee. Licensor is and remains the owner
 *  of all titles, rights, and interests in program.
 *  
 *  Licensee will not make copies of this Software or allow copies of this Software 
 *  to be made by others, unless authorized by the licensor. Licensee may make copies 
 *  of the Software for backup purposes only.
 *
 *	This program is distributed in the hope that it will be useful, but WITHOUT 
 *	ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
 *  FOR A PARTICULAR PURPOSE. LICENSOR IS NOT LIABLE TO LICENSEE FOR ANY DAMAGES, 
 *  INCLUDING COMPENSATORY, SPECIAL, INCIDENTAL, EXEMPLARY, PUNITIVE, OR CONSEQUENTIAL 
 *  DAMAGES, CONNECTED WITH OR RESULTING FROM THIS LICENSE AGREEMENT OR LICENSEE'S 
 *  USE OF THIS SOFTWARE.
 *
 *  @package quest
 *  @subpackage dataLoader
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 * 
 *************************************************************************** */
require_once("../../../interface/globals.php");
require_once("{$GLOBALS['srcdir']}/quest/QuestLoaderHL7v2.php");

$ignoreAuth=true; // signon not required!!

// ENVIRONMENT SETUP
if (defined('STDIN')) {
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

// GET DEFAULT SITE ID
$query = "SELECT title FROM list_options ";
$query .= "WHERE list_id = 'Quest_Site_Identifiers' AND is_default = 1 LIMIT 1";
if ($dummy = sqlQuery($query)) $GLOBALS['lab_quest_siteid'] = $dummy['title'];

$DEBUG = ($_POST['form_debug']) ? $_POST['form_debug'] : $_GET['debug'];
$BROWSER = ($_POST['browser']) ? $_POST['browser'] : $_GET['browser'];
$SITE = ($_SESSION['site_id']) ? $_SESSION['site_id'] : $_GET['site'];
$GROUP = ($GLOBALS["lab_quest_facilityid"]) ? $GLOBALS["lab_quest_facilityid"] : $_GET['group'];

// VALIDATE INSTALL
$invalid = "";
if (!$GLOBALS["lab_quest_enable"]) $invalid .= "Quest Interface Not Enabled\n";
if (!$GLOBALS["lab_quest_facilityid"]) $invalid .= "No Quest Facility Identifier\n";
if (!$GLOBALS["lab_quest_siteid"]) $invalid .= "No Sending Clinic Identifier\n";
if (!$GLOBALS["lab_quest_username"]) $invalid .= "No Quest Username\n";
if (!$GLOBALS["lab_quest_password"]) $invalid .= "No Quest Password\n";

// DEFINE FILE NAMES & LOCATIONS
$ordcode = "../cdc/".$GROUP."/ORDCODE_".$GROUP.".TXT";
if (!file_exists($ordcode)) $invalid .= "File ORDCODE_".$GROUP.".TXT Missing\n";
$profile = "../cdc/".$GROUP."/PROFILE_".$GROUP.".TXT";
if (!file_exists($profile)) $invalid .= "File PROFILE_".$GROUP.".TXT Missing\n";
$aoe = "../cdc/".$GROUP."/AOE_".$GROUP.".TXT";
if (!file_exists($aoe)) $invalid .= "File "."AOE_".$GROUP.".TXT Missing\n";
$methodology = "../cdc/".$GROUP."/METHODOLOGY_".$GROUP.".TXT";
if (!file_exists($methodology)) $invalid .= "File METHODOLOGY_".$GROUP.".TXT Missing\n";
$specimenreq = "../cdc/".$GROUP."/SPECIMENREQ_".$GROUP.".TXT";
if (!file_exists($specimenreq)) $invalid .= "File SPECIMENREQ_".$GROUP.".TXT Missing\n";
$specimenstab = "../cdc/".$GROUP."/SPECIMENSTAB_".$GROUP.".TXT";
if (!file_exists($specimenstab)) $invalid .= "File SPECIMENSTAB_".$GROUP.".TXT Missing\n";
$specimenvol = "../cdc/".$GROUP."/SPECIMENVOL_".$GROUP.".TXT";
if (!file_exists($specimenvol)) $invalid .= "File SPECIMENVOL_".$GROUP.".TXT Missing\n";
$transport = "../cdc/".$GROUP."/TRANSPORT_".$GROUP.".TXT";
if (!file_exists($transport)) $invalid .= "File TRANSPORT_".$GROUP.".TXT Missing\n";

if ($invalid) { ?>
<html><head></head><body>
<h1>Quest Diagnostic Error</h1>
The data load process has terminated unexpectedly!!
<br/><br/>
For assistance with this problem please contact:
<br/><br/>
<a href="http://www.williamsmedtech.com/page4/page4.html" target="_blank"><b>Williams Medical Technologies Support</b></a>
<br/><br/>
<table style="border:2px solid red;padding:20px"><tr><td style="white-space:pre;color:red"><h3>DEBUG OUTPUT</h3><?php echo $invalid ?></td></tr></table>
</body></html>
<?php
exit; 
}

?>
<html><head></head><body>
<h2>LOAD PROCESSING STARTED: <?php echo date('Y-m-d H:i:s')?></h2>
<br/>
This process may take several hours to complete and it can not be terminated once it has been started.
<?php 
    @apache_setenv('no-gzip', 1);
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
    ob_implicit_flush(1);
    
    echo str_repeat(" ", 2048), "\n"; flush(); 
?>

<pre>

<?php
$count = 0;

$loader = new Loader_HL7v2( $ordcode, FALSE );
$count = $loader->load(TRUE); // TRUE for ORDCODE, PROFILE, AOE, initial DOS file
echo "\nORDCODE RECORDS: ".$count;
flush();

$loader = new Loader_HL7v2( $profile, FALSE );
$count = $loader->load(TRUE); // TRUE for ORDCODE, PROFILE, AOE, initial DOS file
echo "\nPROFILE RECORDS: ".$count;
flush();

$loader = new Loader_HL7v2( $aoe, FALSE );
$count = $loader->load(TRUE); // TRUE for ORDCODE, PROFILE, AOE, initial DOS file
echo "\nAOE RECORDS: ".$count;
flush();

$loader = new Loader_HL7v2( $methodology, FALSE );
$count = $loader->load(TRUE); // TRUE for ORDCODE, PROFILE, AOE, initial DOS file
echo "\nMETHODOLOGY RECORDS: ".$count;
flush();

$loader = new Loader_HL7v2( $specimenreq, FALSE );
$count = $loader->load(FALSE); // TRUE for ORDCODE, PROFILE, AOE, initial DOS file
echo "\nSPECIMENREQ RECORDS: ".$count;
flush();

$loader = new Loader_HL7v2( $specimenstab, FALSE );
$count = $loader->load(FALSE); // TRUE for ORDCODE, PROFILE, AOE, initial DOS file
echo "\nSPECIMENSTAB RECORDS: ".$count;
flush();

$loader = new Loader_HL7v2( $specimenvol, FALSE );
$count = $loader->load(FALSE); // TRUE for ORDCODE, PROFILE, AOE, initial DOS file
echo "\nSPECIMENVOL RECORDS: ".$count;
flush();

$loader = new Loader_HL7v2( $transport, FALSE );
$count = $loader->load(FALSE); // TRUE for ORDCODE, PROFILE, AOE, initial DOS file
echo "\nTRANSPORT RECORDS: ".$count;
flush();

?>

</pre>
<br/><br/>
<h2>LOAD PROCESSING COMPLETE: <?php echo date('Y-m-d H:i:s')?></h2>

</body></html>