<?php
require_once "../../../init.php";
require_once "../../../includes/registrarfunctions.php";

use WHMCS\Database\Capsule as Capsule;

/** @var  $pdo PDO */
$pdo = Capsule::connection()->getPdo();

header('Content-Type: application/force-download');
header('Content-Disposition: attachment; filename="whmcs_module_log.html"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

echo '<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <style>
      .json
      {
        font-family: "Courier New", Consolas, "Lucida Console", monospace;
        white-space: pre-wrap;
        word-break: break-word;
      }
    </style>
  </head>
  <body>
    <table width="100%" border="1" bordercolor="black" cellspacing="0">
    <thead><tr>
    <th>Date/time</th>
    <th>Action</th>
    <th>Request</th>
    <th>Response</th>
    <th>Additional information</th>
  </tr></thead><tbody>';

$output = '';
$result = $pdo->query('SELECT date,action,request,response,arrdata FROM tblmodulelog ORDER BY id DESC LIMIT 150');
while($data = $result->fetch(PDO::FETCH_ASSOC))
{
  if($data['response'] AND $data['response'][0] == '{')
  {
    $tmp = json_decode($data['response']);
    if(json_last_error() == JSON_ERROR_NONE) $data['response'] = json_encode($tmp,JSON_PRETTY_PRINT);
  }
  $output = '<tr valign="top">
    <td nowrap>'.$data['date'].'</td>
    <td style="word-break: break-word;">'.str_replace('/authenticate/login/','/authenticate/login/<br>',$data['action']).'</td>
    <td class="json">'.$data['request'].'</td>
    <td class="json">'.$data['response'].'</td>
    <td class="json">'.$data['arrdata'].'</td>
    </tr>'.$output;
}
echo $output;
echo '</tbody></table></body></html>';
