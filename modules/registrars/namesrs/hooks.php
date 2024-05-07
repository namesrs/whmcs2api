<?php

use WHMCS\Database\Capsule as Capsule;
use WHMCS\Domains\Domain as DomPuny;

add_hook("AfterRegistrarRegistration",1,function($vars)
{
  if($vars['params']['registrar'] == 'namesrs')
  {
    //Only launch hook in this module
    logModuleCall(
      'nameSRS',
      "Calling hook DOMAIN_STATUS_REGISTER",
      $vars['params']['sld'].'.'.$vars['params']['tld'],
      $vars
    );
    // set domain to PENDING status - until the callback/webhook updates it accordingly
    localAPI('UpdateClientDomain',Array(
      'domainid' => $vars['params']['domainid'],
      'status' => 'Pending Registration'
    ));
  }
});

add_hook("AfterRegistrarTransfer",1,function($vars)
{
  if($vars['params']['registrar'] == 'namesrs')
  {
    //Only launch hook in this module
    logModuleCall(
      'nameSRS',
      "Calling hook DOMAIN_STATUS_REGISTER",
      $vars['params']['sld'].'.'.$vars['params']['tld'],
      $vars
    );
    // set domain to PENDING status - until the callback/webhook updates it accordingly
    localAPI('UpdateClientDomain',Array(
      'domainid' => $vars['params']['domainid'],
      'status' => 'Pending Transfer'
    ));
  }
});

use WHMCS\View\Menu\Item as MenuItem;

// disable some of the built-in WHMCS menus in the sidebar as we either do not support
// the functionality or offer better implementations
/*
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
*/

/**
 * This widget allows for easy downloading of the last 150 records from the Module Log
 */
add_hook('AdminHomeWidgets', 1, function()
{
    return new NameSRSWidget();
});

/**
 * Module log exporter widget
 */
class NameSRSWidget extends \WHMCS\Module\AbstractWidget
{
 /**
     * @type string The title of the widget
     */
    protected $title = 'NameSRS Log exporter';

    /**
     * @type string A description/purpose of the widget
     */
    protected $description = '';

    /**
     * @type int The sort weighting that determines the output position on the page
     */
    protected $weight = 100;

    /**
     * @type int The number of columns the widget should span (1, 2 or 3)
     */
    protected $columns = 1;

    /**
     * @type bool Set true to enable data caching
     */
    protected $cache = false;

    /**
     * @type int The length of time to cache data for (in seconds)
     */
    protected $cacheExpiry = 120;

    /**
     * @type string The access control permission required to view this widget. Leave blank for no permission.
     * @see Permissions section below.
     */
    protected $requiredPermission = '';

    /**
     * Get Data.
     *
     * Obtain data required to render the widget.
     *
     * We recommend executing queries and API calls within this function to enable
     * you to take advantage of the built-in caching functionality for improved performance.
     *
     * When caching is enabled, this method will be called when the cache is due for
     * a refresh or when the user invokes it.
     *
     * @return array
     */
    public function getData()
    {
      /*
        $clients = localAPI('getclients', []);

        return array(
            'welcome' => 'Hello World!',
            'clients' => $clients['clients'],
        );
      */
    }

    /**
     * Generate Output.
     *
     * Generate and return the body output for the widget.
     *
     * @param array $data The data returned by the getData method.
     *
     * @return string
     */
    public function generateOutput($data)
    {
      /*
        $clientOutput = [];
        foreach ($data['clients']['client'] as $client) {
            $clientOutput[] = "<a href=\"clientsprofile.php?id={$client['id']}\">{$client['firstname']} {$client['lastname']}</a>";
        }

        if (count($clientOutput) == 0) {
            $clientOutput[] = 'No Clients Found';
        }

        $clientOutput = implode('<br>', $clientOutput);
      */
      return <<<EOF
<div class="widget-content-padded">
    <div class="col-12 text-center">
        <a href="/modules/registrars/namesrs/export_log.php" class="btn btn-default btn-sm" target="_blank">
            <i class="fas fa-arrow-right"></i> Download last 150 log entries for the NameSRS registrar module
        </a>
    </div>
</div>
EOF;
    }
}


/**
 * This hook will validate the additional fields that are required for each TLD
 * An error will be returned if the necessary fields are not filled by the user
 * Domain fields are keyed by their display name rather than their key inside additional_domain_fields.php
 */
add_hook('ShoppingCartValidateDomainsConfig', 50, function ($vars)
{
  $errors = [];

  foreach($_SESSION['cart']['domains'] as $key => $domain)
  {
    if(substr($domain['domain'], -strlen('.no')) === '.no')
    {
      switch($vars['domainfield'][$key]["Registrant's type"])
      {
        case 'IND':
          if(!trim($vars['domainfield'][$key]['.NO PID number']))
          {
            $errors[] = 'ID number is required for Norwegian residents';
          }
          elseif(!preg_match("/^N.PRI.(0[1-9]|1[0-9]|2[0-9]|3[0-1])(0[1-9]|1[0-2])\d{7}$/i", trim($vars['domainfield'][$key]['.NO PID number'])))
          {
            $errors[] = 'Given ID number is invalid';
          }
          break;
        case 'ORG':
          if(!trim($vars['domainfield'][$key]['Legal entity registration number']))
          {
            $errors[] = 'VAT/Tax ID number is a required field for corporate bodies';
          }
          break;
        default:
          $errors[] = 'Registrant type is missing';
          break;
      }
    }
  }

  return $errors;
});
