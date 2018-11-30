<?php

try 
{
	require_once("../../../init.php");
	require_once "../../../includes/registrarfunctions.php";
	require_once("lib/Request.php");
  logModuleCall(
    'namesrs',
    "Callback received from ".$_SERVER['REMOTE_ADDR'],
    json_decode(file_get_contents('php://input'),TRUE),
    '',
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