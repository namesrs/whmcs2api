<?php
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

$request = new Request($cfg);
$request->checkExpiring();

?>