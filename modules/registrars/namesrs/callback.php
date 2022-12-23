<?php

require_once "../../../init.php";
require_once "../../../includes/registrarfunctions.php";
require_once "lib/Request.php";
include __DIR__."/version.php";

use WHMCS\Database\Capsule as Capsule;
use WHMCS\Domains\Domain as DomPuny;

define('ROOT_FOLDER',dirname(dirname(__DIR__)));

$old_error_handler = set_error_handler('myErrorHandler', E_ALL);
header('X-WHMCS: '.VERSION.', '.STAMP);
/** @var  $pdo  PDO */
$pdo = Capsule::connection()->getPdo();

// We accept callbacks only from the NameISP server - to prevent hackers from sending fake callbacks
// We also allow invoking this script from the command line and feeding it from STDIN
$remoteIP = '';
if(isset($_SERVER['HTTP_CF_CONNECTING_IP'])) $remoteIP = $_SERVER['HTTP_CF_CONNECTING_IP']; // CloudFlare
if($remoteIP == '') $remoteIP = $_SERVER['REMOTE_ADDR'];
if (in_array($remoteIP, [
    '91.237.66.70', // NameISP production server
    '78.90.165.87', // development machine
  ]) OR php_sapi_name() == 'cli') try
{
  $payload = file_get_contents('php://input');
  $json = json_decode($payload, TRUE);
  if (!(is_array($json) AND count($json) > 0))
  {
    echo '{"code": 1, "message":"Empty payload"}';
    $headers = [];
    foreach ($_SERVER as $name => $value)
    {
      if (substr($name, 0, 5) == 'HTTP_')
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
    }
    logModuleCall(
      'nameSRS',
      "Empty callback received from " . $_SERVER['REMOTE_ADDR'],
      $payload,
      $headers
    );
    adminError("NameSRS callback - Empty callback received from " . $_SERVER['REMOTE_ADDR'], $payload, $headers);
    die;
  }
  logModuleCall(
    'nameSRS',
    "Callback received from " . $_SERVER['REMOTE_ADDR'],
    json_encode($json,JSON_PRETTY_PRINT),
    ''
  );
  $account = "namesrs";
  $cfg = getRegistrarConfigOptions($account);
  $api = new RequestSRS($cfg);

  if ($json['template'] == 'REQUEST_UPDATE')
  {
    $reqid = (int)$json['reqid'];
    $domainname = $json['objectname'];
    if ($domainname != '')
    {
      if ($json['status']['mainstatus']) $status = key($json['status']['mainstatus']);
      else $status = '';
      if ($status != '') $status_name = $json['status']['mainstatus'][$status];
      else $status_name = 'Unknown status';
      if ($json['status']['substatus']) $substatus = key($json['status']['substatus']);
      else $substatus = '';
      if ($substatus != '') $substatus_name = $json['status']['substatus'][$substatus];
      else $substatus_name = 'Unknown substatus';
      // NOT PROVIDED in REQUEST_UPDATE $renewaldate = $json['renewaldate']; // YYYY-MM-DD

      // our local queue of API requests, waiting their reply
      $stm = $pdo->query('SELECT jobs.id,last_id AS domain_id,domain,method,userid,request 
        FROM tblnamesrsjobs AS jobs 
        LEFT JOIN tbldomains ON tbldomains.id = last_id 
        WHERE order_id = ' . $reqid);
      if ($stm->rowCount())
      {
        $req = $stm->fetch(PDO::FETCH_ASSOC);
        switch ($req['method'])
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
            echo '{"code": 6, "message": "Unknown request type in the WHMCS queue"}';
            logModuleCall(
              'nameSRS',
              "Unknown request type (" . $req['method'] . ") in the WHMCS queue",
              json_encode($req,JSON_PRETTY_PRINT),
              ''
            );
            adminError("NameSRS callback - Unknown request type (" . $req['method'] . ") in the WHMCS queue", $req);
        }
      }
      else
      {
        echo '{"code": 7, "message": "Request ID was not found in WHMCS queue"}';
        logModuleCall(
          'nameSRS',
          "Could not find Request ID (" . $reqid . ") in the WHMCS queue",
          '',
          ''
        );
        adminError("NameSRS callback - Could not find Request ID (" . $reqid . ") in the WHMCS queue", '');
      }
    }
    else
    {
      echo '{"code": 2, "message":"Missing object name"}';
      logModuleCall(
        'nameSRS',
        "Missing object name",
        json_encode($json,JSON_PRETTY_PRINT),
        ''
      );
      adminError("NameSRS callback - Missing object name in the callback payload", $json);
    }
  }
	elseif ($json['template'] == 'ITEM_UPDATE' OR $json['template'] == 'ITEM_CREATED')
  {
    $domainid = (int)$json['itemdetails']['custom_field'];
    if ($domainid == 0)
    {
      $domainname = $json['itemdetails']['idndomainname'];
      if($domainname == '')
      {
        $domainObj = new DomPuny($json['itemdetails']['domainname']);
        $domainname = $domainObj->getDomain(FALSE); // get UTF-8
      }
      // find the most recent domain with this name
      $stm = $pdo->prepare('SELECT id,status FROM tbldomains WHERE registrar = "namesrs" AND domain = :name ORDER BY id DESC');
      $stm->execute(['name' => $domainname]);
      $cnt = $stm->rowCount();
      while($row = $stm->fetch(PDO::FETCH_ASSOC))
      {
        if($row['status'] != 'Fraud' AND $row['status'] != 'Cancelled')
        {
          $domainid = $row['id'];
          break;
        }
      }
      if($domainid == 0)
      {
        if($cnt == 0) $msg = 'Could not find this domain"'.$json['itemdetails']['domainname'].'"';
        else $msg = 'Missing custom_field - domain "'.$json['itemdetails']['domainname'].'" was found but status was not PENDING';
        echo '{"code": 3, "message":'.json_encode($msg).'}';
        logModuleCall(
          'nameSRS',
          $msg,
          json_encode($json,JSON_PRETTY_PRINT),
          ''
        );
        adminError("NameSRS callback - ".$msg, $json);
        die;
      }
    }

    // mark all other domains with this name Cancelled
    $stm = $pdo->prepare('UPDATE tbldomains SET status = "Cancelled" WHERE registrar = "namesrs" AND id <> :id AND domain = :name');
    $stm->execute(['id' => $domainid, 'name' => $domainname]);

    $status = $json['itemdetails']['mainstatus'];
    $status_name = 'Missing status name';
    $substatus = $json['itemdetails']['substatus'];
    $substatus_name = 'Missing substatus name';

    // update the expiration date and next due date
    $expire = substr($json['itemdetails']['renewaldate'], 0, 10);
    if ($expire != '' AND preg_match('/^\d{4}-\d{2}-\d{2}$/', $expire))
    {
      $stm = $pdo->prepare('UPDATE tbldomains SET expirydate = :exp'.($cfg['sync_due_date'] ? ', nextduedate = DATE_SUB(:exp2,INTERVAL (
        SELECT value FROM tblconfiguration WHERE setting = "DomainSyncNextDueDateDays" ORDER BY id DESC LIMIT 1
      ) day)' : '').' WHERE registrar = "namesrs" AND id = :id');
      $stm->execute($cfg['sync_due_date'] ? ['exp' => $expire, 'exp2' => $expire, 'id' => $domainid] : ['exp' => $expire, 'id' => $domainid]);
      logModuleCall(
        'nameSRS',
        "Updated expiration date (".$expire.")".($cfg['sync_due_date'] ? " and next due date" : "")." for " . $domainname,
        $json['itemdetails']['domainname'],
        'Affected rows = ' . $stm->rowCount()
      );
    }
    // update the registration date
    $regdate = substr($json['itemdetails']['created'],0,10);
    if($regdate != '' AND preg_match('/^\d{4}-\d{2}-\d{2}$/', $regdate))
    {
      $stm = $pdo->prepare('UPDATE tbldomains SET registrationdate = :regdate WHERE registrar = "namesrs" AND id = :id');
      $stm->execute(['regdate' => $regdate, 'id' => $domainid]);
      logModuleCall(
        'nameSRS',
        "Updated registration date (".$regdate.") for " . $domainname,
        $json['itemdetails']['domainname'],
        'Affected rows = ' . $stm->rowCount()
      );
    }
    // Domain is ACTIVE
    if ($status == 200 OR $status == 201 OR $status == 202)
    {
      $stm = $pdo->prepare('UPDATE tbldomains SET status = :stat WHERE registrar = "namesrs" AND id = :id');
      $stm->execute(['stat' => 'Active', 'id' => $domainid]);
      logModuleCall(
        'nameSRS',
        "Setting status to ACTIVE for " . $domainname,
        $json['itemdetails']['domainname'],
        'Affected rows = ' . $stm->rowCount()
      );
    }
    // Domain is TRANSFERRED AWAY
		elseif ($status == 300)
    {
      $stm = $pdo->prepare('UPDATE tbldomains SET status = :stat WHERE registrar = "namesrs" AND id = :id');
      $stm->execute(['stat' => 'Transferred Away', 'id' => $domainid]);
      logModuleCall(
        'nameSRS',
        "Setting status to TRANSFERRED AWAY for " . $domainname,
        $json['itemdetails']['domainname'],
        'Affected rows = ' . $stm->rowCount()
      );
    }
    // Domain is EXPIRED
		elseif (in_array((int)$status, [500, 503, 504]))
    {
      switch($status)
      {
        case 503:
          $statName = 'Redemption';
          break;
        case 504:
          $statName = 'Grace';
          break;
        default:
          $statName = 'Expired';
      }
      $stm = $pdo->prepare('UPDATE tbldomains SET status = :stat WHERE registrar = "namesrs" AND id = :id');
      $stm->execute(['stat' => $statName, 'id' => $domainid]);
      logModuleCall(
        'nameSRS',
        "Setting status to EXPIRED for " . $domainname,
        $json['itemdetails']['domainname'],
        'Affected rows = ' . $stm->rowCount()
      );
    }
  }
  else
  {
    echo '{"code": 4, "message": "Unknown template"}';
    logModuleCall(
      'nameSRS',
      "Callback IGNORED from " . $_SERVER['REMOTE_ADDR'],
      json_encode($json,JSON_PRETTY_PRINT),
      'Template is not recognized'
    );
    adminError("NameSRS callback - ignored unknown template '".$json['template']."' from " . $_SERVER['REMOTE_ADDR'], $json);
  }
}
catch (Exception $e)
{
  //header('HTTP/1.1 500 Error in callback', TRUE, 500);
  logModuleCall(
    'nameSRS',
    "Error processing callback",
    $e->getMessage(),
    $e->getTrace()
  );
  echo '{"code": 5, "message": '.json_encode($e->getMessage()).'}';
  adminError("NameSRS callback - run-time error (".$e->getMessage().")", $e->getTrace());
}

function domainStatus($domain_id, $status)
{
  $command = "UpdateClientDomain";
  $admin = getAdminUser();
  $values = [];
  $values["domainid"] = $domain_id;
  $values['status'] = $status;
  localAPI($command, $values, $admin);
}


function emailAdmin($tpl, $fields)
{
  $values = [];
  $values["messagename"] = $tpl;
  $values["mergefields"] = $fields;

  $admin = getAdminUser();
  $r = localAPI("SendAdminEmail", $values, $admin);

  logModuleCall(
    'nameSRS',
    'email_admin',
    "Tried to send email to Admin, don't know if it was delivered",
    ['input' => $values, 'output' => $r]
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

function myErrorHandler($errno, $errstr, $errfile, $errline)
{
  //header('HTTP/1.1 500 Error in callback', TRUE, 500);
  // Only handle the errors specified by the error_reporting directive or function
  // Ensure that we should be displaying and/or logging errors
  if ( ! ($errno & error_reporting ()) || ! (ini_get ('display_errors') || ini_get ('log_errors'))) return;

  //if (($errno & (E_NOTICE | E_STRICT)) AND !preg_match('#^'.ROOT_FOLDER.'/(registrars|addons|servers)/namesrs)#',$errfile)) return;

  // define an assoc array of error string
  // in reality the only entries we should
  // consider are 2,8,256,512 and 1024
  $errortype = [
    1 => 'Error',
    2 => 'Warning',
    4 => 'Parsing Error',
    8 => 'Notice',
    16 => 'Core Error',
    32 => 'Core Warning',
    64 => 'Compile Error',
    128 => 'Compile Warning',
    256 => 'User Error',
    512 => 'User Warning',
    1024 => 'User Notice',
    2048 => 'Strict Mode',
    4096 => 'Recoverable Error',
  ];
  $s = "<br>\n<b>" . $errortype[$errno] . "</b><br>\n$errstr<br><br>\n\n# $errline, $errfile";
  $MAXSTRLEN = 1500;
  $s .= '<pre>';
  $a = debug_backtrace();
  $traceArr = array_reverse($a);
  $tabs = 1;
  if (count($traceArr)) foreach ($traceArr as $arr)
  {
    if ($arr['function'] == 'myErrorHandler') continue;
    $Line = (isset($arr['line']) ? $arr['line'] : "unknown");
    $File = (isset($arr['file']) ? str_replace('/var/www/whmcs', '', $arr['file']) : "unknown");
    $s .= "\n<br>";
    for ($i = 0; $i < $tabs; $i++)
    {
      $s .= '#';
    }
    $s .= ' <b>' . $Line . '</b>, <font color="blue">' . $File . "</font>\n<br>";
    for ($i = 0; $i < $tabs; $i++)
    {
      $s .= ' ';
    }
    $tabs++;
    $s .= ' ';
    if (isset($arr['class']))
    {
      $s .= $arr['class'] . '.';
    }
    $args = [];
    if (!empty($arr['args'])) foreach ($arr['args'] as $v)
    {
      if (is_null($v)) $args[] = 'NULL';
			elseif (is_array($v)) $args[] = 'Array[' . sizeof($v) . ']' . (sizeof($v) <= 5 ? substr(serialize($v), 0, $MAXSTRLEN) : '');
			elseif (is_object($v)) $args[] = 'Object:' . get_class($v);
			elseif (is_bool($v)) $args[] = $v ? 'true' : 'false';
      else
      {
        $v = (string)@$v;
        $str = htmlspecialchars(substr($v, 0, $MAXSTRLEN));
        if (strlen($v) > $MAXSTRLEN) $str .= '...';
        $args[] = "\"" . $str . "\"";
      }
    }
    if (isset($arr['function']))
    {
      $s .= $arr['function'] . '(' . implode(', ', $args) . ')';
    }
    else
    {
      $s .= '[PHP Kernel] (' . implode(', ', $args) . ')';
    }
  }
  $s .= '</pre>';
  logModuleCall(
    'nameSRS',
    "Error processing callback",
    $s,
    ''
  );
  echo '{"code": 5, "message": '.json_encode($s).'}';
  die;
}

