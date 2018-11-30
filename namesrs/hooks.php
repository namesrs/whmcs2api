<?php
require_once("lib/Request.php");

function hook_set_domain_status_namesrs($vars) 
{  
	if(strpos($vars["params"]["registrar"], "namesrs") == false) return;	
	$request = new Request(array('API_key'=> $vars["params"]["API_key"],'Base_URL' => $vars["params"]["Base_URL"]));
	$domain = $vars["params"]["sld"].".".$vars["params"]["tld"];
	logActivity("Calling hook for domain ".$domain);
	$type = $vars["params"]["regtype"]; // "Transfer" or "Transfer_Domain";
	$params = array ("DomainName" => $domain);
	$request->setStatus($params,"Pending",$type);
}
 
add_hook("AfterRegistrarRegistration",1,"hook_set_domain_status_nameisp");
add_hook("AfterRegistrarTransfer",1,"hook_set_domain_status_nameisp");

use WHMCS\View\Menu\Item as MenuItem;

// disable some of the built-in WHMCS menus in the sidebar as we either do not support
// the functionality or offer better implementations

add_hook('ClientAreaPrimarySidebar', 1, function(MenuItem $primarySidebar)
{
  $sidebar = $primarySidebar->getChild('Domain Details Management');
  if($sidebar)
  {
    $sidebar->removeChild('Domain Contacts');
    $sidebar->removeChild('Manage Private Nameservers');
    $sidebar->removeChild('Manage Email Forwarding');
  }
});

?>