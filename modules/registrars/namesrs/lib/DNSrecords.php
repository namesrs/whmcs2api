<?php

class DNSnameSRS extends RequestSRS
{
  /**
   * @return array
   * @throws Exception
   */
  public function getDNSrecords()
  {
    $result = $this->request('GET',"/dns/getrecords", Array('domainname' => $this->domainName));
    if($result['dns'] AND is_array($result['dns'][$this->domainName]))
    {
      return $result['dns'][$this->domainName]['records'];
    }
    else throw new Exception('NameSRS: Could not understand the API response');
  }

  /**
   * @param $record
   * @throws Exception
   */
  public function updateDNSrecord($record)
  {
    $this->request('POST',"/dns/updaterecord", $record);
  }

  /**
   * @param $record
   * @throws Exception
   */
  public function deleteDNSrecord($record)
  {
    $this->request('POST',"/dns/deleterecord",$record);
  }

  /**
   * @param $record
   * @return int
   * @throws Exception
   */
  public function addDNSrecord($record)
  {
    $result = $this->request('POST',"/dns/addrecord", $record);
    if($result['recordid']) return $result['recordid'];
    else throw new Exception('NameSRS: Could not understand the API response');
  }
}

// =======================================

function namesrs_GetDNS($params)
{
  try
  {
    $api = new DNSnameSRS($params);
    $dnsrecords = $api->getDNSrecords();
    $result = Array();
    if(is_array($dnsrecords)) foreach($dnsrecords as &$item)
    {
      $rec = Array(
        "hostname" => rtrim(str_replace($api->domainName,'',$item['name']),'.'), // eg. www
        "address" => $item['content'], // eg. 10.0.0.1
        "priority" => $item['prio'], // eg. 10 (N/A for non-MX records)
        "recid" => $item['recordid'],
      );
      switch($item['type'])
      {
        case 'A':
        case 'AAAA':
        case 'TXT':
        case 'MX':
        case 'CNAME':
          $rec["type"] = $item['type'];
          break;
        case 'REDIRECT':
          $rec['type'] = (strtoupper($item['redirecttype'])=='FRAME' ?  'FRAME' : 'URL');
          break;
      }
      // nameservers are changed through namesrs_SaveNameservers() only
      if($rec['type']!='') $result[] = $rec;
    }
    return $result;
  }
  catch (Exception $e)
  {
    return array(
      'error' => 'NameSRS: '.$e->getMessage(),
    );
  }
}

function namesrs_SaveDNS($params)
{
  try
  {
    //Check correct NS
    $api = new RequestSRS($params);
    $domain = $api->searchDomain();
    $ns = $domain['nameservers'];
    $ourNS = FALSE;
    foreach($ns as $value)
    {
      if(preg_match('/^ns[1-5].nameisp.info$/i', $value['nameserver']))
      {
        $ourNS = TRUE;
        break;
      }
    }
    if($ourNS)
    {
      $api = new DNSnameSRS($params);
      if(is_array($params['dnsrecords']))
      {
        $i = 0;
        while($i < count($params['dnsrecords']))
        {
          $item = &$params['dnsrecords'][$i];
          $record = Array(
            'domainname' => $api->domainName,
            'name' => $item['hostname'].($item['hostname']!='' ? '.' : '').$api->domainName,
            'content' => $item['address'],
            'ttl' => 3600,
            'prio' => (int)$item['priority'],
            'recordid' => $item['recid']
          );
          switch($item['type'])
          {
            case 'URL':
              $record['type'] = 'REDIRECT';
              $record['redirecttype'] = 301;
              break;
            case 'FRAME':
              $record['type'] = 'REDIRECT';
              $record['redirecttype'] = 'frame';
              break;
            case 'MXE':
              // create A and then MX
              $record['type'] = 'A';
              $mailer = 'mxe-'.strtr($item['address'],':.','--');
              $params['dnsrecords'][] = Array(
                'domainname' => $api->domainName,
                'name' => $item['hostname'].($item['hostname']!='' ? '.' : '').$api->domainName,
                'content' => $mailer.'.'.$api->domainName,
                'ttl' => 3600,
                'prio' => (int)$item['priority'],
                'type' => 'MX'
              );
              break;
            default:
              $record['type'] = $item['type'];
          }
          if($item['recid'])
          {
            if($item['address']!='')
            {
              // update existing record
              $api->updateDNSrecord($record);
            }
            else
            {
              // delete a record
              $api->deleteDNSrecord(Array(
                'domainname' => $api->domainName,
                'recordid' => $item['recid']
              ));
            }
          }
          elseif($item['address']!='')
          {
            // add new record
            $api->addDNSrecord($record);
          }
          $i++;
        }
      }
      return array('success' => 'success');
    }
    else return array('error' => 'NameSRS: To be able to edit the DNS records you need to set the nameservers to "ns1.nameisp.info" and "ns2.nameisp.info"');
  }
  catch (Exception $e)
  {
    return array(
      'error' => 'NameSRS: '.$e->getMessage(),
    );
  }
}

function namesrs_setEmailForwarding($params)
{
  $error = false;
  $success = false;
  try
  {
    $api = new DNSnameSRS($params);
    if ( isset($_REQUEST["cmdRemove"]) )
    {
      $api->deleteDNSrecord(Array(
        'domainname' => $api->domainName,
        'recordid' => $_REQUEST['item_del']
      ));
      $success = true;
    }
    if(isset($_REQUEST['cmdSave']))
    {
      // update existing records
      if(is_array($_REQUEST['item'])) foreach($_REQUEST['item'] as $key => &$item)
      {
        $new_to = trim($item['to']);
        if($new_to!='')
        {
          $record = Array(
            'domainname' => $api->domainName,
            'name' => $item['from'],
            'content' => $new_to,
            'ttl' => 1,
            'type' => 'MAILFORWARD',
            'recordid' => $key
          );
          $api->updateDNSrecord($record);
        }
      }
      // add new record
      $new_from = preg_replace('/@[^@]*$/','', trim($_REQUEST['new_from']));
      $new_to = trim($_REQUEST['new_to']);
      if($new_from!='' AND preg_match('/^[^@]+@[^@]+$/', $new_to))
      {
        $record = Array(
          'domainname' => $api->domainName,
          'name' => $new_from.'@'.$api->domainName,
          'content' => $new_to,
          'ttl' => 1,
          'type' => 'MAILFORWARD'
        );
        $api->addDNSrecord($record);
      }
      $success = true;
    }
    $result = $api->getDNSrecords();
    $forward = Array();
    foreach($result as &$item)
      if($item['type'] == 'MAILFORWARD')
        $forward[$item['recordid']] = Array(
          'from' => $item['name'],
          'to' => $item['content'],
          'recordid' => $item['recordid']
        );
  }
  catch (Exception $e)
  {
    $error = 'NameSRS: '.$e->getMessage();
  }
  return array(
    'templatefile' => "mailforward",
    'vars' => array('error' => $error, 'successful' => $success, 'forward' => $forward, 'domain'=>$params['domainname']),
  );
}
