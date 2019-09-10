<?php

if($status == 200 OR ($status == 2000 AND $substatus == 2001))
{
  logModuleCall(
    'nameSRS',
    'callback_register_success',
    $json,
    'Main status = '.$status.', substatus = '.$substatus.', domain = '.$req['domain']
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
elseif(in_array((int)$status, Array(2,10,11,4000)))
{
  // processing = nothing to do
  logModuleCall(
    'nameSRS',
    'callback_register_waiting',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Pending Registration');
}
elseif($status == 500 OR $status == 503 OR $status == 504)
{
  // expired
  logModuleCall(
    'nameSRS',
    'callback_register_expired',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  switch($status)
  {
    case 503:
      $statName = 'Redemption';
      break;
    case 504:
      $statName = 'Grace';
      break;
    default:
      $statName = 'Expired'; 
  }
  domainStatus($req['domain_id'], $statName);
}
elseif($status == 300)
{
  // Transferred away
  logModuleCall(
    'nameSRS',
    'callback_register_transferred',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Transferred Away');
}
elseif($status == 2000 AND ($substatus == 4998 OR $substatus == 4999))
{
  // rejected
  logModuleCall(
    'nameSRS',
    'callback_register_rejected',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );

  domainStatus($req['domain_id'], 'Cancelled');
  emailAdmin("NameSRS Status", array(
    'domain_name' => $req['domain'],
    'orderType' => 'Domain Registration',
    'status' => 'Rejected',
    'errors' => $substatus_name
  ));
}
elseif($status == 4006 OR $status == 400 OR $status == 0)
{
  // 4006 = payment required, 400 = inactive, 0 = active (old)
  logModuleCall(
    'nameSRS',
    'callback_register_payment',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );

  domainStatus($req['domain_id'], 'Pending Registration');
  emailAdmin("NameSRS Status", array(
    'domain_name' => $req['domain'],
    'orderType' => 'Domain Registration',
    'status' => $substatus_name,
    'errors' => $substatus_name
  ));
}
else
{
  logModuleCall(
    'nameSRS',
    'callback_register_unknownError',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  emailAdmin("NameSRS Status", array(
    'domain_name' => $req['domain'],
    'orderType' => 'Domain Registration',
    'status' => $substatus_name,
    'errors' => $substatus_name
  ));
}
