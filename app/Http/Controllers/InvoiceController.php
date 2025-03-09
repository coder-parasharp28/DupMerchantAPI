<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ItemVariation;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Create a new invoice.
     */
    public function createInvoice(Request $request)
    {
        $validatedData = $request->validate([
            'merchant_id' => 'required|uuid',
            'location_id' => 'required|uuid',
            'customer_id' => 'required|uuid',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'payer_memo' => 'nullable|string',
            'internal_note' => 'nullable|string',
            'surcharging_enabled' => 'boolean',
            'surcharging_rate' => 'nullable|numeric',
            'template_id' => 'nullable|string',
            'invoice_items' => 'nullable|array',
            'invoice_items.*.item_id' => 'required|uuid',
            'invoice_items.*.item_variation_id' => 'required|uuid',
            'invoice_items.*.quantity' => 'required|integer',
        ]);

        $invoice = Invoice::create($validatedData);

        $totalAmount = 0;
        $totalTaxAmount = 0;

        foreach ($validatedData['invoice_items'] as $item) {
            // find item & item variation
            $itemData = Item::find($item['item_id']);
            $itemVariationData = ItemVariation::find($item['item_variation_id']);

            if (!$itemData || !$itemVariationData) {
                continue;
            }

            $itemPrice = $itemVariationData->price;
            $itemTaxRate = $itemVariationData->item->tax_rate;

            $itemTotal = $itemPrice * $item['quantity'];
            $itemTaxAmount = round($itemTotal * ($itemTaxRate / 100), 2);

            $totalAmount += $itemTotal;
            $totalTaxAmount += $itemTaxAmount;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'merchant_id' => $validatedData['merchant_id'],
                'location_id' => $validatedData['location_id'],
                'item_id' => $item['item_id'],
                'item_variation_id' => $item['item_variation_id'],
                'item_name' => $itemData->name,
                'item_variation_name' => $itemVariationData->name,
                'item_price' => $itemPrice,
                'quantity' => $item['quantity'],
                'item_tax_amount' => $itemTaxAmount,
            ]);
        }

        $invoice->total_amount = $totalAmount;
        $invoice->tax_amount = $totalTaxAmount;

        if ($invoice->surcharging_enabled) {
            $invoice->surcharging_amount = $totalAmount * ($invoice->surcharging_rate / 100);
            $invoice->total_amount += $invoice->surcharging_amount;
        }

        $invoice->save();

        return response()->json($invoice, 201);
    }

    /**
     * Edit an existing invoice.
     */
    public function updateInvoice(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'pending') {
            return response()->json(['message' => 'Invoice is not pending'], 400);
        }

        $validatedData = $request->validate([
            'merchant_id' => 'uuid',
            'location_id' => 'uuid',
            'customer_id' => 'uuid',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'payer_memo' => 'nullable|string',
            'internal_note' => 'nullable|string',
            'surcharging_enabled' => 'boolean',
            'surcharging_rate' => 'nullable|numeric',
            'template_id' => 'nullable|string',
            'invoice_items' => 'nullable|array',
            'invoice_items.*.item_id' => 'required|uuid',
            'invoice_items.*.item_variation_id' => 'required|uuid',
            'invoice_items.*.quantity' => 'required|integer',
        ]);

        $invoice->update($validatedData);

        $totalAmount = 0;
        $totalTaxAmount = 0;

        // delete existing invoice items
        $invoice->invoiceItems()->delete();

        foreach ($validatedData['invoice_items'] as $item) {
            // find item & item variation   
            $itemData = Item::find($item['item_id']);
            $itemVariationData = ItemVariation::find($item['item_variation_id']);

            if (!$itemData || !$itemVariationData) {
                continue;
            }

            $itemPrice = $itemVariationData->price;
            $itemTaxRate = $itemVariationData->item->tax_rate;

            $itemTotal = $itemPrice * $item['quantity'];
            $itemTaxAmount = round($itemTotal * ($itemTaxRate / 100), 2);

            $totalAmount += $itemTotal;
            $totalTaxAmount += $itemTaxAmount;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'merchant_id' => $invoice->merchant_id,
                'location_id' => $invoice->location_id,
                'item_id' => $item['item_id'],
                'item_variation_id' => $item['item_variation_id'],
                'item_name' => $itemData->name,
                'item_variation_name' => $itemVariationData->name,
                'item_price' => $itemPrice,
                'quantity' => $item['quantity'],
                'item_tax_amount' => $itemTaxAmount,
            ]);
        }

        $invoice->total_amount = $totalAmount;
        $invoice->tax_amount = $totalTaxAmount;

        if ($invoice->surcharging_enabled) {
            $invoice->surcharging_amount = $totalAmount * ($invoice->surcharging_rate / 100);
            $invoice->total_amount += $invoice->surcharging_amount;
        }
        
        
        return response()->json($invoice);
    }

    /**
     * Delete an invoice.
     */
    public function deleteInvoice($id)
    {
        
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'pending') {
            return response()->json(['message' => 'Invoice is not pending'], 400);
        }

        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted successfully']);
    }

    /**
     * Get a specific invoice.
     */
    public function getInvoice($id)
    {
        $invoice = Invoice::findOrFail($id);

        $invoiceItems = InvoiceItem::where('invoice_id', $id)->get();

        $invoice->invoice_items = $invoiceItems;

        return response()->json($invoice);
    }

    /**
     * Get all invoices by merchantId and locationId.
     */
    public function getAllInvoicesByMerchantAndLocation($merchantId, $locationId)
    {
        $invoices = Invoice::where('merchant_id', $merchantId)
            ->where('location_id', $locationId)
            ->with('invoiceItems')
            ->get();

        return response()->json($invoices);
    }
}
