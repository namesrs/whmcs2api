<?php

function namesrs_RenewDomain($params)
{
  try
  {
    $api = new RequestSRS($params);
    $result = namesrs_sale_cost($api, $params,'Renew');
    if(is_array($result)) return $result;

    $result = $api->request('POST','/domain/update_domain_renew', Array(
      'domainname' => $api->domainName,
      'itemyear' => $params['regperiod'],
      'custom_field' => $params['domainid'],
    ));
    $handle = $result['parameters']['requestID'][0];
    $api->queueRequest(2 /* renew */, $params['domainid'], $handle);
    if(isset($params['default_pending'])) domainStatus($params['domainid'], 'Pending');
    return array('success' => true);
  }
  catch (Exception $e)
  {
    return array(
      'error' => 'NameSRS: '.$e->getMessage(),
    );
  }
}

