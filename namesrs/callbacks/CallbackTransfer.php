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
  	$api->domainName = $domainname;
    $domain = $api->searchDomain();
    $expire = substr($domain['renewaldate'],0,10);
    logModuleCall(
      'nameSRS',
      'callback_transfer_success - domain details',
      $result,
      $domain
    );

    $command  = "UpdateClientDomain";
    $admin   	= getAdminUser();
    //$dueDateDays = localAPI('GetConfigurationValue', 'DomainSyncNextDueDateDays', $admin);
	$result = $pdo->query('SELECT value FROM tblconfiguration WHERE setting = "DomainSyncNextDueDateDays" ORDER BY id DESC LIMIT 1');
    $dueDateDays = $result->rowCount() ? $result->fetch(PDO::FETCH_NUM)[0] : 0;

    $values   = array();
    $values["domainid"] = $req['domain_id'];
    $values["expirydate"] = $expire;
    $expireDate = new DateTime($expire);
    $expireDate->sub(new DateInterval('P'.(int)$dueDateDays.'D'));
    $values['nextduedate'] = $expireDate->format('Y-m-d');
    $values['status'] = 'Active';
    $results 	= localAPI($command, $values, $admin);
    logModuleCall(
      'nameSRS',
      'callback_transfer_success - updated due date',
      array(
        'due date safety period' => $dueDateDays,
        'renewal' => $expire,
        'next due date' => $values['nextduedate'],
      ),
      $domain
    );
    // completed - remove from the queue
    //$pdo->query('DELETE FROM tblnamesrsjobs WHERE id = '.(int)$req['id']);
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
    // remote from the queue
    //$pdo->query('DELETE FROM tblnamesrsjobs WHERE id = '.(int)$req['id']);
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
  // remove from the queue
  //$pdo->query('DELETE FROM tblnamesrsjobs WHERE id = '.(int)$req['id']);
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
