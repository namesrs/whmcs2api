<?php

require_once("Request.php");

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;

/**
 * Check Domain Availability.
 *
 * Determine if a domain or group of domains are available for
 * registration or transfer.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain availability check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function namesrs_CheckAvailability($params)
{
  $api = new RequestSRS($params);

  // availability check parameters
  $searchTerm = ($params['punyCodeSearchTerm']!='' ? $params['punyCodeSearchTerm'] : $params['searchTerm']);
  $isIdnDomain = (bool) $params['isIdnDomain'];
  $premiumEnabled = (bool) $params['premiumEnabled'];

  $params['tlds'] = array_filter($params['tlds'], 'remove_empty_tld');
  if(count($params['tlds']) == 0) return new ResultsList(); // There is no pricing defined for the current user's currency - or no TLDs defined at all
  try
  {
    $response = $api->request('POST','/domain/searchdomain', Array(
      'domainname' => array_map(
        'search_domains',
        $params['tlds'],
        array_fill(0,count($params['tlds']),$searchTerm)
      )
    ));
    $results = new ResultsList();
    if(is_array($response['parameters'])) foreach ($response['parameters'] as $key => &$domain)
    {
      // Instantiate a new domain search result object
      $searchResult = new SearchResult(preg_replace('/\.'.$domain['tld'].'$/','',$key), $domain['tld']);

      // Determine the appropriate status to return
      if ($domain['status'] == 'available') $status = SearchResult::STATUS_NOT_REGISTERED;
      elseif ($domain['status'] == 'unavailable') $status = SearchResult::STATUS_REGISTERED;
      elseif ($domain['status'] == 'reserved') $status = SearchResult::STATUS_RESERVED;
      else $status = SearchResult::STATUS_TLD_NOT_SUPPORTED;
      $searchResult->setStatus($status);

      // Return premium information if applicable
      if ($domain['isPremiumName'])
      {
        $searchResult->setPremiumDomain(true);
        $searchResult->setPremiumCostPricing(
          array(
            'register' => $domain['premiumRegistrationPrice'],
            'renew' => $domain['premiumRenewPrice'],
            'CurrencyCode' => 'USD',
          )
        );
      }

      $results->append($searchResult);
    }
    return $results;
  }
  catch (Exception $e)
  {
    logModuleCall(
      'nameSRS',
      'Domain Search',
      $e->getMessage(),
      $e->getTrace()
    );
  }
}

function search_domains($tld,$sld)
{
  return $sld.$tld;
}

function remove_empty_tld($tld)
{
  return trim($tld) != '';
}

/**
 * Get Domain Suggestions.
 *
 * Provide domain suggestions based on the domain lookup term provided.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain suggestions check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function namesrs_GetDomainSuggestions($params)
{
  // nameISP does not provide suggestions
  $results = new ResultsList();
  return $results;
}
