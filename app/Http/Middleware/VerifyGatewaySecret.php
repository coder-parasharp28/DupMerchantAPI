<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGatewaySecret
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract the X-Service-Secret header
        $serviceSecretHeader = $request->header('X-Service-Secret');
        
        // Validate if the X-Service-Secret header is present
        if (!$serviceSecretHeader) {
            return response()->json(['error' => 'Unauthorized - Missing Credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // The expected secret for this service, stored in the .env file
        $expectedSecret = env('GATEWAY_SECRET');

        // Check if the provided secret matches the expected secret
        if ($serviceSecretHeader !== $expectedSecret) {
            return response()->json(['error' => 'Unauthorized - Incorrect Credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Allow the request to proceed if the secret is valid
        return $next($request);
    }
}
