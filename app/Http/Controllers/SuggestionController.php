<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Merchant;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function getSuggestions(Request $request, $merchantId, $locationId)
    {
        $suggestions = [];

        // Check if items have been created
        $itemsExist = Item::where('merchant_id', $merchantId)->where('location_id', $locationId)->exists();
        if (!$itemsExist) {
            $suggestions[] = [
                'icon' => 'PlusCircleIcon',
                'title' => "Let's Add Your First Item!",
                'description' => "It looks like you haven't added any items yet. Let's get started by creating an item!",
                'link' => 'items'
            ];
        }

        // Check if location tax_rate is set
        $location = Location::find($locationId);
        if ($location && $location->tax_rate <= 0) {
            $suggestions[] = [
                'icon' => 'CurrencyDollarIcon',
                'title' => "Set Up Your Tax Rate",
                'description' => "Your tax rate is currently not set. Let's update it to ensure everything is accurate!",
                'link' => 'locations'
            ];
        }

        // Check if a device has been created
        $devicesExist = Device::where('merchant_id', $merchantId)->where('location_id', $locationId)->exists();
        if (!$devicesExist) {
            $suggestions[] = [
                'icon' => 'DeviceMobileIcon',
                'title' => "Connect a Device",
                'description' => "No devices are linked yet. Let's add a device to start processing transactions!",
                'link' => 'devices'
            ];
        }

        // Check if a customer has been created
        $customersExist = Customer::where('merchant_id', $merchantId)->where('location_id', $locationId)->exists();
        if (!$customersExist) {
            $suggestions[] = [
                'icon' => 'UsersIcon',
                'title' => "Add Your First Customer!",
                'description' => "You haven't added any customers yet. Let's add one to start tracking your sales!",
                'link' => 'customers'
            ];
        }

        // Check merchant verification document status
        $merchant = Merchant::find($merchantId);
        if ($merchant && $merchant->verification_document_status === 'NOT_STARTED') {
            $suggestions[] = [
                'icon' => 'DocumentAddIcon',
                'title' => "Complete Verification",
                'description' => "Your account isn't verified for payouts yet. Let's complete the verification process!",
                'link' => 'balances'
            ];
        }

        return response()->json($suggestions);
    }
}
