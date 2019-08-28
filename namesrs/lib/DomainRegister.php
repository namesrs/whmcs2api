<?php

use WHMCS\Database\Capsule as Capsule;

function luhnCheck($str)
{
  $sum = 0;

  for ($i = 0; $i < strlen($str); $i++)
  {
    $v = intval($str[$i]);
    $v *= 2 - ($i % 2);

    if ($v > 9)
    {
      $v -= 9;
    }

    $sum += $v;
  }

  return intval(ceil($sum / 10) * 10 - $sum);
}

function getEGNparts($egn)
{
  $reg = '/^(\d{2}){0,1}(\d{2})(\d{2})(\d{2})([\+\-\s]?)(\d{3})(\d)$/';
  preg_match($reg, $egn, $match);

  if (!isset($match) || count($match) !== 8)
  {
    return [];
  }

  $century = $match[1];
  $year = $match[2];
  $month = $match[3];
  $day = $match[4];
  $sep = $match[5];
  $num = $match[6];
  $check = $match[7];

  if (!in_array($sep, ['-', '+']))
  {
    if (empty($century) || date('Y') - intval(strval($century) . strval($year)) < 100)
    {
      $sep = '-';
    }
    else
    {
      $sep = '+';
    }
  }

  if (empty($century))
  {
    if ($sep === '+')
    {
      $baseYear = date('Y', strtotime('-100 years'));
    }
    else
    {
      $baseYear = date('Y');
    }
    $century = substr(($baseYear - (($baseYear - $year) % 100)), 0, 2);
  }

  return [
    'century' => $century,
    'year' => $year,
    'month' => $month,
    'day' => $day,
    'sep' => $sep,
    'num' => $num,
    'check' => $check,
  ];
}

function validateSwedishEGN($egn)
{
  $parts = array_pad(getEGNparts($egn), 7, '');
  if (in_array('', $parts, TRUE)) return FALSE;

  list($century, $year, $month, $day, $sep, $num, $check) = array_values($parts);

  $validDate = checkdate($month, $day, strval($century) . strval($year));
  $validCoOrdinationNumber = checkdate($month, intval($day) - 60, strval($century) . strval($year));
  if (!$validDate && !$validCoOrdinationNumber) return FALSE;

  return luhnCheck($year . $month . $day . $num) === intval($check);
}

function namesrs_RegisterDomain($params)
{
  /**
   * @var PDO
   */
  $pdo = Capsule::connection()->getPdo();

  try
  {
    logModuleCall(
      'nameSRS',
      'Trying to register domain',
      $params,
      ''
    );
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
        ],
        [
          'userid' => $params['userid'],
        ]
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
        ],
        [
          'userid' => $params['userid'],
        ]
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
    if (trim($orig['firstname']) == '') return ['error' => 'First name is required. Please update contact details in profile.'];
    if (trim($orig['lastname']) == '') return ['error' => 'Last name is required. Please update contact details in profile.'];
    if (trim($orig['address1']) == '') return ['error' => 'Address is required. Please update contact details in profile.'];
    if (trim($orig['postcode']) == '') return ['error' => 'ZIP code is required. Please update contact details in profile.'];
    if (trim($orig['city']) == '') return ['error' => 'City is required. Please update contact details in profile.'];
    if (trim($orig['countrycode']) == '') return ['error' => 'Country is required. Please update contact details in profile.'];
    if (trim($orig['phonenumberformatted']) == '') return ['error' => 'Phone number is required. Please update contact details in profile.'];
    if (trim($orig['email']) == '') return ['error' => 'Contact e-mail is required. Please update contact details in profile.'];

    if ($orig['countrycode'] == 'SE' AND in_array($orig['tld'], ['se', 'nu']))
    {
      if ($orig['orgnr'] == '') return ['error' => 'Personal/Organization ID number is required field. Please update your profile.'];
      $orig['orgnr'] = preg_replace('/^19|^20/', '', preg_replace('/[^0-9]/', '', $orig['orgnr']));
      if (!validateSwedishEGN($orig['orgnr']))
      {
        if (is_numeric($orig['orgnr']))
        {
          if ($orig['orgnr'] < 10000) return ['error' => 'Invalid Organization ID (' . $orig['orgnr'] . ')'];
          if (trim($orig['companyname']) == '')
          {
            $orig['companyname'] = trim($orig['firstname'] . ' ' . $orig['lastname']);
            if ($orig['companyname'] == '') return ['error' => 'Company name is required. Please update contact details in profile.'];
          }
        }
        else
          return ['error' => 'Invalid or missing Personal ID (' . $orig['orgnr'] . ')'];
      }

      $orig['postcode'] = preg_replace('/[^0-9]/', '', $orig['postcode']);
      if (!preg_match('/^\d{5}$/', $orig['postcode'])) return ['error' => 'Invalid ZIP code'];
      $orig['fullphonenumber'] = preg_replace('/\.0+/', '.', preg_replace('/[^0-9\.\+]/', '', $orig['fullphonenumber']));
      if (!preg_match('/^\+\d\d+\.\d{5,}$/', $orig['fullphonenumber'])) return ['error' => 'Invalid phone number'];
    }
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

