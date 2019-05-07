<?php

if(in_array($_SERVER['REMOTE_ADDR'],array(
'91.237.66.70',
))) try 
{
	require_once("../../../init.php");
	require_once "../../../includes/registrarfunctions.php";
	require_once("lib/Request.php");
	$arr = json_decode(file_get_contents('php://input'),TRUE);
	if(!(is_array($arr) AND count($arr)>0))
	{
    header('HTTP/1.1 400 Empty payload', true, 400);
    $headers = []; 
    foreach ($_SERVER as $name => $value) 
    { 
      if (substr($name, 0, 5) == 'HTTP_') 
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
    }    
    logModuleCall(
      'namesrs',
      "Empty callback received from ".$_SERVER['REMOTE_ADDR'],
      file_get_contents('php://input'),
      $headers,
      '',
      array()
    );
    die;
	}
  logModuleCall(
    'namesrs',
    "Callback received from ".$_SERVER['REMOTE_ADDR'],
    json_decode(file_get_contents('php://input'),TRUE),
    file_get_contents('php://input'),
    '',
    array()
  );
	$account = "namesrs";
	$cfg = getRegistrarConfigOptions($account);
	$request = new Request($cfg);
	$request->getCallbackData();
} 
catch (Exception $e) 
{
  header('HTTP/1.1 500 Error in callback', true, 500);
  logModuleCall(
    'namesrs',
    "Error processing callback",
    $e->getMessage(),
    '',
    '',
    array()
  );
}

?>