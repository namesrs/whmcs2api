<?php

function namesrs_RenewDomain($params)
{
  try
  {
    $api = new RequestSRS($params);
    $result = $api->request('POST','/domain/update_domain_renew', Array('domainname' => $api->domainName, 'itemyear' => $params['regperiod']));
    $handle = $result['parameters']['requestID'][0];
    $api->queueRequest(2 /* renew */, $params['domainid'], $handle);
    return array('success' => true);
  }
  catch (Exception $e)
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

