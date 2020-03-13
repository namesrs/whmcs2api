<?php

function namesrs_GetNameservers($params)
{
  try
  {
    $api = new RequestSRS($params);
    $domain = $api->searchDomain();
    $ns = $domain['nameservers'];

    $values["ns1"] = $ns[0]['nameserver'];
    $values["ns2"] = $ns[1]['nameserver'];
    $values["ns3"] = $ns[2]['nameserver'];
    $values["ns4"] = $ns[3]['nameserver'];
    $values["ns5"] = $ns[4]['nameserver'];
    $values["success"] = TRUE;

    return $values;
  }
  catch (Exception $e)
  {
    // More details
    $msg = $e->getMessage();
    if (substr($msg, 0, 6) == '(2003)') $msg = 'This domain is either not registered with us or currently being transferred';
    return array(
      'error' => $msg,
    );
  }
}

function namesrs_SaveNameservers($params)
{
  try
  {
    $api = new RequestSRS($params);
    $nameServers = array();
    putenv('RES_OPTIONS=retrans:1 retry:1 timeout:3 attempts:1'); // timeout of 3sec
    for($i = 1; $i <= 5; $i++)
    {
      if($params["ns".$i]!='') 
      {
        if(gethostbyname($params["ns".$i].'.')) $nameServers[] = $params["ns".$i];
        else return array('error' => 'The hostname "'.$params["ns".$i].'" can not be resolved');
      }
    }
    $myParams = Array('domainname' => $api->domainName,'nameserver' => $nameServers);
    $api->request('POST',"/domain/update_domain_dns", $myParams);
    // also update the cache (if any)
    $domain = DomainCache::get($api->domainName);
    if(is_array($domain))
    {
      $ns = $domain['nameservers'];
      for($i = 1; $i <= 5; $i++)
        $ns['ns'.$i] = $params['ns'.$i];
      DomainCache::put($domain);
    }
    return array('success' => true);
  }
  catch (Exception $e)
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}
