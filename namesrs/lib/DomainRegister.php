<?php

use WHMCS\Database\Capsule as Capsule; 

function namesrs_RegisterDomain($params)
{
  /**
   * @var PDO
   */
  $pdo = Capsule::connection()->getPdo(); 

  try
  {
    // Different TLDs use different names for the field which holds VAT number
    // we can not cope with that so we check for the field "Tax ID" which should be defined for all relevant domains in "/whmcs/resources/domains/additionalfields.php"
    if(trim($params['additionalfields']['Tax ID']) == '')
    {
      // this domain does not require OrgNumber - so try to get it from the client profile
      $stm = $pdo->prepare('SELECT cfv.value FROM `tblcustomfields` AS cf 
                        JOIN `tblcustomfieldsvalues` AS cfv  ON (cfv.fieldid = cf.id AND cfv.relid = :userid) 
                        WHERE cf.fieldname LIKE :name AND cf.type = "client"');
      $stm->execute(Array('userid' => $params['userid'], 'name' => 'orgnr|%'));
      $params['original']['orgnr'] = $stm->fetch(PDO::FETCH_NUM)[0];
    }
    else $params['original']['orgnr'] = $params['additionalfields']['Tax ID'];
    if($params['original']['orgnr'] == '') $params['original']['orgnr'] = '000000-0000';

    if(trim($params['original']['firstname']) == '') return array('error' => 'First name is required. Please update contact details in profile.');
    if(trim($params['original']['lastname']) == '') return array('error' => 'Last name is required. Please update contact details in profile.');
    if(trim($params['original']['address1']) == '') return array('error' => 'Address is required. Please update contact details in profile.');
    if(trim($params['original']['postcode']) == '') return array('error' => 'ZIP code is required. Please update contact details in profile.');
    if(trim($params['original']['city']) == '') return array('error' => 'City is required. Please update contact details in profile.');
    if(trim($params['original']['countrycode']) == '') return array('error' => 'Country is required. Please update contact details in profile.');
    if(trim($params['original']['phonenumberformatted']) == '') return array('error' => 'Phone number is required. Please update contact details in profile.');
    if(trim($params['original']['email']) == '') return array('error' => 'Contact e-mail is required. Please update contact details in profile.');

    $api = new RequestSRS($params);
    // Always create new contact - NameISP will remove duplicates on their side
    $orig = is_array($params['original']) ? $params['original'] : $params;
    $data = Array(
      'firstname' => $orig['firstname'],
      'lastname' => $orig['lastname'],
      'organization' => $orig['companyname'],
      'orgnr' => $orig['orgnr'],
      'address1' => $orig['address1'],
      'zipcode' => $orig['postcode'],
      'city' => $orig['city'],
      'countrycode' => $orig['countrycode'],
      'phone' => $orig['fullphonenumber'], // +CC.xxxx
      'email' => $orig['email']
    );
    $contact = $api->request('POST','/contact/createcontact', $data);
    $owner_id = $contact['contactid'];
    // register the domain asynchronously
    $nserver = Array();
    for($i = 1; $i <= 5; $i++)
      if($params['ns'.$i]!='')
      {
        $ns = explode('.',trim(trim($params['ns'.$i]),'.'));
        if(count($ns) > 2) $nserver[] = implode('.',$ns);
      }

    $result = $api->request('POST','/domain/create_domain_registration', Array(
      'domainname' => $api->domainName,
      'itemyear' => $params['regperiod'],
      'ownerid' => $owner_id,
      'shieldwhois' => (int)$params['idprotection'],
      'nameserver' => $nserver,
      'tmchacceptance' => 1
    ));
    $handle = $result['parameters']['requestID'][0];
    $api->queueRequest(4 /* register */, $params['domainid'], $handle, json_encode(Array('ns' => $nserver)));
    return array('success' => true);
  }
  catch (Exception $e)
  {
    return array(
      'error' => $e->getMessage(),
    );
  }
}

