<?php

include __DIR__."/version.php";
use WHMCS\Database\Capsule;
// use WHMCS\Domains\Domain as DomPuny;

session_start();

function namesrs_price_config()
{
  $configarray = [
    "name" => "NameSRS Prices Importer",
    "description" => "Quickly update your WHMCS domain pricing list from NameSRS.",
    "version" => VERSION.' - '.STAMP,
    "author" => "NameSRS",
    "language" => "english",
    "fields" => [
      "username" => [
        "FriendlyName" => "Admin username",
        "Type" => "text",
        "Size" => "30",
        "Description" => "[REQUIRED]",
        "Default" => "admin",
      ],
    ],
  ];
  return $configarray;
}

function namesrs_price_output($vars)
{
  //load and check if registrar module is installed
  require_once(implode(DIRECTORY_SEPARATOR, [ROOTDIR, "includes", "registrarfunctions.php"]));

  //check if the registrar module exists
  $file = "namesrs";
  $error = TRUE;
  $registrar_main = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "modules", "registrars", $file, $file . ".php"]);
  if (file_exists($registrar_main))
  {
    require_once($registrar_main);
    $configoptions = getregistrarconfigoptions($file);
    try
    {
      $api = new RequestSRS($configoptions);
      $error = FALSE;
    }
    catch (Exception $e)
    {
      echo $e->getMessage();
      return;
    }
  }
  if ($error)
  {
    echo "The NameSRS Prices importer Module requires NameSRS Registrar Module!";
    return;
  }

  /** @var  $pdo PDO */
  $pdo = Capsule::connection()->getPdo();
  //import button clicked
  if (isset($_POST['cmdImport']))
  {
    namesrs_price_import($pdo, json_decode(html_entity_decode($_POST['pricelist']),TRUE));
  }
  elseif (isset($_POST['cmdFetch']))
  {
    //step 2
    $currencyList = array();
    // must be before calling the API - otherwise we get an error "MySQL has gone away"
    try
    {
      $result = $pdo->query('SELECT code,rate FROM tblcurrencies');
      while($row = $result->fetch(PDO::FETCH_ASSOC))
      {
        $currencyList[$row['code']] = TRUE;
      }
    }
    catch (Exception $e)
    {
      echo $e->getMessage();
      return;
    }
    if(count($currencyList) == 0)
    {
      echo 'No currencies have been defined in WHMCS';
      return;
    }

    $result = $api->request('GET','/economy/pricelist', array('print' => 1, 'skiprules' => 1));
    $pricelist = array();
    $price_currencies = array();
    if(is_array($result['pricelist']['domains'])) foreach($result['pricelist']['domains'] as $domain => $priceObj)
    {
      $pricelist[$domain] = $priceObj['Retail'];
      $item = &$priceObj['Retail']['Registration'];
      $cur = $item['currency'];
      if($currencyList[$cur]) $price_currencies[$cur] = TRUE;
      foreach($item['currencies'] as $cur => &$val)
      {
        if($currencyList[$cur]) $price_currencies[$cur] = TRUE;
      }
    }
    unset($result);
    ksort($pricelist);
    $tpl = file_get_contents(dirname(__FILE__) . '/templates/step2.html');
    $tpl = str_replace('{PRICELIST}', json_encode($pricelist,JSON_FORCE_OBJECT), $tpl);
    $tpl = str_replace('{CNT_TLD}', count($pricelist), $tpl);
    $tpl = str_replace('{TLD_CURRENCY}', json_encode($price_currencies,JSON_FORCE_OBJECT), $tpl);

    echo $tpl;
  }
  else
  {
    //step 1
    echo file_get_contents(dirname(__FILE__) . '/templates/step1.html');
  }
}

function namesrs_price_import($pdo, $pricelist)
{
  /** @var  $pdo PDO */
  try
  {
    $currencyList = array();
    $base_currency = '';
    try
    {
      $result = $pdo->query('SELECT id,code,rate FROM tblcurrencies');
      while($row = $result->fetch(PDO::FETCH_ASSOC))
      {
        $currencyList[$row['id']] = $row['code'];
        if($row['rate'] == 1) $base_currency = $row['code'];
      }
    }
    catch (Exception $e)
    {
      echo $e->getMessage();
      return;
    }

    $addons = &$pricelist['addons'];

    foreach ($pricelist['prices'] as &$value)
    {
      $tld_idn = "." . $value['tld'];
      //with TLD/extension
      $stmt = $pdo->prepare("SELECT * FROM tbldomainpricing WHERE extension=?");
      $stmt->execute([$tld_idn]);
      $tbldomainpricing = $stmt->fetch(PDO::FETCH_ASSOC);
      // domain addons + grace + redemption
      $arguments = array(
        (int)$addons['dns'],
        (int)$addons['email'],
        (int)$addons['whois'],
        (int)$addons['auth'],
        isset($value['Renew'][$base_currency]) ? $value['Renew'][$base_currency] : -1, // must be in the base WHMCS currency
        isset($value['Restore'][$base_currency]) ? $value['Restore'][$base_currency]: -1, // must be in the base WHMCS currency
        $tld_idn,
      );
      if (!empty($tbldomainpricing))
      {
        $update_stmt = $pdo->prepare("UPDATE tbldomainpricing SET dnsmanagement=?, emailforwarding=?, idprotection=?, eppcode=?, grace_period_fee=?, redemption_grace_period_fee=? WHERE extension=?");
        $update_stmt->execute($arguments);
      }
      else
      {
        $insert_stmt = $pdo->prepare("INSERT INTO tbldomainpricing ( dnsmanagement, emailforwarding, idprotection, eppcode, grace_period_fee, redemption_grace_period_fee, extension, autoreg) 
          VALUES ( ?, ?, ?, ?, ?, ?, ?, 'namesrs')");
        $insert_stmt->execute($arguments);
        if ($insert_stmt->rowCount() != 0)
        {
          $stmt = $pdo->prepare("SELECT id FROM tbldomainpricing WHERE extension=?");
          $stmt->execute([$tld_idn]);
          $tbldomainpricing = $stmt->fetch(PDO::FETCH_ASSOC);
        }
      }

      //replace or add pricing for domainregister/domainrenew/domaintransfer
      foreach($currencyList as $c_id => $c_code)
      {
        namesrs_price_save($pdo, $tbldomainpricing['id'], $c_id, 'domainregister', $value['Registration'][$c_code]);
        namesrs_price_save($pdo, $tbldomainpricing['id'], $c_id, 'domainrenew', $value['Renew'][$c_code]);
        namesrs_price_save($pdo, $tbldomainpricing['id'], $c_id, 'domaintransfer', $value['Transfer'][$c_code]);
      }
    }
    echo '<script language="JavaScript" type="text/javascript">window.parent.showSuccess(true);</script>';
  }
  catch (Exception $e)
  {
    echo '<script language="JavaScript" type="text/javascript">alert("'.addslashes($e->getMessage()).'");</script>';
  }
}

function namesrs_price_save($pdo, $tld_id, $currency_id, $price_type, $price_value)
{
  /** @var  $pdo PDO */
  $stmt = $pdo->prepare("SELECT * FROM tblpricing WHERE type=? AND currency=? AND relid=? ORDER BY id DESC LIMIT 1");
  $stmt->execute([$price_type, $currency_id, $tld_id]);
  $tblpricing = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!empty($tblpricing))
  {
    $update_stmt = $pdo->prepare("UPDATE tblpricing SET msetupfee=? WHERE id=?");
    $update_stmt->execute([isset($price_value) ? $price_value : -1, $tblpricing['id']]);
  }
  else
  {
    // msetupfee = 1 year
    // qsetupfee = 2 years
    // ssetupfee = 3 years
    // asetupfee = 4 years
    // bsetupfee = 5 years
    // monthly = 6 years
    // quarterly = 7 years
    // semiannually = 8 years
    // annually = 9 years
    // biennially = 10 years
    $insert_stmt = $pdo->prepare("INSERT INTO tblpricing (type, currency, relid, msetupfee, qsetupfee, ssetupfee, asetupfee, bsetupfee, monthly, quarterly, semiannually, annually, biennially) 
          VALUES (?, ?, ?, ?, -1, -1, -1, -1, -1, -1, -1, -1, -1)");
    $insert_stmt->execute([
      $price_type,
      $currency_id,
      $tld_id,
      isset($price_value) ? $price_value : -1,
    ]);
  }
}
