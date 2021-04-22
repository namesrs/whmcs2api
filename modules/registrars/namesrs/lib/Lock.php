<?php

function namesrs_saveRegistrarLock($params)
{
  try
  {
    $api = new RequestSRS($params);
    $yes_no = $params['lockenabled'] == 'locked';
    $api->request('POST','/domain/editdomain', Array('domainname' => Array($api->domainName), 'transferlock' => (int)$yes_no));
    $domain = DomainCache::get($api->domainName);
    if(is_array($domain))
    {
      $domain['transferlock'] = (int)$yes_no;
      DomainCache::put($domain);
    }
    return array('success' => true);
  }
  catch (Exception $e)
  {
    return array(
      'error' => 'NameSRS: '.$e->getMessage(),
    );
  }
}

function namesrs_GetRegistrarLock($params)
{
  try
  {
    $api = new RequestSRS($params);
    $result = $api->searchDomain();
    return $result['transferlock'] ? 'locked' : 'unlocked';
  }
  catch (Exception $e)
  {
    return array(
      'error' => 'NameSRS: '.$e->getMessage(),
    );
  }
}

