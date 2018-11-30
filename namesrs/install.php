<?php
require_once("../../../init.php");
require_once("lib/Tools.php");

error_reporting(E_ALL);
ini_set('error_reporting', E_ERROR);
ini_set('display_errors', "on");
$isCLI = ( php_sapi_name() == 'cli' );
$lineBreak = $isCLI ? "\n" : "<br>\n";

function check($name,$value) 
{
	global $lineBreak;	

	if($value==true) $ret =  "ok";
	  else $ret = "failed";
	echo "- Check ".$name.": ".$ret.$lineBreak;
	if($ret=="failed") die("Please fix the errors and retry".$lineBreak);
}

echo $lineBreak."* Check requirements *".$lineBreak;
check("init.php",file_exists("../../../init.php"));
check("registrarfunctions.php",file_exists("../../../includes/registrarfunctions.php"));

echo $lineBreak."* Creating email templates *";
Tools::createEmailTemplates();

echo $lineBreak."* Creating SQL tables".$lineBreak;
echo "- Creating tblnamesrstlds table".$lineBreak;
$q = 'CREATE TABLE IF NOT EXISTS `tblnamesrstlds` (
  `Tld` char(255) NOT NULL, 
  `Threshold` int(11) NOT NULL, 
  `Renew` tinyint(1) NOT NULL, 
  `LocalPresenceRequired` tinyint(1) NOT NULL, 
  `LocalPresenceOffered` tinyint(1) NOT NULL, 
  `AuthCodeRequired` tinyint(1) NOT NULL, 
  `Country` char(255) NOT NULL, 
  UNIQUE KEY `tld` (`Tld`) 
)';
mysql_query($q);
if(mysql_error()) echo mysql_error().$lineBreak;

echo "- Creating tblnamesrsjobs table".$lineBreak;
$q = 'CREATE TABLE IF NOT EXISTS `tblnamesrsjobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT, 
  `last_id` int(11) NOT NULL, 
  `order_id` BIGINT NOT NULL, 
  `method` TINYINT NOT NULL, 
  `request` text NOT NULL, 
  `response` text NOT NULL, 
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
  PRIMARY KEY (`id`), 
  KEY `last_id` (`last_id`), 
  KEY `order_id` (`order_id`) 
)';
mysql_query($q);
if(mysql_error()) echo mysql_error().$lineBreak;

echo "- Creating tblnamesrshandles table".$lineBreak;
$q = 'CREATE TABLE IF NOT EXISTS `tblnamesrshandles` (
  `type` tinyint NOT NULL, 
  `whmcs_id` int(10) NOT NULL, 
  `namesrs_id` bigint NOT NULL, 
  PRIMARY KEY (`whmcs_id`,`type`), 
  KEY `namesrs_id` (`namesrs_id`,`type`) 
)';
mysql_query($q);
if(mysql_error()) echo mysql_error().$lineBreak;

echo "- Creating mod_namesrssession table".$lineBreak;
$q = 'CREATE TABLE IF NOT EXISTS `mod_namesrssession` (
  `account` varchar(255) NOT NULL, 
  `sessionId` varchar(255) NOT NULL, 
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
  UNIQUE KEY `account` (`account`), 
  KEY `date` (`timestamp`) 
)';
mysql_query($q);
if(mysql_error()) echo mysql_error().$lineBreak;

echo "- Creating custom client field OrgNr".$lineBreak;
$q = 'INSERT INTO tblcustomfields(type,fieldname,fieldtype,required,showorder,showinvoice) 
  SELECT "client","orgnr|Organization Number / Personal Number","text","on","on","on" FROM tblcustomfields AS t2 
  WHERE NOT EXISTS(SELECT 1 FROM tblcustomfields AS t3 WHERE fieldname LIKE "orgnr|%")';
mysql_query($q);
if(mysql_error()) echo mysql_error().$lineBreak;

?>