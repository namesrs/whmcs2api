<?php
use WHMCS\Database\Capsule as Capsule;
require_once("Tools.php");


function loger($x)
{
  syslog(LOG_INFO | LOG_LOCAL1, $x);
}

Class SessionCache
{
	public static function get($account)
	{
		try
		{
			$result = Capsule::select("select sessionId from mod_namesrssession where account = ?",Array($account));
  		return $result[0]->sessionId;
		}
		catch (Exception $e)
		{
      logModuleCall(
        'SessionCache',
        'GET',
        $account,
        '',
        $e->getMessage(),
        array()
      );
      return '';
		}
	}

	public static function put($sessionId,$account)
	{
	  try
	  {
  	  Capsule::insert("INSERT INTO mod_namesrssession (account, sessionId)
  			VALUES(?,?)
  			ON DUPLICATE KEY UPDATE account=VALUES(account), sessionId=VALUES(sessionId)",
  			Array($account,$sessionId));
  	}
		catch (Exception $e)
		{
      logModuleCall(
        'SessionCache',
        'PUT',
        Array('account'=>$account,'session'=>$sessionId),
        '',
        $e->getMessage(),
        array()
      );
		}
	}

	public static function clear($account)
	{
		SessionCache::put("",$account);
	}
}

Class DomainCache
{
	public static function get($domainName)
	{
		if(!$_SESSION['namesrsDomainCache']) $_SESSION['namesrsDomainCache'] = array();
		return $_SESSION['namesrsDomainCache'][$domainName];
	}

	public static function put($domain)
	{
		if(!$_SESSION['namesrsDomainCache'] OR count($_SESSION['namesrsDomainCache']) > 1000) $_SESSION['namesrsDomainCache'] = array();
		$_SESSION['namesrsDomainCache'][$domain['domainname']] = $domain;
	}

	public static function clear($domainName)
	{
	  unset($_SESSION['namesrsDomainCache'][$domainName]);
	}
}

function createRequest($params)
{
	return new Request($params);
}

Class Request
{
	var $account;
	var $base_url;
	var $params;
	var $domainName;

	public function __construct($params)
	{
		$this->setParams($params);
	}

	public function setParams($params)
	{
		if($params)
		{
			$this->params = $params;
			$this->domainName = $params["sld"] . "." . $params["tld"];
			if($this->params["API_key"]) $this->account = trim($this->params["API_key"]);
			if($this->params["Base_URL"]) $this->base_url = trim($this->params["Base_URL"]);
		}
		if($this->params['API_key']=='') throw new Exception('Missing API key');
		if($this->params['Base_URL']=='') $this->base_url = 'api.domainname.systems';
    logModuleCall(
      'nameSRS',
      'create_request',
      $params,
      '',
      '',
      array()
    );
		return $this->params;
	}

	private function login()
	{
		$result =  $this->sendRequest('GET','/authenticate/login/'.$this->account,'');
 		SessionCache::put($result['parameters']['token'],$this->account);
		return $result;
	}

	public function request($action, $functionName, $myParams, $second = false)
	{
		$this->sessionId = SessionCache::get($this->account);
		if ($this->sessionId == "")
		{
			$loginResult = $this->login();
			if($loginResult["code"]==1000) $this->sessionId = $loginResult['parameters']['token'];
			elseif($loginResult["code"]==2200) 
			{
			  throw new Exception('Invalid API key');
			}
			else 
			{
			  throw new Exception($loginResult['desc']!='' ? $loginResult['desc'] : 'Unknown login error');
			}
		}
		return $this->sendRequest($action, $functionName,$myParams,$second);
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
		$url = 'https://'.$this->base_url.$functionName.($this->sessionId!='' ? '/'.$this->sessionId : '');
    $ch = curl_init();
    curl_setopt_array($ch, Array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_SSL_VERIFYPEER => 1,
      CURLOPT_TIMEOUT => 16
    ));
    if(is_array($postfields))
    {
      $query = preg_replace('/%5B[0-9]+%5D=/simU', '%5B%5D=', http_build_query($postfields,'x_','&',PHP_QUERY_RFC3986));
    }
    else $query = $postfields;
    if(strtoupper($action) == 'GET')
    {
      curl_setopt($ch, CURLOPT_URL, $url.'?'.$query);
    }
    else
    {
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    }

    $response = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
    curl_close($ch);
    $result = json_decode($response,TRUE);
    logModuleCall(
      'namesrs',
      $functionName,
      $postfields,
      $response,
      $result,
      array()
    );
    if ($result === null && json_last_error() !== JSON_ERROR_NONE) throw new Exception('Bad response received from API');
    return $result;
  }
    
	private function sendRequest($action, $functionName,$myParams, $second = false)
	{
		$result = $this->call($action,$functionName,$myParams);
		if ($result['code']==1000 OR $result['code']==1300) return $result;
    elseif ($result['code']==2200)
    {
      if($second) throw new Exception('Could not authenticate to the API');
			SessionCache::clear($this->account);
			return $this->request($action, $functionName, $myParams, TRUE);
  	}
		else throw new Exception('('.$result['code'].') '.$result['desc']);
	}

	public function getDomain($handle)
	{
		$result = $this->request('GET',"/domain/domaindetails", Array('itemid'=>$handle));
    $domain = $result['items'][$handle];
		///$this->setDomainStatus($domain);
		DomainCache::put($domain);
		$this->setHandle($domain,$handle);
		return $domain;
	}

	public function searchDomain()
	{
		$domain = DomainCache::get($this->domainName);
		if(is_array($domain)) return $domain;
		$handle = $this->getHandle(1 /* domain */,$this->params['domainid']);
		if($handle) return $this->getDomain($handle);
		$result = $this->request('GET',"/domain/domainlist",Array('domainname'=>$this->domainName));
		if($result['items']) return $this->getDomain($result['items'][0]['itemID']);
  		else throw new Exception('Could not retrieve domain ID from the API');
	}

	public function getDNSrecords()
	{
		$result = $this->request('GET',"/dns/getrecords",Array('domainname'=>$this->domainName));
		if($result['dns'] AND is_array($result['dns'][$this->domainName])) 
		{
		  return $result['dns'][$this->domainName]['records'];
		}
  	else throw new Exception('Could not understand the API response');
	}

	public function addDNSrecord($record)
	{
		$result = $this->request('POST',"/dns/addrecord",$record);
		if($result['recordid']) return $result['recordid'];
  		else throw new Exception('Could not understand the API response');
	}

	public function updateDNSrecord($record)
	{
		$result = $this->request('POST',"/dns/updaterecord",$record);
	}

	public function deleteDNSrecord($record)
	{
		$result = $this->request('POST',"/dns/deleterecord",$record);
	}

  public function mapToWHMCS($dnsrecords)
  {
    $result = Array();
    if(is_array($dnsrecords)) foreach($dnsrecords as &$item)
    {
      $rec = Array(
        "hostname" => rtrim(str_replace($this->domainName,'',$item['name']),'.'), // eg. www
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
  
  public function publishDNSSEC($data)
  {
    $data['domainname'] = $this->domainName;
    $this->request('POST','/dns/publishdnssec',$data);
    DomainCache::clear($this->domainName);
  }

  public function unpublishDNSSEC()
  {
    $this->request('POST','/dns/unpublishdnssec',Array($this->domainName));
    DomainCache::clear($this->domainName);
  }
  
  public function getWHOISprotect()
  {
    $domain = $this->searchDomain();
    return (bool)$domain['shieldwhois'];
  }
  
  public function protectWHOIS($yes_no)
  {
    $this->request('POST','/domain/editdomain',Array('domainname'=>Array($this->domainName),'setshieldwhois'=>(int)$yes_no));
    $domain = $this->searchDomain();
		if(is_array($domain))
		{
      $domain['shieldwhois'] = (int)$yes_no;
  	  DomainCache::put($domain);
		}
  }
  
  public function domainLock($yes_no)
  {
    $this->request('POST','/domain/editdomain',Array('domainname'=>Array($this->domainName),'transferlock'=>(int)$yes_no));
    $domain = $this->searchDomain();
		$domain = DomainCache::get($this->domainName);
		if(is_array($domain))
		{
      $domain['transferlock'] = (int)$yes_no;
  	  DomainCache::put($domain);
		}
  }

	public function updateContacts($params) 
	{
	  $this->request('POST','/contact/updatecontact',$params);
    DomainCache::clear($this->domainName);
	}
	
	function saveNameservers() 
	{
  	$myParams = Array('domainname'=>$this->domainName,'nameserver'=>$this->mapToNameservers());
		$response = $this->request('POST',"/domain/update_domain_dns",$myParams);
		$domain = DomainCache::get($this->domainName);
		if(is_array($domain))
		{
		  $ns = $domain['nameservers'];
  	  for($i=1;$i<=5;$i++) $ns['ns'.$i] = $this->params['ns'.$i];
  	  DomainCache::put($domain);
		}
	}
	
	public function getEPPCode() 
	{
		$code = $this->request('POST','/domain/genauthcode',Array('domainname'=>$this->domainName));
		return $code['authcode'];
	}
	
	public function updateDomain() 
	{
	  $result = $this->request('POST','/domain/update_domain_renew',Array('domainname'=>$this->domainName,'itemyear'=>$this->params['regperiod']));
		$this->queueRequest(2 /* renew */,$this->params['domainid'],$result['parameters']['requestID'][0]);
		return $result['parameters']['requestID'];
	}

	public function transferDomain() 
	{
	  $result = $this->request('POST','/domain/create_domain_transfer',Array('domainname'=>$this->domainName,'auth'=>$this->params['eppcode']));
		$this->queueRequest(3 /* transfer */,$this->params['domainid'],$result['parameters']['requestID'][0]);
		return $result['parameters']['requestID'];
	}
	
	public function registerDomain($years,$owner_id,$protect) 
	{
	  $nserver = Array();
	  for($i=1;$i<=5;$i++)
  	  if($this->params['ns'.$i]!='')
  	  {
  	    $ns = explode('.',trim(trim($this->params['ns'.$i]),'.'));
  	    if(count($ns)>2) $nserver[] = implode('.',$ns);
  	  }
    $domainName = $this->params['domainObj']->getDomain(TRUE);

	  $result = $this->request('POST','/domain/create_domain_registration',Array('domainname'=>$domainName,'itemyear'=>$years,'ownerid'=>$owner_id,'shieldwhois'=>(int)$protect,'nameserver'=>$nserver,'tmchacceptance'=>1));
		$this->queueRequest(4 /* register */,$this->params['domainid'],$result['parameters']['requestID'][0],json_encode(Array('ns'=>$nserver)));
		localAPI('UpdateClientDomain',Array(
		  'domainid' => $this->params['domainid'],
		  'status' => 'Pending'
		));
		return $result['parameters']['requestID'][0];
	}
	
	public function findContact($arr) 
	{
		$res = $this->request('POST','/contact/contactlist',array_merge(Array('domainname'=>$this->domainName,'limit'=>1),$arr));
		return $res['contacts'];
	}

	public function makeContact() 
	{
	  $orig = is_array($this->params['original']) ? $this->params['original'] : $this->params;
	  $data = Array(
	    'firstname' => $orig['firstname'],
	    'lastname' => $orig['lastname'],
	    'organization' => $orig['companyname'],
	    'orgnr' => $orig['orgnr'],
	    'address1' => $orig['address1'],
	    'zipcode' => $orig['postcode'],
	    'city' => $orig['city'],
	    'countrycode' => $orig['countrycode'],
	    'phone' => $orig['fullphonenumber'], // +CC.xxxx
	    'email' => $orig['email']
	  );
		$res = $this->request('POST','/contact/createcontact',$data);
		return $res['contactid'];
	} 
	
	public function sendAuthCode($order,$domainId) 
	{
		if($order->Type != "Update_AuthInfo") return;
		$domain =  $this->getDomain($order->Domain->DomainHandle);
		$values = array();
 		$values["messagename"] = "EPP Code";
 		$values["customvars"] = array("code"=> $domain->domain->AuthInfo);
		$values["id"] = $domainId;
		$results = localAPI("sendemail",$values,Tools::getApiUser());
		return $results;
	}
	
	
	// =========================
	
	public function mapToNameservers() 
	{
	  $result = Array();
	  for($i=1;$i<=5;$i++)
  	  if($this->params["ns".$i]!='') $result[] = $this->params["ns".$i];
		return $result;
	}
	
	public function getHandle($type,$whmcsId) 
	{
	  try
	  {
  		$result = Capsule::select("SELECT namesrs_id FROM tblnamesrshandles WHERE type = ? AND whmcs_id = ?",
  		  array($type, $whmcsId));
  		return $result[0]->namesrs_id;
  	}
		catch (Exception $e)
		{
      logModuleCall(
        'namesrs',
        'getHandle',
        Array('type'=>$type,'whmcs_id'=>$whmcsId),
        '',
        $e->getMessage(),
        array()
      );
      return 0;
		}
	}
	
	// map nameISP domain IDs into WHMCS
	public function setHandle($domain,$nameisp_id) 
	{
		$this->storeHandle(1 /* domain */,Tools::getDomainId($domain['domainname']),$nameisp_id);
	}
	
	public function storeHandle($type,$whmcsId, $nameispId) 
	{
		$handle = $this->getHandle($type,$whmcsId);
		try
		{
  		if(!$handle) 
  		{
  			Capsule::insert("INSERT INTO tblnamesrshandles(whmcs_id,type,namesrs_id) VALUES(?,?,?)",
  			  array($whmcsId,$type,$nameispId));
  		} 
  		else 
  		{
  			Capsule::update("UPDATE tblnamesrshandles SET namesrs_id = ? WHERE type = ? AND whmcs_id = ?",
  			  array($nameispId,$type,$whmcsId));
  		}
  	}
		catch (Exception $e)
		{
      logModuleCall(
        'namesrs',
        'storeHandle',
        Array('type'=>$type,'whmcs_id'=>$whmcsId,'nameisp_id'=>$nameispId),
        '',
        $e->getMessage(),
        array()
      );
		}
	}
	
	private function queueRequest($type,$domain_id,$reqid,$json="")
	{
	  try
	  {
	    Capsule::insert('INSERT INTO tblnamesrsjobs(last_id,order_id,method,request,response) VALUES(?,?,?,?,"")',Array($domain_id,$reqid,$type,$json));
	  }
		catch (Exception $e)
		{
      logModuleCall(
        'namesrs',
        'queueRequest',
        Array('type'=>$type,'domain_id'=>$whmcsId,'req_id'=>$reqid,'json'=>json_decode($json,TRUE)),
        '',
        $e->getMessage(),
        array()
      );
		}
	}
	
  private function emailAdmin($tpl,$fields)
  {
    logModuleCall(
      'namesrs',
      'emailAdmin',
      print_r($fields,TRUE),
      '',
      'Tried to send email to Admin, dont know if it was delivered',
      array()
    );
  
		$values = array();
		$values["messagename"] = $tpl;
		$values["mergefields"] = $fields;
		$r = localAPI("SendAdminEmail",$values,Tools::getApiUser());
 		loger(print_r($r,TRUE));
  }
  
	public function getCallbackData()
	{
	  $json = json_decode(file_get_contents('php://input'),TRUE);
	  if(json_last_error() == JSON_ERROR_NONE)
    {
      if($json['template'] != 'REQUEST_UPDATE')
      {
        logModuleCall(
          'namesrs',
          'getCallbackData_IGNORED',
          $json,
          'Template is not REQUEST_UPDATE',
          '',
          array()
        );      
        return;
      }
      $cb_id = $json['callbackid'];
      $reqid = $json['reqid'];
      $status = key($json['status']['mainstatus']);
      $status_name = $json['status']['mainstatus'][$status];
      $substatus = key($json['status']['substatus']);
      $substatus_name = $json['status']['substatus'][$substatus];
      $renewaldate = $json['renewaldate']; // YYYY-MM-DD
  	  try
  	  {
    	  // our local queue of API requests, pending for reply
    	  $result = Capsule::select('SELECT jobs.id,last_id AS domain_id,domain,method,userid,request FROM tblnamesrsjobs AS jobs LEFT JOIN tbldomains ON tbldomains.id = last_id WHERE order_id = '.$reqid);
    	  foreach($result as &$reg)
    	  {
    	    switch($reg->method)
    	    {
    	      case 2: // renewal
        	    if($status == 2000)
        	    {
        	      if($substatus != 2001)
        	      {
            			$values = array();
             			$values["messagename"] = "Domain Renewal Failed";
             			$values["mergefields"] = array(
             				"client_id" => $reg->userid,
             				"domain_id" => $reg->domain_id,
             				"domain_name" => $reg->domain,
             				"error_msg" => $substatus_name,
             			);
            			$results = localAPI("SendAdminEmail",$values,Tools::getApiUser());
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_renewal_failed',
                    $json,
                    'Main status = 2000, substatus != 2001, domain = '.$reg->domain,
                    '',
                    array()
                  );
        	      }
        	      else
        	      {
        	        try
        	        {
        	          // successful renewal
        	          $expire = $renewaldate != '' ? $renewaldate : date('Y-m-d',mktime() + 3600 * 24 * 365);
        	          Capsule::update("UPDATE tbldomains SET expirydate = ?, nextduedate = ?, status = ? WHERE id = ?",
              			  array($expire,$expire,'Active',$reg->domain_id));
                    logModuleCall(
                      'namesrs',
                      'getCallbackData_renewal_success',
                      $json,
                      'Expiration = '.$expire.', domain = '.$reg->domain,
                      '',
                      array()
                    );
        	        }
              		catch (Exception $e)
              		{
                    logModuleCall(
                      'namesrs',
                      'getCallbackData_renewal_active',
                      $reqid,
                      '',
                      $e->getMessage(),
                      array()
                    );
              		}
        	      }
      	        try
      	        {
          	      // completed - remove from queue
          	      Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
      	        }
            		catch (Exception $e)
            		{
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_renewal_remove',
                    $reqid,
                    '',
                    $e->getMessage(),
                    array()
                  );
            		}
        	    }    	        
        	    break;

    	      case 3: // transfer
        	    if($status == 2000)
        	    {
        	      if($substatus == 2001)
        	      {
          	      try
          	      {
        	          $expire = $renewaldate != '' ? $renewaldate : date('Y-m-d',mktime() + 3600 * 24 * 365);
        	          Capsule::update("UPDATE tbldomains SET expirydate = ?, nextduedate = ?, status = ? WHERE id = ?",
              			  array($expire,$expire,'Active',$reg->domain_id));
            	      // completed - remove from queue
            	      Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
                    logModuleCall(
                      'namesrs',
                      'getCallbackData_transfer_success',
                      $json,
                      'Main status = 2000, substatus == 2001, domain = '.$reg->domain,
                      '',
                      array()
                    );
            	    }
              		catch (Exception $e)
              		{
                    logModuleCall(
                      'namesrs',
                      'getCallbackData_deleteCompletedTransfer',
                      $reqid,
                      '',
                      $e->getMessage(),
                      array()
                    );
              		}
                }
          	    elseif($substatus == 2004 OR $substatus == 4998)
          	    {
          	      // transfer failed
          	      try
          	      {
            	      $r = localAPI('UpdateClientDomain',Array(
            	        'domainid' => $reg->domain_id,
            	        'status' => 'Cancelled'
            	      ));
           	        Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
                    logModuleCall(
                      'namesrs',
                      'getCallbackData_transfer_failed',
                      $json,
                      'Main status = 2000, substatus = '.$substatus.', domain = '.$reg->domain,
                      '',
                      array()
                    );
           	      }
              		catch (Exception $e)
              		{
                    logModuleCall(
                      'namesrs',
                      'callbackTransfer_deleteFailed',
                      $reqid,
                      '',
                      $e->getMessage(),
                      array()
                    );
              		}
          	    }
          	    else
          	    {
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_unknownErrorTransfer',
                    $reg->domain,
                    'Main status = '.$status.', substatus = '.$substatus.' '.$substatus_name,
                    '',
                    array()
                  );
          	      $r = localAPI('UpdateClientDomain',Array(
          	        'domainid' => $reg->domain_id,
          	        'status' => 'Cancelled'
          	      ));                  
          	      try
          	      {
           	        Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
           	      }
              		catch (Exception $e)
              		{
                    logModuleCall(
                      'namesrs',
                      'getCallbackData_deleteOtherTransfer',
                      $reqid,
                      '',
                      $e->getMessage(),
                      array()
                    );
              		}
            			$values = array();
             			$values["messagename"] = "Domain Transfer Failed";
             			$values["mergefields"] = array(
             				"domain_transfer_failure_reason" => $substatus_name,
             			);
            			$results = localAPI("SendAdminEmail",$values,Tools::getApiUser());
                }
        	    }
        	    break;
    	        
    	      case 4: // registration
        	    if($status == 200 OR ($status == 2000 AND $substatus == 2001))
        	    {
        	      try
        	      {
          	      // completed - remove from queue
          	      Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
          	      $r = localAPI('UpdateClientDomain',Array(
          	        'domainid' => $reg->domain_id,
          	        'status' => 'Active'
          	      ));
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_register_success',
                    $json,
                    'Main status = '.$status.', substatus = '.$substatus.', domain = '.$reg->domain,
                    '',
                    array()
                  );
          	    }
            		catch (Exception $e)
            		{
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_deleteCompletedRegister',
                    $reqid,
                    '',
                    $e->getMessage(),
                    array()
                  );
            		}
              }
              elseif(in_array((int)$status,Array(2,10,11,4000)))
              {
                // processing = nothing to do
            		$r = localAPI('UpdateClientDomain',Array(
            		  'domainid' => $reg->domain_id,
            		  'status' => 'Pending'
            		));
                logModuleCall(
                  'namesrs',
                  'getCallbackData_register_waiting',
                  $json,
                  'Main status = '.$status.', substatus = '.$substatus.', domain = '.$reg->domain,
                  '',
                  array()
                );
              }
              elseif($status == 201 OR $status == 500)
              {
                // expired or expiring (201)
                try
                {
         	        Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
         	      }
            		catch (Exception $e)
            		{
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_deleteExpiredRegister',
                    $reqid,
                    '',
                    $e->getMessage(),
                    array()
                  );
            		}
            		$r = localAPI('UpdateClientDomain',Array(
            		  'domainid' => $reg->domain_id,
            		  'status' => 'Expired'
            		));
                logModuleCall(
                  'namesrs',
                  'getCallbackData_register_expired',
                  $json,
                  'Main status = '.$status.', substatus = '.$substatus.', domain = '.$reg->domain,
                  '',
                  array()
                );
              }
              elseif($status == 300)
              {
                // Transferred away
                try
                {
         	        Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
         	      }
            		catch (Exception $e)
            		{
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_deleteAwayRegister',
                    $reqid,
                    '',
                    $e->getMessage(),
                    array()
                  );
            		}
            		$r = localAPI('UpdateClientDomain',Array(
            		  'domainid' => $reg->domain_id,
            		  'status' => 'Transferred Away'
            		));
                logModuleCall(
                  'namesrs',
                  'getCallbackData_register_transferred',
                  $json,
                  'Main status = '.$status.', substatus = '.$substatus.', domain = '.$reg->domain,
                  '',
                  array()
                );
              }
              elseif($status == 2000 AND ($substatus == 4998 OR $substatus == 4999))
              {
                // rejected
                try
                {
         	        Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
         	      }
            		catch (Exception $e)
            		{
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_deleteFraudRegister',
                    $reqid,
                    '',
                    $e->getMessage(),
                    array()
                  );
            		}
                logModuleCall(
                  'namesrs',
                  'getCallbackData_register_rejected',
                  $json,
                  'Main status = '.$status.', substatus = '.$substatus.', domain = '.$reg->domain,
                  '',
                  array()
                );
            		$r = localAPI('UpdateClientDomain',Array(
            		  'domainid' => $reg->domain_id,
            		  'status' => 'Fraud'
            		));
                $this->emailAdmin('NameSRS Status',Array(
                  'domain_name'=>$reg->domain,
                  'orderType'=>'Domain Registration',
                  'status'=>'Rejected',
                  'errors'=>$substatus_name)
                );
              }
              elseif($status == 4006 OR $status == 400 OR $status == 0)
              {
                // 4006 = payment required, 400 = inactive, 0 = active (old)
                try
                {
         	        Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
         	      }
            		catch (Exception $e)
            		{
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_deletePaymentRegister',
                    $reqid,
                    '',
                    $e->getMessage(),
                    array()
                  );
            		}
                logModuleCall(
                  'namesrs',
                  'getCallbackData_register_payment',
                  $json,
                  'Main status = '.$status.', substatus = '.$substatus.', domain = '.$reg->domain,
                  '',
                  array()
                );
            		$r = localAPI('UpdateClientDomain',Array(
            		  'domainid' => $reg->domain_id,
            		  'status' => 'Cancelled'
            		));
                $this->emailAdmin('NameSRS Status',Array(
                  'domain_name'=>$reg->domain,
                  'orderType'=>'Domain Registration',
                  'status'=>$substatus_name,
                  'errors'=>$substatus_name)
                );
              }
        	    else
        	    {
                logModuleCall(
                  'namesrs',
                  'getCallbackData_unknownErrorRegister',
                  $json,
                  'Main status ('.$status.' = '.$status_name.'), substatus ('.$substatus.' = '.$substatus_name.'), domain = '.$reg->domain,
                  '',
                  array()
                );
        	      try
        	      {
         	        Capsule::delete('DELETE FROM tblnamesrsjobs WHERE id = ?',Array($reg->id));
         	      }
            		catch (Exception $e)
            		{
                  logModuleCall(
                    'namesrs',
                    'getCallbackData_deleteOtherRegister',
                    $reqid,
                    '',
                    $e->getMessage(),
                    array()
                  );
            		}
            		$r = localAPI('UpdateClientDomain',Array(
            		  'domainid' => $reg->domain_id,
            		  'status' => 'Cancelled'
            		));
                $this->emailAdmin('NameSRS Status',Array(
                  'domain_name'=>$reg->domain,
                  'orderType'=>'Domain Registration',
                  'status'=>$substatus_name,
                  'errors'=>$substatus_name)
                );
              }
    	        break;
    	    }
        }
      }
  		catch (Exception $e)
  		{
        logModuleCall(
          'namesrs',
          'getCallbackData',
          $cb_id,
          $e->getMessage(),
          $req_id,
          $json
        );
  		}
    }
    else
    {
      header('HTTP/1.1 499 Invalid JSON', true, 499);
      die;      
    }
	}

	public function checkExpiring()
	{
	  $result = Capsule::select('SELECT domain,expirydate FROM tbldomains WHERE registrar= ? AND status="Active" AND donotrenew = 0',array($this->params['registrar']));
	  foreach($result as &$dom)
	  {
	    $this->domainName = $dom->domain;
	    try
	    {
  	    $info = $this->searchDomain();
  	    if(!$info) continue; // this domain was not found by NameISP
      	if($info['renewaldate']!='') $expiration = $info['renewaldate'];
      	  else $expiration = substr($info['expires'],0,10);
      	$active = ($info['status']['200'] != ''); 	    
      	if(abs(str_replace($dom->expirydate) - str_replace($expiration)) > MARGIN_DAYS)
      	{
          $mail = new PHPMailer;
          $mail->isSMTP();
          $mail->Host = SMTP_HOST;
          $mail->SMTPAuth = true;
          $mail->Username = SMTP_USER;
          $mail->Password = SMTP_PASS;
          $mail->SMTPSecure = SMTP_SECURE;
          $mail->Port = SMTP_PORT;
          $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
          $mail->addAddress(SMTP_RECIPIENT);
          $mail->Subject = SMTP_SUBJECT.' -- '.$dom->domain;
          $mail->Body = "WHMCS thinks that domain '".$dom->domain."' expires on ".$dom->expirydate."\n"
            ."However, NameISP reports the expiration date as ".$expiration." (".($active ? "active" : "inactive").")";
        
          if(!$mail->send()) echo 'Error sending expiration notification - ',$mail->ErrorInfo,"\n";    	  
      	}
      }
      catch(Exception $e)
      {
        echo "Exception during API call - ".$e->getMessage()."\n";
      }
	  }
  }
  
}

?>