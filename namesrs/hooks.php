<?php

function hook_set_domain_status_namesrs($vars)
{
  logModuleCall(
    'nameSRS',
    "Calling hook SET_DOMAIN_STATUS",
    $vars['params']['sld'].'.'.$vars['params']['tld'],
    $vars
  ); 
	if($vars["params"]["registrar"] != "namesrs") return;
	// set domain to PENDING status - until the callback/webhook update it accordingly
  localAPI('UpdateClientDomain',Array(
    'domainid' => $vars['params']['domainid'],
    'status' => 'Pending'
  ));
}

add_hook("AfterRegistrarRegistration",1,"hook_set_domain_status_namesrs");
add_hook("AfterRegistrarTransfer",1,"hook_set_domain_status_namesrs");

use WHMCS\View\Menu\Item as MenuItem;

// disable some of the built-in WHMCS menus in the sidebar as we either do not support
// the functionality or offer better implementations

add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $primarySidebar)
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
