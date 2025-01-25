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
    Route::middleware(['throttle.transactions', 'check.merchant.verification'])->group(function () {
        Route::post('/merchant/v1/transactions/payment-intent', [TransactionController::class, 'createPaymentIntent']);
    });
    Route::post('/merchant/v1/transactions/finalize/{transactionId}', [TransactionController::class, 'finalizePaymentIntent']);
    Route::get('/merchant/v1/transactions', [TransactionController::class, 'getAllTransactions']);

    // Route to get weekly metrics
    Route::get('/merchant/v1/transactions/metrics', [TransactionController::class, 'getWeeklyMetrics']);

    // Device routes
    Route::post('/merchant/v1/devices/connection-token', [DeviceController::class, 'createConnectionToken']);
    Route::get('/merchant/v1/devices', [DeviceController::class, 'index']);
    Route::post('/merchant/v1/devices', [DeviceController::class, 'store']);
    Route::delete('/merchant/v1/devices/{deviceId}', [DeviceController::class, 'destroy']);
    Route::put('/merchant/v1/devices/{deviceId}', [DeviceController::class, 'update']);

});

