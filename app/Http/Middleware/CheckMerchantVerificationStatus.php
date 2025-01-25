<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Merchant; 

class CheckMerchantVerificationStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $merchantId = $request->input('merchant_id');
        $merchant = Merchant::find($merchantId);

        if (!$merchant || $merchant->verification_status !== 'SUCCESS') {
            return response()->json(['error' => 'Merchant verification failed.'], 403);
        }

        return $next($request);
    }
}
