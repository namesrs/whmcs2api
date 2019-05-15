<?php
require_once("../../../init.php");
require_once("lib/Tools.php");

use Illuminate\Database\Capsule\Manager as Capsule;

error_reporting(E_ALL);
ini_set('error_reporting', E_ERROR);
ini_set('display_errors', "on");
$isCLI = ( php_sapi_name() == 'cli' );
$lineBreak = $isCLI ? "\n" : "<br>\n";
$pdo = Capsule::connection()->getPdo();

function check($name,$value)
{
	global $lineBreak;

	if($value == true) $ret = "ok";
	  else $ret = "failed";
	echo "- Check ".$name.": ".$ret.$lineBreak;
	if($ret == "failed") die("Please fix the errors and retry".$lineBreak);
}

// E-mail templates
echo $lineBreak."* Creating email templates *";

$usedTemplates = array(
  "EPP Code" => "INSERT INTO tblemailtemplates (id, type, name, subject, message, attachments, fromname, fromemail, disabled, custom, language, copyto, plaintext) 
    VALUES (NULL, 'domain', 'EPP Code', 'New EPP Code for {\$domain_name}', 
      '<p>Dear {\$client_name},</p> <p>A new EPP Code was generated for the domain {\$domain_name}: {\$code}</p> <p>You may transfer away your domain with the new EPP-Code.</p> <p>{\$signature}</p>', 
      '', '', '', '0', '1', '', '', '0');",
  "NameSRS Status" => "INSERT INTO tblemailtemplates (id, type, name, subject, message, attachments, fromname, fromemail, disabled, custom, language, copyto, plaintext) 
    VALUES (NULL, 'domain', 'NameSRS Status', '{\$orderType} {\$domain_name}: {\$status}', 
    '<p>Dear {\$client_name},</p> <p>we received following status for your domain {\$domain_name} ({\$orderType}): {\$status}</p> <p>{\$errors}</p> <p> </p> <p>{\$signature}</p>', 
    '', '', '', '0', '1', '', '', '0');"
);

$result = Capsule::select("select username from tbladmins where disabled=0 limit 1");
$adminUser = $result[0]->username;
$values["type"] = "domain";
$results = localAPI("getemailtemplates", $values, $adminUser);

foreach($results["emailtemplates"]["emailtemplate"] as $key => $value)
{
  $existingTemplates[$value["name"]] = true;
}
foreach($usedTemplates as $name => $body)
{
  if(!$existingTemplates[$name])
  {
    try
    {
      $pdo->query($body);
    }
    catch (PDOException $e)
    {
      echo "Error writing email-templates (".$name."): ". $e->getMessage().$lineBreak;
    }
  }
}

// SQL tables
echo $lineBreak."* Creating SQL tables".$lineBreak;

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
try
{
  $pdo->query($q);
}
catch (PDOException $e)
{
  echo $e->getMessage().$lineBreak;
}

echo "- Creating tblnamesrshandles table".$lineBreak;
$q = 'CREATE TABLE IF NOT EXISTS `tblnamesrshandles` (
  `type` tinyint NOT NULL, 
  `whmcs_id` int(10) NOT NULL, 
  `namesrs_id` bigint NOT NULL, 
  PRIMARY KEY (`whmcs_id`,`type`), 
  KEY `namesrs_id` (`namesrs_id`,`type`) 
)';
try
{
  $pdo->query($q);
}
catch (PDOException $e)
{
  echo $e->getMessage().$lineBreak;
}

echo "- Creating mod_namesrssession table".$lineBreak;
$q = 'CREATE TABLE IF NOT EXISTS `mod_namesrssession` (
  `account` varchar(255) NOT NULL, 
  `sessionId` varchar(255) NOT NULL, 
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
  UNIQUE KEY `account` (`account`), 
  KEY `date` (`timestamp`) 
)';
try
{
  $pdo->query($q);
}
catch (PDOException $e)
{
  echo $e->getMessage().$lineBreak;
}

echo "- Creating custom client field OrgNr".$lineBreak;
$q = 'INSERT INTO tblcustomfields(type,fieldname,fieldtype,required,showorder,showinvoice) 
  SELECT "client","orgnr|Organization Number / Personal Number","text","on","on","on" FROM tblcustomfields AS t2 
  WHERE NOT EXISTS(SELECT 1 FROM tblcustomfields AS t3 WHERE fieldname LIKE "orgnr|%")';
try
{
  $pdo->query($q);
}
catch (PDOException $e)
{
  echo $e->getMessage().$lineBreak;
}

?>
