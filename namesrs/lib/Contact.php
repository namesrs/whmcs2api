<?php

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
    logActivity("WHMCS GetContactDetails");
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
  $error = false;
  $success = false;
  $phone = Array();
  try
  {
    $api = new RequestSRS($params);
    if(isset($_POST['cmdSave']))
    {
      $values['contactid'] = (int)$_POST['contactid'];
      $values['city']      = trim($_POST['city']);
      $values['zipcode']   = trim($_POST['zip']);
      $values['address1']  = trim($_POST['address']);
      $values['phone']     = trim('+'.$_POST['country-calling-code-phone'].'.'.str_replace(' ','',$_POST['phone']));
      $values['email']     = trim($_POST['email']);
      $api->request('POST','/contact/updatecontact', $values);
      DomainCache::clear($api->domainName);
    }
    $domain = $api->searchDomain();
    $owner = &$domain['owner'];
    $cid       = $owner['contactid'];
    $firstname = $owner['firstname'];
    $lastname  = $owner['lastname'];
    $orgname   = $owner['organization'];
    $orgnum    = $owner['orgnr'];
    $address   = $owner['address1'];
    $zipcode   = $owner['zipcode'];
    $city      = $owner['city'];
    $country   = $owner['countrycode'];
    $phone     = explode('.',$owner['phone']);
    $email     = $owner['email'];
  }
  catch (Exception $e)
  {
    $error = $e->getMessage(); // do not overwrite existing error
  }
  return array(
    'templatefile' => "contactdetails",
    'vars' => array('error' => $error, 'successful' => $success, 'cid' => $cid, 'first_name' => $firstname, 'last_name' => $lastname,
      'org_name' => $orgname, 'org_num' => $orgnum, 'country' => $country, 'city' => $city, 'zip' => $zipcode,
      'address' => $address, 'phone' => $phone[1], 'email' => $email
    ),
  );
}
