<?php

namespace App\Http\Controllers;

use App\Models\AdsIntegration;
use App\Models\Location;
use App\Models\AdsGoogleBusinessProfile;
use App\Jobs\InviteAdminToGBPJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Ads\GoogleAds\V18\Resources\Customer;
use Google\Ads\GoogleAds\V18\Services\CreateCustomerClientRequest;
use App\Services\GoogleAdsService;

class AdsController extends Controller
{
    protected $googleAdsService;

    public function __construct(GoogleAdsService $googleAdsService)
    {
        $this->googleAdsService = $googleAdsService;
    }

    /**
     * Create a new AdsIntegration.
     */
    public function createAdsIntegration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'type' => 'required|string',
            'access_token' => 'nullable|string',
            'expires_in' => 'nullable|integer',
            'refresh_token' => 'nullable|string',
            'customer_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adsIntegration = AdsIntegration::create($validator->validated());

        return response()->json($adsIntegration, 201);
    }

    /**
     * Update an existing AdsIntegration.
     */
    public function updateAdsIntegration(Request $request, $id)
    {
        $adsIntegration = AdsIntegration::find($id);

        if (!$adsIntegration) {
            return response()->json(['error' => 'AdsIntegration not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|string',
            'access_token' => 'sometimes|required|string',
            'expires_in' => 'sometimes|required|integer',
            'refresh_token' => 'sometimes|required|string',
            'customer_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adsIntegration->update($validator->validated());

        return response()->json($adsIntegration);
    }

    /**
     * Get AdsIntegrations by Merchant ID.
     */
    public function getAdsIntegrationsByMerchantIdAndLocationId($merchantId, $locationId)
    {
        $adsIntegrations = AdsIntegration::where('merchant_id', $merchantId)
                                            ->where('location_id', $locationId)
                                            ->first();

        if (!$adsIntegrations) {
            return response()->json(null);
        }

        // do not return the access token, refresh token, or expires in
        $adsIntegrations = $adsIntegrations->makeHidden(['access_token', 'refresh_token', 'expires_in']);

        return response()->json($adsIntegrations);
    }

    // Get Google Oauth URL
    public function getGoogleOauthUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        $adsIntegration = AdsIntegration::where('merchant_id', $request->merchant_id)
                                            ->where('location_id', $request->location_id)
                                            ->first();

        if (!$adsIntegration) {
            $adsIntegration = AdsIntegration::create([
                'merchant_id' => $request->merchant_id,
                'location_id' => $request->location_id,
                'type' => 'google',
                'status' => 'pending'
            ]);
        }
        $scopes = ["https://www.googleapis.com/auth/adwords",
        "https://www.googleapis.com/auth/business.manage"];

        $url = "https://accounts.google.com/o/oauth2/auth?client_id=" . env('GOOGLE_CLIENT_ID') . "&redirect_uri=" . env('GOOGLE_REDIRECT_URI') . "&access_type=offline&prompt=consent&response_type=code&scope=" . implode(" ", $scopes);

        return response()->json(['url' => $url]);
    }

    // Get Google OAuth Token
    public function getGoogleOauthToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adsIntegration = AdsIntegration::where('merchant_id', $request->merchant_id)
                                            ->where('location_id', $request->location_id)
                                            ->first();

        if (!$adsIntegration) {
            $adsIntegration = AdsIntegration::create([
                'merchant_id' => $request->merchant_id,
                'location_id' => $request->location_id,
                'type' => 'google',
            ]);
        }

        $url = "https://oauth2.googleapis.com/token";

        $response = Http::post($url, [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'code' => $validator->validated()['code'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        ]);

        $adsIntegration->update([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'],
            'expires_in' => $response['expires_in'],
            'status' => 'connected',
        ]);

        // hide the access token, refresh token, and expires in
        $adsIntegration = $adsIntegration->makeHidden(['access_token', 'refresh_token', 'expires_in']);

        return response()->json($adsIntegration);
    }

    /*
    Get all google my business accounts for a merchant
    Deprecated
    */
    public function getAllGoogleAccounts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adsIntegration = AdsIntegration::where('merchant_id', $request->merchant_id)
                                            ->where('location_id', $request->location_id)
                                            ->first();

        if (!$adsIntegration || !$adsIntegration->refresh_token) {
            return response()->json(['error' => 'AdsIntegration not found or refresh token missing'], 404);
        }

        // Exchange refresh token for access token
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'refresh_token' => $adsIntegration->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($tokenResponse->failed()) {
            return response()->json(['error' => 'Failed to refresh access token'], $tokenResponse->status());
        }

        $accessToken = $tokenResponse['access_token'];

        // Use the access token to get all accounts
        $url = "https://mybusinessaccountmanagement.googleapis.com/v1/accounts";
        $response = Http::withToken($accessToken)->get($url);

        return response()->json($response->json());

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch accounts'], $response->status());
        }

        return response()->json($response->json());
    }

    /*
    * Get all locations for a google my business profile locations associated with a merchant
    */
    public function getGoogleBusinessProfiles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adsIntegration = AdsIntegration::where('merchant_id', $request->merchant_id)
                                            ->where('location_id', $request->location_id)
                                            ->first();

        if (!$adsIntegration || !$adsIntegration->refresh_token) {
            return response()->json(['error' => 'AdsIntegration not found or refresh token missing'], 404);
        }

        // Exchange refresh token for access token
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'refresh_token' => $adsIntegration->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($tokenResponse->failed()) {
            return response()->json(['error' => 'Failed to refresh access token'], $tokenResponse->status());
        }

        $accessToken = $tokenResponse['access_token'];

        // Use the access token to get all accounts
        $url = "https://mybusinessaccountmanagement.googleapis.com/v1/accounts";
        $response = Http::withToken($accessToken)->get($url);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch accounts'], $response->status());
        }

        $accounts = $response->json();

        if (empty($accounts['accounts'])) {
            return response()->json(['error' => 'No accounts found'], 404);
        }

        // Assuming you want the first account's ID
        $accountId = $accounts['accounts'][0]['name'];

        // Use the account ID to get locations with a read_mask
        $locationsUrl = "https://mybusinessbusinessinformation.googleapis.com/v1/{$accountId}/locations";
        $locationsResponse = Http::withToken($accessToken)->get($locationsUrl, [
            'read_mask' => 'name,title,storefrontAddress,websiteUri,phoneNumbers,regularHours,categories,serviceItems',
        ]);


        if ($locationsResponse->failed()) {
            return response()->json(['error' => 'Failed to fetch locations'], $locationsResponse->status());
        }

        $locations = $locationsResponse->json();

        $filteredLocations = [];

        // Fetch admins for each location and filter based on role
        if (isset($locations['locations'])) {
            foreach ($locations['locations'] as $location) {
                $locationId = $location['name'];
                $adminsUrl = "https://mybusinessaccountmanagement.googleapis.com/v1/{$locationId}/admins";
                $adminsResponse = Http::withToken($accessToken)->get($adminsUrl);

                if ($adminsResponse->failed()) {
                    continue; // Skip this location if fetching admins fails
                }

                $admins = $adminsResponse->json()['admins'] ?? [];

                // Check if the current user is a PRIMARY_OWNER or OWNER
                foreach ($admins as $admin) {
                    if (isset($admin['account']) && $admin['account'] === $accountId && in_array($admin['role'], ['PRIMARY_OWNER', 'OWNER'])) {
                        $filteredLocations[] = $location;
                        break; // No need to check further admins for this location
                    }
                }
            }
        }

        return response()->json($filteredLocations);
    }

    /*
     * Link Google Business Profile
     */
    public function linkGoogleBusinessProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'google_business_profile_id' => 'required|string',
            'name' => 'required|string',
            'google_business_profile_object' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adsIntegration = AdsIntegration::where('merchant_id', $request->merchant_id)
                                            ->where('location_id', $request->location_id)
                                            ->first();

        if (!$adsIntegration) {
            return response()->json(['error' => 'AdsIntegration not found'], 404);
        }

        $adsGoogleBusinessProfile = AdsGoogleBusinessProfile::where('merchant_id', $request->merchant_id)
                                            ->where('location_id', $request->location_id)
                                            ->where('ads_integration_id', $adsIntegration->id)
                                            ->first();

        $gbpJSON = $request->google_business_profile_object;

        if ($adsGoogleBusinessProfile) {
            $adsGoogleBusinessProfile->update([
                'google_business_profile_id' => $request->google_business_profile_id,
                'name' => $request->name,
                'google_business_profile_object' => $gbpJSON,
            ]);
        } else {
            $adsGoogleBusinessProfile = AdsGoogleBusinessProfile::create([
                'merchant_id' => $request->merchant_id,
                'location_id' => $request->location_id,
                'ads_integration_id' => $adsIntegration->id,
                'google_business_profile_id' => $request->google_business_profile_id,
                'name' => $request->name,
                'google_business_profile_object' => $gbpJSON,
            ]);
        }
        
        $adsIntegration->update([
            'gbp_linking_status' => 'completed',
        ]);

        dispatch(new InviteAdminToGBPJob($adsIntegration, $adsGoogleBusinessProfile));

        return response()->json(['message' => 'Google Business Profile linked successfully'], 200);
    }


    /*
    * Get all linked Google Business Profiles for a merchant
    */
    public function getLinkedGoogleBusinessProfiles(Request $request)
    {

        $merchantId = $request->query('merchantId');
        $locationId = $request->query('locationId');
        
        if (!$merchantId || !$locationId) {
            return response()->json(['error' => 'Merchant ID and Location ID are required'], 422);
        }

        $adsGoogleBusinessProfiles = AdsGoogleBusinessProfile::where('merchant_id', $merchantId)
                                            ->where('location_id', $locationId)
                                            ->get();

        return response()->json($adsGoogleBusinessProfiles);
    }

    /*
    * Create a Google Ads Account
    */
    public function createGoogleAdsAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {      
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adsIntegration = AdsIntegration::where('merchant_id', $request->merchant_id)
                                            ->where('location_id', $request->location_id)
                                            ->first();

        if (!$adsIntegration) {
            return response()->json(['error' => 'AdsIntegration not found'], 404);
        }

        if ($adsIntegration->ads_account_creation_status == 'completed') {
            $adsIntegration = $adsIntegration->makeHidden(['access_token', 'refresh_token', 'expires_in']);
            return response()->json($adsIntegration);   
        }
        
        // Get Location 
        $location = Location::find($request->location_id);

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }
        
        // MCC ID
        $mccId = env('GOOGLE_ADS_MCC_ID');
        
        // Use the service to get the Google Ads client
        $googleAdsClient = $this->googleAdsService->getGoogleAdsClient(
            env('GOOGLE_ADS_CLIENT_ID'),
            env('GOOGLE_ADS_CLIENT_SECRET'),
            env('GOOGLE_ADS_DEVELOPER_TOKEN'),
            env('GOOGLE_ADS_REFRESH_TOKEN'),
            $mccId
        );

        // Create a customer client
        $customer = new Customer([
            'descriptive_name' => env('GOOGLE_ADS_ACCOUNT_NAME_PREFIX') . $location->name . ' - ' . $location->city,
            'currency_code' => 'USD',
            'time_zone' => 'America/New_York',
        ]);

        $customerId = null;

        try {
            $customerServiceClient = $googleAdsClient->getCustomerServiceClient();
            $response = $customerServiceClient->createCustomerClient(
                CreateCustomerClientRequest::build($mccId, $customer)
            );
            $customerId = explode('/', $response->getResourceName())[1];
        } catch (\Exception $e) {
            Log::error('Failed to create customer: ' . $e->getMessage());
            return response()->json($adsIntegration);
        }

        if ($customerId) {
            $adsIntegration->update([
                'customer_id' => $customerId,
                'mcc_id' => $mccId,
                'ads_account_creation_status' => 'completed',
            ]);
        }

        $adsIntegration = $adsIntegration->makeHidden(['access_token', 'refresh_token', 'expires_in']);
        return response()->json($adsIntegration);   
    }

}
