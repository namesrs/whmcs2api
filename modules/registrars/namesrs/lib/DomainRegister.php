<?php

use WHMCS\Database\Capsule as Capsule;
require_once dirname(__FILE__).'/OwnerValidations.php';

function namesrs_RegisterDomain($params)
{
  /**
   * @var $pdo PDO
   */
  $pdo = Capsule::connection()->getPdo();

  try
  {
    $stm = $pdo->prepare('SELECT cfv.value FROM `tblcustomfields` AS cf 
                        JOIN `tblcustomfieldsvalues` AS cfv  ON (cfv.fieldid = cf.id AND cfv.relid = :userid) 
                        WHERE cf.fieldname LIKE :name AND cf.type = "client"');
    $stm->execute(['userid' => $params['userid'], 'name' => 'orgnr|%']);
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
    /*
    if ($orig['orgnr'] == '')
    {
      $orig['orgnr'] = trim($params['additionalfields']['Tax ID']);
      logModuleCall(
        'nameSRS',
        'Using Tax ID as Personal ID',
        array(
          'personal ID' => $orig['orgnr'],
        ),
        array(
          'userid' => $params['userid']
        )
      );
    }
*/
    $err = namesrs_ValidOwner($orig);
    if($err) return $err;

    $api = new RequestSRS($params);
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
    ]);
    $handle = $result['parameters']['requestID'][0];
    $api->queueRequest(4 /* register */, $params['domainid'], $handle, json_encode(['ns' => $nserver]));
    return ['success' => TRUE];
  }
  catch (Exception $e)
  {
    return [
      'error' => $e->getMessage(),
    ];
  }
}

