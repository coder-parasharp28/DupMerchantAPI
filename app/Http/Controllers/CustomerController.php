<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers filtered by merchant and location.
     */
    public function index(Request $request)
    {
        $merchantId = $request->query('merchantId');
        $locationId = $request->query('locationId');

        if (!$merchantId || !$locationId) {
            return response()->json([]);
        }

        $query = Customer::query();

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $customers = $query->get();

        return response()->json($customers);
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('customers')->where(function ($query) use ($request) {
                    return $query->where('merchant_id', $request->merchant_id)
                                 ->where('location_id', $request->location_id);
                }),
            ],
            'phone' => 'nullable|string|max:20',
            'phone_country_code' => 'nullable|string|max:5',
            'birth_date' => 'nullable|date',
            'company' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
        ]);

        $customer = Customer::create($validatedData);

        return response()->json($customer, 201);
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validatedData = $request->validate([
            'merchant_id' => 'sometimes|required|exists:merchants,id',
            'location_id' => 'sometimes|required|exists:locations,id',
            'name' => 'sometimes|nullable|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('customers')->ignore($customer->id)->where(function ($query) use ($request) {
                    return $query->where('merchant_id', $request->merchant_id)
                                 ->where('location_id', $request->location_id);
                }),
            ],
            'phone' => 'nullable|string|max:20',
            'phone_country_code' => 'nullable|string|max:5',
            'birth_date' => 'nullable|date',
            'company' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
        ]);

        $customer->update($validatedData);

        return response()->json($customer);
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json(null, 204);
    }

    /**
     * Search for customers.
     */
    public function search(Request $request)
    {
        $query = $request->input('query');
        $merchantId = $request->query('merchantId');
        $locationId = $request->query('locationId');

        if (!$query) {
            return response()->json(['error' => 'Query parameter is required'], 400);
        }

        if (!$merchantId || !$locationId) {
            return response()->json([]);
        }

        // Use Laravel Scout for searching with explicit filters
        $customers = Customer::search($merchantId . " " . $locationId . " " . $query)
            ->get();

        return response()->json($customers);
    }
}