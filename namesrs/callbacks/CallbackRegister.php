<?php

if($status == 200 OR ($status == 2000 AND $substatus == 2001))
{
  logModuleCall(
    'nameSRS',
    'callback_register_success',
    $json,
    'Main status = '.$status.', substatus = '.$substatus.', domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Active');
  // completed - remove from queue
  Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?', Array($req['id']));
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
  domainStatus($req['domain_id'], 'Pending');
}
elseif($status == 201 OR $status == 500)
{
  // expired or expiring (201)
  logModuleCall(
    'nameSRS',
    'callback_register_expired',
    $json,
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Expired');
  // remove from the queue
  Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?', Array($req['id']));
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
  // remove from the queue
  Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?', Array($req['id']));
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
  // remove from the queue
  Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?', Array($req['id']));

  domainStatus($req['domain_id'], 'Fraud');
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

  domainStatus($req['domain_id'], 'Pending');
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
