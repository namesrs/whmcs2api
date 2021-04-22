<?php

function namesrs_luhnCheck($str)
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

function namesrs_getEGNparts($egn)
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

function namesrs_validateSwedishEGN($egn)
{
  $parts = array_pad(namesrs_getEGNparts($egn), 7, '');
  if (in_array('', $parts, TRUE)) return FALSE;

  list($century, $year, $month, $day, $sep, $num, $check) = array_values($parts);

  $validDate = checkdate($month, $day, strval($century) . strval($year));
  $validCoOrdinationNumber = checkdate($month, intval($day) - 60, strval($century) . strval($year));
  if (!$validDate && !$validCoOrdinationNumber) return FALSE;

  return namesrs_luhnCheck($year . $month . $day . $num) === intval($check);
}

function namesrs_ValidOwner($data)
{
  if (trim($data['firstname']) == '') return ['error' => 'NameSRS: First name is required. Please update contact details in profile.'];
  if (trim($data['lastname']) == '') return ['error' => 'NameSRS: Last name is required. Please update contact details in profile.'];
  if (trim($data['address1']) == '') return ['error' => 'NameSRS: Address is required. Please update contact details in profile.'];
  if (trim($data['postcode']) == '') return ['error' => 'NameSRS: ZIP code is required. Please update contact details in profile.'];
  if (trim($data['city']) == '') return ['error' => 'NameSRS: City is required. Please update contact details in profile.'];
  if (trim($data['countrycode']) == '') return ['error' => 'NameSRS: Country is required. Please update contact details in profile.'];
  if (trim(preg_replace('/^[^\.]\./','',$data['fullphonenumber'])) == '') return ['error' => 'NameSRS: Phone number is required. Please update contact details in profile.'];
  if (trim($data['email']) == '') return ['error' => 'NameSRS: Contact e-mail is required. Please update contact details in profile.'];

  if ($data['countrycode'] == 'SE' AND in_array($data['tld'], ['se', 'nu']))
  {
    if ($data['orgnr'] == '') return ['error' => 'NameSRS: Personal/Organization ID number is required field. Please update your profile.'];
    $data['orgnr'] = preg_replace('/^19|^20/', '', preg_replace('/[^0-9]/', '', $data['orgnr']));
    if (!namesrs_validateSwedishEGN($data['orgnr']))
    {
      if (is_numeric($data['orgnr']))
      {
        if ($data['orgnr'] < 10000) return ['error' => 'NameSRS: Invalid Organization ID (' . $data['orgnr'] . ')'];
        if (trim($data['companyname']) == '')
        {
          $data['companyname'] = 'Företag'; // Comply with GDPR - otherwise the person's name is visible on WHOIS
        }
      }
      else
        return ['error' => 'NameSRS: Invalid or missing Personal ID (' . $data['orgnr'] . ')'];
    }

    $data['postcode'] = preg_replace('/[^0-9]/', '', $data['postcode']);
    if (!preg_match('/^\d{5}$/', $data['postcode'])) return ['error' => 'NameSRS: Invalid ZIP code'];
    $data['fullphonenumber'] = preg_replace('/\.0+/', '.', preg_replace('/[^0-9\.\+]/', '', $data['fullphonenumber']));
    if (!preg_match('/^\+\d\d+\.\d{5,}$/', $data['fullphonenumber'])) return ['error' => 'NameSRS: Invalid phone number'];
  }

  return FALSE;
}
