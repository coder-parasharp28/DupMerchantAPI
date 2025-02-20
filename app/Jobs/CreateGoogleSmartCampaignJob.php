<?php

namespace App\Jobs;

use App\Models\AdCampaign;
use App\Models\AdsIntegration;
use App\Models\AdsGoogleBusinessProfile;
use Google\Ads\GoogleAds\Lib\V18\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V18\Services\MutateGoogleAdsRequest;
use Google\Ads\GoogleAds\V18\Services\GoogleAdsServiceClient;
use Google\Ads\GoogleAds\V18\Services\MutateOperation;
use Google\Ads\GoogleAds\V18\Resources\Campaign;
use Google\Ads\GoogleAds\V18\Resources\AdGroup;
use Google\Ads\GoogleAds\V18\Resources\Ad;
use Google\Ads\GoogleAds\V18\Common\SmartCampaignAdInfo;
use Google\Ads\GoogleAds\V18\Common\AdTextAsset;
use Google\Ads\GoogleAds\V18\Resources\SmartCampaignSetting;
use Google\Ads\GoogleAds\V18\Resources\SmartCampaignSetting\AdOptimizedBusinessProfileSetting;
use Google\Ads\GoogleAds\V18\Enums\AdvertisingChannelTypeEnum\AdvertisingChannelType;
use Google\Ads\GoogleAds\V18\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V18\Enums\AdGroupTypeEnum\AdGroupType;
use Google\Ads\GoogleAds\V18\Enums\AdTypeEnum\AdType;
use Google\Auth\Credentials\UserRefreshCredentials;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Google\Ads\GoogleAds\V18\Common\AddressInfo;
use Google\Ads\GoogleAds\V18\Common\ProximityInfo;
use Google\Ads\GoogleAds\V18\Resources\CampaignCriterion;
use Google\Ads\GoogleAds\V18\Enums\ProximityRadiusUnitsEnum\ProximityRadiusUnits;
use Google\Ads\GoogleAds\Util\FieldMasks;
use Google\Ads\GoogleAds\V18\Resources\CampaignBudget;
use Google\Ads\GoogleAds\V18\Enums\BudgetDeliveryMethodEnum\BudgetDeliveryMethod;
use Google\Ads\GoogleAds\V18\Enums\BudgetTypeEnum\BudgetType;
use Google\Ads\GoogleAds\V18\Services\CampaignOperation;
use Google\Ads\GoogleAds\V18\Services\CampaignBudgetOperation;
use Google\Ads\GoogleAds\V18\Services\SmartCampaignSettingOperation;
use Google\Ads\GoogleAds\V18\Services\CampaignCriterionOperation;
use Google\Ads\GoogleAds\V18\Services\AdGroupOperation;
use Google\Ads\GoogleAds\V18\Services\AdGroupAdOperation;
use Google\Ads\GoogleAds\V18\Resources\AdGroupAd;
use Google\Ads\GoogleAds\V18\Common\KeywordThemeInfo;
use Google\Ads\GoogleAds\V18\Common\LocationInfo;
use Google\Ads\GoogleAds\V18\Enums\AdvertisingChannelSubTypeEnum\AdvertisingChannelSubType;
use Google\Ads\GoogleAds\V18\Enums\CriterionTypeEnum\CriterionType;


class CreateGoogleSmartCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $adCampaigns = AdCampaign::where('processing_status', 'pending')
            ->where('payment_status', 'paid')
            ->get();

        Log::info('Found ' . $adCampaigns->count() . ' AdCampaigns to process');

        foreach ($adCampaigns as $adCampaign) {
            $this->processAdCampaign($adCampaign);
        }
    }

    private function processAdCampaign(AdCampaign $adCampaign)
    {
        $adsIntegration = AdsIntegration::where('merchant_id', $adCampaign->merchant_id)
            ->where('location_id', $adCampaign->location_id)
            ->first();

        if (!$adsIntegration) {
            Log::error('AdsIntegration not found for AdCampaign ID: ' . $adCampaign->id);
            return;
        }

        $adsGoogleBusinessProfile = AdsGoogleBusinessProfile::where('merchant_id', $adCampaign->merchant_id)
            ->where('location_id', $adCampaign->location_id)
            ->first();

        if (!$adsGoogleBusinessProfile) {
            Log::error('AdsGoogleBusinessProfile not found for AdCampaign ID: ' . $adCampaign->id);
            return;
        }

        $credentials = new UserRefreshCredentials(
            ['https://www.googleapis.com/auth/adwords'],
            [
                'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
                'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
                'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
            ]
        );

        $googleAdsClient = (new GoogleAdsClientBuilder())
            ->withDeveloperToken(env('GOOGLE_ADS_DEVELOPER_TOKEN'))
            ->withLoginCustomerId($adsIntegration->mcc_id)
            ->withOAuth2Credential($credentials)
            ->build();

        try {
            $googleAdsServiceClient = $googleAdsClient->getGoogleAdsServiceClient();

            // Create the budget operation
            $budgetOperation = $this->createCampaignBudgetOperation($adsIntegration->customer_id, $adCampaign);

            // Execute the budget operation to get the budget resource name
            $request = new MutateGoogleAdsRequest([
                'customer_id' => $adsIntegration->customer_id,
                'mutate_operations' => [$budgetOperation],
            ]);

            $budgetResponse = $googleAdsServiceClient->mutate($request);
            $budgetResourceName = $budgetResponse->getMutateOperationResponses()[0]->getCampaignBudgetResult()->getResourceName();

            // Create the campaign operation using the budget resource name
            $campaignOperation = $this->createCampaignOperation($adsIntegration->customer_id, $adCampaign, $budgetResourceName);

            // Create the smart campaign setting operation
            $smartCampaignSettingOperation = $this->createSmartCampaignSettingOperation($adsIntegration->customer_id, $adCampaign, $adsGoogleBusinessProfile->google_business_profile_id);

            // Create campaign criterion operations
            $keywordThemes = []; // Populate with actual keyword themes
            $campaignCriterionOperations = $this->createCampaignCriterionOperations($adsIntegration->customer_id, $adCampaign, $keywordThemes);

            // Add proximity criterion operation
            $proximityCriterionOperation = $this->createProximityCriterionOperation($adsIntegration->customer_id, $adCampaign);

            // Create ad group operation
            $adGroupOperation = $this->createAdGroupOperation($adsIntegration->customer_id, $adCampaign);

            // Create ad group ad operation
            $adGroupAdOperation = $this->createAdGroupAdOperation($adsIntegration->customer_id, $adCampaign);

            // Combine all operations
            $operations = array_merge(
                [$campaignOperation, $smartCampaignSettingOperation, $adGroupOperation, $adGroupAdOperation, $proximityCriterionOperation],
                $campaignCriterionOperations
            );

            // Prepare the request with customer ID and operations.
            $request = new MutateGoogleAdsRequest([
                'customer_id' => $adsIntegration->customer_id,
                'mutate_operations' => $operations,
            ]);

            // Execute the mutate request.
            $response = $googleAdsServiceClient->mutate($request);

            // Extract the campaign ID from the response
            $campaignId = $response->getMutateOperationResponses()[0]->getCampaignResult()->getResourceName();

            Log::info('Smart Campaign created successfully for AdCampaign ID: ' . $adCampaign->id);

            // Update the AdCampaign with the external_id and set the processing status to 'completed'
            $adCampaign->update([
                'external_id' => $campaignId,
                'processing_status' => 'completed',
                'status' => 'active'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create Smart Campaign for AdCampaign ID: ' . $adCampaign->id . ' - ' . $e->getMessage());
        }
    }

    private function createCampaignBudgetOperation($customerId, AdCampaign $adCampaign)
    {
        $budgetTempId = '-1'; // Temporary ID

        // Create the CampaignBudget resource.
        $campaignBudget = new CampaignBudget([
            'name' => $adCampaign->name . ' budget ' . uniqid(),
            'delivery_method' => BudgetDeliveryMethod::STANDARD,
            'type' => BudgetType::SMART_CAMPAIGN,
            'amount_micros' => $adCampaign->budget * 1e6,
        ]);

        // Create the CampaignBudgetOperation.
        $campaignBudgetOperation = new CampaignBudgetOperation();
        $campaignBudgetOperation->setCreate($campaignBudget);

        // Create the MutateOperation with the CampaignBudgetOperation.
        return new MutateOperation([
            'campaign_budget_operation' => $campaignBudgetOperation,
        ]);
    }

    private function createCampaignOperation($customerId, AdCampaign $adCampaign, $budgetResourceName)
    {
        $campaignTempId = '-2'; // Temporary ID

        $campaign = new Campaign([
            'name' => $adCampaign->name . ' ' . date('Y-m-d'),
            'advertising_channel_type' => AdvertisingChannelType::SMART,
            'advertising_channel_sub_type' => AdvertisingChannelSubType::SMART_CAMPAIGN,
            'status' => CampaignStatus::ENABLED,
            'campaign_budget' => $budgetResourceName,
            'resource_name' => sprintf('customers/%s/campaigns/%s', $customerId, $campaignTempId),
        ]);

        $campaignOperation = new CampaignOperation();
        $campaignOperation->setCreate($campaign);

        return new MutateOperation([
            'campaign_operation' => $campaignOperation,
        ]);
    }

    private function createSmartCampaignSettingOperation($customerId, AdCampaign $adCampaign, $googleBusinessProfileId)
    {
        $campaignTempId = '-2'; // Temporary ID

        $adOptimizedBusinessProfileSetting = new AdOptimizedBusinessProfileSetting([
            'include_lead_form' => true,
        ]);

        $smartCampaignSetting = new SmartCampaignSetting([
            'resource_name' => sprintf('customers/%s/smartCampaignSettings/%s', $customerId, $campaignTempId),
            'advertising_language_code' => 'en',
            'ad_optimized_business_profile_setting' => $adOptimizedBusinessProfileSetting,
            'business_profile_location' => $googleBusinessProfileId,
        ]);

        // Create the update mask with the fields you are updating
        $updateMask = FieldMasks::allSetFieldsOf($smartCampaignSetting);

        // Create the SmartCampaignSettingOperation
        $smartCampaignSettingOperation = new SmartCampaignSettingOperation([
            'update' => $smartCampaignSetting,
            'update_mask' => $updateMask,
        ]);

        // Wrap the SmartCampaignSettingOperation in a MutateOperation
        return new MutateOperation([
            'smart_campaign_setting_operation' => $smartCampaignSettingOperation,
        ]);
    }

    private function createCampaignCriterionOperations($customerId, AdCampaign $adCampaign, $keywordThemes)
    {
        $campaignTempId = '-2'; // Temporary ID
        $operations = [];

        foreach ($keywordThemes as $theme) {
            $keywordThemeInfo = new KeywordThemeInfo([
                'keyword_theme_constant' => $theme,
            ]);

            $campaignCriterion = new CampaignCriterion([
                'campaign' => sprintf('customers/%s/campaigns/%s', $customerId, $campaignTempId),
                'type' => CriterionType::KEYWORD_THEME,
                'keyword_theme' => $keywordThemeInfo,
            ]);

            $campaignCriterionOperation = new CampaignCriterionOperation();
            $campaignCriterionOperation->setCreate($campaignCriterion);

            $operations[] = new MutateOperation([
                'campaign_criterion_operation' => $campaignCriterionOperation,
            ]);
        }

        return $operations;
    }

    private function createProximityCriterionOperation($customerId, AdCampaign $adCampaign)
    {
        $campaignTempId = '-2'; // Temporary ID

        // Create AddressInfo
        $addressInfo = new AddressInfo([
            'postal_code' => $adCampaign->zip_code,
            'province_code' => $adCampaign->state,
            'city_name' => $adCampaign->city,
            'street_address' => $adCampaign->address_line_1,
        ]);

        // Create ProximityInfo
        $proximityInfo = new ProximityInfo([
            'address' => $addressInfo,
            'radius' => $adCampaign->radius,
            'radius_units' => ProximityRadiusUnits::MILES,
        ]);

        // Create CampaignCriterion for Proximity
        $campaignCriterion = new CampaignCriterion([
            'campaign' => sprintf('customers/%s/campaigns/%s', $customerId, $campaignTempId),
            'proximity' => $proximityInfo,
        ]);

        $campaignCriterionOperation = new CampaignCriterionOperation();
        $campaignCriterionOperation->setCreate($campaignCriterion);

        return new MutateOperation([
            'campaign_criterion_operation' => $campaignCriterionOperation,
        ]);
    }

    private function createAdGroupOperation($customerId, AdCampaign $adCampaign)
    {
        $adGroupTempId = '-3'; // Temporary ID
        $campaignTempId = '-2'; // Temporary ID

        $adGroup = new AdGroup([
            'name' => $adCampaign->name . ' Ad Group',
            'campaign' => sprintf('customers/%s/campaigns/%s', $customerId, $campaignTempId),
            'type' => AdGroupType::SMART_CAMPAIGN_ADS,
            'resource_name' => sprintf('customers/%s/adGroups/%s', $customerId, $adGroupTempId),
        ]);

        $adGroupOperation = new AdGroupOperation();
        $adGroupOperation->setCreate($adGroup);

        return new MutateOperation([
            'ad_group_operation' => $adGroupOperation,
        ]);
    }

    private function createAdGroupAdOperation($customerId, AdCampaign $adCampaign)
    {
        $adGroupTempId = '-3'; // Temporary ID

        $adTextAsset = new AdTextAsset([
            'text' => $adCampaign->headline1,
        ]);

        $adTextAsset2 = new AdTextAsset([
            'text' => $adCampaign->headline2,
        ]);

        $adTextAsset3 = new AdTextAsset([
            'text' => $adCampaign->headline3,
        ]);

        $adTextAsset4 = new AdTextAsset([
            'text' => $adCampaign->description1,
        ]);

        $adTextAsset5 = new AdTextAsset([
            'text' => $adCampaign->description2,
        ]);


        $smartCampaignAdInfo = new SmartCampaignAdInfo([
            'headlines' => [
                $adTextAsset,
                $adTextAsset2,
                $adTextAsset3,
            ],
            'descriptions' => [
                $adTextAsset4,
                $adTextAsset5,
            ],
        ]);

        $ad = new Ad([
            'type' => AdType::SMART_CAMPAIGN_AD,
            'smart_campaign_ad' => $smartCampaignAdInfo,
        ]);

        $adGroupAd = new AdGroupAd([
            'ad_group' => sprintf('customers/%s/adGroups/%s', $customerId, $adGroupTempId),
            'ad' => $ad,
        ]);

        $adGroupAdOperation = new AdGroupAdOperation();
        $adGroupAdOperation->setCreate($adGroupAd);

        return new MutateOperation([
            'ad_group_ad_operation' => $adGroupAdOperation,
        ]);
    }
}