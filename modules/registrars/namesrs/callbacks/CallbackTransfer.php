<?php

if($status == 2000)
{
  $admin = getAdminUser();
  if($substatus == 2001)
  {
    logModuleCall(
      'nameSRS',
      'callback_transfer_success',
      $json,
      'Main status = 2000, substatus == 2001, domain = '.$req['domain']
    );

    /** @var  $api  RequestSRS */
  	$api->domainName = $domainname;
    $domain = $api->searchDomain(); // it will update expiration date, next due date and registration date

    $command  = "UpdateClientDomain";
    $values   = array();
    $values["domainid"] = $req['domain_id'];
    $values['status'] = 'Active';
    $results 	= localAPI($command, $values, $admin);

    //Send notification to customer
  	$postData = array(
   		'messagename' => 'Domain Transfer Completed',
  		'id' =>$req['domain_id']);
  	$results = localAPI('SendEmail', $postData, $admin);  
  }
  elseif($substatus == 2004 OR $substatus == 4998)
  {
    // transfer failed
    logModuleCall(
      'namesrs',
      'callback_transfer_failed',
      $json,
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
  }
}
elseif($status == 300)
{
  // transferred away
  logModuleCall(
    'nameSRS',
    'callback_transfer_away',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Transferred Away');
}
elseif($status == 4000)
{
  //Transfer failed because authcode is bad or empy
  logModuleCall(
    'nameSRS',
    'callback_transfer_authcode',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
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
}
