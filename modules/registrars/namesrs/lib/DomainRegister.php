<?php

use WHMCS\Database\Capsule as Capsule;
require_once dirname(__FILE__).'/OwnerValidations.php';

function namesrs_RegisterDomain($params)
{
  if(trim($params['orgnr_field']) == '') return [
    'error' => 'NameSRS: registrar module config is missing the name of the custom field that stores Company/Person ID'
  ];
  /**
   * @var $pdo PDO
   */
  $pdo = Capsule::connection()->getPdo();

  try
  {
    $stm = $pdo->prepare('SELECT cfv.value FROM `tblcustomfields` AS cf 
      JOIN `tblcustomfieldsvalues` AS cfv  ON (cfv.fieldid = cf.id AND cfv.relid = :userid) 
      WHERE cf.fieldname '.(strpos($params['orgnr_field'], '^') === 0 ? 'R' /* The pattern is RegExp */: '').'LIKE :name AND cf.type = "client" AND COALESCE(cfv.value,"") <> ""');
    $stm->execute(['userid' => $params['userid'], 'name' => $params['orgnr_field']]);
    if ($stm->rowCount())
    {
      $orgnr = $stm->fetch(PDO::FETCH_NUM)[0];
      logModuleCall(
        'nameSRS',
        'Getting Personal ID from client',
        [
          'personal ID' => $orgnr,
          'userid' => $params['userid'],
        ],
        ''
      );
    }
    else
    {
      $orgnr = '';
      logModuleCall(
        'nameSRS',
        'Could not get Personal ID from client',
        [
          'personal ID' => 0,
          'userid' => $params['userid'],
        ],
        ''
      );
    }

    $orig = is_array($params['original']) ? $params['original'] : $params;
    // Different TLDs use different names for the field which holds VAT number
    // we can not cope with that so we check for the field "Tax ID" which should be defined for all relevant domains in "/whmcs/resources/domains/additionalfields.php"
    $orig['orgnr'] = $orgnr;
    if(is_array($orig['additionalFields']) AND preg_match("/\.no$/i", $orig['domain']))
      {
        $regType = '';
        $companyID = '';
        $personID = '';
        foreach ($orig['additionalFields'] as $field)
        {
          switch($field['id'])
          {
            case 1000:
              $regType = $field['value'];
              break;
            case 1001:
              $companyID = $field['value'];
              break;
            case 1002:
              $personID = $field['value'];
          }
        }
        if($regType === 'ORG') $orig['no-npri'] = $companyID;
        elseif($regType === 'IND') $orig['no-npri'] = $personID;
      }
    $err = namesrs_ValidOwner($orig);
    if($err) return $err;

    $api = new RequestSRS($params);
    $result = namesrs_sale_cost($api, $params,'Registration');
    if(is_array($result)) return $result;

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
      'nameSRS',
      'Creating account',
      $data,
      ''
    );
    $contact = $api->request('POST', '/contact/createcontact', $data);
    $owner_id = $contact['contactid'];
    // register the domain asynchronously
    $nserver = [];
    for ($i = 1; $i <= 5; $i++)
      if ($params['ns' . $i] != '')
      {
        $ns = explode('.', trim(trim($params['ns' . $i]), '.'));
        if (count($ns) > 2) $nserver[] = implode('.', $ns);
      }

    $result = $api->request('POST', '/domain/create_domain_registration', [
      'domainname' => $api->domainName,
      'itemyear' => $params['regperiod'],
      'ownerid' => $owner_id,
      'shieldwhois' => (int)$params['idprotection'],
      'nameserver' => $nserver,
      'custom_field' => $params['domainid'],
      'tmchacceptance' => 1,
      'DNSid' => $params['DNS_id'],
      'price' => $price,
      'currency' => $currency,
    ]);
    $handle = $result['parameters']['requestID'][0];
    $api->queueRequest(4 /* register */, $params['domainid'], $handle, json_encode(['ns' => $nserver]));
    if($params['default_pending']) domainStatus($params['domainid'], 'Pending Registration');
    return ['success' => TRUE];
  }
  catch (Exception $e)
  {
    return [
      'error' => 'NameSRS: '.$e->getMessage(),
    ];
  }
}

