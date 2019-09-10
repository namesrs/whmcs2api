<?php

function namesrs_TransferDomain($params)
{
  try
  {
    $api = new RequestSRS($params);
    $result = $api->request('POST','/domain/create_domain_transfer', Array('domainname' => $api->domainName, 'auth' => $params['eppcode'], 'custom_field' => $params['domainid']));
    $handle = $result['parameters']['requestID'][0];
    $api->queueRequest(3 /* transfer */, $params['domainid'], $handle);
    return $handle;
  }
  catch (Exception $e)
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

