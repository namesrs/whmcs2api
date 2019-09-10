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
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_SaveNameservers($params)
{
  try
  {
    $api = new RequestSRS($params);
    $nameServers = array();
    for($i = 1; $i <= 5; $i++)
      if($params["ns".$i]!='') $nameServers[] = $params["ns".$i];
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
