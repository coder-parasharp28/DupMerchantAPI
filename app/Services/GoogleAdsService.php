<?php

namespace App\Services;

use Google\Ads\GoogleAds\Lib\V18\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V18\GoogleAdsClientBuilder;
use Google\Auth\Credentials\UserRefreshCredentials;

class GoogleAdsService
{
    public function getGoogleAdsClient($clientId, $clientSecret, $developerToken, $refreshToken, $mccId)
    {
        $credentials = new UserRefreshCredentials(
            ['https://www.googleapis.com/auth/adwords'],
            [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
            ]
        );

        $googleAdsClient = (new GoogleAdsClientBuilder())
            ->withDeveloperToken($developerToken)
            ->withLoginCustomerId($mccId)
            ->withOAuth2Credential($credentials)
            ->build();

        return $googleAdsClient;
    }
}
