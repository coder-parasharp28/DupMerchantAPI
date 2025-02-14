<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyGatewaySecret;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\AdsCampaignController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\MerchantBalanceController;

// Put all routes that go through API Gateway in this middleware
Route::middleware([VerifyGatewaySecret::class])->group(function () {
    Route::get('/merchant/v1/test', [MerchantController::class, 'respondOkay']);

    // Merchant CRUD routes
    Route::post('/merchant/v1/merchants', [MerchantController::class, 'store']);
    Route::get('/merchant/v1/merchants/{id}', [MerchantController::class, 'show']);
    Route::put('/merchant/v1/merchants/{id}', [MerchantController::class, 'update']);
    Route::delete('/merchant/v1/merchants/{id}', [MerchantController::class, 'destroy']);

    // Payout routes
    Route::post('/merchant/v1/merchants/{merchantId}/payout', [MerchantController::class, 'createPayout']);
    Route::get('/merchant/v1/merchants/{merchantId}/payout', [MerchantController::class, 'getPayout']);
    Route::put('/merchant/v1/merchants/{merchantId}/payout', [MerchantController::class, 'updatePayout']);

    // Merchant Balance
    Route::post('/merchant/v1/merchants/locations/balance', [MerchantBalanceController::class, 'getCurrentBalance']);

    // Get merchants by owner ID
    Route::get('/merchant/v1/merchants', [MerchantController::class, 'getByOwnerId']);

    // Plaid verification
    Route::post('/merchant/v1/merchants/{merchantId}/identity-verification/create', [MerchantController::class, 'createIdentityVerification']);
    Route::get('/merchant/v1/merchants/{merchantId}/identity-verification/get-shareable-url', [MerchantController::class, 'getIdentityVerificationShareableUrl']);
    Route::post('/merchant/v1/merchants/{merchantId}/payout-verification/create', [MerchantController::class, 'createPayoutVerification']);
    Route::get('/merchant/v1/merchants/{merchantId}/payout-verification/get-shareable-url', [MerchantController::class, 'getPayoutVerificationShareableUrl']);

    // Locations
    Route::post('/merchant/v1/merchants/{merchantId}/locations', [MerchantController::class, 'addLocation']);
    Route::get('/merchant/v1/merchants/{merchantId}/locations', [MerchantController::class, 'getLocations']);
    Route::put('/merchant/v1/merchants/{merchantId}/locations/{locationId}', [MerchantController::class, 'updateLocation']);

    // Suggestions
    Route::get('/merchant/v1/merchants/{merchantId}/location/{locationId}/suggestions', [SuggestionController::class, 'getSuggestions']);

    // Customers
    Route::get('/merchant/v1/customers/search', [CustomerController::class, 'search']);
    Route::resource('/merchant/v1/customers', CustomerController::class)->only([
        'index', 'store', 'show', 'update'
    ]);

    // Items
    Route::post('/merchant/v1/items', [ItemController::class, 'create']);
    Route::put('/merchant/v1/items/{id}', [ItemController::class, 'edit']);
    Route::delete('/merchant/v1/items/{id}', [ItemController::class, 'delete']);
    Route::get('/merchant/v1/items/merchant/{merchantId}/location/{locationId}', [ItemController::class, 'getByMerchantAndLocation']);
    Route::post('/merchant/v1/items/{itemId}/variations', [ItemController::class, 'createVariation']);
    Route::put('/merchant/v1/items/{itemId}/variations/{variationId}', [ItemController::class, 'editVariation']);
    Route::delete('/merchant/v1/items/{itemId}/variations/{variationId}', [ItemController::class, 'deleteVariation']);

    Route::get('/merchant/v1/items/search', [ItemController::class, 'search']);

    // Transaction routes
    //Route::middleware(['throttle.transactions', 'check.merchant.verification'])->group(function () {
        Route::post('/merchant/v1/transactions/payment-intent', [TransactionController::class, 'createPaymentIntent']);
    //});
    
    Route::post('/merchant/v1/transactions/payment-intent/process', [TransactionController::class, 'processPaymentIntent']);
    Route::post('/merchant/v1/transactions/payment-intent/check', [TransactionController::class, 'checkPaymentIntent']);
    Route::post('/merchant/v1/transactions/payment-intent/finalize', [TransactionController::class, 'finalizePaymentIntent']);
    Route::get('/merchant/v1/transactions', [TransactionController::class, 'getAllTransactions']);

    // Reconciliation
    Route::post('/merchant/v1/reconciliation/dispatch-daily', [ReconciliationController::class, 'dispatchDailyJob']);

    // Route to get weekly metrics
    Route::get('/merchant/v1/transactions/metrics', [TransactionController::class, 'getWeeklyMetrics']);

    // Device routes
    Route::post('/merchant/v1/devices/connection-token', [DeviceController::class, 'createConnectionToken']);
    Route::get('/merchant/v1/devices', [DeviceController::class, 'index']);
    Route::post('/merchant/v1/devices', [DeviceController::class, 'store']);
    Route::delete('/merchant/v1/devices/{deviceId}', [DeviceController::class, 'destroy']);
    Route::put('/merchant/v1/devices/{deviceId}', [DeviceController::class, 'update']);
    Route::get('/merchant/v1/devices/{deviceId}', [DeviceController::class, 'getDevice']);

    // Ads routes
    Route::post('/merchant/v1/ads/integrations', [AdsController::class, 'createAdsIntegration']);
    Route::put('/merchant/v1/ads/integrations/{id}', [AdsController::class, 'updateAdsIntegration']);
    Route::get('/merchant/v1/ads/integrations/{merchantId}', [AdsController::class, 'getAdsIntegrationsByMerchantId']);

    // Google OAuth
    Route::get('/merchant/v1/ads/integrations/{merchantId}/oauth/url', [AdsController::class, 'getGoogleOauthUrl']);
    Route::post('/merchant/v1/ads/integrations/oauth/token', [AdsController::class, 'getGoogleOauthToken']);

    // Ad Campaigns
    Route::post('/merchant/v1/ads/campaigns', [AdsCampaignController::class, 'createAdCampaign']);
    Route::put('/merchant/v1/ads/campaigns/{id}', [AdsCampaignController::class, 'updateAdCampaign']);
    Route::get('/merchant/v1/ads/campaigns', [AdsCampaignController::class, 'getAdCampaigns']);
    Route::get('/merchant/v1/ads/campaigns/{id}', [AdsCampaignController::class, 'getAdCampaign']);
    Route::delete('/merchant/v1/ads/campaigns/{id}', [AdsCampaignController::class, 'deleteAdCampaign']);

    // Ad Campaigns
    Route::post('/merchant/v1/ads/campaigns/copy/generate', [AdsCampaignController::class, 'generateAdContent']);
});

