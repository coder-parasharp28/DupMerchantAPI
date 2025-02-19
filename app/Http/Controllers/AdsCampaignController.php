<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdsGoogleBusinessProfile;
use App\Models\Merchant;
use App\Models\Location;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenAI;

class AdsCampaignController extends Controller
{
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

        $adCampaigns = AdCampaign::where('merchant_id', $merchantId)
            ->where('location_id', $locationId)
            ->get();

        foreach ($adCampaigns as $adCampaign) {
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
}