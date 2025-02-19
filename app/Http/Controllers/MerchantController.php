<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantMember;
use App\Models\MerchantPayout;
use App\Models\Location;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class MerchantController extends Controller
{       
    public function respondOkay()
    {
        return response()->json(["message" => "okay"]);
    }

    // Create Payout on Plaid
    public function createPayoutVerification(Request $request, $merchantId)
    {
        $merchant = Merchant::findOrFail($merchantId);

        // Check if verification_id is already set
        if ($merchant->verification_document_id != null) {
            return $this->getPayoutVerificationShareableUrl($request, $merchantId);
        }

        $response = \Http::post(env("PLAID_API_ENDPOINT") . "/identity_verification/create?idempotent=true", [
            'client_user_id' => $request->header('X-USER-ID').'-'.time(),
            'client_id' => env("PLAID_CLIENT_ID"),
            'secret' => env("PLAID_SECRET"),
            'is_shareable' => true,
            'template_id' => env("PLAID_PAYOUT_TEMPLATE_ID"),
            'gave_consent' => false
        ]);

        if ($response->successful()) {
            $verificationDocumentId = $response->json('id');
            $shareableUrl = $response->json('shareable_url');
            
            $merchant->verification_document_id = $verificationDocumentId;
            $merchant->verification_document_status = 'IN_PROGRESS';
            $merchant->save();

            return response()->json([
                'message' => 'Payout verification created successfully',
                'shareable_url' => $shareableUrl,
                'status' => $merchant->verification_document_status
            ]);
        } else {
            return response()->json(['error' => 'Failed to create payout verification'], $response->status());
        }
    }

    // Get Payout Verification Shareable URL
    public function getPayoutVerificationShareableUrl(Request $request, $merchantId)
    {
        $merchant = Merchant::findOrFail($merchantId);

        $response = \Http::post(env("PLAID_API_ENDPOINT") . "/identity_verification/get", [
            'identity_verification_id' => $merchant->verification_document_id,
            'client_id' => env("PLAID_CLIENT_ID"),
            'secret' => env("PLAID_SECRET"),
            'is_shareable' => true,
            'template_id' => env("PLAID_PAYOUT_TEMPLATE_ID")
        ]);

        if ($response->successful()) {
            $shareableUrl = $response->json('shareable_url');
            $status = $response->json('status');

            switch ($status) {
                case 'active':
                    $merchant_status = 'IN_PROGRESS';
                    break;
                case 'failed':
                    $merchant_status = 'FAILED';
                    break;
                case 'success':
                    $merchant_status = 'SUCCESS';
                    break;
                case 'expired':
                    $merchant_status = 'EXPIRED';
                    break;
                case 'pending_review':
                    $merchant_status = 'PENDING';  
                    break;
                default:
                    $merchant_status = 'IN_PROGRESS';       
            }

            $merchant->verification_document_status = $merchant_status;
            $merchant->save();

            return response()->json([
                'message' => 'Payout verification shareable URL retrieved successfully',
                'shareable_url' => $shareableUrl,
                'status' => $merchant_status
            ]);
        } else {
            return response()->json(['error' => 'Failed to retrieve payout verification shareable URL'], $response->status());
        }
    }

    // Create Identification on Plaid
    public function createIdentityVerification(Request $request, $merchantId)
    {
        $merchant = Merchant::findOrFail($merchantId);

        // Check if verification_id is already set
        if ($merchant->verification_id) {
            return $this->getIdentityVerificationShareableUrl($request, $merchantId);
        }

        $response = \Http::post(env("PLAID_API_ENDPOINT") . "/identity_verification/create?idempotent=true", [
            'client_user_id' => $request->header('X-USER-ID').'-'.time(),
            'client_id' => env("PLAID_CLIENT_ID"),
            'secret' => env("PLAID_SECRET"),
            'is_shareable' => true,
            'template_id' => env("PLAID_TEMPLATE_ID"),
            'gave_consent' => false
        ]);

        if ($response->successful()) {
            $verificationId = $response->json('id');
            $shareableUrl = $response->json('shareable_url');
            
            $merchant->verification_id = $verificationId;
            $merchant->verification_status = 'IN_PROGRESS';
            $merchant->save();

            return response()->json([
                'message' => 'Identity verification created successfully',
                'shareable_url' => $shareableUrl,
                'status' => $merchant->verification_status
            ]);
        } else {
            return response()->json(['error' => 'Failed to create identity verification'], $response->status());
        }
    }

    // Get Verification
    public function getIdentityVerificationShareableUrl(Request $request, $merchantId)
    {
        $merchant = Merchant::findOrFail($merchantId);

        $response = \Http::post(env("PLAID_API_ENDPOINT") . "/identity_verification/get", [
            'identity_verification_id' => $merchant->verification_id,
            'client_id' => env("PLAID_CLIENT_ID"),
            'secret' => env("PLAID_SECRET"),
            'is_shareable' => true,
            'template_id' => env("PLAID_TEMPLATE_ID")
        ]);

        if ($response->successful()) {
            $shareableUrl = $response->json('shareable_url');
            $status = $response->json('status');

            switch ($status) {
                case 'active':
                    $merchant_status = 'IN_PROGRESS';
                    break;
                case 'failed':
                    $merchant_status = 'FAILED';
                    break;
                case 'success':
                    $merchant_status = 'SUCCESS';
                    break;
                case 'expired':
                    $merchant_status = 'EXPIRED';
                    break;
                case 'pending_review':
                    $merchant_status = 'PENDING';  
                    break;
                default:
                    $merchant_status = 'IN_PROGRESS';       
            }

            $merchant->verification_status = $merchant_status;
            $merchant->save();

            return response()->json([
                'message' => 'Shareable URL retrieved successfully',
                'shareable_url' => $shareableUrl,
                'status' => $merchant_status
            ]);
        } else {
            return response()->json(['error' => 'Failed to retrieve shareable URL'], $response->status());
        }
    }

    // Create a new merchant
    public function store(Request $request)
    {
        $ownerId = $request->header('X-USER-ID');

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'category' => 'nullable|string',
            'mcc_id' => 'nullable|string',
            'brand_color' => 'nullable|string|max:7',
            'logo_url' => 'nullable|url',
            'icon_url' => 'nullable|url',
            'ein' => 'nullable|string|unique:merchants,ein',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'business_email' => 'required|email',
        ]);

        $merchant = Merchant::create($validatedData);

        // By pass verification for now. TODO: Decide how we want to verify merchants for ads.
        $merchant->verification_status = 'SUCCESS';
        $merchant->save();

        // Add the owner to merchant_members
        MerchantMember::create([
            'merchant_id' => $merchant->id,
            'user_id' => $ownerId,
            'role' => MerchantMember::ROLE_OWNER,
            'is_activated' => true
        ]);

        // Create a Stripe location
        $stripe = new StripeClient(env('STRIPE_SECRET'));
        $stripeLocation = $stripe->terminal->locations->create([
            'display_name' => $merchant->name.'-'.$merchant->city,
            'address' => [
                'line1' => $merchant->address_line_1,
                'city' => $merchant->city,
                'state' => $merchant->state,
                'country' => $merchant->country,
                'postal_code' => $merchant->zipcode,
            ],
        ]);

        // Create a Stripe customer
        $stripeCustomer = $stripe->customers->create([
            'email' => $merchant->business_email,
            'name' => $merchant->name.'-'.$merchant->city,
        ]);

        // Create a location for the merchant using the merchant's address
        $location = $merchant->locations()->create([
            'name' => $merchant->name, 
            'address_line_1' => $merchant->address_line_1,
            'address_line_2' => $merchant->address_line_2,
            'city' => $merchant->city,
            'state' => $merchant->state,
            'country' => $merchant->country,
            'zipcode' => $merchant->zipcode,
            'stripe_location_id' => $stripeLocation->id,
            'stripe_customer_id' => $stripeCustomer->id,
            'business_email' => $merchant->business_email,
        ]);

        return response()->json($merchant, 201);
    }

    // Retrieve a specific merchant
    public function show(Request $request, $id)
    {
        $userId = $request->header('X-USER-ID');

        $merchant = Merchant::whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with('locations')
        ->findOrFail($id);

        return response()->json($merchant);
    }

    // Update a specific merchant
    public function update(Request $request, $id)
    {
        $userId = $request->header('X-USER-ID');

        $merchant = Merchant::whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->whereIn('role', [MerchantMember::ROLE_OWNER, MerchantMember::ROLE_MANAGER]);
        })->findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
            'category' => 'nullable|string',
            'mcc_id' => 'nullable|string',
            'brand_color' => 'nullable|string|max:7',
            'logo_url' => 'nullable|url',
            'icon_url' => 'nullable|url',
            'ein' => 'sometimes|string|unique:merchants,ein,' . $merchant->id,
            'address_line_1' => 'nullable|string|max:255', 
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'business_email' => 'nullable|email',
        ]);

        $merchant->update($validatedData);

        return response()->json($merchant);
    }

    // Delete a specific merchant
    public function destroy($id)
    {
        $userId = request()->header('X-USER-ID');

        $merchant = Merchant::whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->where('role', MerchantMember::ROLE_OWNER);
        })->findOrFail($id);

        $merchant->delete();

        return response()->json(['message' => 'Merchant deleted successfully']);
    }

    // Retrieve merchants by owner ID
    public function getByOwnerId(Request $request)
    {
        $ownerId = $request->header('X-USER-ID');

        $merchants = Merchant::whereHas('members', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        })
        ->with('locations')
        ->get();

        return response()->json($merchants);
    }


    // Add Location to Merchant
    public function addLocation(Request $request, $merchantId)
    {
        $merchant = Merchant::findOrFail($merchantId);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'zipcode' => 'required|string|max:20',
            'tax_rate' => 'nullable|numeric',
            'business_email' => 'required|email',
            'min_avg_order_value' => 'nullable|numeric|min:0',
            'max_avg_order_value' => 'nullable|numeric|min:0',
        ]);

        // Create a Stripe location
        $stripe = new StripeClient(env('STRIPE_SECRET'));
        $stripeLocation = $stripe->terminal->locations->create([
            'display_name' => $validatedData['name'].'-'.$validatedData['city'],
            'address' => [
                'line1' => $validatedData['address_line_1'],
                'city' => $validatedData['city'],
                'state' => $validatedData['state'],
                'country' => $validatedData['country'],
                'postal_code' => $validatedData['zipcode'],
            ],
        ]);

        // Create a Stripe customer
        $stripeCustomer = $stripe->customers->create([
            'email' => $validatedData['business_email'],
            'name' => $validatedData['name'].'-'.$validatedData['city'],
        ]);

        $location = $merchant->locations()->create($validatedData);
        $location->stripe_location_id = $stripeLocation->id;
        $location->stripe_customer_id = $stripeCustomer->id;
        $location->save();

        return response()->json($location, 201);
    }
    

    // Update a specific location
    public function updateLocation(Request $request, $merchantId, $locationId)
    {
        $location = Location::findOrFail($locationId);
        // Check if the location belongs to the merchant
        if ($location->merchant_id !== $merchantId) {
            return response()->json(['error' => 'Location does not belong to this merchant'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address_line_1' => 'sometimes|required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state' => 'sometimes|required|string|max:255',
            'country' => 'sometimes|required|string|max:255',
            'zipcode' => 'sometimes|required|string|max:20',
            'tax_rate' => 'nullable|numeric',
            'min_avg_order_value' => 'nullable|numeric|min:0',
            'max_avg_order_value' => 'nullable|numeric|min:0',
            'business_email' => 'nullable|email',
        ]);

        // Update Stripe customer if business_email is provided
        if ($validatedData['business_email']) {
            $stripe = new StripeClient(env('STRIPE_SECRET'));
            $stripe->customers->update($location->stripe_customer_id, [
                'email' => $validatedData['business_email'],
            ]);
        }

        $location->update($validatedData);
        return response()->json($location);
    }

    // Get all locations
    public function getLocations($merchantId)
    {
        $merchant = Merchant::findOrFail($merchantId);
        $locations = $merchant->locations;

        return response()->json($locations);
    }

    // Create a payout for a merchant
    public function createPayout(Request $request, $merchantId)
    {
        $merchant = Merchant::findOrFail($merchantId);

        $validatedData = $request->validate([
            'nickname' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'routing_number' => 'required|string|max:255',
            'bank' => 'nullable|string|max:255',
        ]);

        $merchantPayout = MerchantPayout::create([
            'merchant_id' => $merchant->id,
            'nickname' => $validatedData['nickname'] ?? '',
            'account_number' => $validatedData['account_number'] ?? '',
            'routing_number' => $validatedData['routing_number'] ?? '',
            'bank' => $validatedData['bank'] ?? '',
        ]);

        return response()->json($merchantPayout, 201);
    }

    // Get a payout for a merchant
    public function getPayout($merchantId)
    {
        $merchantPayout = MerchantPayout::where('merchant_id', $merchantId)->first();
        return response()->json($merchantPayout);
    }

    // Update a payout for a merchant
    public function updatePayout(Request $request, $merchantId)
    {
        $merchantPayout = MerchantPayout::where('merchant_id', $merchantId)->first();

        $validatedData = $request->validate([
            'nickname' => 'sometimes|required|string|max:255',
            'account_number' => 'sometimes|required|string|max:255',
            'routing_number' => 'sometimes|required|string|max:255',
            'bank' => 'nullable|string|max:255',
        ]);

        $merchantPayout->update($validatedData);
        return response()->json($merchantPayout);
    }
}
