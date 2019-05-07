<?php
/*
*
* NameISP Web Service 
* Author: www.nameisp.com - support@nameisp.com
*
*/


//
//  WHMCS functions
//
require_once("lib/Tools.php");
require_once("lib/Request.php");

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Database\Capsule as Capsule; 

function namesrs_getConfigArray() 
{
	$configarray = array(
	  "API_key" => array( "Type" => "text", "Size" => "65", "Description" => "Enter your API key here", "FriendlyName" => "API key" ),
	  "Base_URL" => array( "Type" => "text", "Size" => "25", "Default" => "api.domainname.systems", "Description" => "Hostname for API endpoints", "FriendlyName" => "Base URL"),	 
	  "AutoExpire" => array( "Type" => "yesno", "Size" => "20", "Description" => "Do not use NameISP's auto-renew feature. Let WHMCS handle the renew","FriendlyName" =>"Auto Expire"),
    "DNSSEC" => array( "Type" => "yesno", "Description" => "Display the DNSSEC Management functionality in the domain details" ), 
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
  $buttonarray = array(
	 "Set E-mail forwarding" => "setEmailForwarding",
	 "Contact details" => "contact_details"
	);
	if ( isset($params["domainid"]) ) $domainid = $params["domainid"];
  else if ( !isset($_REQUEST["id"]) ) 
  {
    $params = $GLOBALS["params"];
		$domainid = $params["domainid"];
  }
  else $domainid = $_REQUEST["id"];
  $result = select_query('tbldomains', 'idprotection,domain', array ('id' => $domainid));
  $data = mysql_fetch_array ($result); 	
  
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

function namesrs_GetNameservers($params) 
{	
	$request = createRequest($params);
	try
	{
	  $domain = $request->searchDomain();
	  $ns = $domain['nameservers'];

  	# Put your code to get the nameservers here and return the values below
  	$values["ns1"] = $ns[0]['nameserver'];
  	$values["ns2"] = $ns[1]['nameserver'];
  	$values["ns3"] = $ns[2]['nameserver'];
  	$values["ns4"] = $ns[3]['nameserver'];
  	$values["ns5"] = $ns[4]['nameserver'];
  	$values["success"] = TRUE;

	  return $values;
	}
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_SaveNameservers($params) 
{
	$request = createRequest($params);
	try
	{
	  $request->saveNameservers();
	  return array('success' => true);
	}
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_GetDNS($params) 
{
	$request = createRequest($params);
	try
	{
	  return $request->mapToWHMCS($request->getDNSrecords());
	}
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_SaveDNS($params) 
{		
	$request = createRequest($params);
	try
	{
	  if(is_array($params['dnsrecords'])) 
	  {
	    $i = 0;
  	  while($i<count($params['dnsrecords']))
  	  {
  	    $item = &$params['dnsrecords'][$i];
	      $record = Array(
	        'domainname' => $params['domainname'],
	        'name' => $item['hostname'].($item['hostname']!='' ? '.' : '').$params['domainname'],
	        'content' => $item['address'],
	        'ttl' => 3600,
	        'prio' => (int)$item['priority'],
	        'recordid' => $item['recid']
	      );
	      switch($item['type'])
	      {
	        case 'URL':
	          $record['type'] = 'REDIRECT';
	          $record['redirecttype'] = 301;
	          break;
	        case 'FRAME':
	          $record['type'] = 'REDIRECT';
	          $record['redirecttype'] = 'frame';
	          break;
	        case 'MXE':
	          // create A and then MX
	          $record['type'] = 'A';
	          $mailer = 'mxe-'.strtr($item['address'],':.','--');
	          $params['dnsrecords'][] = Array(
    	        'domainname' => $params['domainname'],
    	        'name' => $item['hostname'].($item['hostname']!='' ? '.' : '').$params['domainname'],
    	        'content' => $mailer.'.'.$params['domainname'],
    	        'ttl' => 3600,
    	        'prio' => (int)$item['priority'],
    	        'type' => 'MX'
	          );
	        default:
	          $record['type'] = $item['type'];
	      }
  	    if($item['recid'])
  	    {
  	      if($item['address']!='')
  	      {
    	      // update existing record
    	      $request->updateDNSrecord($record);
    	    }
    	    else
    	    {
    	      // delete a record
    	      $request->deleteDNSrecord(Array(
    	        'domainname' => $params['domainname'],
    	        'recordid' => $item['recid']
    	      ));
    	    }
  	    }
  	    elseif($item['address']!='')
  	    {
  	      // add new record
  	      $request->addDNSrecord($record);
  	    }
 	      $i++;
  	  }
	  }
	  return array('success' => 'success');
	}
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_setEmailForwarding($params) 
{
	if ( isset($params["original"]) ) 
	{
    $params = $params["original"];
  }
	$request = createRequest($params);
	$error = false;
	$success = false;
  if ( isset($_REQUEST["cmdRemove"]) ) 
  {
    try
    {
      $request->deleteDNSrecord(Array(
        'domainname' => $params['domainname'],
        'recordid' => $_REQUEST['item_del']
      ));
      $success = true;
	  }
    catch (Exception $e) 
    {
      $error = $e->getMessage();
    }
	}
	if(isset($_REQUEST['cmdSave']))
	{
  	try
  	{
  	  // update existing records
      if(is_array($_REQUEST['item'])) foreach($_REQUEST['item'] as $key=>&$item)
      {
        $new_to = trim($item['to']);
        if($new_to!='')
        {
  	      $record = Array(
  	        'domainname' => $params['domainname'],
  	        'name' => $item['from'],
  	        'content' => $new_to,
  	        'ttl' => 1,
  	        'type' => 'MAILFORWARD',
  	        'recordid' => $key
  	      );
  	      $request->updateDNSrecord($record);
  	    }
      }
      // add new record
      $new_from = preg_replace('/@[^@]*$/','',trim($_REQUEST['new_from']));
      $new_to = trim($_REQUEST['new_to']);
      if($new_from!='' AND preg_match('/[^@]+@[^@]+/',$new_to))
      {
	      $record = Array(
	        'domainname' => $params['domainname'],
	        'name' => $new_from.'@'.$params['domainname'],
	        'content' => $new_to,
	        'ttl' => 1,
	        'type' => 'MAILFORWARD'
	      );
	      $id = $request->addDNSrecord($record);
      }
      $success = true;
  	}
    catch (Exception $e) 
    {
      $error = $e->getMessage();
    }
  }
  try
  {
    $result = $request->getDNSrecords();
    $forward = Array();
    foreach($result as &$item)
      if($item['type']=='MAILFORWARD')
        $forward[$item['recordid']] = Array(
          'from' => $item['name'],
          'to' => $item['content'],
          'recordid' => $item['recordid']
        );
  }
  catch (Exception $e) 
  {
    $error = $e->getMessage();
  }
  return array(
    'templatefile' => "mailforward",
    'vars' => array('error' => $error, 'successful' => $success, 'forward' => $forward, 'domain'=>$params['domainname']),
  );
}

/**
 * Handle the DNSSEC management page of a domain
 *
 * @param array $params common module parameters
 *
 * @return array an array with a template name
 */
function namesrs_dnssec($params) 
{
	$origparams = $params;
	if ( isset($params["original"]) ) $params = $params["original"];
	$error = false;
	$successful = false;
	$request = createRequest($params);
	if(isset($_POST["cmdPublish"]))
	{
	  $dnskey = trim($_POST['dnskey']);
	  $flags = trim($_POST['flags']);
	  $alg = trim($_POST['alg']);
	  if($dnskey=='') $error = 'Missing DNS key';
	  elseif($flags==0) $error = 'Missing flags';
	  elseif($alg==0) $error = 'Missing algorithm';
	  else
	  {
    	try
    	{
    	  $response = $request->publishDNSSEC(Array('dnskey'=>$dnskey,'flags'=>$flags,'alg'=>$alg));
    	  $success = true;
    	}
      catch (Exception $e) 
      {
        $error = $e->getMessage();
      }
    }
  }
	if(isset($_POST["cmdUnpublish"]))
	{
  	try
  	{
  	  $response = $request->unpublishDNSSEC();
  	  $success = true;
  	}
    catch (Exception $e) 
    {
      $error = $e->getMessage();
    }
  }
  try
  {
    $domain = $request->searchDomain();
    $status = $domain['signedzone'];
  }
  catch (Exception $e) 
  {
    if(!$error) $error = $e->getMessage(); // do not overwrite existing error
  }
	return array(
		'templatefile' => "dnssec",
		'vars' => array('error' => $error, 'successful' => $success, 'status' => (int)$status)
	);
} 

/**
 * Handle the ID Protection (whoisprivacy) of a domain name
 *
 * @param array $params common module parameters
 *
 * @return array an array with a template name and some variables
 */
function namesrs_whoisprivacy($params) 
{
	if ( isset($params["original"]) ) 
	{
    $params = $params["original"];
  }
	$request = createRequest($params);
	$error = false;
  if ( isset($_REQUEST["idprotection"]) ) 
  {
    try
    {
	    $request->protectWHOIS($_REQUEST["idprotection"] == 'enable');
	    return false;
	  }
    catch (Exception $e) 
    {
      $error = $e->getMessage();
    }
	}
	$protected = 0;
	try
	{
	  $protected = $request->getWHOISprotect();
	}
  catch (Exception $e) 
  {
    if(!$error) $error = $e->getMessage(); // do not overwrite existing error
  }
  return array(
    'templatefile' => "whoisprivacy",
    'vars' => array('error' => $error, 'protected' => $protected),
  );
}  

function namesrs_GetContactDetails($params) 
{
	$request = createRequest($params);
	try
	{
  	$result = $request->searchDomain();
  	$values["Registrant"]["First Name"] = $result['owner']["firstname"];
  	$values["Registrant"]["Last Name"]  = $result['owner']["lastname"];
  	$values["Admin"]["First Name"] 		  = $result['admin']['firstname'];
  	$values["Admin"]["Last Name"] 		  = $result['admin']['lastname'];
  	$values["Tech"]["First Name"] 		  = $result['tech']['firstname'];
  	$values["Tech"]["Last Name"] 		    = $result['tech']['lastname'];
  	logActivity("WHMCS GetContactDetails"); 
  	return $values;
  }
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_contact_details($params) 
{
	if ( isset($params["original"]) ) 
	{
    $params = $params["original"];
  }
	$request = createRequest($params);
	$error = false;
	$success = false;
	$phone = Array();
  if(isset($_POST['cmdSave']))
  {
  	try
  	{
  	  $values['contactid'] = (int)$_POST['contactid'];
  	  $values['city'] = trim($_POST['city']);
  	  $values['zipcode'] = trim($_POST['zip']);
  	  $values['address1'] = trim($_POST['address']);
  	  $values['phone'] = trim('+'.$_POST['country-calling-code-phone'].'.'.str_replace(' ','',$_POST['phone']));
  	  $values['email'] = trim($_POST['email']);
  	  $request->updateContacts($values);
  	}
    catch (Exception $e) 
    {
      $error = $e->getMessage();
    }
  }
  try
  {
    $domain = $request->searchDomain();
    $owner = &$domain['owner'];
    $cid = $owner['contactid'];
    $firstname = $owner['firstname'];
    $lastname = $owner['lastname'];
    $orgname = $owner['organization'];
    $orgnum = $owner['orgnr'];
    $address = $owner['address1'];
    $zipcode = $owner['zipcode'];
    $city = $owner['city'];
    $country = $owner['countrycode'];
    $phone = explode('.',$owner['phone']);
    $email = $owner['email'];
  }
  catch (Exception $e) 
  {
    if(!$error) $error = $e->getMessage(); // do not overwrite existing error
  }
  return array(
    'templatefile' => "contactdetails",
    'vars' => array('error' => $error, 'successful' => $success, 'cid' => $cid, 'first_name' => $firstname, 'last_name' => $lastname,
      'org_name' => $orgname, 'org_num' => $orgnum, 'country' => $country, 'city' => $city, 'zip' => $zipcode,
      'address' => $address, 'phone' => $phone[1], 'email' => $email
    ),
  );
}

function namesrs_GetEPPCode($params) 
{
	$request = createRequest($params);	
	try
	{
	  $code = $request->getEPPCode();
	  return array('eppcode' => $code);
	}
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_RegisterDomain($params) 
{
  $data = (is_array($params['original']) ? $params['original'] : $params);
	try
	{
	  // Different TLDs use different names for the field which holds VAT number 
	  // we can not cope with that so we check for the field "Tax ID" which should be defined for all relevant domains in "/whmcs/resources/domains/additionalfields.php"
		if(trim($data['additionalfields']['Tax ID'])=='')
		{
		  // this domain does not require OrgNumber - so try to get it from the client profile
			$result = Capsule::select('SELECT cfv.value FROM `tblcustomfields` AS cf 
                        JOIN `tblcustomfieldsvalues` AS cfv  ON (cfv.fieldid = cf.id AND cfv.relid = ?) 
                        WHERE cf.fieldname LIKE ? AND cf.type = "client"',Array($data['userid'],'orgnr|%'));
  		$data['orgnr'] = $result[0]->value;
		}
		else $data['orgnr'] = $data['additionalfields']['Tax ID'];
		if(empty($data['orgnr'])) $data['orgnr'] = '000000-0000';
      // return array('error' => 'Organization number / Personal Number is required. Please update contact details in profile.');
    if(empty($data['firstname'])
            || empty($data['lastname'])
            || empty($data['address1'])
            || empty($data['postcode'])
            || empty($data['city'])
            || empty($data['countrycode'])
            || empty($data['phonenumberformatted'])
            || empty($data['email']))
    {
      return array('error' => 'Contact details are required. Please update contact details in profile.');
    }
		
  	$request = createRequest($data);

	  $owner_id = $request->makeContact(); // Always create new contact - NameISP will remove duplicates on their side
	  $req_id = $request->registerDomain($data['regperiod'],$owner_id,$data['idprotection']); 
	  return array('success' => true);
	}
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_TransferDomain($params) 
{
	$request = createRequest($params);
	try
	{
	  return $request->transferDomain();  
	}
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_RenewDomain($params) 
{
	$request = createRequest($params);
	try
	{
	  $request->updateDomain();
	  return array('success' => true);
	}
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_saveRegistrarLock($params) 
{
	$request = createRequest($params);
	try
	{
    $request->domainLock($params['lockenabled'] == 'locked' ? 1 : 0); 
	  return array('success' => true);
  }
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_GetRegistrarLock($params) 
{
	$request = createRequest($params);
	try
	{
  	$result = $request->searchDomain();
  	return $result['transferlock'] ? 'locked' : 'unlocked';
  }
  catch (Exception $e) 
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}


// =======================
// Unsupported functions
// =======================

function namesrs_IDProtectToggle($params)
{
	$request = createRequest($params);
	try
	{
	  $request->protectWHOIS((bool)$params["protectenable"]);
	  return array('success' => true);
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

/**
 * Check Domain Availability.
 *
 * Determine if a domain or group of domains are available for
 * registration or transfer.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain availability check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function namesrs_CheckAvailability($params)
{
	$request = createRequest($params);

  // availability check parameters
  $searchTerm = ($params['punyCodeSearchTerm']!='' ? $params['punyCodeSearchTerm'] : $params['searchTerm']);
  $isIdnDomain = (bool) $params['isIdnDomain'];
  $premiumEnabled = (bool) $params['premiumEnabled'];

  try 
  {
    $response = $request->request('POST','/domain/searchdomain', Array(
      'domainname' => array_map(
        'search_domains',
        $params['tlds'],
        array_fill(0,count($params['tlds']),$searchTerm)
      )
    ));
    $results = new ResultsList();
    if(is_array($response['parameters'])) foreach ($response['parameters'] as $key=>&$domain) 
    {
      // Instantiate a new domain search result object
      $searchResult = new SearchResult(preg_replace('/\.'.$domain['tld'].'$/','',$key), $domain['tld']);

      // Determine the appropriate status to return
      if ($domain['status'] == 'available') $status = SearchResult::STATUS_NOT_REGISTERED;
      elseif ($domain['statis'] == 'unavailable') $status = SearchResult::STATUS_REGISTERED;
      elseif ($domain['statis'] == 'reserved') $status = SearchResult::STATUS_RESERVED;
      else $status = SearchResult::STATUS_TLD_NOT_SUPPORTED;
      $searchResult->setStatus($status);

      // Return premium information if applicable
      if ($domain['isPremiumName']) 
      {
        $searchResult->setPremiumDomain(true);
        $searchResult->setPremiumCostPricing(
          array(
            'register' => $domain['premiumRegistrationPrice'],
            'renew' => $domain['premiumRenewPrice'],
            'CurrencyCode' => 'USD',
          )
        );
      }

      $results->append($searchResult);
    }
    return $results;
  } 
  catch (Exception $e) 
  {
    return array('error' => $e->getMessage());
  }
}

function search_domains($tld,$sld)
{
  return $sld.$tld;
}

/**
 * Get Domain Suggestions.
 *
 * Provide domain suggestions based on the domain lookup term provided.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain suggestions check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function namesrs_GetDomainSuggestions($params)
{
  // nameISP does not provide suggestions
  $results = new ResultsList();
  return $results;
}

?>
