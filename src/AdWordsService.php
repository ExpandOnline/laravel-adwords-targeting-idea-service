<?php

namespace SchulzeFelix\AdWords;

use Google\Ads\GoogleAds\Lib\V12\GoogleAdsClient;
use Google\Ads\GoogleAds\Util\V12\ResourceNames;
use Google\Ads\GoogleAds\V12\Enums\KeywordPlanNetworkEnum\KeywordPlanNetwork;
use Google\Ads\GoogleAds\V12\Services\KeywordSeed;
use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Google\ApiCore\PagedListResponse;

class AdWordsService
{
    const MAX_RETRIES = 10;

    /** @var GoogleAdsClient */
    protected $adsClient;

    protected $customerId;

    public function __construct(GoogleAdsClient $adsClient, $customerId)
    {
        $this->adsClient = $adsClient;
        $this->customerId = $customerId;
    }

    /**
     * Query the Google AdWords TargetingIdeaService with given parameters.
     *
     * @param array $keywords
     * @param $requestType
     * @param $language
     * @param $location
     * @param bool $withTargetedMonthlySearches
     * @param $included
     * @param $excluded
     *
     * @throws ApiException
     *
     * @return PagedListResponse
     */
    public function performQuery(array $keywords, $requestType = null, $language = null, $location = null, $withTargetedMonthlySearches = false, $included = [], $excluded = [])
    {
        $query = [
            'keywordSeed' => new KeywordSeed((['keywords' => $keywords])),
            'keywordPlanNetwork' => KeywordPlanNetwork::GOOGLE_SEARCH_AND_PARTNERS,
            'customerId' => $this->customerId
        ];

        if ($language !== null) {
            $query['language'] = ResourceNames::forLanguageConstant($language);
        }

        if ($location !== null) {
            $query['geoTargetConstants'] = [ResourceNames::forGeoTargetConstant($location)];
        }

        #return (new ExponentialBackoff(10))->execute(function () use ($query) {
        return $this->adsClient->getKeywordPlanIdeaServiceClient()->generateKeywordIdeas($query);
        #});
    }

    public function performSearchVolumeQuery(array $keywords, $requestType = null, $language = null, $location = null, $withTargetedMonthlySearches = false, $included = [], $excluded = []) {
        $planner = new KeywordPlanner();
        return $planner->getSearchVolume($this->adsClient, $this->customerId, array_merge($keywords, $included), $language, $location, $excluded);

    }

    /**
     * @return GoogleAdsClient
     */
    public function getTargetingIdeaService()
    {
        return $this->adsClient;
    }
}
