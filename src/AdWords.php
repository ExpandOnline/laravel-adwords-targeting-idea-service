<?php

namespace SchulzeFelix\AdWords;

use Google\Ads\GoogleAds\V12\Services\GenerateKeywordIdeaResult;
use Google\Ads\GoogleAds\V12\Services\KeywordPlanIdeaServiceClient;
use Illuminate\Support\Collection;
use SchulzeFelix\AdWords\Responses\Keyword;
use SchulzeFelix\AdWords\Responses\MonthlySearchVolume;

class AdWords
{
    const CHUNK_SIZE = 700;

    /**
     * @var AdWordsService
     */
    private $service;

    /** @var bool */
    protected $withTargetedMonthlySearches = false;

    /** @var bool */
    protected $convertNullToZero = false;

    /** @var int|null */
    protected $language = null;

    /** @var int|null */
    protected $location = null;

    /** @var array|null */
    protected $include = null;

    /** @var array|null */
    protected $exclude = null;

    /**
     * AdWords constructor.
     *
     * @param AdWordsService $service
     */
    public function __construct(AdWordsService $service)
    {
        $this->service = $service;
    }

    /**
     * @param array $keywords
     *
     * @return Collection
     */
    public function searchVolumes(array $keywords)
    {
        $keywords = $this->prepareKeywords($keywords);

        $chunks = array_chunk($keywords, self::CHUNK_SIZE);

        foreach ($chunks as $index => $keywordChunk) {
            $searchVolumes = $this->service->performSearchVolumeQuery($keywordChunk, null, $this->language, $this->location, $this->withTargetedMonthlySearches);
        }

        $missingKeywords = array_diff($keywords, $searchVolumes->pluck('keyword')->toArray());

        foreach ($missingKeywords as $missingKeyword) {
            $missingKeywordInstance = new Keyword([
                'keyword'       => $missingKeyword,
                'search_volume' => $this->convertNullToZero ? 0 : null,
                'cpc'           => $this->convertNullToZero ? 0 : null,
                'competition'   => $this->convertNullToZero ? 0 : null,
            ]);

            if ($this->withTargetedMonthlySearches) {
                $missingKeywordInstance->targeted_monthly_searches = $this->convertNullToZero ? collect() : null;
            }

            $searchVolumes->push($missingKeywordInstance);
        }

        return $searchVolumes;
    }

    public function keywordIdeas($keyword)
    {
        $keyword = $this->prepareKeywords([$keyword]);
        $requestType = RequestType::IDEAS;

        $keywordIdeas = new Collection();

        $results = $this->service->performQuery($keyword, $requestType, $this->language, $this->location, $this->withTargetedMonthlySearches, $this->include, $this->exclude);
        foreach ($results->iterateAllElements() as $result) {
            $keyword = $this->extractKeyword($result);
            $keywordIdeas->push($keyword);
        }

        return $keywordIdeas;
    }

    /**
     * Include Targeted Monthly Searches.
     *
     * @return $this
     */
    public function withTargetedMonthlySearches()
    {
        $this->withTargetedMonthlySearches = true;

        return $this;
    }

    /**
     * Convert Null Values To Zero.
     *
     * @return $this
     */
    public function convertNullToZero()
    {
        $this->convertNullToZero = true;

        return $this;
    }

    /**
     * Add Language Search Parameter.
     *
     * @return $this
     */
    public function language($language = null)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Add Location Search Parameter.
     *
     * @return $this
     */
    public function location($location = null)
    {
        $this->location = $location;

        return $this;
    }

    public function include(array $words)
    {
        $this->include = $this->prepareKeywords($words);

        return $this;
    }

    public function exclude(array $words)
    {
        $this->exclude = $this->prepareKeywords($words);

        return $this;
    }

    /**
     * @return KeywordPlanIdeaServiceClient
     */
    public function getTargetingIdeaService(): KeywordPlanIdeaServiceClient
    {
        return $this->service->getTargetingIdeaService();
    }

    /**
     * Private Functions.
     */
    private function prepareKeywords(array $keywords)
    {
        $keywords = array_map('trim', $keywords);
        $keywords = array_map('mb_strtolower', $keywords);
        $keywords = array_filter($keywords);
        $keywords = array_unique($keywords);
        $keywords = array_values($keywords);

        return $keywords;
    }

    /**
     * @param GenerateKeywordIdeaResult $targetingIdea
     *
     * @return Keyword
     */
    private function extractKeyword($targetingIdea)
    {
        $keywordData = [
            'keyword' => $targetingIdea->getText(),
            'search_volume' => 0,
            'cpc' => 0,
            'competition' => 0,
            'targeted_monthly_searches' => null
        ];

        $hasMetrics = !is_null($targetingIdea->getKeywordIdeaMetrics());

        if ($hasMetrics) {
            $keywordData['search_volume'] =  $targetingIdea->getKeywordIdeaMetrics()->getAvgMonthlySearches();
            $keywordData['cpc'] = $targetingIdea->getKeywordIdeaMetrics()->getAverageCpcMicros();
            $keywordData['competition'] = $targetingIdea->getKeywordIdeaMetrics()->getCompetition();
        }

        $result = new Keyword($keywordData);

        if ($this->withTargetedMonthlySearches && $hasMetrics) {
            $targeted_monthly_searches = $targetingIdea->getKeywordIdeaMetrics()->getMonthlySearchVolumes();

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
