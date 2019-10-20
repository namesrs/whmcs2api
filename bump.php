<?php
if(!file_exists('version')) file_put_contents('version','1');
$ver = (int)file_get_contents('version');
$ver++;
file_put_contents('version',$ver);
$stamp = date('Y-m-d, H:i');
$def = "<?php define('VERSION',$ver); define('STAMP','$stamp'); ";
file_put_contents('modules/addons/namesrs_price/version.php',$def);
file_put_contents('modules/registrars/namesrs/version.php',$def);
file_put_contents('modules/servers/namesrsowner/version.php',$def);

$readme = file_get_contents('README.md');
$readme = preg_replace('/^# WHMCS modules from NameSRS.*$/', '# WHMCS modules from NameSRS - version '.$ver.' ('.$stamp.')', $readme);
file_put_contents('README.md',$readme);
