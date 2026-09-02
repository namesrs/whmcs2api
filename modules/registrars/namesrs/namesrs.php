<?php
/*
*
* NameISP Web Service
* Author: www.nameisp.com - support@nameisp.com
*
*/
include_once __DIR__."/version.php";
//include_once __DIR__."/vendor/autoload.php"; only needed for Sentry which is currently not used (but used to be)
use WHMCS\Database\Capsule as Capsule;
use WHMCS\Exception\Module\InvalidConfiguration as InvalidConfig;
use WHMCS\Carbon;
use WHMCS\Domain\Registrar\Domain;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Results\ResultsList as TldResultsList;

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
    ///"callback_reported" => array( "Type" => "yesno", "Description" => "Is the webhook (callback) URL reported to NameSRS ?" ),
    "DNSSEC" => array( "Type" => "yesno", "Description" => "Display the DNSSEC Management functionality in the domain details" ),
    "show_registrant" => array( "Type" => "yesno", "FriendlyName" => "Show owner details", "Description" => "Display the Registrant Management functionality in the domain details" ),
    "owner_change" => array( "Type" => "yesno", "FriendlyName" => "Enable owner transfer", "Description" => "Enable/disable ability to change registrant details - reachable only if 'Show owner' is ON" ),
    "show_mail_forward" => array( "Type" => "yesno", "FriendlyName" => "Show e-mail forwarding settings", "Description" => "Display the E-mail Forwarding functionality in the domain details" ),
    "DNS_id" => array( "Type" => "text", "Size" => "20", "FriendlyName" => "DNS id", "Description" => "ID of your DNS template in NameSRS to be used for every new domain registration/transfer" ),
    "sync_due_date" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Enable NextDueDate synchronization", "Description" => "Enable/disable automatic sync/update of Next Due Date every time you access domain details" ),
    "include_stacktrace" => array( "Type" => "yesno", "Description" => "Include stacktrace in each API request" ),
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
    "ENABLE_NOTIFY_UNK_STATUS" => array( "Type" => "yesno", "Default" => "1", "FriendlyName" => "Notify about unmapped domain status", "Description" => "Notify Admins by e-mail when the domain status from NameSRS has no corresponding meaning in WHMCS" ),
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
 * @return array $buttonarray an array custom buttons
 */
function namesrs_ClientAreaCustomButtonArray($params)
{
  /**
   * @var $pdo PDO
   */
  /*$pdo = Capsule::connection()->getPdo();
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
  }*/

  $buttonarray = array();
  if($params["show_mail_forward"] == "on") $buttonarray["Set E-mail forwarding"] = "setEmailForwarding";
	if($params["show_registrant"] == "on") $buttonarray["Registrant details"] = "setContactDetails";
	if($params["DNSSEC"] == "on")	$buttonarray["DNSSEC Management"] = "dnssec";

	return $buttonarray;
}

// declare the buttons we inject in ADMIN area
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
    logModuleCall(
      'nameSRS',
      'Trying to get EPP code for "'.$api->domainName.'" but not allowed by Admin',
      json_encode($params,JSON_PRETTY_PRINT),
      ''
    );
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
    logModuleCall(
      'nameSRS',
      $e->getMessage(),
      '',
      $e->getTrace(),
    );
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

function namesrs_GetDomainInformation($params)
{
  try
  {
    $api = new RequestSRS($params);
    $domain = $api->searchDomain();
    logModuleCall(
      'nameSRS',
      'GetDomainInformation('.$api->domainName.')',
      '',
      $domain
    );

    $nameservers = [];
    if(is_array($domain['nameservers']))
    {
      foreach ($domain['nameservers'] as $index => $ns)
      {
        $nameservers['ns' . ($index + 1)] = $ns['nameserver'];
        if ($index >= 4) break; // WHMCS supports max 5
      }
    }

    $status_id = key($domain['status']);
    $substatus_id = key($domain['substatus']);
    switch ($status_id)
    {
      case 200:
        $status = Domain::STATUS_ACTIVE;
        break;
      case 300:
      case 10000:
        $status = Domain::STATUS_ARCHIVED;
        break;
      case 400:
        $status = Domain::STATUS_INACTIVE;
        break;
      case 500:
        switch ($substatus_id)
        {
          case 503:
            $status = Domain::STATUS_PENDING_DELETE;
            break;
          case 504:
            $status = Domain::STATUS_EXPIRED;
            break;
        }
        break;
      case 900:
        $status = Domain::STATUS_SUSPENDED;
        break;
      default:
        switch ($substatus_id)
        {
          case 204:
          case 205:
          case 902:
            $status = Domain::STATUS_INACTIVE;
            break;
          case 403:
          case 4666:
            $status = Domain::STATUS_SUSPENDED;
            break;
        }
    }

    $result = (new Domain)
      ->setDomain($domain['domainname'])
      ->setNameservers($nameservers)
      ->setTransferLock($domain['transferlock'] > 0)
      ->setTransferLockExpiryDate(null)
      ->setRestorable(false)
      //->setIdProtectionStatus($response['addons']['hasidprotect']) not provided by the API
      ->setDnsManagementStatus(TRUE)
      //->setEmailForwardingStatus($response['addons']['hasemailforwarding']) not provided by the API
      ->setIsIrtpEnabled(in_array($domain['tld']['tld'], ['com']))
      ->setIrtpOptOutStatus(TRUE)
      ->setIrtpTransferLock($domain['domainlock'] > 0)
      //->IrtpTransferLockExpiryDate(null) not provided by the API
      //->setDomainContactChangePending(FALSE) not provided by the API
      ->setPendingSuspension($status == 403)
      //->setDomainContactChangeExpiryDate($response['status']['expires']) not provided by the API
      ->setRegistrantEmailAddress($domain['owner']['email'])
      ->setIrtpVerificationTriggerFields(
        [
          'Registrant' => [
            'First Name',
            'Last Name',
            'Organization Name',
            'Email',
          ],
        ]
    );
    if($domain['renewaldate']) $result->setExpiryDate(Carbon::createFromFormat('Y-m-d', substr($domain['renewaldate'], 0, 10)));
    if($status) $result->setRegistrationStatus($status);
    else adminError("UNK_STATUS",
      'Unmatched domain status from NameSRS',
      'NameSRS returned status "'.$domain['status'][$status_id].'" () and substatus "'.$domain['substatus'][$substatus_id].'" ('.$substatus_id.') for domain "'.$domain['domainname'].'" and there is no corresponding status value in WHMCS.'
    );
    return $result;
  }
  catch (Exception $e)
  {
    // More details
    $msg = $e->getMessage();
    logModuleCall(
      'nameSRS',
      'GetDomainInformation('.$api->domainName.')',
      $msg,
      $domain
    );
    if (substr($msg, 0, 6) == '(2003)') $msg = 'This domain is either not registered with us or currently being transferred';
    return array(
      'error' => 'NameSRS: '.$msg,
    );
  }
}

// called from our button in ADMIN area
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
    logModuleCall(
      'nameSRS',
      $e->getMessage(),
      $e->getTraceAsString(),
      ''
    );
    return $e->getMessage();
  }
}

// WHMCS can call this through CRON
function namesrs_Sync($params)
{
  logModuleCall(
    'nameSRS',
    'Syncing '.$params['domain'],
    '',
    ''
  );
  try
  {
    $api = new RequestSRS($params);
    $domain = $api->request('GET','/domain/whmcs', Array('domainname' => $api->domainName));
  }
  catch (Exception $e)
  {
    logModuleCall(
      'nameSRS',
      $e->getMessage(),
      $e->getTraceAsString(),
      ''
    );
    return array('error' => $e->getMessage());
  }
  if(is_array($domain))
  {
    $status_id = $domain['status'];
    return array(
      'active' => in_array($status_id, array(200, 201, 202)),
      'cancelled' => in_array($status_id, array(501, 502, 505, 506)),
      'transferredAway' => $status_id === 300,
      'expirydate' => substr($domain['renewaldate'],0,10),
    );
  }
  else return array('error' => 'Could not sync domain '.$params['domain']);
  /* Upcoming feature
  if($params['callback_reported'] == 'on')
  {
    // no need to PULL - registrar will PUSH through webhook/callback when needed
    logModuleCall(
      'nameSRS',
      'No need to PULL domain status of "'.$params['domain'].'" through CRON - will use webhook instead',
      '',
      ''
    );
    return array('namesrs' => TRUE);
  }
  else
  {
    $result = Capsule::select("SELECT EXISTS(SELECT 1 FROM tbldomains WHERE registrar = 'namesrs' AND synced = 0) AS cnt");
    if(is_array($result) AND count($result) AND $result[0]->cnt > 0)
    {
      // fetch information for all domains of this account from NameSRS
      logModuleCall(
        'nameSRS',
        'Fetching status for all domains of the account at once from NameSRS',
        '',
        ''
      );
      try
      {
        $api = new RequestSRS($params);
        $domains = $api->syncDomain();
      }
      catch (Exception $e)
      {
        logModuleCall(
          'nameSRS',
          $e->getMessage(),
          $e->getTraceAsString(),
          ''
        );
        return array('error' => $e->getMessage());
      }
      if(is_array($domains))
      {
        // update DB for each domain

        // mark all domains as "synchronized"
        Capsule::update("UPDATE tbldomains SET synced = 1 WHERE registrar = 'namesrs'");
        return array('namesrs' => TRUE); // result should NOT be empty array or WHMCS will consider this as an error
      }
      else
      {
        logModuleCall(
          'nameSRS',
          'No domains returned from NameSRS when trying to PULL "'.$params['domain'].'"',
          '',
          ''
        );
        return array('error' => 'Could not sync domain '.$params['domain']);
      }
    }
    else
    {
      // all domains have already been synchronized
      logModuleCall(
        'nameSRS',
        'Webhook is probably not active but we have already PULL-ed status for all domains on the account from NameSRS',
        $params['domain'],
        ''
      );
      return array('namesrs' => TRUE);
    }
  }
  */
}

// called by our button in ADMIN area
function namesrs_domain_sync($params)
{
  //Api request status
  $api = new RequestSRS($params);
  $domain = $api->searchDomain();
	if(is_array($domain))	return 'success';
	else return 'Invalid domain';
}

// called when registering/renewing/transferring domain - to ensure that WHMCS does not sell below the cost from NameSRS
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
      logModuleCall(
        'nameSRS',
        'Could not get domain selling price from WHMCS',
        '',
        [
          'domainid' => $params['domainid'],
          'domain' => $api->domainName,
        ]
      );
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
      logModuleCall(
        'nameSRS',
        'Missing price class',
        '',
        [
          'TLD' => $params['tld'],
        ]
      );
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
      logModuleCall(
        'nameSRS',
        'Could not get the current TLD price',
        '',
        [
          'TLD' => $params['tld'],
          'operation' => $operation,
          'pricelist' => $result['pricelist']['domains'][$params['tld']],
        ]
      );
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
        logModuleCall(
          'nameSRS',
          'No exchange rate for SEK was set in the module config',
          '',
          [
            'operation' => $operation,
            'params' => $params,
          ]
        );
        return Array('error' => 'NameSRS: No exchange rate for SEK was set in the module config');
      }
      $min_price = $cost['SEK'] * $exchange_rate * ($params['exchange_rate'] > 0 ? $params['exchange_rate'] : 1);
      $min_currency = 'SEK';
    }
    if($price < $min_price)
    {
      logModuleCall(
        'nameSRS',
        'The selling price '.$price.' '.$user_currency.' is less than the cost '.$min_price.' '.$min_currency,
        '',
        [
          'operation' => $operation,
          'params' => $params,
        ]
      );
      return Array('error' => 'NameSRS: The selling price '.$price.' '.$user_currency.' is less than the cost '.$min_price.' '.$min_currency);
    }
  }
  return true;
}

// Get TLD Pricing for the Registrar TLD & Pricing Sync Utility.
function namesrs_GetTldPricing($params)
{
  try
  {
    $api = new RequestSRS($params);
    $response = $api->request('GET',"/economy/pricelist/", [
      'print' => 1,
      'skiprules' => 1,
    ]);
    logModuleCall(
      'nameSRS',
      'Getting prices for all TLDs',
      '',
      $response
    );

    $results = new TldResultsList();

    $tldPricing = $response['pricelist']['domains'] ?? [];
    foreach ($tldPricing as $extension => $pricing)
    {
      $buffer = [];
      foreach($pricing as $tier => $types)
      {
        foreach($types as $priceDetails)
        {
          $buffer[$priceDetails['desc']] = [
            'currency' => $priceDetails['currency'],
            'price' => $priceDetails['price'], // excluding VAT
          ];
        }
      }
      $item = (new ImportItem)
        ->setExtension($extension)
        ->setMinYears(1)
        ->setMaxYears(10)
        ->setRegisterPrice($buffer['Registration']['price'])
        ->setRenewPrice($buffer['Renew']['price'])
        ->setTransferPrice($buffer['Transfer']['price'])
        ->setCurrency($buffer['currency']);
        //->setEppRequired(true); pricing endpoint does not provide this information - only "get TLD rules" per each TLD

      $results[] = $item;
    }

    return $results;
  }
  catch (Exception $e)
  {
    logModuleCall(
      'nameSRS',
      'Could not get prices for all TLDs',
      $e->getMessage(),
      $response
    );
    return array(
      'error' => 'NameSRS: '.$e->getMessage(),
    );
  }
}

// update domain status in WHMCS - called both before and after the webhooks
function domainStatus($domain_id, $status)
{
  $command = "UpdateClientDomain";
  $admin = getAdminUser();
  $values = [];
  $values["domainid"] = $domain_id;
  $values['status'] = $status;
  localAPI($command, $values, $admin);
}

// send an email message to the first found WHMCS administrator
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

if( php_sapi_name() != 'cli' ) include dirname(__FILE__).'/install.php';
