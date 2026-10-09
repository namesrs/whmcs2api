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
  $dskey = '';
  $dsflag = '';
  $dsalgo = '';
  $dsData = array();
  $keyData = array();
  try
  {
    $api = new RequestSRS($params);
    if(isset($_POST["cmdPublish"]))
    {
      $clearCache = FALSE;
      if(is_array($_POST["DNSSEC"])) foreach($_POST["DNSSEC"] as $record)
      {
        $dnskey = strtolower(str_replace(' ', '', $record['pubKey'] ?? ''));
        $flags  = $record['flag'];
        $alg    = $_POST['alg'];
        if($dnskey != '' && $flags != '' && $alg != '')
        {
          $data = array(
            'domainname' => $api->domainName,
            'dnskey' => $dnskey,
            'flags' => $flags,
            'alg' => $alg,
          );
          $api->request('POST','/dns/publishdnssec', $data);
          $clearCache = TRUE;
        }
      }
      if($clearCache)
      {
        DomainCache::clear($api->domainName);
        $success = true;
      }
    }
    elseif(isset($_POST["cmdUnpublish"]))
    {
      $api->request('POST','/dns/unpublishdnssec',Array('domainname' => $api->domainName));
      DomainCache::clear($api->domainName);
      $success = true;
    }
    elseif(isset($_POST['cmdSign']))
    {
      $api->request('POST','/dns/signdnszone',Array('domainname' => $api->domainName));
      DomainCache::clear($api->domainName);
      $success = true;
    }
    // fetch existing data
    $ds = $api->request('GET', '/dns/getds', array('domainname' => $api->domainName));
    if($ds)
    {
      if(is_array($ds['dsdata']))
      {
        foreach(array('nameserver', 'published', 'unpublished', 'pending', 'local') as $kind)
        {
          if(is_array($ds['dsdata'][$kind])) foreach($ds['dsdata'][$kind] as $ds)
          {
            $dsData[] = array(
              'keyTag' => $ds['keytag'],
              'alg' => $ds['algorithm'],
              'digestType' => $ds['digesttype'],
              'digest' => strtolower($ds['digest']),
            );
          }
        }
      }
      if(is_array($ds['dnskeys'])) foreach($ds['dnskeys'] as $dsKey)
      {
        $keyData[] = array(
          'flag' => $dsKey['flags'],
          'protocol' => $dsKey['keytag'],
          'alg' => $dsKey['algorithm'],
          'pubkey' => $dsKey['key'],
        );
      }
    }

    $domain = $api->searchDomain();
    $status = $domain['signedzone'];
  }
  catch (Exception $e)
  {
    $error = 'NameSRS: '.$e->getMessage();
  }
  return array(
    'templatefile' => "dnssec",
    'vars' => array(
      'error' => $error,
      'successful' => $success,
      'status' => (int)$status,
      'dskey' => $dskey,
      'dsflag' => $dsflag,
      'dsalgo' => $dsalgo,
      'dsdata' => $dsData,
      'ksdata' => $keyData,
      'algOptions' => array(
        '3' => 'DSA/SHA-1',
        '5' => 'RSA/SHA-1',
        '6' => 'DSA/NSEC3-SHA1',
        '7' => 'RSA/SHA-1-NSEC3-SHA1',
        '8' => 'RSA/SHA-256',
        '10' => 'RSA/SHA-512',
        '12' => 'GOST R 34.10-2001',
        '13' => 'ECDSA Curve P-256/SHA-256',
        '14' => 'ECDSA Curve P-384/SHA-384',
      ),
      'digestOptions' => array(
        '1' => 'SHA-1',
        '2' => 'SHA-256',
        '3' => 'GOST R 34.11-94',
        '4' => 'SHA-384',
      ),
      'flagOptions' => array(
        '256' => 'Zone Signing Key',
        '257' => 'Key Signing Key',
      ),
      'protocols' => array('3' => 'DNSSEC'),
    )
  );
}

function addDS(&$arr, $item)
{
  $arr[] = array(
    'keyTag' => $item['keytag'],
    'alg' => $item['algorithm'],
    'digestType' => $item['digesttype'],
    'digest' => strtolower($item['digest']),
  );
}
