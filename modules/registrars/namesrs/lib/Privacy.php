<?php

/**
 * Handle the ID Protection (whoisprivacy) of a domain name
 *
 * @param array $params common module parameters
 *
 * @return array|bool an array with a template name and some variables
 */
function namesrs_whoisprivacy($params)
{
  $error = false;
  $protected = 0;
  try
  {
    if ( isset($_REQUEST["idprotection"]) )
    {
      protectWHOIS($params, $_REQUEST["idprotection"] == 'enable');
      return false;
    }
    $api = new RequestSRS($params);
    $domain = $api->searchDomain();
    $protected = (bool)$domain['shieldwhois'];
  }
  catch (Exception $e)
  {
    $error = $e->getMessage();
  }
  return array(
    'templatefile' => "whoisprivacy",
    'vars' => array('error' => $error, 'protected' => $protected),
  );
}

function namesrs_IDProtectToggle($params)
{
  try
  {
    protectWHOIS($params, $params["protectenable"]);
    return array('success' => true);
  }
  catch (Exception $e)
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

/**
 * @param $params
 * @param $yes_no - 0/1 to disable/enable WHOIS protection
 * @throws Exception
 */
function protectWHOIS($params, $yes_no)
{
  $api = new RequestSRS($params);
  $api->request('POST','/domain/editdomain', Array('domainname' => Array($api->domainName),'setshieldwhois' => (int)$yes_no));
  $domain = $api->searchDomain();
  if(is_array($domain))
  {
    $domain['shieldwhois'] = $yes_no;
    DomainCache::put($domain);
  }
}
