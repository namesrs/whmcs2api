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
    $result = $api->request('GET',"/domain/domaindetails", Array('itemid' => $reqid));
    $domain = $result['items'][$reqid];
    $expire = substr($domain['renewaldate'],0,10);
    logModuleCall(
      'nameSRS',
      'callback_renewal_success - domain details',
      $result,
      $domain
    );
    
    $command  = "UpdateClientDomain";
    $admin  	= getAdminUser();
    $dueDateDays = localAPI('GetConfigurationValue', 'DomainSyncNextDueDateDays', $admin);
    $values = array();
    $values["domainid"] = $req['domain_id'];
    $values["expirydate"] = $expire;
    $expireDate = new DateTime($expire);
    $expireDate->sub(new DateInterval('P'.(int)$dueDateDays.'D'));
    $values['nextduedate'] = $expireDate->format('Y-m-d');
    $values['status'] = 'Active';
    localAPI($command, $values, $admin);
    // remove from the queue
    //$pdo->query('DELETE FROM tblnamesrsjobs WHERE id = '.(int)$req['id']);
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
