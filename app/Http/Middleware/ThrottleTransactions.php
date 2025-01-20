<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Merchant;
use App\Models\ItemVariation;
use Carbon\Carbon;

class ThrottleTransactions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $merchantId = $request->input('merchant_id');
        $locationId = $request->input('location_id');
        $transactionItems = $request->input('transaction_items'); // Assuming transaction items are passed in the request

        $merchant = Merchant::find($merchantId);
        if (!$merchant) {
            return response()->json(['error' => 'Merchant not found'], 404);
        }

        $onboardedDays = Carbon::now()->diffInDays($merchant->created_at);

        // Determine rules based on onboarding duration
        if ($onboardedDays < 7) {
            $maxTransactionsPerMinute = 4;
            $maxAmount = 1000;
        } elseif ($onboardedDays < 30) {
            $maxTransactionsPerMinute = 6;
            $maxAmount = 1500;
        } else {
            $maxTransactionsPerMinute = 8;
            $maxAmount = 2500;
        }

        // Calculate total amount
        $totalAmount = 0;
        foreach ($transactionItems as $item) {
            $itemVariation = ItemVariation::find($item['item_variation_id']);
            if (!$itemVariation) {
                return response()->json(['error' => "Item variation not found for ID: {$item['item_variation_id']}"], 404);
            }
            $totalAmount += $itemVariation->price * $item['quantity'];
        }

        // Check transaction amount
        if ($totalAmount >= $maxAmount) {
            return response()->json(['error' => 'Transaction amount exceeds limit'], 403);
        }

        // Throttle based on merchant and location
        $cacheKey = "transactions:{$merchantId}:{$locationId}";
        $transactionCount = Cache::get($cacheKey, 0);

        if ($transactionCount >= $maxTransactionsPerMinute) {
            return response()->json(['error' => 'Transaction rate limit exceeded'], 429);
        }

        // Increment transaction counts
        Cache::put($cacheKey, $transactionCount + 1, now()->addMinute());

        return $next($request);
    }
}
