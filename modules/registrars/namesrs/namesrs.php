<?php
/*
*
* NameISP Web Service
* Author: www.nameisp.com - support@nameisp.com
*
*/
include __DIR__."/version.php";
use WHMCS\Database\Capsule as Capsule;

/** @var  $pdo PDO */
$pdo = Capsule::connection()->getPdo();
define('API_HOST',"api.domainname.systems");

function namesrs_getConfigArray()
{
	$configarray = array(
	  "Description" => array("Type" => "System", "Value" => "version ".VERSION.' ('.STAMP.')'),
	  "API_key" => array( "Type" => "text", "Size" => "65", "Description" => "Enter your API key here", "FriendlyName" => "API key" ),
	  "Base_URL" => array( "Type" => "text", "Size" => "25", "Default" => API_HOST, "Description" => "Hostname for API endpoints", "FriendlyName" => "Base URL"),
	  "AutoExpire" => array( "Type" => "yesno", "Size" => "20", "Description" => "Do not use NameISP's auto-renew feature. Let WHMCS handle the renew","FriendlyName" =>"Auto Expire"),
    "DNSSEC" => array( "Type" => "yesno", "Description" => "Display the DNSSEC Management functionality in the domain details" ),
	);
	if($_SERVER['HTTP_HOST'] == 'whmcs.nameisp.com') $configarray['Test_mode'] = array(
    "Type" => "yesno", "Size" => "20", "Description" => "Use the fake NameISP backend instead of the real API", "FriendlyName" => "Test mode"
  );
	return $configarray;
}

// PARAMS
// AutoExpire, DNSSEC = on, regtype = Register, regperiod = 1, additionalfields = array()

/**
 * Provide custom buttons (whoisprivacy, DNSSEC Management, E-mail forward) for domains and change of registrant button for certain domain names on client area
 *
 * @param array $params common module parameters
 *
 * @return array $buttonarray an array custum buttons
 */
function namesrs_ClientAreaCustomButtonArray($params)
{
  /**
   * @var $pdo PDO
   */
  $pdo = Capsule::connection()->getPdo();

  $buttonarray = array(
	 "Set E-mail forwarding" => "setEmailForwarding",
	 "Registrant details" => "setContactDetails"
	);
	if ( isset($params["domainid"]) ) $domainid = $params["domainid"];
  else if ( !isset($_REQUEST["id"]) )
  {
    $params = $GLOBALS["params"];
		$domainid = $params["domainid"];
  }
  else $domainid = $_REQUEST["id"];
  $result = $pdo->query('SELECT idprotection,domain FROM tbldomains WHERE id = '.(int)$domainid);
  $data = $result->fetch(PDO::FETCH_ASSOC);

  if ($data)
  {
    if($data["idprotection"]) $buttonarray["WHOIS Privacy"] = "whoisprivacy";
    /*
	  if(preg_match('/[.]ca$/i', $data["domain"])) $buttonarray[".CA Registrant WHOIS Privacy"] = "whoisprivacy_ca";
  	if(preg_match('/[.]ca$/i', $data["domain"])) $buttonarray[".CA Change of Registrant"] = "registrantmodification_ca";
	  if(preg_match('/[.]it$/i', $data["domain"])) $buttonarray[".IT Change of Registrant"] = "registrantmodification_it";
	  if(preg_match('/[.]ch$/i', $data["domain"])) $buttonarray[".CH Change of Registrant"] = "registrantmodification_tld";
	  if(preg_match('/[.]li$/i', $data["domain"])) $buttonarray[".LI Change of Registrant"] = "registrantmodification_tld";
	  if(preg_match('/[.]se$/i', $data["domain"])) $buttonarray[".SE Change of Registrant"] = "registrantmodification_tld";
	  if(preg_match('/[.]sg$/i', $data["domain"])) $buttonarray[".SG Change of Registrant"] = "registrantmodification_tld";
	  */
	}

	if($params["DNSSEC"] == "on")	$buttonarray["DNSSEC Management"] = "dnssec";

	return $buttonarray;
}

require_once "lib/Request.php";
require_once "lib/NameServers.php";
require_once "lib/DNSrecords.php";
require_once "lib/DNSSEC.php";
require_once "lib/Contact.php";
require_once "lib/Privacy.php";
require_once "lib/Lock.php";
require_once "lib/DomainRegister.php";
require_once "lib/DomainTransfer.php";
require_once "lib/DomainRenew.php";
require_once "lib/Search.php";

function namesrs_GetEPPCode($params)
{
	try
	{
    $api = new RequestSRS($params);
    $code = $api->request('POST','/domain/genauthcode',Array('domainname' => $api->domainName));
	  return array('eppcode' => $code['authcode']);
	}
  catch (Exception $e)
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_GetEmailForwarding($params)
{
  return array('error' => 'Not supported through this interface');
}

function namesrs_SaveEmailForwarding($params)
{
  return array('error' => 'Not supported through this interface');
}

function namesrs_RegisterNameserver($params)
{
  return Array('error' => 'Not supported');
}

function namesrs_ModifyNameserver($params)
{
  return Array('error' => 'Not supported');
}

function namesrs_DeleteNameserver($params)
{
  return Array('error' => 'Not supported');
}

if( php_sapi_name() != 'cli' ) include dirname(__FILE__).'/install.php';
