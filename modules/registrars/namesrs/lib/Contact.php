<?php

use WHMCS\Database\Capsule as Capsule;

function namesrs_GetContactDetails($params)
{
  try
  {
    $api = new RequestSRS($params);
    $result = $api->searchDomain();
    $values["Registrant"]["First Name"] = $result['owner']["firstname"];
    $values["Registrant"]["Last Name"]  = $result['owner']["lastname"];
    $values["Admin"]["First Name"] 		  = $result['admin']['firstname'];
    $values["Admin"]["Last Name"] 		  = $result['admin']['lastname'];
    $values["Tech"]["First Name"] 		  = $result['tech']['firstname'];
    $values["Tech"]["Last Name"] 		    = $result['tech']['lastname'];
    return $values;
  }
  catch (Exception $e)
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

function namesrs_setContactDetails($params)
{
  /**
   * @var $pdo PDO
   */
  $pdo = Capsule::connection()->getPdo();

  $error = false;
  $success = false;
  $phone = array();
  $values = array();
  $redirect = '';
  try
  {
    $result = $pdo->query('SELECT p.id FROM tblproducts AS p LEFT JOIN tblproductgroups AS g ON gid = g.id WHERE g.name = "Domain owner change" AND p.name = "Change registrant"');
    $pid = $result->fetch(PDO::FETCH_NUM)[0]; // our custom product
    $result = $pdo->query('SELECT id FROM tblcustomfields WHERE type = "product" AND fieldname = "ownerdata" AND relid = '.(int)$pid);
    $fid = $result->fetch(PDO::FETCH_NUM)[0]; // the custom field for our product - will store the registrant details until the payment is received

    if(isset($_POST['cmdSave']))
    {
      $values['domainid']    = $params['domainid'];
      $values['tld']         = $params['tld'];
      $values['firstname']   = trim($_POST['firstname']);
      $values['lastname']    = trim($_POST['lastname']);
      $values['companyname'] = trim($_POST['orgname']);
      $values['orgnr']       = trim($_POST['orgnr']);
      $values['countrycode'] = trim($_POST['country']);
      $values['city']        = trim($_POST['city']);
      $values['postcode']    = trim($_POST['zip']);
      $values['address1']    = trim($_POST['address']);
      $values['email']       = trim($_POST['email']);
      $values['fullphonenumber'] = '+'.$_POST['country-calling-code-phone'].'.'.preg_replace('/[^0-9]/','',preg_replace('/^0+/','',trim($_POST['phone']))); // +CC.xxx

      // prepare values for the template - in case there is a validation error and we need to retry
      $firstname = $values['firstname'];
      $lastname  = $values['lastname'];
      $orgname   = $values['companyname'];
      $orgnum    = $values['orgnr'];
      $country   = getCountriesDropDown($values['countrycode']);
      $city      = $values['city'];
      $zipcode   = $values['postcode'];
      $address   = $values['address1'];
      $phone     = explode('.',$values['fullphonenumber']);
      $email     = $values['email'];
      $err = namesrs_ValidOwner($values);
      if($err)
      {
        $error = $err['error'];
      }
      else
      {
        // add our custom product "Change registrant" to the basket and then redirect to it
        //$api->request('POST','/contact/updatecontact', $values);
        $idx = -1;
        if(is_array($_SESSION['cart']) AND is_array($_SESSION['cart']['products'])) foreach($_SESSION['cart']['products'] as $index => &$prod)
        {
          // check whether our custom product is already in the cart or not
          if($prod['pid'] == $pid)
          {
            $idx = $index;
            break;
          }
        }

        /*
         *
        $_SESSION =
          [cart] => Array
              (
                  [domainoptionspid] => 2 // productid
                  [products] => Array
                      (
                          [0] => Array
                              (
                                  [pid] => 2 // productid
                                  [domain] => test5.com  // should be populated from domain_id in order to be visible in the invoice
                                  [billingcycle] => "onetime"
                                  [configoptions] => Array
                                      (
                                      )

                                  [customfields] => Array
                                      (
                                          [2] => 4 // tblcustomfields.id => value (I will use it as domain_id)
                                      )

                                  [addons] => Array
                                      (
                                      )

                                  [server] => Array
                                      (
                                      )

                                  [skipConfig] =>
                              )

                      )

                  [cartsummarypid] => 2 // tblproducts.id
                  [user] =>
              )
         */
        if($idx < 0)
        {
          // add the custom product to the cart
          $_SESSION['cart']['cartsummarypid'] = $pid;
          $_SESSION['cart']['domainoptionspid'] = $pid;
          $_SESSION['cart']['products'] = array(
            array(
              'pid' => $pid,
              'domain' => $params['sld'].".".$params['tld'],
              'billingcycle' => 'onetime',
              'customfields' => array(
                $fid => json_encode($values),
              ),
              'configoptions' => array(),
              'addons' => array(),
              'server' => array(),
              'skipConfig' => TRUE,
            )
          );
        }
        else
        {
          // product exists in the cart - update its custom field with the new registrant details
          $_SESSION['cart']['products'][$idx]['customfields'][$fid] = json_encode($values);
        }
        $redirect = 'window.location.href = "/cart.php?a=checkout";';
      }
    }
    else
    {
      $api = new RequestSRS($params);
      $domain = $api->searchDomain();
      $owner = &$domain['owner'];
      $firstname = $owner['firstname'];
      $lastname  = $owner['lastname'];
      $orgname   = $owner['organization'];
      $orgnum    = $owner['orgnr'];
      $country   = getCountriesDropDown($owner['countrycode']);
      $city      = $owner['city'];
      $zipcode   = $owner['zipcode'];
      $address   = $owner['address1'];
      $phone     = explode('.',$owner['phone']);
      $email     = $owner['email'];
    }
  }
  catch (Exception $e)
  {
    $error = $e->getMessage();
  }
  return array(
    'templatefile' => "contactdetails",
    'vars' => array(
      'error' => $error,
      'successful' => $success,
      // 'cid' => $cid,
      'first_name' => $firstname,
      'last_name' => $lastname,
      'org_name' => $orgname,
      'org_num' => $orgnum,
      'country' => $country,
      'city' => $city,
      'zip' => $zipcode,
      'address' => $address,
      'phone' => $phone[1],
      'email' => $email,
      'redirect' => $redirect,
    ),
  );
}
