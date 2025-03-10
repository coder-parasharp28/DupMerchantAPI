<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdsGoogleBusinessProfile;
use App\Models\AdsIntegration;
use App\Models\Merchant;
use App\Models\Location;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenAI;
use App\Jobs\CreateGoogleSmartCampaignJob;
use Google\Ads\GoogleAds\V18\Services\GoogleAdsServiceClient;
use Google\Ads\GoogleAds\Lib\V18\GoogleAdsClientBuilder;
use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Ads\GoogleAds\V18\Services\SearchGoogleAdsStreamRequest;

class AdsCampaignController extends Controller
{
    /**
     * Dispatch the Smart Campaign job
     */
    public function dispatchSmartCampaignJob(Request $request)
    {
        // validate super admin password    
        $validator = Validator::make($request->all(), [
            'super_admin_password' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->super_admin_password !== env('SUPER_ADMIN_PASSWORD')) {
            return response()->json(['error' => 'Invalid password'], 401);
        }

        // Dispatch the job
        $job = new CreateGoogleSmartCampaignJob();
        $result = $job->handle();

        if ($result) {
            return response()->json(['message' => 'Smart Campaign job dispatched.', 'result' => $result]);
        } else {
            return response()->json(['error' => 'Smart Campaign job failed.', 'result' => $result], 500);
        }
    }

    /**
     * Create a new AdCampaign.
     */
    public function createAdCampaign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'business_profile_id' => 'nullable|exists:business_profiles,id',
            'budget' => 'required|numeric|min:0',
            'status' => 'required|in:draft,learning,active,paused,completed',
            'type' => 'required|in:smart,pmax,search,display,video',
            'goal' => 'required|in:awareness,consideration,conversion',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip_code' => 'required|string|max:20',
            'radius' => 'required|integer|min:0',
            'headline1' => 'required|string|max:255',
            'headline2' => 'required|string|max:255',
            'headline3' => 'nullable|string|max:255',
            'description1' => 'required|string|max:255',
            'description2' => 'nullable|string|max:255',
            'landing_page_url' => 'nullable|url',
            'stripe_price_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adCampaign = AdCampaign::create($validator->validated());

        return response()->json($adCampaign, 201);
    }

    /**
     * Update an existing AdCampaign.
     */
    public function updateAdCampaign(Request $request, $id)
    {
        $adCampaign = AdCampaign::find($id);

        if (!$adCampaign) {
            return response()->json(['error' => 'AdCampaign not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'budget' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:draft,active,paused,completed',
            'type' => 'sometimes|required|in:search,display,video',
            'goal' => 'sometimes|required|in:awareness,consideration,conversion',
            'address_line_1' => 'sometimes|required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state' => 'sometimes|required|string|max:255',
            'zip_code' => 'sometimes|required|string|max:20',
            'radius' => 'sometimes|required|integer|min:0',
            'headline1' => 'sometimes|required|string|max:255',
            'headline2' => 'sometimes|required|string|max:255',
            'headline3' => 'nullable|string|max:255',
            'description1' => 'sometimes|required|string|max:255',
            'description2' => 'nullable|string|max:255',
            'landing_page_url' => 'sometimes|required|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Cannot update stripe related fields
        $validator->offsetUnset('stripe_price_id');
        $validator->offsetUnset('stripe_checkout_session_id');
        $validator->offsetUnset('stripe_subscription_id');

        $adCampaign->update($validator->validated());

        return response()->json($adCampaign);
    }

    /**
     * Get AdCampaigns by Merchant ID and Location ID.
     */
    public function getAdCampaigns(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'merchantId' => 'required|exists:merchants,id',
            'locationId' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $merchantId = $request->query('merchantId');
        $locationId = $request->query('locationId');

        $location = Location::where('merchant_id', $merchantId)->where('id', $locationId)->first();

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }
        
        $adCampaigns = AdCampaign::where('merchant_id', $merchantId)
            ->where('location_id', $locationId)
            ->get();

        foreach ($adCampaigns as $adCampaign) {

            $campaignMetrics = $this->getCampaignMetrics($adCampaign->id, $request);

            if (empty($campaignMetrics['campaignMetrics'])) {
                $adCampaign->impressions = 0;
                $adCampaign->clicks = 0;
                $adCampaign->conversions = 0;
                $adCampaign->ad_spend = 0;
            } else {
                $adCampaign->impressions = $campaignMetrics['campaignMetrics'][0]['impressions'];
                $adCampaign->clicks = $campaignMetrics['campaignMetrics'][0]['clicks'];
                $adCampaign->conversions = $campaignMetrics['campaignMetrics'][0]['allConversionsValue'];
                $adCampaign->ad_spend = round($campaignMetrics['campaignMetrics'][0]['costMicros'] / 1000000, 2);
            }

            if (empty($campaignMetrics['localActionsMetrics'])) {
                $adCampaign->store_visits = 0;
                $adCampaign->phone_calls = 0;
                $adCampaign->direction_views = 0;
                $adCampaign->website_visits = 0;
                $adCampaign->other = 0;
            } else {
                $adCampaign->store_visits = $campaignMetrics['localActionsMetrics'][0]['storeVisits'];
                $adCampaign->phone_calls = $campaignMetrics['localActionsMetrics'][0]['clickToCall'];
                $adCampaign->direction_views = $campaignMetrics['localActionsMetrics'][0]['directions'];
                $adCampaign->website_visits = $campaignMetrics['localActionsMetrics'][0]['website'];
                $adCampaign->other = $campaignMetrics['localActionsMetrics'][0]['otherEngagement'];
            }

            // find the largest value between store_visits, phone_calls, direction_views, website_visits, other
            $totalConversions = max([$adCampaign->store_visits, $adCampaign->phone_calls, $adCampaign->direction_views, $adCampaign->website_visits, $adCampaign->other]);
            $estimatedConversion = round($totalConversions * 0.3);
            
            if ($adCampaign->ad_spend > 0) {
                $adCampaign->roasLow = round(($location->min_avg_order_value * $estimatedConversion) / $adCampaign->ad_spend, 2);
                $adCampaign->roasHigh = round(($location->max_avg_order_value * $estimatedConversion) / $adCampaign->ad_spend, 2);
                $adCampaign->revenueLow = round($estimatedConversion * $location->min_avg_order_value, 2);
                $adCampaign->revenueHigh = round($estimatedConversion * $location->max_avg_order_value, 2);
            } else {
                $adCampaign->roasLow = 0;
                $adCampaign->roasHigh = 0;
                $adCampaign->revenueLow = 0;
                $adCampaign->revenueHigh = 0;
            }

        }

        return response()->json($adCampaigns);
    }

    /**
     * Get a single AdCampaign by ID.
     */
    public function getAdCampaign($id)
    {
        $adCampaign = AdCampaign::find($id);

        if (!$adCampaign) {
            return response()->json(['error' => 'AdCampaign not found'], 404);
        }   

        $adCampaign->roas = 0;
        $adCampaign->impressions = 0;
        $adCampaign->clicks = 0;
        $adCampaign->conversions = 0;
        $adCampaign->revenue = 0;
        $adCampaign->ad_spend = 0;
        $adCampaign->store_visits = 0;
        $adCampaign->phone_calls = 0;
        $adCampaign->direction_views = 0;
        $adCampaign->website_visits = 0;
        $adCampaign->other = 0;

        return response()->json($adCampaign);
    }

    /**
     * Delete an AdCampaign by ID.
     */
    public function deleteAdCampaign($id)
    {
        $adCampaign = AdCampaign::find($id);

        if (!$adCampaign) {
            return response()->json(['error' => 'AdCampaign not found'], 404);
        }

        $adCampaign->delete();

        return response()->json(['message' => 'AdCampaign deleted successfully']);
    }

    /**
     * Generate headlines and descriptions using OpenAI.
     */
    public function generateAdContent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $merchantId = $request->merchant_id;
        $locationId = $request->location_id;

        // Fetch item names using merchantId and locationId
        $items = Item::where('merchant_id', $merchantId)
            ->where('location_id', $locationId)
            ->get();

        $itemNames = $items->pluck('name')->toArray();

        if (empty($itemNames)) {
            return response()->json(['headlines' => [], 'descriptions' => []]);
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that generates ad content.',
            ],
            [
                'role' => 'user',
                'content' => "Generate 3 headlines (30 characters or less) and 3 descriptions (90 characters or less) for a Google Ads campaign for a merchant who sells the following item: " . implode(', ', $itemNames) . ".",
            ],
        ];

        // Initialize OpenAI client using the factory method
        $openAIClient = OpenAI::client(env('OPENAI_API_KEY'));

        $response = $openAIClient->chat()->create([
            'model' => 'gpt-4o',
            'messages' => $messages,
            'max_tokens' => 150,
            'temperature' => 0.7,
        ]);

        $generatedText = $response['choices'][0]['message']['content'];

        // Parse the generated text into headlines and descriptions
        $lines = explode("\n", trim($generatedText));
        $headlines = [];
        $descriptions = [];
        $isHeadline = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (stripos($line, 'headlines:') !== false) {
                $isHeadline = true;
                continue;
            }
            if (stripos($line, 'descriptions:') !== false) {
                $isHeadline = false;
                continue;
            }
            // Remove leading numbers and punctuation
            $line = preg_replace('/^\d+\.\s*|^\"|\"$/', '', $line);
            if ($isHeadline) {
                $headlines[] = $line;
            } else {
                $descriptions[] = $line;
            }
        }

        return response()->json([
            'headlines' => $headlines,
            'descriptions' => $descriptions,
        ]);
    }

    /*
     * Generate Copy using user prompt
     */
    public function generateAdCopyByPrompt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'string|nullable|max:255',
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $prompt = $request->prompt;
        $merchantId = $request->merchant_id;
        $locationId = $request->location_id;

        if (empty($prompt)) {
            // Get Google Business Profile
            $googleBusinessProfile = AdsGoogleBusinessProfile::where('merchant_id', $merchantId)->where('location_id', $locationId)->first();

            if (!$googleBusinessProfile) {
                return response()->json(['error' => 'Google Business Profile not found'], 404);
            }

            $gbpObject = json_decode($googleBusinessProfile->google_business_profile_object, true);
            $category = '';

            if (isset($gbpObject['categories']) && isset($gbpObject['categories']['primaryCategory'])) {
                $category = $gbpObject['categories']['primaryCategory']['displayName'];
            }


            if (empty($category)) {
                return response()->json(['headlines' => [], 'descriptions' => []]);
            }

            $prompt = $category;

            $content = "Generate 3 headlines (30 characters or less) and 3 descriptions (90 characters or less) for a Google Ads campaign for a merchant who describes thier business as the following category : " . $category . ". Ensure 3 headlines and 3 descriptions are always generated. Do not include any other text in your response. Also dont include \"\" or any wildcards in your response.";
        } else {
            $content = "Generate 3 headlines (30 characters or less) and 3 descriptions (90 characters or less) for a Google Ads campaign for a merchant who describes thier business as : " . $prompt . ". Ensure 3 headlines and 3 descriptions are always generated. Also dont include \"\" or any wildcards in your response.";
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that generates ad content.',
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ];

        // Initialize OpenAI client using the factory method
        $openAIClient = OpenAI::client(env('OPENAI_API_KEY'));

        $response = $openAIClient->chat()->create([
            'model' => 'gpt-4o',
            'messages' => $messages,
            'max_tokens' => 150,
            'temperature' => 0.7,
        ]);

        $generatedText = $response['choices'][0]['message']['content'];

        // Parse the generated text into headlines and descriptions
        $lines = explode("\n", trim($generatedText));
        $headlines = [];
        $descriptions = [];
        $isHeadline = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (stripos($line, 'headlines:') !== false) {
                $isHeadline = true;
                continue;
            }
            if (stripos($line, 'descriptions:') !== false) {
                $isHeadline = false;
                continue;
            }
            // Remove leading numbers and punctuation
            $line = preg_replace('/^\d+\.\s*|^"|"$/', '', $line);
            if ($isHeadline) {
                $headlines[] = $line;
            } else {
                $descriptions[] = $line;
            }
        }

        return response()->json([
            'headlines' => $headlines,
            'descriptions' => $descriptions,
        ]);

    }

    /*
     * Generate Ad Copy using Google Ads API
     */
    public function generateKeywordSuggestionsByPrompt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'string|nullable|max:255',
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $prompt = $request->prompt;
        $merchantId = $request->merchant_id;
        $locationId = $request->location_id;
        
        if (empty($prompt)) {
            // Get Google Business Profile
            $googleBusinessProfile = AdsGoogleBusinessProfile::where('merchant_id', $merchantId)->where('location_id', $locationId)->first();

            if (!$googleBusinessProfile) {
                return response()->json(['error' => 'Google Business Profile not found'], 404);
            }

            $gbpObject = json_decode($googleBusinessProfile->google_business_profile_object, true);
            $category = '';

            if (isset($gbpObject['categories']) && isset($gbpObject['categories']['primaryCategory'])) {
                $category = $gbpObject['categories']['primaryCategory']['displayName'];
            }

            if (empty($category)) {
                return response()->json(['keywords' => []]);
            }

            $prompt = $category;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that generates ad content.',
            ],
            [
                'role' => 'user',
                'content' => "Generate 3 keywords (30 characters or less) for a Google Ads campaign for a merchant who describes thier business as : " . $prompt . ". Ensure 3 keywords are always generated. Do not include any other text in your response. Also dont include \"\" or any wildcards in your response.",
            ],
        ];

        // Initialize OpenAI client using the factory method
        $openAIClient = OpenAI::client(env('OPENAI_API_KEY'));

        $response = $openAIClient->chat()->create([
            'model' => 'gpt-4o',
            'messages' => $messages,
        ]);

        $generatedText = $response['choices'][0]['message']['content'];

        // Parse the generated text into keywords
        $keywords = explode("\n", trim($generatedText));

        return response()->json(['keywords' => $keywords]);
    }

    /*
     * Get Campaign Metrics
     */
    public function getCampaignMetrics($id, Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Get Campaign
        $campaign = AdCampaign::where('id', $id)->first();

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $merchantId = $campaign->merchant_id;
        $locationId = $campaign->location_id;

        // Get AdsIntegration
        $adsIntegration = AdsIntegration::where('merchant_id', $merchantId)->where('location_id', $locationId)->first();

        if (!$adsIntegration) {
            return response()->json(['error' => 'Ads Integration not found'], 404);
        }

        $customerId = (string) $adsIntegration->customer_id;
        $mccId = (string) $adsIntegration->mcc_id;

        // Get External Campaign ID
        $externalCampaignId = (string) $campaign->external_id;

        if (empty($externalCampaignId)) {
            return [
                'campaignMetrics' => [],
                'localActionsMetrics' => [],
            ]; 
        }

        // Initialize the Google Ads client
        $googleAdsClient = (new GoogleAdsClientBuilder())
            ->withDeveloperToken(env('GOOGLE_ADS_DEVELOPER_TOKEN'))
            ->withLoginCustomerId($mccId)
            ->withOAuth2Credential(new UserRefreshCredentials(
                ['https://www.googleapis.com/auth/adwords'],
                [
                    'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
                    'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
                    'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
                ]
            ))
            ->build();

        // Create the campaign query
        $campaignQuery = sprintf(
            "SELECT
                campaign.id,
                campaign.name,
                campaign.status,
                campaign_budget.id,
                campaign_budget.amount_micros,
                metrics.impressions,
                metrics.clicks,
                metrics.ctr,
                metrics.average_cpc,
                metrics.cost_micros,
                metrics.all_conversions_value
            FROM campaign
            WHERE campaign.id = %d
            %s",
            $externalCampaignId,
            (!is_null($startDate) && !is_null($endDate)) ? "AND segments.date >= '$startDate' AND segments.date <= '$endDate'" : ""
        );

        // Create the local actions query
        $localActionsQuery = sprintf(
            "SELECT
                metrics.all_conversions_from_location_asset_click_to_call,
                metrics.all_conversions_from_location_asset_directions,
                metrics.all_conversions_from_location_asset_menu,
                metrics.all_conversions_from_location_asset_order,
                metrics.all_conversions_from_location_asset_website,
                metrics.all_conversions_from_location_asset_store_visits,
                metrics.all_conversions_from_location_asset_other_engagement
            FROM campaign
            WHERE campaign.id = %d",
            $externalCampaignId
        );

        // Create the request objects
        $campaignRequest = new SearchGoogleAdsStreamRequest([
            'customer_id' => $customerId,
            'query' => $campaignQuery,
        ]);

        $localActionsRequest = new SearchGoogleAdsStreamRequest([
            'customer_id' => $customerId,
            'query' => $localActionsQuery,
        ]);

        // Execute the queries using the Google Ads service client
        $googleAdsServiceClient = $googleAdsClient->getGoogleAdsServiceClient();

        // Execute the campaign query
        $campaignResponse = $googleAdsServiceClient->searchStream($campaignRequest);

        // Execute the local actions query
        $localActionsResponse = $googleAdsServiceClient->searchStream($localActionsRequest);

        // Process the responses and return the results
        $campaignMetrics = [];
        foreach ($campaignResponse->iterateAllElements() as $googleAdsRow) {
            $campaignMetrics[] = [
                'campaignId' => $googleAdsRow->getCampaign()->getId(),
                'campaignName' => $googleAdsRow->getCampaign()->getName(),
                'status' => $googleAdsRow->getCampaign()->getStatus(),
                'budgetId' => $googleAdsRow->getCampaignBudget()->getId(),
                'budgetAmountMicros' => $googleAdsRow->getCampaignBudget()->getAmountMicros(),
                'impressions' => $googleAdsRow->getMetrics()->getImpressions(),
                'clicks' => $googleAdsRow->getMetrics()->getClicks(),
                'ctr' => $googleAdsRow->getMetrics()->getCtr(),
                'averageCpc' => $googleAdsRow->getMetrics()->getAverageCpc(),
                'costMicros' => $googleAdsRow->getMetrics()->getCostMicros(),
                'allConversionsValue' => $googleAdsRow->getMetrics()->getAllConversionsValue(),
            ];
        }

        $localActionsMetrics = [];
        foreach ($localActionsResponse->iterateAllElements() as $googleAdsRow) {
            $localActionsMetrics[] = [
                'clickToCall' => $googleAdsRow->getMetrics()->getAllConversionsFromLocationAssetClickToCall(),
                'directions' => $googleAdsRow->getMetrics()->getAllConversionsFromLocationAssetDirections(),
                'menu' => $googleAdsRow->getMetrics()->getAllConversionsFromLocationAssetMenu(),
                'order' => $googleAdsRow->getMetrics()->getAllConversionsFromLocationAssetOrder(),
                'website' => $googleAdsRow->getMetrics()->getAllConversionsFromLocationAssetWebsite(),
                'storeVisits' => $googleAdsRow->getMetrics()->getAllConversionsFromLocationAssetStoreVisits(),
                'otherEngagement' => $googleAdsRow->getMetrics()->getAllConversionsFromLocationAssetOtherEngagement(),
            ];
        }

        return [
            'campaignMetrics' => $campaignMetrics,
            'localActionsMetrics' => $localActionsMetrics,
        ];
    }


    /*
     * Get Campaign Keyword Searches
     */
    public function getCampaignKeywordSearches($id, Request $request) 
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Get Campaign
        $campaign = AdCampaign::where('id', $id)->first();

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $merchantId = $campaign->merchant_id;
        $locationId = $campaign->location_id;

        // Get AdsIntegration
        $adsIntegration = AdsIntegration::where('merchant_id', $merchantId)->where('location_id', $locationId)->first();

        if (!$adsIntegration) {
            return response()->json(['error' => 'Ads Integration not found'], 404);
        }
        
        $customerId = (string) $adsIntegration->customer_id;
        $mccId = (string) $adsIntegration->mcc_id;

        // Get External Campaign ID
        $externalCampaignId = (string) $campaign->external_id;

        if (empty($externalCampaignId)) {
            return [
                'campaignKeywordSearches' => [],
            ]; 
        }

        // Initialize the Google Ads client
        $googleAdsClient = (new GoogleAdsClientBuilder())
            ->withDeveloperToken(env('GOOGLE_ADS_DEVELOPER_TOKEN'))
            ->withLoginCustomerId($mccId)
            ->withOAuth2Credential(new UserRefreshCredentials(
                ['https://www.googleapis.com/auth/adwords'],
                [
                    'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
                    'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
                    'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
                ]
            ))
            ->build();

        // Create the campaign query
        $campaignKeywordSearchesQuery = sprintf(
            "SELECT
              campaign.id,
              campaign.name,
              metrics.clicks,
              metrics.impressions,
              metrics.cost_micros,
              smart_campaign_search_term_view.search_term
            FROM smart_campaign_search_term_view
            WHERE campaign.id = %d AND metrics.clicks > 0
            %s
            ORDER BY metrics.clicks DESC
            LIMIT 100",
            $externalCampaignId,
            (!is_null($startDate) && !is_null($endDate)) ? "AND segments.date >= '$startDate' AND segments.date <= '$endDate'" : ""
        );

        // Create the request objects
        $campaignKeywordSearchesRequest = new SearchGoogleAdsStreamRequest([
            'customer_id' => $customerId,
            'query' => $campaignKeywordSearchesQuery,
        ]);

        // Execute the queries using the Google Ads service client
        $googleAdsServiceClient = $googleAdsClient->getGoogleAdsServiceClient();

        // Execute the campaign query
        $campaignKeywordSearchesResponse = $googleAdsServiceClient->searchStream($campaignKeywordSearchesRequest);

        $campaignKeywordSearches = [];
        foreach ($campaignKeywordSearchesResponse->iterateAllElements() as $googleAdsRow) {

            $campaignKeywordSearches[] = [
                'searchTerm' => $googleAdsRow->getSmartCampaignSearchTermView()->getSearchTerm(),
                'clicks' => $googleAdsRow->getMetrics()->getClicks(),
                'impressions' => $googleAdsRow->getMetrics()->getImpressions(),
                'costMicros' => $googleAdsRow->getMetrics()->getCostMicros(),
            ];
        }

        return response()->json(['campaignKeywordSearches' => $campaignKeywordSearches]);
    }
    
}