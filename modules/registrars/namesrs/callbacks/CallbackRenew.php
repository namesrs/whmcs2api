<?php

if($status == 2000)
{
  if($substatus != 2001)
  {
    logModuleCall(
      'nameSRS',
      'callback_renewal_failed',
      $json,
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
      $json
    );

    /** @var  $api  RequestSRS */
    $api->domainName = $domainname;
    $domain = $api->searchDomain(); // it will update expiration date, next due date and registration date

    $command = "UpdateClientDomain";
    $admin = getAdminUser();
    $values = array();
    $values["domainid"] = $req['domain_id'];
    $values['status'] = 'Active';
    localAPI($command, $values, $admin);
  }
}
else
{
  logModuleCall(
    'nameSRS',
    'callback_renewal_unknownError',
    $req['domain'],
    'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$req['domain']
  );
  emailAdmin("NameSRS Status", array(
    'domain_name' => $req['domain'],
    'orderType' => 'Domain Renew',
    'status' => $substatus_name,
    'errors' => $substatus_name
  ));
}
