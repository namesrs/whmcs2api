<?php

use WHMCS\Database\Capsule as Capsule;

set_time_limit ( 0 );
require_once(realpath(dirname(__FILE__))."/../../../init.php");
require_once realpath(dirname(__FILE__))."/../../../includes/registrarfunctions.php";
require_once(realpath(dirname(__FILE__))."/../../../vendor/phpmailer/phpmailer/PHPMailerAutoload.php");
require_once(realpath(dirname(__FILE__))."/lib/Request.php");

define('SMTP_HOST','smtp.gmail.com');
define('SMTP_PORT',587);
define('SMTP_SECURE','tls');
define('SMTP_USER','user@example.com');
define('SMTP_PASS','1234');
define('SMTP_FROM_EMAIL','noreply@reseller.com');
define('SMTP_FROM_NAME','WHMCS poller for NameSRS');
define('SMTP_RECIPIENT','reseller@example.com');
define('SMTP_SUBJECT','WHMCS - domain sync notification');
define('MARGIN_DAYS',2); // at least 2 days difference - othweise do not report

$account = "namesrs";
$cfg = getRegistrarConfigOptions($account);
$cfg['registrar'] = $account;
$api = new RequestSRS($cfg);

$result = Capsule::select('SELECT domain,expirydate FROM tbldomains WHERE registrar= ? AND status="Active" AND donotrenew = 0', array($account));
foreach($result as &$dom)
{
  try
  {
    $domainObj = new WHMCS\Domains\Domain($domain);
    $api->domainName = $domainObj->getDomain(TRUE);
    $info = $api->searchDomain();
    if(!$info) continue; // this domain was not found by NameISP
    if($info['renewaldate']!='') $expiration = $info['renewaldate'];
    else $expiration = substr($info['expires'],0,10);
    $active = ($info['status']['200'] != '');
    if(abs(str_replace($dom->expirydate) - str_replace($expiration)) > MARGIN_DAYS)
    {
      $mail = new PHPMailer();
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
