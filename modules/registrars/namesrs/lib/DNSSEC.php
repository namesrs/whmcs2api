<?php

/**
 * Handle the DNSSEC management page of a domain
 *
 * @param array $params common module parameters
 * @return array an array with a template name
 */
function namesrs_dnssec($params)
{
  $error = false;
  $success = false;
  try
  {
    $api = new RequestSRS($params);
    if(isset($_POST["cmdPublish"]))
    {
      $dnskey = trim($_POST['dnskey']);
      $flags  = trim($_POST['flags']);
      $alg    = trim($_POST['alg']);
      if($dnskey == '') $error = 'Missing DNS key';
      elseif($flags == 0) $error = 'Missing flags';
      elseif($alg == 0) $error = 'Missing algorithm';
      else
      {
        $data = array(
          'domainname' => $api->domainName,
          'dnskey' => $dnskey,
          'flags' => $flags,
          'alg' => $alg,
        );
        $api->request('POST','/dns/publishdnssec', $data);
        DomainCache::clear($api->domainName);
        $success = true;
      }
    }
    if(isset($_POST["cmdUnpublish"]))
    {
      $api->request('POST','/dns/unpublishdnssec',Array($api->domainName));
      DomainCache::clear($api->domainName);
      $success = true;
    }
    $domain = $api->searchDomain();
    $status = $domain['signedzone'];
  }
  catch (Exception $e)
  {
    $error = $e->getMessage();
  }
  return array(
    'templatefile' => "dnssec",
    'vars' => array('error' => $error, 'successful' => $success, 'status' => (int)$status)
  );
}

