<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Environments;
use Square\OAuth\Requests\ObtainTokenRequest;
use Illuminate\Support\Facades\Validator;
use App\Models\ExternalPaymentConfig;

class ExternalPaymentConfigController extends Controller
{
    /*
    * Get all external payment configs
    */
    public function getSquareConnectUrl(Request $request)
    {
        $url = env('SQUARE_URL') . '?client_id=' 
        . env('SQUARE_APPLICATION_ID') 
        . '&scope=CUSTOMERS_READ,ORDERS_READ,ITEMS_READ'
        . '&redirect_uri=' . env('SQUARE_REDIRECT_URI');

        return response()->json(['url' => $url]);
    }

    /*
    * Get the access token from the redirect uri
    */
    public function getSquareAccessToken(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'merchant_id' => 'required|string',
            'location_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Find ExternalPaymentConfig by merchant_id and location_id
        $externalPaymentConfig = ExternalPaymentConfig::where('merchant_id', $request->merchant_id)
            ->where('location_id', $request->location_id)
            ->first();

        if (!$externalPaymentConfig) {
            //create new external payment config
            $externalPaymentConfig = ExternalPaymentConfig::create([
                'merchant_id' => $request->merchant_id,
                'location_id' => $request->location_id,
                'name' => 'Square Integration',
                'type' => 'square',
                'status' => 'pending',
                'access_token' => null,
                'refresh_token' => null,
            ]);
        }

        // Exchange the code for an access token
        $client = new SquareClient(
            token: env('SQUARE_PERSONAL_ACCESS_TOKEN'),
            options: [
                'baseUrl' => Environments::Sandbox->value,
            ]
        );
        $response = $client->oAuth->obtainToken(
            new ObtainTokenRequest([
                'clientId' => env('SQUARE_APPLICATION_ID'),
                'clientSecret' => env('SQUARE_APPLICATION_SECRET'),
                'code' => $request->code,
                'grantType' => 'authorization_code',
                'redirectUri' => env('SQUARE_REDIRECT_URI'),
            ]),
        );


        // Update the external payment config with the access token
        $externalPaymentConfig->access_token = $response->getAccessToken();
        $externalPaymentConfig->refresh_token = $response->getRefreshToken();
        $externalPaymentConfig->status = 'active';
        $externalPaymentConfig->save(); 
        
        return response()->json(['message' => 'Access token saved successfully']);
        
    }

    // Get ExternalPaymentConfig by merchant_id and location_id
    public function getExternalPaymentConfig($merchantId, $locationId)
    {
        $externalPaymentConfig = ExternalPaymentConfig::where('merchant_id', $merchantId)
            ->where('location_id', $locationId)
            ->get();

        return response()->json($externalPaymentConfig);    
    }

}
