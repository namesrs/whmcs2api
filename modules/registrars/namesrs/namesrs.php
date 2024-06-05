<?php
/*
*
* NameISP Web Service
* Author: www.nameisp.com - support@nameisp.com
*
*/
include_once __DIR__."/version.php";
include_once __DIR__."/vendor/autoload.php";
use WHMCS\Database\Capsule as Capsule;
use WHMCS\Exception\Module\InvalidConfiguration as InvalidConfig;

\Sentry\init([
  'dsn' => 'https://2492d31026d3f16e2ca2969d03618b64@o475096.ingest.us.sentry.io/4507118220083200',
  'release' => (string)VERSION,
  'attach_stacktrace' => TRUE,
  'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event 
  {
    if ($hint !== null && $hint->exception !== null)
    {
      $errFile = $hint->exception->getFile();
      if (!(str_contains($errFile, '/modules/registrars/namesrs/') 
        OR str_contains($errFile, '/modules/addons/namesrs_price/')
        OR str_contains($errFile, '/modules/servers/namesrsowner/')
      ))
      {
        return NULL;
      }
    }
    return $event;
  },
]);


/** @var  $pdo PDO */
$pdo = Capsule::connection()->getPdo();
define('API_HOST',"api.domainname.systems");

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

function namesrs_getConfigArray()
{
  $result = Capsule::select("select code from tblcurrencies where `default` limit 1");
  $base_currency = $result[0]->code;

	$configarray = array(
	  "Description" => array("Type" => "System", "Value" => "version ".VERSION.' ('.STAMP.')'),
	  "API_key" => array( "Type" => "password", "Size" => "65", "Description" => "Enter your API key here", "FriendlyName" => "API key" ),
	  "Base_URL" => array( "Type" => "text", "Size" => "25", "Default" => API_HOST, "Description" => "Hostname for API endpoints", "FriendlyName" => "Base URL"),
	  "AutoExpire" => array( "Type" => "yesno", "Size" => "20", "Description" => "Do not use NameSRS's auto-renew feature. Let WHMCS handle the renew","FriendlyName" =>"Auto Expire"),
    "DNSSEC" => array( "Type" => "yesno", "Description" => "Display the DNSSEC Management functionality in the domain details" ),
    "DNS_id" => array( "Type" => "text", "Size" => "20", "FriendlyName" => "DNS id", "Description" => "ID of your DNS template in NameSRS to be used for every new domain registration/transfer" ),
    "owner_change" => array( "Type" => "yesno", "FriendlyName" => "Enable owner transfer", "Description" => "Enable/disable ability to change registrant details" ),
    "sync_due_date" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Enable NextDueDate synchronization", "Description" => "Enable/disable automatic sync/update of Next Due Date every time you access domain details" ),
    //"custom_orgnr" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Use custom OrgID field", "Description" => "Use a custom field (whose name is specified below) for Organization ID instead of WHMCS default Tax ID field" ),
    "orgnr_field" => array( "Type" => "text", "Size" => "65", "Default" => "orgnr|%", "FriendlyName" => "OrgNr field name", "Description" => "The name of the custom field in user details that is used as Company/Person ID. You can use a POSIX regular expression if you need to handle multiple field names - begin the RegExp with ^ (to distinguish from a regular MySQL search pattern) and then use alternation symbol | (pipe) as a logical OR" ),
    "cost_check" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Automatic cost check", "Description" => "Checking if the selling price is below the domain cost for Register, Renew, Transfer domain" ),
    "exchange_rate" => array( "Type" => "text", "Size" => "10", "Default" => "1.00", "FriendlyName" => "Exchange rate for ".$base_currency."/SEK", "Description" => "How many ".$base_currency." can be bought for 1.00 SEK"),
    "allow_epp_code" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Allow clients to get the EPP code themselves", "Description" => "Clients can request the EPP code of their domain name" ),
    "default_pending" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Set domain status to PENDING until the webhook comes", "Description" => "Domain registration, renewal and transfer-in are asynchronous - so by default domain is immediately put in PENDING status in WHMCS until the webhook from NameSRS comes with the actual status" ),
    "ENABLE_NOTIFY_API_ERROR" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about errors from NameSRS API backend", "Description" => "Notify Admins by e-mail when the NameSRS server returns error for any API call" ),
    "ENABLE_NOTIFY_EMPTY_CALLBACK" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about empty callbacks", "Description" => "Notify Admins by e-mail when the API callback sends no data" ),
    "ENABLE_NOTIFY_UNK_REQ_TYPE" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about unknown REQUEST_TYPE", "Description" => "Notify Admins by e-mail when the API callback sends unrecognized REQUEST_TYPE" ),
    "ENABLE_NOTIFY_MISSING_REQID" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about missing request ID", "Description" => "Notify Admins by e-mail when the API callback sends a request ID which is missing in WHMCS queue" ),
    "ENABLE_NOTIFY_NO_OBJ_NAME" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about missing object name", "Description" => "Notify Admins by e-mail when the API callback does not provide a domain name" ),
    "ENABLE_NOTIFY_NO_CUSTOM_FIELD" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about missing CUSTOM_FIELD", "Description" => "Notify Admins by e-mail when the API callback does not provide the CUSTOM_FIELD value" ),
    "ENABLE_NOTIFY_DOMAIN_NOT_FOUND" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about non-existent domain name", "Description" => "Notify Admins by e-mail when the API callback refers a domain name which does not exist in WHMCS" ),
    "ENABLE_NOTIFY_UNK_TEMPLATE" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about unrecognized template name", "Description" => "Notify Admins by e-mail when the API callback uses unrecognized template name" ),
    "ENABLE_NOTIFY_EXCEPTION" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about PHP run-time errors", "Description" => "Notify Admins by e-mail when there is a run-time error in the PHP code" ),
	);
	if($_SERVER['HTTP_HOST'] == 'whmcs.namesrs.com') $configarray['Test_mode'] = array(
    "Type" => "yesno", "Size" => "20", "Description" => "Use the fake NameISP backend instead of the real API", "FriendlyName" => "Test mode"
  );
	return $configarray;
}

function namesrs_config_validate($params)
{
  if(trim($params['API_key']) == '') throw new InvalidConfig('Missing API key');
  elseif(strlen(trim($params['API_key'])) < 50) throw new InvalidConfig('Incorrect API key');
  if (trim($params['Base_URL']) != '')
  {
    $result = isValidDomain($params['Base_URL']);
    if ($result !== TRUE) throw new InvalidConfig($result);
  }
  if (trim($params['exchange_rate']) != '')
  {
    if ($params['exchange_rate'] <= 0) throw new InvalidConfig('Exchange rate must be a positive number');
  }
  else
  {
    $result = Capsule::select("select code from tblcurrencies where `default` limit 1");
    $base_currency = $result[0]->code;
    throw new InvalidConfig('Missing exchange rate for '.$base_currency.'/SEK');
  }
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

function namesrs_GetEPPCode($params)
{
  $allow = $params['allow_epp_code'];
  $api = new RequestSRS($params);
  if (!$allow)
  {
    logSentry('Trying to get EPP code for "'.$api->domainName.'" but not allowed by Admin');
    return array(
      'error' => 'Please contact the support to get the EPP code'
    );
  }
	try
	{
    $code = $api->request('POST','/domain/genauthcode',Array('domainname' => $api->domainName));
	  return array('eppcode' => $code['authcode']);
	}
  catch (Exception $e)
  {
    \Sentry\captureException($e);
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
    \Sentry\captureException($e);
    return $e->getMessage();
  }
}

function namesrs_Sync($params)
{
  //Api request status
  try
  {
    $api = new RequestSRS($params);
    $domain = $api->searchDomain();
  }
  catch (Exception $e)
  {
    \Sentry\captureException($e);
    return array('error' => $e->getMessage());
  }
  if(is_array($domain))
  {
    $status_id = key($domain['status']);
    return array(
      'active' => in_array($status_id, array(200, 201, 202)),
      'cancelled' => in_array($status_id, array(501, 502, 505, 506)),
      'transferredAway' => $status_id === 300,
      'expirydate' => substr($domain['renewaldate'],0,10),
    );
  }
  else return array('error' => 'Could not sync domain '.$params['domain']);
}

function namesrs_domain_sync($params)
{
  //Api request status
  $api = new RequestSRS($params);
  $domain = $api->searchDomain();
	if(is_array($domain))	return 'success';
	else return 'Invalid domain';
}

function namesrs_sale_cost($api,$params,$operation)
{
  if($params['cost_check'])
  {
    // get the price from WHMCS
    $result = Capsule::select("select username from tbladmins where disabled=0 limit 1");
    $admin = is_array($result) && count($result) ? $result[0]->username : '';

    $results = localAPI('GetClientsDomains', array('domainid' => $params['domainid']), $admin);
    if(is_array($results)) $price = $results['domains']['domain'][0]['firstpaymentamount'];
    else
    {
      logSentry('Could not get domain selling price from WHMCS', [
        'domainid' => $params['domainid'],
        'domain' => $api->domainName,
      ]);
      return Array('error' => 'NameSRS: Could not get domain selling price from WHMCS');
    }
    // get user's currency
    $result = Capsule::select("select * from tblcurrencies where id=".(int)$params['currency']);
    $user_currency = $result[0]->code;
    $exchange_rate = $result[0]->default ? 1 : $result[0]->rate;
    if($result[0]->default) $base_currency = $user_currency;
    else
    {
      // get WHMCS base currency
      $result = Capsule::select("select code from tblcurrencies where `default` limit 1");
      $base_currency = $result[0]->code;
    }
    // get the price from NameSRS
    $result = $api->request('GET','/economy/pricelist', Array(
      'skiprules' => 1,
      'pricetypes' => 0,
      'tldname' => $params['tld'],
    ));
    if(!is_array($result['pricelist']['domains'][$params['tld']]))
    {
      logSentry('Missing price class', [
        'TLD' => $params['tld'],
      ]);
      return Array('error' => 'NameSRS: Missing price class');
    }
    $pricing = [];
    foreach($result['pricelist']['domains'][$params['tld']] as $priceClass => $operations)
    {
      foreach($operations as $opName => $opCost) $pricing[$opName] = $opCost;
    }
    $retail = $pricing[$operation];
    if(!is_array($retail))
    {
      logSentry('Could not get the current TLD price', [
        'TLD' => $params['tld'],
        'operation' => $operation,
        'pricelist' => $result['pricelist']['domains'][$params['tld']],
      ]);
      return Array('error' => 'NameSRS: Could not get the current TLD price');
    }
    $cost[$retail['currency']] = $retail['price'];
    if(is_array($retail['currencies'])) foreach($retail['currencies'] as $currency => $values) $cost[$currency] = $values['price'];
    // check if cost < sell price
    $codes = array_keys($cost); // currency codes
    if(in_array($user_currency, $codes))
    {
      $min_price = $cost[$user_currency];
      $min_currency = $user_currency;
    }
    elseif(in_array($base_currency, $codes))
    {
      $min_price = $cost[$base_currency] * $exchange_rate;
      $min_currency = $base_currency;
    }
    else
    {
      if($params['exchange_rate'] <= 0)
      {
        logSentry('No exchange rate for SEK was set in the module config', [
          'operation' => $operation,
          'params' => $params,
        ]);
        return Array('error' => 'NameSRS: No exchange rate for SEK was set in the module config');
      }
      $min_price = $cost['SEK'] * $exchange_rate * ($params['exchange_rate'] > 0 ? $params['exchange_rate'] : 1);
      $min_currency = 'SEK';
    }
    if($price < $min_price)
    {
      logSentry('The selling price '.$price.' '.$user_currency.' is less than the cost '.$min_price.' '.$min_currency, [
        'operation' => $operation,
        'params' => $params,
      ]);
      return Array('error' => 'NameSRS: The selling price '.$price.' '.$user_currency.' is less than the cost '.$min_price.' '.$min_currency);
    }
  }
  return true;
}

function domainStatus($domain_id, $status)
{
  $command = "UpdateClientDomain";
  $admin = getAdminUser();
  $values = [];
  $values["domainid"] = $domain_id;
  $values['status'] = $status;
  localAPI($command, $values, $admin);
}

function emailAdmin($tpl, $fields)
{
  $values = [];
  $values["messagename"] = $tpl;
  $values["mergefields"] = $fields;

  $admin = getAdminUser();
  $r = localAPI("SendAdminEmail", $values, $admin);

  logModuleCall(
    'nameSRS',
    'email_admin',
    "Tried to send email to Admin, don't know if it was delivered",
    ['input' => $values, 'output' => $r]
  );
}

function getAdminUser()
{
  $result = Capsule::select("select username from tbladmins where disabled=0 limit 1");
  return is_array($result) && count($result) ? $result[0]->username : '';
}

function logSentry($message, $context = array())
{
  \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($message,$context): void
  {
    $context['WHMCS_VERSION'] = WHMCS\Application::FILES_VERSION;
    $scope->setContext('WHMCS context', $context);

    \Sentry\captureMessage($message);
    // or: \Sentry\captureException($e);
  });
}

if( php_sapi_name() != 'cli' ) include dirname(__FILE__).'/install.php';
