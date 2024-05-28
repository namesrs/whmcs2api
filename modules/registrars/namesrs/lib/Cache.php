<?php

use WHMCS\Database\Capsule as Capsule;

/**
 * Class SessionCache
 *
 * Maintains the current session token per API key
 */
Class SessionCache
{
  public static function get($account)
  {
    /**
     * @var $pdo PDO
     */
    $pdo = Capsule::connection()->getPdo();
    try
    {
      $stm = $pdo->prepare("select sessionId from mod_namesrssession where account = :acc");
      $stm->execute(array('acc' => $account));
      return $stm->rowCount() ? $stm->fetch(PDO::FETCH_NUM)[0] : '';
    }
    catch (PDOException $e)
    {
      logModuleCall(
        'nameSRS',
        'SessionCache__GET',
        $account,
        $e->getMessage()
      );
      return '';
    }
  }

  public static function put($sessionId,$account)
  {
    /**
     * @var $pdo PDO
     */
    $pdo = Capsule::connection()->getPdo();
    try
    {
      $stm = $pdo->prepare('INSERT INTO mod_namesrssession (sessionId, account) VALUES(:sess,:acc) ON DUPLICATE KEY UPDATE sessionId = VALUES(sessionId)');
      $stm->execute(array('acc' => $account, 'sess' => $sessionId));
    }
    catch (PDOException $e)
    {
      logModuleCall(
        'nameSRS',
        'SessionCache__PUT',
        Array('account' => $account, 'session' => $sessionId),
        $e->getMessage()
      );
    }
  }

  public static function clear($account)
  {
    self::put("",$account);
  }
}

/**
 * Class DomainCache
 *
 * Caches the domain ID from the API
 */
Class DomainCache
{
  public static function get($domainName)
  {
    if(empty($_SESSION['namesrsDomainCache'])) $_SESSION['namesrsDomainCache'] = array();
    return $_SESSION['namesrsDomainCache'][$domainName];
  }

  public static function put($domain)
  {
    if(empty($_SESSION['namesrsDomainCache']) OR count($_SESSION['namesrsDomainCache']) > 1000) $_SESSION['namesrsDomainCache'] = array();
    $_SESSION['namesrsDomainCache'][$domain['domainname']] = $domain;
  }

  public static function clear($domainName)
  {
    unset($_SESSION['namesrsDomainCache'][$domainName]);
  }
}
