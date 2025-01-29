<?php

namespace App\Http\Controllers;

use App\Models\AdsIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class AdsController extends Controller
{
    /**
     * Create a new AdsIntegration.
     */
    public function createAdsIntegration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|exists:merchants,id',
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
    public function getAdsIntegrationsByMerchantId($merchantId)
    {
        $adsIntegrations = AdsIntegration::where('merchant_id', $merchantId)->get();

        // do not return the access token, refresh token, or expires in
        $adsIntegrations = $adsIntegrations->makeHidden(['access_token', 'refresh_token', 'expires_in']);

        return response()->json($adsIntegrations);
    }

    // Get Google Oauth URL
    public function getGoogleOauthUrl($merchantId) 
    {
        $adsIntegration = AdsIntegration::where('merchant_id', $merchantId)->first();

        if (!$adsIntegration) {
            $adsIntegration = AdsIntegration::create([
                'merchant_id' => $merchantId,
                'type' => 'google'
            ]);
        }

        if ($adsIntegration && $adsIntegration->status === 'connected') {
            return response()->json(['url' => ''], 200);
        }

        $adsIntegration->update([
            'status' => 'pending',
        ]);

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
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adsIntegration = AdsIntegration::where('merchant_id', $request->merchant_id)->first();

        if (!$adsIntegration) {
            $adsIntegration = AdsIntegration::create([
                'merchant_id' => $request->merchant_id,
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

}
