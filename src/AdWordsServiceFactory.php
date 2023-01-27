<?php

namespace SchulzeFelix\AdWords;

use Google\Ads\GoogleAds\Lib\V12\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V12\GoogleAdsClientBuilder;
use Google\AdsApi\Common\AdsLoggerFactory;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Illuminate\Support\Arr;

class AdWordsServiceFactory
{
    private static $DEFAULT_SOAP_LOGGER_CHANNEL = 'AW_SOAP';

    public static function createForConfig(array $adwordsConfig): AdWordsService
    {
        return self::createTargetingIdeaService(self::createAuthenticatedAdWordsSessionBuilder($adwordsConfig), $adwordsConfig['client_customer_id']);
    }

    /**
     * @param array $config
     *
     * @return GoogleAdsClient
     *
     * Generate a refreshable OAuth2 credential for authentication.
     * Construct an API session
     */
    protected static function createAuthenticatedAdWordsSessionBuilder(array $config): GoogleAdsClient
    {
        $oAuth2Credential = (new OAuth2TokenBuilder())
            ->withClientId($config['client_id'])
            ->withClientSecret($config['client_secret'])
            ->withRefreshToken($config['client_refresh_token'])
            ->build();

        $soapLogger = (new AdsLoggerFactory())
            ->createLogger(
                self::$DEFAULT_SOAP_LOGGER_CHANNEL,
                Arr::get($config, 'soap_log_file_path', null),
                Arr::get($config, 'soap_log_level', 'ERROR')
            );

        return (new GoogleAdsClientBuilder())
            ->withOAuth2Credential($oAuth2Credential)
            ->withDeveloperToken($config['developer_token'])
            ->withLoginCustomerId($config['client_customer_id'])
            ->withLogger($soapLogger)
            ->build();
    }

    protected static function createTargetingIdeaService(GoogleAdsClient $adsClient, $customerId): AdWordsService
    {
        return new AdWordsService($adsClient, $customerId);
    }
}
