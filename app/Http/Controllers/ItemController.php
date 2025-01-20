<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariation;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Create a new item.
     */
    public function create(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'tax_rate' => 'nullable|numeric',
            'color' => 'nullable|string|max:255',
            'variations' => 'required|array',
            'variations.*.name' => 'required|string|max:255',
            'variations.*.unit' => 'required|string|max:255',
            'variations.*.price' => 'required|numeric',
            'variations.*.currency' => 'required|string|max:3',
            'variations.*.gstin' => 'nullable|string|max:255',
            'variations.*.SKU' => 'nullable|string|max:255',
            'variations.*.weight' => 'nullable|numeric',
        ]);

        $itemData = $request->only(['name', 'type', 'merchant_id', 'location_id', 'tax_rate', 'color']);
        $item = Item::create($itemData);

        foreach ($validatedData['variations'] as $variationData) {
            $item->variations()->create($variationData);
        }

        return response()->json($item->load('variations'), 201);
    }

    /**
     * Edit an existing item.
     */
    public function edit(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
            'tax_rate' => 'nullable|numeric',
            'color' => 'nullable|string|max:255',
            'variations' => 'sometimes|array',
            'variations.*.id' => 'nullable|exists:item_variations,id',
            'variations.*.name' => 'required_with:variations|string|max:255',
            'variations.*.unit' => 'required_with:variations|string|max:255',
            'variations.*.price' => 'required_with:variations|numeric',
            'variations.*.currency' => 'required_with:variations|string|max:3',
            'variations.*.gstin' => 'nullable|string|max:255',
            'variations.*.SKU' => 'nullable|string|max:255',
            'variations.*.weight' => 'nullable|numeric',
        ]);

        $item->update($validatedData);

        if (isset($validatedData['variations'])) {
            $existingVariationIds = $item->variations()->pluck('id')->toArray();
            $incomingVariationIds = array_filter(array_column($validatedData['variations'], 'id'));

            // Delete variations that are not in the incoming request
            $variationsToDelete = array_diff($existingVariationIds, $incomingVariationIds);
            $item->variations()->whereIn('id', $variationsToDelete)->delete();

            foreach ($validatedData['variations'] as $variationData) {
                if (isset($variationData['id'])) {
                    // Update existing variation
                    $variation = $item->variations()->findOrFail($variationData['id']);
                    $variation->update($variationData);
                } else {
                    // Create new variation
                    $item->variations()->create($variationData);
                }
            }
        }

        return response()->json($item->load('variations'), 200);
    }

    /**
     * Delete an item.
     */
    public function delete($id)
    {
        $item = Item::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Item deleted successfully'], 200);
    }

    /**
     * Get all items by merchant_id and location_id, including their variations.
     */
    public function getByMerchantAndLocation($merchantId, $locationId)
    {
        $items = Item::with('variations')
                     ->where('merchant_id', $merchantId)
                     ->where('location_id', $locationId)
                     ->get();

        return response()->json($items, 200);
    }

    /**
     * Create a new item variation.
     */
    public function createVariation(Request $request, $itemId)
    {
        $item = Item::findOrFail($itemId);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:255',
            'price' => 'required|numeric',
            'currency' => 'required|string|max:3',
            'gstin' => 'nullable|string|max:255',
            'SKU' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric',
        ]);

        $variation = $item->variations()->create($validatedData);

        return response()->json($variation, 201);
    }

    /**
     * Edit an existing item variation.
     */
    public function editVariation(Request $request, $itemId, $variationId)
    {
        $item = Item::findOrFail($itemId);
        $variation = $item->variations()->findOrFail($variationId);

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'unit' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric',
            'currency' => 'sometimes|required|string|max:3',
            'gstin' => 'nullable|string|max:255',
            'SKU' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric',
        ]);

        $variation->update($validatedData);

        return response()->json($variation, 200);
    }

    /**
     * Delete an item variation.
     */
    public function deleteVariation($variationId)
    {
        $item = Item::findOrFail($itemId);
        $variation = $item->variations()->findOrFail($variationId);
        $variation->delete();

        return response()->json(['message' => 'Item variation deleted successfully'], 200);
    }

    /**
     * Search items by name, filtered by merchant_id and location_id.
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

        $items = Item::search($merchantId . " " . $locationId . " " . $query)
            ->get();

        return response()->json($items, 200);
    }
}
