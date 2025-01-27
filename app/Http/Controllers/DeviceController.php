<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Location;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    // Create a connection token for a specific device
    public function createConnectionToken(Request $request)
    {
        $stripe = new StripeClient(env('STRIPE_SECRET'));
        $token = $stripe->terminal->connectionTokens->create();
        return response()->json($token);
    }

    // List devices for a specific merchant and location
    public function index(Request $request)
    {
        $merchantId = $request->query('merchantId');
        $locationId = $request->query('locationId');

        $devices = Device::where('merchant_id', $merchantId)
                         ->where('location_id', $locationId)
                         ->get();

        return response()->json($devices);
    }

    // Create a new device
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'registration_code' => 'required|string|max:255',
            'merchant_id' => 'required|uuid',
            'location_id' => 'required|uuid',
        ]);

        $location = Location::findOrFail($validatedData['location_id']);

        $stripe = new StripeClient(env('STRIPE_SECRET'));

        try {
            $reader = $stripe->terminal->readers->create([
                'registration_code' => $validatedData['registration_code'],
                'label' => $validatedData['name'],
                'location' => $location->stripe_location_id,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Handle Stripe API error
            return response()->json(['error' => 'Failed to create Stripe reader: ' . $e->getMessage()], 400);
        }

        $device = Device::create(array_merge($validatedData, [
            'stripe_location_id' => $location->stripe_location_id,
            'stripe_reader_id' => $reader->id,
            'name' => $validatedData['name'],
            'type' => $validatedData['type'],
            'merchant_id' => $validatedData['merchant_id'],
            'location_id' => $validatedData['location_id'],
        ]));

        return response()->json($device, 201);
    }

    // Delete a specific device
    public function destroy($deviceId)
    {
        $device = Device::findOrFail($deviceId);

        $device->delete();

        return response()->json(['message' => 'Device deleted successfully']);
    }

    // Update a specific device's name and type
    public function update(Request $request, $deviceId)
    {
        $merchantId = $request->input('merchant_id');
        $locationId = $request->input('location_id');

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
        ]);

        $device = Device::where('merchant_id', $merchantId)
                        ->where('location_id', $locationId)
                        ->findOrFail($deviceId);

        $device->update($validatedData);

        return response()->json($device);
    }

    // Get a specific device's reader info
    public function getDevice($deviceId)
    {
        $device = Device::findOrFail($deviceId);

        $stripe = new StripeClient(env('STRIPE_SECRET'));$stripe = new \Stripe\StripeClient('sk_test_51Q8ABJRpHrzPp5HL0JVdcaCNz7mrbzKiF1hSjVNXuTvA09vEsrUZyENx3C3RGgDglnld1IVShR9RtD8nJbjt7u4G00j4Jtre5i');

        $reader = $stripe->terminal->readers->retrieve($device->stripe_reader_id, []);

        $device->readerInfo = $reader;
        return response()->json($device);
    }
}
