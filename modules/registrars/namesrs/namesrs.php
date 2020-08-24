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
    "DNS_id" => array( "Type" => "text", "Size" => "20", "FriendlyName" => "DNS id", "Description" => "ID of your DNS template in NameSRS to be used for every new domain registration/transfer" ),
    "owner_change" => array( "Type" => "yesno", "FriendlyName" => "Enable owner transfer", "Description" => "Enable/disable ability to change registrant details" ),
	);
	if($_SERVER['HTTP_HOST'] == 'whmcs.namesrs.com') $configarray['Test_mode'] = array(
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

function namesrs_AdminCustomButtonArray($params) 
{
   $buttonarray = array(
 	 	 "DomainStatus" => "domain_status",
		 "DomainSync" => "domain_sync"
	);
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

function namesrs_domain_status($params)
{
  //Api request status
  $api = new RequestSRS($params);
  try
  {
    $result = $api->request('GET', "/request/requestlist", ['domainname' => $params['original']['domainname']]);
    echo '<br>History Status domain: '.$params['original']['domainname'].' <br>';
    foreach ($result['requests'] as $request)
  	{
  		foreach ($request['substatus'] as $status) $substatus .= " ".$status;
  		echo ' Request Date: '.$request['created'].' reqType: '.$request['reqType'].' substatus: <strong>'.$substatus.'</strong>';
  		if($request['error'][0]['desc'] != '') echo 'Request error: <strong style="color:red;">'.$request['error'][0]['desc'].'</strong>';
  		echo '<br>'; 
  		$substatus = '';
  	}
  
    $domain = $api->searchDomain();
    if(is_array($domain)) switch($domain['tldrules']['status'])
    {
      case 200:
      case 201:
        $statusName = 'Active';
        break;
   	  case 300:
        $statusName = 'Pending Transfer';
        break;
   	  case 500:
        $statusName = 'Expired';
        break;
   	  case 503:
        $statusName = 'Redemption';
        break;
   	  case 504:
        $statusName = 'Grace';
        break;
   	  case 2:
   	  case 10:
   	  case 11:
      case 400:
   	  case 4000:
   	  case 4006:
        $statusName = 'Pending';
        break;
   	}
    //Return real status
    echo "<br><br>Domain status: <strong>".$statusName."</strong><br>Expiration Date: ".$domain['expires']."<br>Created Date: ".$domain['created'].'<br><br>';
    return 'success';
  }
  catch(Exception $e)
  {
    return $e->getMessage();
  }
}

function namesrs_domain_sync($params)
{
  //Api request status
  $api = new RequestSRS($params);
  $domain = $api->searchDomain();
	if(is_array($domain))	return 'success';
	else return 'Invalid domain';
}

if( php_sapi_name() != 'cli' ) include dirname(__FILE__).'/install.php';
