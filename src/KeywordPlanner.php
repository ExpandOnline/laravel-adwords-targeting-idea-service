<?php


namespace SchulzeFelix\AdWords;


use Google\Ads\GoogleAds\Lib\V12\GoogleAdsClient;
use Google\Ads\GoogleAds\Util\V12\ResourceNames;
use Google\Ads\GoogleAds\V12\Common\HistoricalMetricsOptions;
use Google\Ads\GoogleAds\V12\Enums\KeywordMatchTypeEnum\KeywordMatchType;
use Google\Ads\GoogleAds\V12\Enums\KeywordPlanForecastIntervalEnum\KeywordPlanForecastInterval;
use Google\Ads\GoogleAds\V12\Enums\KeywordPlanNetworkEnum\KeywordPlanNetwork;
use Google\Ads\GoogleAds\V12\Resources\KeywordPlan;
use Google\Ads\GoogleAds\V12\Resources\KeywordPlanAdGroup;
use Google\Ads\GoogleAds\V12\Resources\KeywordPlanAdGroupKeyword;
use Google\Ads\GoogleAds\V12\Resources\KeywordPlanCampaign;
use Google\Ads\GoogleAds\V12\Resources\KeywordPlanForecastPeriod;
use Google\Ads\GoogleAds\V12\Resources\KeywordPlanGeoTarget;
use Google\Ads\GoogleAds\V12\Services\KeywordPlanAdGroupKeywordOperation;
use Google\Ads\GoogleAds\V12\Services\KeywordPlanAdGroupOperation;
use Google\Ads\GoogleAds\V12\Services\KeywordPlanCampaignOperation;
use Google\Ads\GoogleAds\V12\Services\KeywordPlanKeywordHistoricalMetrics;
use Google\Ads\GoogleAds\V12\Services\KeywordPlanOperation;
use Illuminate\Support\Collection;
use SchulzeFelix\AdWords\Responses\Keyword;
use SchulzeFelix\AdWords\Responses\MonthlySearchVolume;

class KeywordPlanner
{

    const COMPETITION_VALUES = [
        2 => 'Low',
        3 => 'Medium',
        4 => 'High'
    ];

    public function getSearchVolume(GoogleAdsClient  $adsClient, $customerId, $keywords, $language, $location, $excluded) {

        $keywordPlan = $this->createKeywordPlan($adsClient, $customerId);
        $campaignPlan = $this->createKeywordPlanCampaign($adsClient, $customerId, $keywordPlan, $location, $language);
        $adGroupPlan = $this->createKeywordPlanAdGroup($adsClient, $customerId, $campaignPlan);
        $this->createKeywordPlanKeywords($adsClient, $customerId, $adGroupPlan, $keywords);;

        if (!empty($excluded))
            $this->createNegativeKeywordPlan($adsClient, $customerId, $campaignPlan, $excluded);

        $a = new HistoricalMetricsOptions();
        $a->setIncludeAverageCpc(true);
        $response = $adsClient->getKeywordPlanServiceClient()->generateHistoricalMetrics($keywordPlan, [$a]);
        $keywordIdeas = new Collection();
        foreach($response->getMetrics() as $item) {
            $keywordIdeas->push($this->extractKeyword($item, true));
        }

        $delete = new KeywordPlanOperation();
        $delete->setRemove($keywordPlan);
        $adsClient->getKeywordPlanServiceClient()->mutateKeywordPlans($customerId, [$delete]);

        return $keywordIdeas;
    }

    private function createKeywordPlan(GoogleAdsClient $adsClient, $customerId)
    {
        $keywordPlan = new KeywordPlan([
            'name' => 'Keyword plan for traffic estimate #' . microtime(),
            'forecast_period' => new KeywordPlanForecastPeriod([
                'date_interval' => KeywordPlanForecastInterval::NEXT_QUARTER
            ])
        ]);

        $keywordPlanOperation = new KeywordPlanOperation();
        $keywordPlanOperation->setCreate($keywordPlan);

        $keywordPlanServiceClient = $adsClient->getKeywordPlanServiceClient();
        $response = $keywordPlanServiceClient->mutateKeywordPlans(
            $customerId,
            [$keywordPlanOperation]
        );

        return $response->getResults()[0]->getResourceName();
    }

    private function createKeywordPlanCampaign(GoogleAdsClient $adsClient, $customerId, $keywordPlan, $location, $language)
    {

        $keywordPlanCampaign = new KeywordPlanCampaign([
            'name' => 'Keyword plan campaign #' . microtime(),
            'cpc_bid_micros' => 1000000,
            'keyword_plan_network' => KeywordPlanNetwork::GOOGLE_SEARCH,
            'keyword_plan' => $keywordPlan,
        ]);
        if ($location) {
            $keywordPlanCampaign->setGeoTargets([
                new KeywordPlanGeoTarget([
                    'geo_target_constant' => ResourceNames::forGeoTargetConstant($location) // USA
                ])
            ]);
        }
        if ($language) {
            $keywordPlanCampaign->setLanguageConstants([ResourceNames::forLanguageConstant($language)]);
        }

        $keywordPlanCampaignOperation = new KeywordPlanCampaignOperation();
        $keywordPlanCampaignOperation->setCreate($keywordPlanCampaign);

        $keywordPlanCampaignServiceClient =
            $adsClient->getKeywordPlanCampaignServiceClient();
        $response = $keywordPlanCampaignServiceClient->mutateKeywordPlanCampaigns(
            $customerId,
            [$keywordPlanCampaignOperation]
        );

        return $response->getResults()[0]->getResourceName();
    }

    private function createKeywordPlanAdGroup(GoogleAdsClient $adsClient, $customerId, $campaignPlan)
    {
        // Creates a keyword plan ad group.
        $keywordPlanAdGroup = new KeywordPlanAdGroup([
            'name' => 'Keyword plan ad group #' . microtime(),
            'cpc_bid_micros' => 2500000,
            'keyword_plan_campaign' => $campaignPlan
        ]);

        // Creates a keyword plan ad group operation.
        $keywordPlanAdGroupOperation = new KeywordPlanAdGroupOperation();
        $keywordPlanAdGroupOperation->setCreate($keywordPlanAdGroup);

        $keywordPlanAdGroupServiceClient = $adsClient->getKeywordPlanAdGroupServiceClient();
        $response = $keywordPlanAdGroupServiceClient->mutateKeywordPlanAdGroups(
            $customerId,
            [$keywordPlanAdGroupOperation]
        );

        $planAdGroupResource = $response->getResults()[0]->getResourceName();

        return $planAdGroupResource;
    }

    private function createKeywordPlanKeywords(GoogleAdsClient $adsClient, $customerId, $adGroupPlan, $keywords)
    {

        $planKeywords = [];
        foreach ($keywords as $keyword) {
            $planKeywords[] = new KeywordPlanAdGroupKeyword([
                'text' => $keyword,
                'cpc_bid_micros' => 2000000,
                'match_type' => KeywordMatchType::BROAD,
                'keyword_plan_ad_group' => $adGroupPlan
            ]);
        }


        // Creates an array of keyword plan ad group keyword operations.
        $keywordPlanAdGroupKeywordOperations = [];

        foreach ($planKeywords as $keyword) {
            $keywordPlanAdGroupKeywordOperation = new KeywordPlanAdGroupKeywordOperation();
            $keywordPlanAdGroupKeywordOperation->setCreate($keyword);
            $keywordPlanAdGroupKeywordOperations[] = $keywordPlanAdGroupKeywordOperation;
        }

        $keywordPlanAdGroupKeywordServiceClient = $adsClient->getKeywordPlanAdGroupKeywordServiceClient();

        // Adds the keyword plan ad group keywords.
        $keywordPlanAdGroupKeywordServiceClient->mutateKeywordPlanAdGroupKeywords(
            $customerId,
            $keywordPlanAdGroupKeywordOperations
        );
    }

    private function createNegativeKeywordPlan(GoogleAdsClient $adsClient, $customerId, $adGroupPlan, $keywords) {
        $planKeywords = [];
        foreach ($keywords as $keyword) {
            $planKeywords[] = new KeywordPlanAdGroupKeyword([
                'text' => $keyword,
                'negative' => true,
                'match_type' => KeywordMatchType::BROAD,
                'keyword_plan_ad_group' => $adGroupPlan
            ]);
        }

        $keywordPlanAdGroupKeywordOperations = [];
        foreach ($planKeywords as $keyword) {
            $keywordPlanAdGroupKeywordOperation = new KeywordPlanAdGroupKeywordOperation();
            $keywordPlanAdGroupKeywordOperation->setCreate($keyword);
            $keywordPlanAdGroupKeywordOperations[] = $keywordPlanAdGroupKeywordOperation;
        }

        $keywordPlanCampaignKeywordServiceClient = $adsClient->getKeywordPlanCampaignKeywordServiceClient();

        // Adds the negative campaign keyword.
        $keywordPlanCampaignKeywordServiceClient->mutateKeywordPlanCampaignKeywords(
            $customerId,
            $keywordPlanAdGroupKeywordOperations
        );
    }


    /**
     * @return Keyword
     * @var KeywordPlanKeywordHistoricalMetrics $item
     */
    private function extractKeyword($item, $withTargetedMonthlySearches = false)
    {
        $keywordData = [
            'keyword' => $item->getSearchQuery(),
            'search_volume' => 0,
            'cpc' => 0,
            'competition' => 0,
            'targeted_monthly_searches' => []
        ];

        $hasMetrics = !is_null($item->getKeywordMetrics());

        if ($hasMetrics) {
            $keywordData['search_volume'] =  $item->getKeywordMetrics()->getAvgMonthlySearches();
            $keywordData['cpc'] = $item->getKeywordMetrics()->getAverageCpcMicros();

            $competition = $item->getKeywordMetrics()->getCompetition();
            if (array_key_exists($competition, KeywordPlanner::COMPETITION_VALUES)) {
                $competition = KeywordPlanner::COMPETITION_VALUES[$competition];
            }
            $keywordData['competition'] = $competition;
        }

        $result = new Keyword($keywordData);

        if ($hasMetrics) {
            $targeted_monthly_searches = $item->getKeywordMetrics()->getMonthlySearchVolumes();

            $result->targeted_monthly_searches = [];
            /**
             * @var \Google\Ads\GoogleAds\V12\Common\MonthlySearchVolume $monthly_search
             */
            foreach ($targeted_monthly_searches as $monthly_search) {
                $result->targeted_monthly_searches[] = new MonthlySearchVolume([
                    'year'  => $monthly_search->getYear(),
                    'month' => $monthly_search->getMonth(),
                    'count' => $monthly_search->getMonthlySearches(),
                ]);
            }
        }

        return $result;
    }
}
