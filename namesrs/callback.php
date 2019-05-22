<?php

require_once "../../../init.php";
require_once "../../../includes/registrarfunctions.php";
require_once "lib/Request.php";

use WHMCS\Database\Capsule as Capsule;

$pdo = Capsule::connection()->getPdo();

if(in_array($_SERVER['REMOTE_ADDR'],array(
'91.237.66.70',
))) try
{
  $payload = file_get_contents('php://input');
  $json = json_decode($payload,TRUE);
	if(!(is_array($json) AND count($json)>0))
	{
    header('HTTP/1.1 400 Empty payload', true, 400);
    $headers = [];
    foreach ($_SERVER as $name => $value)
    {
      if (substr($name, 0, 5) == 'HTTP_')
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
    }
    logModuleCall(
      'nameSRS',
      "Empty callback received from ".$_SERVER['REMOTE_ADDR'],
      $payload,
      $headers
    );
    die;
	}
  logModuleCall(
    'nameSRS',
    "Callback received from ".$_SERVER['REMOTE_ADDR'],
    $json,
    $payload
  );
	$account = "namesrs";
	$cfg = getRegistrarConfigOptions($account);
	$api = new RequestSRS($cfg);

	if($json['template'] == 'REQUEST_UPDATE')
  {
    $cb_id = (int)$json['callbackid'];
    $reqid = (int)$json['reqid'];
    $status = key($json['status']['mainstatus']);
    $status_name = $json['status']['mainstatus'][$status];
    $substatus = key($json['status']['substatus']);
    $substatus_name = $json['status']['substatus'][$substatus];
    // NOT PROVIDED in REQUEST_UPDATE $renewaldate = $json['renewaldate']; // YYYY-MM-DD

    // our local queue of API requests, waiting their reply
    $stm = $pdo->query('SELECT jobs.id,last_id AS domain_id,domain,method,userid,request FROM tblnamesrsjobs AS jobs LEFT JOIN tbldomains ON tbldomains.id = last_id WHERE order_id = '.$reqid);
    if($stm->rowCount())
    {
      $req = $stm->fetch(PDO::FETCH_ASSOC);
      switch($req['method'])
      {
        case 2: // renewal
          include "callbacks/CallbackRenew.php";
          break;
        case 3: // transfer
          include "callbacks/CallbackTransfer.php";
          break;
        case 4: // register
          include "callbacks/CallbackRegister.php";
          break;
        default:
          logModuleCall(
            'nameSRS',
            "Unknown request type (".$req['method'].") in the WHMCS queue",
            $req,
            ''
          );
      }
    }
    else logModuleCall(
      'nameSRS',
      "Could not find Request ID (".$reqid.") in the WHMCS queue",
      '',
      ''
    );
  }
	elseif($json['template'] == 'ITEM_UPDATE')
	{
    $status = key($json['status']['mainstatus']);
    $status_name = $json['status']['mainstatus'][$status];
    $substatus = key($json['status']['substatus']);
    $substatus_name = $json['status']['substatus'][$substatus];
	  $domainname = idn_to_utf8($json['objectname']);
	  if($domainname != '')
	  {
	    $expire = substr($json['renewaldate'],0,10);
  	  if($expire!='' AND preg_match('/\d{4}-\d{2}-\d{2}/',$expire))
  	  {
  	    $stm = $pdo->prepare('UPDATE tbldomains SET expirydate = :exp, nextduedate = :exp WHERE registrar = "namesrs" AND domain = :name');
  	    $stm->execute(array('exp' => $expire, 'name' => $domainname));
        logModuleCall(
          'nameSRS',
          "Updated expiration date for ".$domainname,
          $json['objectname'],
          ''
        );  	    
  	  }
  	  if($status == 200 OR $status == 201)
  	  {
  	    $stm = $pdo->prepare('UPDATE tbldomains SET status = :stat WHERE registrar = "namesrs" AND domain = :name');
  	    $stm->execute(array('name' => $domainname, 'stat' => 'Active'));
  	  }
  	  elseif($status == 300)
  	  {
  	    $stm = $pdo->prepare('UPDATE tbldomains SET status = :stat WHERE registrar = "namesrs" AND domain = :name');
  	    $stm->execute(array('name' => $domainname, 'stat' => 'Transferred Away'));
  	  }
  	  elseif(in_array((int)$status,Array(500,503,504)))
  	  {
  	    $stm = $pdo->prepare('UPDATE tbldomains SET status = :stat WHERE registrar = "namesrs" AND domain = :name');
  	    $stm->execute(array('name' => $domainname, 'stat' => 'Expired'));
  	  }
  	}
  	else logModuleCall(
      'nameSRS',
      "Could not convert the object name from IDN to UTF-8 - ".$json['objectname'],
      $json,
      ''
    );  	
	}
	else
  {
    logModuleCall(
      'nameSRS',
      "Callback IGNORED from ".$_SERVER['REMOTE_ADDR'],
      $json,
      'Template is not recognized'
    );
  }  
}
catch (Exception $e)
{
  header('HTTP/1.1 500 Error in callback', true, 500);
  logModuleCall(
    'nameSRS',
    "Error processing callback",
    $e->getMessage(),
    $e->getTrace()
  );
  echo $e->getMessage(),"\n\n",$e->getTrace();
}

function domainStatus($domain_id, $status)
{
  $command  = "UpdateClientDomain";
  $admin   	= getAdminUser();
  $values   = array();
  $values["domainid"] = $domain_id;
  $values['status'] = $status;
  localAPI($command, $values, $admin);
}

function emailAdmin($tpl, $fields)
{
  $values = array();
  $values["messagename"] = $tpl;
  $values["mergefields"] = $fields;

  $admin = getAdminUser();
  $r = localAPI("SendAdminEmail", $values, $admin);

  logModuleCall(
    'nameSRS',
    'email_admin',
    "Tried to send email to Admin, don't know if it was delivered",
    array('input' => $values, 'output' => $r)
  );
}

function getAdminUser()
{
  $result = Capsule::select("select username from tbladmins where disabled=0 limit 1");
  return is_array($result) && count($result) ? $result[0]->username : '';
}

function namesrs_log($x)
{
  syslog(LOG_INFO | LOG_LOCAL1, $x);
}

?>
