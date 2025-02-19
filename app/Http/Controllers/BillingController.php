<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\AdCampaign;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /*
     * Get Customer Portal URL
     */ 
    public function getCustomerPortalUrl($merchantId, $locationId) {

        $location = Location::find($locationId);

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        if (!$location->stripe_customer_id) {
            return response()->json(['error' => 'Location does not have a Stripe customer ID'], 400);
        }

        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        $session = $stripe->billingPortal->sessions->create([
            'customer' => $location->stripe_customer_id,
            'return_url' => env('STRIPE_REDIRECT_URL'),
        ]);

        return response()->json(['url' => $session->url]);
    }

    /*
     * Create a Checkout Session
     */
    public function createCheckoutSession($merchantId, $locationId, $adCampaignId) {

        $location = Location::find($locationId);

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        if (!$location->stripe_customer_id) {
            return response()->json(['error' => 'Location does not have a Stripe customer ID'], 400);
        }

        $adCampaign = AdCampaign::find($adCampaignId);

        if (!$adCampaign) { 
            return response()->json(['error' => 'Ad Campaign not found'], 404);
        }

        if ($adCampaign->payment_status !== 'pending') {
            return response()->json(['error' => 'Ad Campaign payment status is not pending'], 400);
        }
        
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        $session = $stripe->checkout->sessions->create([
            'customer' => $location->stripe_customer_id,
            'line_items' => [[
                'price' => $adCampaign->stripe_price_id,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => env('STRIPE_CAMPAIGN_APP_URL'),
            'cancel_url' => env('STRIPE_CAMPAIGN_APP_URL'),
            'subscription_data' => [
                'trial_period_days' => 30,
            ]
        ]);

        $adCampaign->stripe_checkout_session_id = $session->id;
        $adCampaign->save();

        return response()->json(['url' => $session->url]);
    
    }


    /**
     * Check Subscription Status
     * Also write a cron job to check this nightly
     */
    public function checkSubscriptionStatus($merchantId, $locationId, $adCampaignId) {

        $location = Location::find($locationId);
        
        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        if (!$location->stripe_customer_id) {
            return response()->json(['error' => 'Location does not have a Stripe subscription ID'], 400);
        }

        $adCampaign = AdCampaign::find($adCampaignId);

        if (!$adCampaign) {
            return response()->json(['error' => 'Ad Campaign not found'], 404);
        }

        if ($adCampaign->payment_status === 'pending') {
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $checkoutSession = $stripe->checkout->sessions->retrieve(
                $adCampaign->stripe_checkout_session_id,
                []
            );

            if ($checkoutSession->status === 'complete' && $checkoutSession->payment_status === 'paid') {
                $adCampaign->stripe_subscription_id = $checkoutSession->subscription;
                $adCampaign->payment_status = 'paid';
                $adCampaign->save();
            }
        } 
       
        return response()->json(['status' => $adCampaign->payment_status]);

    }
}
