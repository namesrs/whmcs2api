<?php
/*
*
* NameISP Registrant Change Module
* Author: www.nameisp.com - support@nameisp.com
*
*/
include __DIR__."/version.php";
use WHMCS\Database\Capsule as Capsule;
use WHMCS\Domains\Domain as DomPuny;

if (!defined("WHMCS")) die("This file cannot be accessed directly");

function namesrsowner_MetaData()
{
  return array(
    'DisplayName' => 'NameSRS Registrant Change Module v'.VERSION.' ('.STAMP.')',
  );
}

/**
 * After the payment for the Registrant Change custom product has been received,
 * we should actually send the request to the NameSRS API.
 *
 * @param $params array
 * serviceid - tblhosting.id
 * pid - tblproducts.id ("Change registrant")
 * domain - tblhosting.domain
 * customfields - an array of all custom fields defined on the product, the key is the custom field name ($params[‘customfields’][‘Field Name’]); our field is named "ownerdata" and contains JSON
 * @return string "success" or an error message
 */
function namesrsowner_CreateAccount($params)
{
  /** @var  $pdo PDO */
  $pdo = Capsule::connection()->getPdo();

  $registrant = $params['customfields']['ownerdata'];
  if($registrant == '') return 'Empty registrant data';
  $json = json_decode($registrant,TRUE);

  // get the domain name from domain ID
  try
  {
    $res = $pdo->query('SELECT domain FROM tbldomains WHERE id = '.(int)$json['domainid']);
    if(!$res->rowCount()) return 'Could not find a domain with ID = '.(int)$json['domainid'];
    $domainName = $res->fetch(PDO::FETCH_NUM)[0];
  }
  catch (Exception $e)
  {
    // Record the error in WHMCS's module log
    logModuleCall(
      'namesrs_owner_domainid',
      $params,
      $e->getMessage(),
      $e->getTraceAsString()
    );
    return $e->getMessage();
  }
  //load and check if registrar module is installed
  require_once(implode(DIRECTORY_SEPARATOR, [ROOTDIR, "includes", "registrarfunctions.php"]));

  //check if the registrar module exists
  $module = "namesrs";
  $error = TRUE;
  $registrar_main = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "modules", "registrars", $module, $module . ".php"]);
  if (file_exists($registrar_main))
  {
    require_once($registrar_main);
    $configoptions = getregistrarconfigoptions($module);
    $configoptions['domainObj'] = new DomPuny($domainName); // NameSRS API requires Punycoded domains rather than Unicode
    try
    {
      $api = new RequestSRS($configoptions);
      $error = FALSE;
    }
    catch (Exception $e)
    {
      // Record the error in WHMCS's module log
      logModuleCall(
        'namesrs_owner_api',
        $params,
        $e->getMessage(),
        $e->getTraceAsString()
      );
      return $e->getMessage();
    }
  }
  if ($error)
  {
    return "The NameSRS Registrant Change Module requires NameSRS Registrar Module!";
  }

  try
	{
    $orig = json_decode($params['customfields']['ownerdata'],TRUE);
    $err = namesrs_ValidOwner($orig);
    if($err) return $err['error'];

    // Always create new contact - NameISP will remove duplicates on their side
    $data = [
      'firstname' => $orig['firstname'],
      'lastname' => $orig['lastname'],
      'organization' => $orig['companyname'],
      'orgnr' => $orig['orgnr'],
      'address1' => $orig['address1'],
      'zipcode' => $orig['postcode'],
      'city' => $orig['city'],
      'countrycode' => $orig['countrycode'],
      'phone' => $orig['fullphonenumber'], // +CC.xxxx
      'email' => $orig['email'],
    ];
    logModuleCall(
      'namesrs_owner_change',
      'Creating new registrant',
      $data,
      ''
    );
    $contact = $api->request('POST', '/contact/createcontact', $data);
    $owner_id = $contact['contactid'];
    // change the registrant
    $api->request('POST','/domain/update_domain_registrant',Array('domainname' => $api->domainName, 'ownerid' => $owner_id));
    DomainCache::clear($api->domainName);
    return 'success';
	}
  catch (Exception $e)
  {
    logModuleCall(
      'namesrs_owner',
      $params,
      $e->getMessage(),
      $e->getTraceAsString()
    );
    return $e->getMessage();
  }
}
