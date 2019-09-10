<?php

use WHMCS\Database\Capsule as Capsule;
use WHMCS\Domains\Domain as DomPuny;

add_hook('OrderProductPricingOverride', 1, function($vars)
{
  logModuleCall(
    'nameSRS',
    "Calling hook PRODUCT_PRICE_OVERRIDE",
    $vars,
    ''
  );
  $result = [];
  /*
$vars = Array
(
    [key] => 0
    [pid] => 2
    [proddata] => Array
        (
            [pid] => 2
            [domain] =>
            [billingcycle] => onetime
            [configoptions] => Array
                (
                )

            [customfields] => Array
                (
                    [0] => Array
                        (
                            [id] => 2
                            [textid] => domainid
                            [name] => domainid
                            [description] => Domain ID
                            [type] => text
                            [input] => <input type="text" name="customfield[2]" id="customfield2" value="" size="30" class="form-control" />
                            [value] =>
                            [rawvalue] =>
                            [required] =>
                            [adminonly] =>
                        )

                )

            [addons] => Array
                (
                )

            [server] => Array
                (
                )

            [noconfig] => 1
            [allowqty] => 0
            [qty] => 1
            [productinfo] => Array
                (
                    [pid] => 2
                    [gid] => 3
                    [type] => other
                    [groupname] => Owner change
                    [name] => Owner change 3
                    [description] =>
                    [freedomain] =>
                    [freedomainpaymentterms] => Array
                        (
                            [0] =>
                        )

                    [freedomaintlds] => Array
                        (
                            [0] =>
                        )

                    [qty] =>
                )

            [billingcyclefriendly] => One Time
        )

)
  */

  $prod = $vars['proddata']['productinfo'];
  if(is_array($prod) AND $prod['name'] == 'Change registrant' AND $prod['groupname'] == 'Domain owner change')
  {
    // get the domain name from the ID
    $owner = trim($vars['proddata']['customfields'][0]['rawvalue']); // registrant details
    if($owner != '')
    {
      $json = json_decode($owner,TRUE);
      /** @var  $pdo  PDO */
      $pdo = Capsule::connection()->getPdo();
      $res = $pdo->query('SELECT domain FROM tbldomains WHERE id = '.(int)$json['domainid']);
      if($res->rowCount())
      {
        $domainName = $res->fetch(PDO::FETCH_NUM)[0];
        //check if the registrar module exists
        if(!function_exists('namesrs_getConfigArray'))
        {
          //load and check if registrar module is installed
          require_once(implode(DIRECTORY_SEPARATOR, [ROOTDIR, "includes", "registrarfunctions.php"]));
          $module = "namesrs";
          $registrar_main = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "modules", "registrars", $module, $module . ".php"]);
          if (file_exists($registrar_main))
          {
            require_once($registrar_main);
            $configoptions = getregistrarconfigoptions($module);
            $configoptions['domainObj'] = new DomPuny($domainName); // NameSRS API requires Punycoded domains rather than Unicode

            // get the price of OwnerChange for the given domain
            $api = new RequestSRS($configoptions);
            $domain = $api->searchDomain();
            $price = $domain['prices']['Retail']['OwnerChange'];
            // use the currency from the client's profile
            $currency = getCurrency($_SESSION['uid']);
            $value = 0;
            if ($currency['code'] == $price['currency']) $value = $price['price'];
            elseif (is_array($price['currencies']) AND is_array($price['currencies'][$currency['code']]))
            {
              $value = $price['currencies'][$currency['code']]['price'];
            }
            // add the reseller's profit margin
            if($value)
            {
              $stm = $pdo->prepare('SELECT monthly FROM tblpricing WHERE type = "product" AND relid = ? AND currency = ? ORDER BY id DESC LIMIT 1');
              $stm->execute([$prod['pid'], $currency['id']]);
              $profit = $stm->fetch(PDO::FETCH_NUM)[0];
              logModuleCall(
                'nameSRS',
                "Hook PRODUCT_PRICE_OVERRIDE",
                'We got the price from the API',
                array(
                  'currency' => $currency['code'],
                  'price' => $value,
                  'profit' => $profit,
                )
              );
              $value += (double)$profit;
            }

            $result = ['setup' => '0.00', 'recurring' => round($value,2)];
          }
          else logModuleCall(
            'nameSRS',
            "Hook PRODUCT_PRICE_OVERRIDE",
            'Could not load the NameSRS registrar module',
            ''
          );
        }
      }
    }
    else logModuleCall(
      'nameSRS',
      "Hook PRODUCT_PRICE_OVERRIDE",
      'The custom field is empty or missing - no registrant details',
      ''
    );
  }

  return $result;
});
