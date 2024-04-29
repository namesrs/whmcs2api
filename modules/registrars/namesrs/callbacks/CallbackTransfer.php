<?php

if($status == 2000)
{
  $admin = getAdminUser();
  if($substatus == 2001)
  {
    logModuleCall(
      'nameSRS',
      'callback_transfer_success',
      json_encode($json,JSON_PRETTY_PRINT),
      'Main status = 2000, substatus == 2001, domain = '.$req['domain']
    );

    /** @var  $api  RequestSRS */
  	$api->domainName = $domainname;
    $domain = $api->searchDomain(); // it will update expiration date, next due date and registration date

    domainStatus($req['domain_id'], 'Active');

    //Send notification to customer
  	$postData = array(
   		'messagename' => 'Domain Transfer Completed',
  		'id' =>$req['domain_id']);
  	$results = localAPI('SendEmail', $postData, $admin);

    // Custom code to trigger hook when domain successfully transferred
    $postData = array(
      'domainid' => $req['domain_id']
    );
    $apiresults = localAPI('GetClientsDomains', $postData, $admin);
    $domainDetails = $apiresults['domains']['domain'][0];
    run_hook("DomainTransferCompleted", array("domainId" => $req['domain_id'], "domain" => $domainDetails['domainname'], "registrationPeriod" => $domainDetails['regperiod'], "expiryDate" => $domainDetails['expirydate'], "registrar" => $domainDetails['registrar']));
    // EOF custom code
  }
  elseif($substatus == 2004 OR $substatus == 4998)
  {
    // transfer failed
    logModuleCall(
      'namesrs',
      'callback_transfer_failed',
      json_encode($json,JSON_PRETTY_PRINT),
      'Main status = 2000, substatus = '.$substatus.', domain = '.$req['domain']
    );
    domainStatus($req['domain_id'], 'Cancelled');

    //Send notification to customer
  	$postData = array(
   		'messagename' => 'Domain Transfer Failed',
  		'id' =>$req['domain_id']);
  	$results = localAPI('SendEmail', $postData, $admin);
  }
  else
  {
    logModuleCall(
      'nameSRS',
      'callback_transfer_unknownError',
      $req['domain'],
      'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
    );
    emailAdmin("NameSRS Status", array(
      'domain_name' => $req['domain'],
      'orderType' => 'Domain Transfer',
      'status' => $substatus_name,
      'errors' => $substatus_name
    ));
    logSentry('Unknown error in Callback for Transfer = Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain'], $json);
  }
}
elseif($status == 300)
{
  // transferred away
  logModuleCall(
    'nameSRS',
    'callback_transfer_away',
    json_encode($json,JSON_PRETTY_PRINT),
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Transferred Away');
}
elseif($status == 4000)
{
  //Transfer failed because authcode is bad or empty
  logModuleCall(
    'nameSRS',
    'callback_transfer_authcode',
    json_encode($json,JSON_PRETTY_PRINT),
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Cancelled');

  //Send notification to customer
	$postData = array(
 		'messagename' => 'Domain Transfer Failed',
		'id' =>$req['domain_id']);
	$results = localAPI('SendEmail', $postData, $admin);
}
elseif($status == 1)
{
  // Pending
  logModuleCall(
    'nameSRS',
    'callback_transfer_pending',
    json_encode($json,JSON_PRETTY_PRINT),
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Pending Transfer');
}
else
{
  logModuleCall(
    'nameSRS',
    'callback_transfer_unknownError',
    $req['domain'],
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  emailAdmin("NameSRS Status", array(
    'domain_name' => $req['domain'],
    'orderType' => 'Domain Transfer',
    'status' => $substatus_name,
    'errors' => $substatus_name
  ));
  logSentry('Unknown error in Callback for Transfer = Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain'], $json);
}
