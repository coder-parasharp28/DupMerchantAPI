<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MerchantBalance;
class MerchantBalanceController extends Controller
{
    /**
     * Get the current balance of a given merchant at a specific location.
     *
     * @param  int  $merchantId
     * @param  int  $locationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentBalance(Request $request)
    {

        // Validate the request input
        $validatedData = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        $merchantId = $validatedData['merchant_id'];
        $locationId = $validatedData['location_id'];

        // Retrieve the merchant balance for the given merchant_id and location_id
        $merchantBalance = MerchantBalance::where('merchant_id', $merchantId)
                                          ->where('location_id', $locationId)
                                          ->first();

        // Check if merchant balance exists
        if (!$merchantBalance) {
            return response()->json([
                'merchant_id' => $merchantId,
                'location_id' => $locationId,
                'current_balance' => round(0, 2),
                'funds_on_hold' => round(0, 2)
            ]);
        }

        // Return the current balance and other relevant information
        return response()->json([
            'merchant_id' => $merchantBalance->merchant_id,
            'location_id' => $merchantBalance->location_id,
            'current_balance' => round($merchantBalance->current_balance, 2),
            'funds_on_hold' => round(0, 2)
        ]);
    }
}
