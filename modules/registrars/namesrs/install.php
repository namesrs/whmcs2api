<?php
$isCLI = ( php_sapi_name() == 'cli' );
$lineBreak = $isCLI ? PHP_EOL : "<br>".PHP_EOL;
if( !function_exists("gracefulCoreRequiredFileInclude") )
{
  require_once("../../../init.php");
  error_reporting(E_ALL);
  ini_set('error_reporting', E_ERROR);
  ini_set('display_errors', "on");
}
use Illuminate\Database\Capsule\Manager as Capsule;

/** @var  $pdo  PDO */
$pdo = Capsule::connection()->getPdo();

// E-mail templates
if($isCLI) echo $lineBreak."* Creating email templates *";

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
if($isCLI) echo $lineBreak.$lineBreak."* Creating SQL tables *".$lineBreak;

if($isCLI) echo "- Creating tblnamesrsjobs table".$lineBreak;
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

if($isCLI) echo "- Creating tblnamesrshandles table".$lineBreak;
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

if($isCLI) echo "- Creating mod_namesrssession table".$lineBreak;
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

// "orgnr" is a custom client field - SE/NU registries require ID of the person or EIK/VAT of the company
/* We no longer create this field but take its name from the module config - it is very likely you already have the field defined
if($isCLI) echo "- Creating custom client field OrgNr".$lineBreak;
$q = 'INSERT INTO tblcustomfields(type,fieldname,fieldtype,required,showorder,showinvoice)
  SELECT "client","orgnr|Organization Number / Personal Number","text","on","on","on" FROM dual
  WHERE NOT EXISTS(SELECT 1 FROM tblcustomfields AS t3 WHERE fieldname LIKE "orgnr|%")';
try
{
  $pdo->query($q);
}
catch (PDOException $e)
{
  echo $e->getMessage().$lineBreak;
}
*/
// we use this custom product to charge the customer when the TLD requires a fee to change the domain registrant
if($isCLI) echo "- Creating custom product 'Change registrant'".$lineBreak;
try
{
  // create a product group - WHMCS does not allow products without a group
  $q = 'INSERT INTO tblproductgroups(name,headline,orderfrmtpl,disabledgateways,hidden,`order`,created_at,updated_at)
    SELECT "Domain owner change","Change the registrant of a domain","","",0,1,NOW(),NOW() FROM dual
    WHERE NOT EXISTS(SELECT 1 FROM tblproductgroups AS t3 WHERE name = "Domain owner change")';
  $pdo->query($q);
  $gid = $pdo->lastInsertId();
  if($gid == 0)
  {
    $result = $pdo->query('SELECT id FROM tblproductgroups WHERE name = "Domain owner change"');
    $gid = $result->fetch(PDO::FETCH_NUM)[0];
  }
  if($gid != 0)
  {
    // we do not know all the columns - so we get them from DB and then populate all of them with an empty string,
    // because most of them are NOT NULL
    $q = 'SHOW columns FROM tblproducts';
    $result = $pdo->query($q);
    $values = array();
    while($row = $result->fetch(PDO::FETCH_ASSOC))
    {
      $values["`".$row['Field']."`"] = '""';
    }
    unset($values['`id`']);
    // we set the values we need
    $values['`type`'] = '"other"';
    $values['`gid`'] = $gid;
    $values['`name`'] = '"Change registrant"';
    $values['`description`'] = '"Change the owner of a domain"';
    $values['`paytype`'] = '"onetime"';
    $values['`autosetup`'] = '"payment"';
    $values['`servertype`'] = '"namesrsowner"';
    $values['`hidden`'] = 1;
    $values['`order`'] = 1;
    $values['`created_at`'] = 'NOW()';
    $values['`updated_at`'] = 'NOW()';
    $q = 'INSERT INTO tblproducts('.implode(',',array_keys($values)).') SELECT '.implode(',',$values).' FROM dual
      WHERE NOT EXISTS(SELECT 1 FROM tblproducts AS t3 WHERE name = "Change registrant")';
    $pdo->query($q);
    $pid = $pdo->lastInsertId();
    if($pid == 0)
    {
      $result = $pdo->query('SELECT id FROM tblproducts WHERE name = "Change registrant"');
      $pid = $result->fetch(PDO::FETCH_NUM)[0];
    }
    if($pid != 0)
    {
      // we must create a one-time price for the product - otherwise our hook "OrderProductPricingOverride" will not be called
      // and we need to repeat it for all the currencies
      // So we use this default price as a profit margin for the reseller - the amount will be added on top of the OwnerChange price from the NameSRS API
      $q = 'SHOW columns FROM tblpricing';
      $result = $pdo->query($q);
      $values = array();
      while($row = $result->fetch(PDO::FETCH_ASSOC))
      {
        $values["`".$row['Field']."`"] = '""';
      }
      unset($values['`id`']);
      unset($values['`currency`']);
      $values['`type`'] = '"product"';
      $values['`relid`'] = $pid;
      $values['`monthly`'] = 1;
      $values['`quarterly`'] = -1;
      $values['`semiannually`'] = -1;
      $values['`annually`'] = -1;
      $values['`biennially`'] = -1;
      $values['`triennially`'] = -1;

      $q = 'INSERT INTO tblpricing(currency,'.implode(',',array_keys($values)).') SELECT id,'.implode(',',$values).' FROM tblcurrencies AS c 
        WHERE NOT EXISTS(SELECT 1 FROM tblpricing AS t3 WHERE type="product" AND relid = '.$pid.' AND currency = c.id)';
      $pdo->query($q);
      // we also need a custom field for the product - to store the domain ID and registrant details (as JSON)
      $q = 'INSERT INTO tblcustomfields(type,relid,fieldname,fieldtype,description,fieldoptions,regexpr,adminonly,required,showorder,showinvoice,created_at,updated_at)
        SELECT "product",'.$pid.',"ownerdata","text","Registrant details + domain ID","","","","","","",NOW(),NOW() FROM dual
        WHERE NOT EXISTS(SELECT 1 FROM tblcustomfields AS t3 WHERE type="product" AND relid = '.$pid.')';
      $result = $pdo->query($q);
    }
  }
}
catch (PDOException $e)
{
  echo $e->getTraceAsString().$lineBreak;
  echo $e->getMessage().$lineBreak;
}

// append our definition for additional domain fields (currently only .NO)
if($isCLI) echo $lineBreak."* Adding our custom additional domain fields *".$lineBreak;
$filepath = implode(DIRECTORY_SEPARATOR, array(ROOTDIR,"resources","domains","additionalfields.php"));
if(file_exists($filepath))
{
  $new_comment = "// NameSRS additional domain fields";
  $new_text = "include ROOTDIR.'/modules/registrars/namesrs/additional_domain_fields.php';";
  // modify existing file
  $old = file_get_contents($filepath);
  if ($old === FALSE AND $isCLI) echo "Could not read from \033[96m".$filepath."\033[0m file - you will have to manually add there this text = \033[92m".$new_text."\033[0m";
  else
  {
    if (!strpos($old, "/modules/registrars/namesrs/additional_domain_fields.php"))
    {
      $old = str_replace('<'.'?php',"<"."?php".PHP_EOL.PHP_EOL.$new_comment.PHP_EOL.$new_text.PHP_EOL.PHP_EOL,$old);
      $result = file_put_contents($filepath,$old);
      if ($result === FALSE AND $isCLI) echo "Could not update the file \033[96m".$filepath."\033[0m - you will have to manually add there this text =\033[92m <"."?php ".$new_text."\033[0m";
    }
  }
}
else
{
  // create new file
  $result = file_put_contents($filepath,"<"."?php".PHP_EOL.PHP_EOL.$new_comment.PHP_EOL.$new_text.PHP_EOL.PHP_EOL);
  if ($result === FALSE AND $isCLI) echo "Could not create the file \033[96m".$filepath."\033[0m - you will have to manually create it with the following content =\033[92m <"."?php ".$new_text."\033[0m";
}

if($isCLI) echo $lineBreak;
