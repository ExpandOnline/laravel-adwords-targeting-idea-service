<?php

namespace SchulzeFelix\AdWords;

use Google\AdsApi\AdWords\v201705\o\TargetingIdeaService;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;

class AdWordsServiceFactory
{
    public static function createForConfig(array $adwordsConfig): AdWordsService
    {
        $session = self::createAuthenticatedAdWordsSessionBuilder($adwordsConfig);

        $adWordsServices = new AdWordsServices();
        $targetingIdeaService = $adWordsServices->get($session, TargetingIdeaService::class);

        return self::createTargetingIdeaService($targetingIdeaService);
    }

    /**
     * @param array $config
     * @return AdWordsSession
     *
     * Generate a refreshable OAuth2 credential for authentication.
     * Construct an API session
     */
    protected static function createAuthenticatedAdWordsSessionBuilder(array $config): AdWordsSession
    {
        $oAuth2Credential = (new OAuth2TokenBuilder())
            ->withClientId($config['client_id'])
            ->withClientSecret($config['client_secret'])
            ->withRefreshToken($config['client_refresh_token'])
            ->build();

        $session = (new AdWordsSessionBuilder())
            ->withOAuth2Credential($oAuth2Credential)
            ->withDeveloperToken($config['developer_token'])
            ->withUserAgent($config['user_agent'])
            ->withClientCustomerId($config['client_customer_id'])
            ->build();

        return $session;
    }

    protected static function createTargetingIdeaService(TargetingIdeaService $targetingIdeaService): AdWordsService
    {
        return new AdWordsService($targetingIdeaService);
    }

}