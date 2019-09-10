<?php

if($status == 2000)
{
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
    $admin   	= getAdminUser();
    $values   = array();
    $values["domainid"] = $req['domain_id'];
    $values['status'] = 'Active';
    $results 	= localAPI($command, $values, $admin);
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
