<?php

use WHMCS\Database\Capsule as Capsule;

require_once("Cache.php");

function adminError($title, $body, $values = NULL)
{
  $body = '<strong>'.$title.'</strong><br><pre>'.$body.'</pre>';
  if(is_array($values) AND count($values) > 0) $body.= "<br><br><pre>".json_encode($values, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."</pre>";
  $r = sendAdminNotification('system', $title, $body);
}

Class RequestSRS
{
  protected $account;
  protected $base_url;
  public $params;
  protected $sessionId;
  public $domainName;
  protected $dns_id;

  /**
   * Request constructor.
   * @param $params
   * @throws Exception
   */
  public function __construct($params)
  {
    $this->params = $params;
    if (is_object($params['original']["domainObj"])) $this->domainName = $params['original']["domainObj"]->getDomain(TRUE);
    elseif (is_object($params["domainObj"])) $this->domainName = $params["domainObj"]->getDomain(TRUE);
    else $this->domainName = $params['domainname'];
    if ($this->params["API_key"]) $this->account = trim($this->params["API_key"]);
    if ($this->params["Base_URL"]) $this->base_url = trim($this->params["Base_URL"]);
    if ($this->account == '') throw new Exception('NameSRS: Missing API key');
    if ($this->params['Base_URL'] == '') $this->base_url = API_HOST;
    if ($this->params['DNS_id']) $this->dnsid = (int)$this->params["DNS_id"];
    logModuleCall(
      'nameSRS',
      'request',
      $params,
      ''
    );
  }

  /**
   * @param $action - either GET or POST
   * @param $functionName - API endpoint (the path after API_HOST)
   * @param $myParams - array with the API parameters
   * @return array
   * @throws Exception
   */
  public function request($action, $functionName, $myParams)
  {
    $this->sessionId = SessionCache::get($this->account);
    if ($this->sessionId == "")
    {
      // probably we have not been logged-in before
      $loginResult = $this->call('GET', '/authenticate/login/' . $this->account, []);
      if ($loginResult["code"] == 1000)
      {
        $this->sessionId = $loginResult['parameters']['token'];
        SessionCache::put($this->sessionId, $this->account);
      }
      elseif ($loginResult["code"] == 2200)
      {
        throw new Exception('NameSRS: Invalid API key');
      }
      else
      {
        throw new Exception('NameSRS: '.($loginResult['desc'] != '' ? $loginResult['desc'] : 'Unknown login error'));
      }
    }
    $result = $this->call($action, $functionName, $myParams);
    if ($result['code'] == 1000 OR $result['code'] == 1300) return $result;
    elseif ($result['code'] == 2200)
    {
      // session token has expired - get a new one
      SessionCache::clear($this->account);
      $loginResult = $this->call('GET', '/authenticate/login/' . $this->account, []);
      if ($loginResult["code"] == 1000)
      {
        $this->sessionId = $loginResult['parameters']['token'];
        SessionCache::put($this->sessionId, $this->account);
      }
      elseif ($loginResult["code"] == 2200)
      {
        throw new Exception('NameSRS: Could not renew the session token for the API');
      }
      else
      {
        throw new Exception('NameSRS: '.($loginResult['desc'] != '' ? $loginResult['desc'] : 'Unknown login error'));
      }
      $result = $this->call($action, $functionName, $myParams);
      if ($result['code'] == 1000 OR $result['code'] == 1300) return $result;
      else throw new Exception('NameSRS: (' . $result['code'] . ') ' . $result['desc']);
    }
    else throw new Exception('NameSRS: (' . $result['code'] . ') ' . $result['desc']);
  }

  /**
   * Make external API call to registrar API.
   *
   * @param string $action - GET or POST
   * @param string $functionName - API endpoint
   * @param array $postfields
   *
   * @throws Exception Connection error
   * @throws Exception Bad API response
   *
   * @return array
   */
  private function call($action, $functionName, $postfields)
  {
    $url = 'https://' . $this->base_url . $functionName . ($this->sessionId != '' ? '/' . $this->sessionId : '');
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_SSL_VERIFYPEER => 1,
      //CURLOPT_TIMEOUT => 35, removed by FREI
    ]);
    if (is_array($postfields))
    {
      // converts indexed field names into array syntax (e.g. "ns[2]" becomes "ns[]")
      $query = preg_replace('/%5B[0-9]+%5D=/simU', '%5B%5D=', http_build_query($postfields, 'x_', '&', PHP_QUERY_RFC3986));
    }
    else $query = $postfields;
    if (strtoupper($action) == 'GET')
    {
      curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
    }
    else
    {
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    }

    $response = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception('NameSRS: Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
    curl_close($ch);
    $result = json_decode($response, TRUE);
    logModuleCall(
      'nameSRS',
      $functionName,
      $postfields,
      $response,
      $result
    );
    if ($result === NULL && json_last_error() !== JSON_ERROR_NONE) throw new Exception('NameSRS: Bad response received from API');
    return $result;
  }

  /**
   * @return array
   * @throws Exception
   */
  public function searchDomain()
  {
    /**
     * @var $pdo PDO
     */
    $pdo = Capsule::connection()->getPdo();
    if (is_object($this->params['original']["domainObj"]))
    {
      $this->domainName = $this->params['original']["domainObj"]->getDomain(TRUE);
    }
    elseif (is_object($this->params["domainObj"]))
    {
      $this->domainName = $this->params["domainObj"]->getDomain(FALSE);
    }
/*
    $domain = DomainCache::get($this->domainName);
    if (is_array($domain))
      {
        logModuleCall(
          'nameSRS',
          "SearchDomain($domainname)",
          'Got from session cache',
          $domain
        );
        return $domain;
      }
*/
    $result = $pdo->query('SELECT namesrs_id FROM tblnamesrshandles WHERE type = 1 AND whmcs_id = ' . (int)$this->params['domainid']);
    if ($result->rowCount()) $handle = $result->fetch(PDO::FETCH_NUM)[0];
    else
    {
      $list = $this->request('GET', "/domain/domainlist", ['domainname' => $this->domainName, 'status' => 200, 'exact' => 1]);
      if ($list)
      {
        $handle = 0;
        foreach($list['items'] as $domItem)
        {
          if($domItem['domainname'] == $this->domainName)
          {
            if ($domItem['itemID'] > $handle) $handle = $domItem['itemID'];
          }
        }
      }
      logModuleCall(
        'nameSRS',
        'SearchDomain('.$this->domainName.')',
        'We asked API for domain ID',
        $handle ? 'Domain ID = '.$handle : 'No domain ID was found'
      );
      if (!$handle) throw new Exception('NameSRS: Could not retrieve domain ID from the API');
    }
    $result = $this->request('GET', "/domain/domaindetails", ['itemid' => $handle]);
    $domain = $result['items'][$handle];
    if(!$domain OR $domain['domainname'] != $this->domainName)
    {
      // either no info returned (wrong domain ID) or domain name is not ours (again wrong domain ID)
      // so we need to ask for the domain ID and update our mapping, if possible
      if(!$domain) $reason = 'DomainDetails did not recognize domain ID ('.$handle.') - trying to search for domain ID';
      else $reason = 'Domain ID ('.$handle.') is for '.$domain['domainname'].' instead of '.$this->domainName.' - trying to search for domain ID';

      $handle = 0;
      $list = $this->request('GET', "/domain/domainlist", ['domainname' => $this->domainName, 'status' => 200]);
      if ($list)
      {
        foreach($list['items'] as $domItem)
        {
          if($domItem['domainname'] == $this->domainName)
          {
            if ($domItem['itemID'] > $handle) $handle = $domItem['itemID'];
          }
        }
      }
      logModuleCall(
        'nameSRS',
        'SearchDomain('.$this->domainName.')',
        $reason,
        $handle ? 'Domain ID = '.$handle : 'No domain ID was found'
      );
      if (!$handle) throw new Exception('NameSRS: Domain ID for '.$this->domainName.' was wrong but we could not retrieve a new domain ID from the API');
      $result = $this->request('GET', "/domain/domaindetails", ['itemid' => $handle]);
      $domain = $result['items'][$handle];
    }
    logModuleCall(
      'nameSRS',
      'SearchDomain('.$this->domainName.')',
      'We fetched domain details from API',
      $domain
    );
    DomainCache::put($domain);
    // store the mapping between WHMCS domainID and NameISP domainHandle
    if($domain['custom_field'] != 0) $this->params['domainid'] = $domain['custom_field'];
    if ($this->params['domainid'] != 0)
    {
      $pdo->query('INSERT INTO tblnamesrshandles(whmcs_id,type,namesrs_id) VALUES(' . $this->params['domainid'] . ',1,' . $handle . ') ON DUPLICATE KEY UPDATE namesrs_id = VALUES(namesrs_id)');
      $expire = substr($domain['renewaldate'],0,10);
      // update status, expiration and next due date
      $command  = "UpdateClientDomain";
      $res      = $pdo->query("SELECT username FROM tbladmins WHERE disabled=0 LIMIT 1");
      $admin   	= $res->rowCount() ? $res->fetch(PDO::FETCH_NUM)[0] : '';
      //$dueDateDays = localAPI('GetConfigurationValue', 'DomainSyncNextDueDateDays', $admin);
      $result = $pdo->query('SELECT value FROM tblconfiguration WHERE setting = "DomainSyncNextDueDateDays" ORDER BY id DESC LIMIT 1');
      $dueDateDays = $result->rowCount() ? $result->fetch(PDO::FETCH_NUM)[0] : 0;

      $values   = array();
      $values["domainid"] = $this->params['domainid'];
      $values["expirydate"] = $expire;
      $expireDate = new DateTime($expire);
      $dueDays = new DateInterval('P'.abs((int)$dueDateDays).'D');
      if($dueDateDays < 0) $dueDays->invert = 1;
      $expireDate->sub($dueDays);
      if($this->params['sync_due_date']) $values['nextduedate'] = $expireDate->format('Y-m-d');
      $values['regdate'] = substr($domain['created'],0,10);
      $status_id = key($domain['status']);
      $statusName = '';
      switch($status_id)
      {
        case 200:
        case 201:
          $statusName = 'Active';
          break;
        case 300:
          $statusName = 'Transferred Away';
          break;
        case 500:
          $statusName = 'Expired';
          break;
        case 503:
          $statusName = 'Redemption';
          break;
        case 504:
          $statusName = 'Grace';
          break;
        case 2:
        case 10:
        case 11:
        case 400:
        case 4000:
        case 4006:
          $statusName = 'Pending';
      }
      if ($statusName != '') $values['status'] = $statusName;
      localAPI($command, $values, $admin);
      logModuleCall(
        'nameSRS',
        'SearchDomain('.$this->domainName.')',
        "We updated domain status ($statusName), expiration".($this->params['sync_due_date'] ? " and next due date" : ""),
        $values
      );
    }
    return $domain;
  }

  /**
   * @param int $type - 2 (renew domain), 3 (transfer domain), 4 (register domain), 5 (change owner)
   * @param int $domain_id - WHMCS domainID
   * @param int $reqid - ID of the API request in NameSRS
   * @param string $json - only used when registering a domain, to store NameServers
   */
  public function queueRequest($type, $domain_id, $reqid, $json = "")
  {
    /**
     * @var $pdo PDO
     */
    $pdo = Capsule::connection()->getPdo();

    try
    {
      $stm = $pdo->prepare('INSERT INTO tblnamesrsjobs(last_id,order_id,method,request,response) VALUES(:dom_id,:req_id,:type,:json,"")');
      $stm->execute(['dom_id' => $domain_id, 'req_id' => $reqid, 'type' => $type, 'json' => $json]);
    }
    catch (Exception $e)
    {
      logModuleCall(
        'nameSRS',
        'queueRequest',
        ['type' => $type, 'domain_id' => $domain_id, 'req_id' => $reqid, 'json' => json_decode($json, TRUE)],
        $e->getMessage()
      );
    }
  }
}

?>
