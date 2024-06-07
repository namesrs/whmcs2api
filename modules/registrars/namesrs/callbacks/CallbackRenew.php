<?php

if($status == 2000)
{
  if($substatus != 2001)
  {
    logModuleCall(
      'nameSRS',
      'callback_renewal_failed',
      json_encode($json,JSON_PRETTY_PRINT),
      'Main status = 2000, substatus != 2001, domain = '.$req['domain']
    );
    emailAdmin("Domain Renewal Failed", array(
      "client_id" => $req['userid'],
      "domain_id" => $req['domain_id'],
      "domain_name" => $req['domain'],
      "error_msg" => $substatus_name,
    ));
  }
  else
  {
    // successful renewal
    logModuleCall(
      'nameSRS',
      'callback_renewal_success',
      'domain = '.$req['domain'],
      json_encode($json,JSON_PRETTY_PRINT)
    );

    /** @var  $api  RequestSRS */
    $api->domainName = $domainname;
    $domain = $api->searchDomain(); // it will update expiration date, next due date and registration date

    domainStatus($req['domain_id'], 'Active');
  }
}
elseif($status == 1)
{
  // Pending
  logModuleCall(
    'nameSRS',
    'callback_renewal_pending',
    json_encode($json,JSON_PRETTY_PRINT),
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  domainStatus($req['domain_id'], 'Pending');
}
else
{
  logModuleCall(
    'nameSRS',
    'callback_renewal_unknownError',
    'Unknown error in Callback for Renew = Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain'],
    $json
  );
  emailAdmin("NameSRS Status", array(
    'domain_name' => $req['domain'],
    'orderType' => 'Domain Renew',
    'status' => $substatus_name,
    'errors' => $substatus_name
  ));
}
