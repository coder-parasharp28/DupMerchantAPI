<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Charge;
use App\Models\ItemVariation;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class TransactionController extends Controller
{
    public function createPaymentIntent(Request $request)
    {
        // Validate the request input
        $validatedData = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'tip_amount' => 'required|numeric|min:0',
            'transaction_items' => 'required|array',
            'transaction_items.*.item_id' => 'required|exists:items,id',
            'transaction_items.*.item_variation_id' => 'required|exists:item_variations,id',
            'transaction_items.*.quantity' => 'required|integer|min:1',
            'payment_type' => 'required|in:cash,card_present,manual,online,other',
            'customer_id' => 'nullable|exists:customers,id', // Optional customer ID
        ]);

        $totalAmount = 0;
        $totalTaxAmount = 0;

        // Calculate total amount and total tax amount
        foreach ($validatedData['transaction_items'] as $item) {
            $itemVariation = ItemVariation::find($item['item_variation_id']);
            
            if (!$itemVariation) {
                Log::error("ItemVariation not found for ID: {$item['item_variation_id']}");
                return response()->json(['error' => "Item variation not found for ID: {$item['item_variation_id']}"], 404);
            }

            $itemPrice = $itemVariation->price;
            $itemTaxRate = $itemVariation->item->tax_rate; // Assuming tax rate is on the item

            $itemTotal = $itemPrice * $item['quantity'];
            $itemTaxAmount = $itemTotal * ($itemTaxRate / 100);

            $totalAmount += $itemTotal;
            $totalTaxAmount += $itemTaxAmount;

            // Create transaction item
            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'merchant_id' => $validatedData['merchant_id'],
                'location_id' => $validatedData['location_id'],
                'item_id' => $item['item_id'],
                'item_variation_id' => $item['item_variation_id'],
                'item_name' => $itemVariation->item->name,
                'item_variation_name' => $itemVariation->name,
                'item_price' => $itemPrice,
                'quantity' => $item['quantity'],
                'item_tax_rate' => $itemTaxRate,
                'item_tax_amount' => $itemTaxAmount,
            ]);
        }

        // Create a new transaction
        $transaction = Transaction::create([
            'merchant_id' => $validatedData['merchant_id'],
            'location_id' => $validatedData['location_id'],
            'customer_id' => $validatedData['customer_id'] ?? null, 
            'total_amount' => $totalAmount,
            'tax_amount' => $totalTaxAmount,
            'tip_amount' => $validatedData['tip_amount'],
            'payment_type' => $validatedData['payment_type'],
            'status' => 'initiated',
        ]);

        // Set your secret key. Remember to switch to your live secret key in production!
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // Create a PaymentIntent with the order amount and currency
        $paymentIntent = PaymentIntent::create([
            'amount' => ($totalAmount + $validatedData['tip_amount']) * 100, // Amount in cents
            'currency' => 'usd',
            'metadata' => [
                'transaction_id' => $transaction->id,
            ],
        ]);

        // Return the client secret to the frontend
        return response()->json(['client_secret' => $paymentIntent->client_secret]);
    }

    public function finalizePaymentIntent($transactionId)
    {
        // Find the transaction
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if ($transaction->status !== 'initiated') {
            return response()->json(['error' => 'Transaction is not valid.'], 400);
        }

        $transaction->status = 'pending';
        $transaction->save();

        // Set your secret key. Remember to switch to your live secret key in production!
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Retrieve the PaymentIntent
            $paymentIntent = PaymentIntent::retrieve($transaction->payment_intent_id);

            // Check if the payment was successful
            if ($paymentIntent->status !== 'succeeded') {
                $transaction->status = 'cancelled';
                $transaction->save();
                return response()->json(['error' => 'Payment not successful'], 400);
            }

            // Retrieve the charge associated with the payment intent
            $charges = $paymentIntent->charges->data;
            $stripeRealFee = 0;
            $cardType = null;
            $cardLastFour = null;

            if (!empty($charges)) {
                $charge = $charges[0]; // Assuming there's only one charge
                $stripeRealFee = $charge->balance_transaction->fee / 100; // Convert from cents to dollars

                // Extract card details
                if (isset($charge->payment_method_details->card)) {
                    $cardType = $charge->payment_method_details->card->brand;
                    $cardLastFour = $charge->payment_method_details->card->last4;
                }
            }

            // Calculate fees
            $totalAmountWithTip = $transaction->total_amount + $transaction->tip_amount;
            $stripeFee = (env('STRIPE_MANUAL_PROCESSING_RATE') / 100) * $totalAmountWithTip + env('STRIPE_MANUAL_PROCESSING_CENTS');
            $platformFee = (env('PLATFORM_MANUAL_PROCESSING_RATE') / 100) * $totalAmountWithTip + env('PLATFORM_MANUAL_PROCESSING_CENTS');
            $netAmount = $totalAmountWithTip - $stripeFee - $platformFee;

            // Update the transaction
            $transaction->update([
                'status' => 'completed',
                'stripe_fee' => $stripeFee,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'stripe_real_fee' => $stripeRealFee, // Update with the real fee from Stripe
                'card_type' => $cardType, // Update card type
                'card_last_four' => $cardLastFour, // Update card last four digits
            ]);

            // Return success response
            return response()->json(['success' => true, 'transaction' => $transaction]);

        } catch (\Exception $e) {
            Log::error("Error finalizing payment intent: " . $e->getMessage());
            $transaction->status = 'cancelled';
            $transaction->save();
            return response()->json(['error' => 'An error occurred while finalizing the payment'], 500);
        }
    }

    public function getAllTransactions(Request $request)
    {
        // Validate the query parameters
        $validatedData = $request->validate([
            'merchantId' => 'required|exists:merchants,id',
            'locationId' => 'required|exists:locations,id',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
        ]);

        $merchantId = $validatedData['merchantId'];
        $locationId = $validatedData['locationId'];

        // Build the query
        $query = Transaction::where('merchant_id', $merchantId)
            ->where('location_id', $locationId);

        // Apply date range filter if provided
        if ($request->has('startDate')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('startDate'))->startOfDay());
        }

        if ($request->has('endDate')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('endDate'))->endOfDay());
        }

        // Retrieve the transactions
        $transactions = $query->get();

        // Return the transactions as a JSON response
        return response()->json(['transactions' => $transactions]);
    }

    public function getWeeklyMetrics(Request $request)
    {
        // Validate the request input
        $validatedData = $request->validate([
            'merchantId' => 'required|exists:merchants,id',
            'locationId' => 'required|exists:locations,id',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $merchantId = $validatedData['merchantId'];
        $locationId = $validatedData['locationId'];
        $startDate = Carbon::parse($validatedData['startDate'])->startOfDay();
        $endDate = Carbon::parse($validatedData['endDate'])->endOfDay();

        // Calculate the previous week date range
        $previousStartDate = $startDate->copy()->subWeek();
        $previousEndDate = $endDate->copy()->subWeek();

        // Get current week transactions
        $currentWeekTransactions = Transaction::where('merchant_id', $merchantId)
            ->where('location_id', $locationId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Get previous week transactions
        $previousWeekTransactions = Transaction::where('merchant_id', $merchantId)
            ->where('location_id', $locationId)
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->get();

        // Calculate metrics for the current week
        $netSales = $currentWeekTransactions->sum('net_amount');
        $grossSales = $currentWeekTransactions->sum('total_amount');
        $transactionCount = $currentWeekTransactions->count();

        // Calculate metrics for the previous week
        $previousNetSales = $previousWeekTransactions->sum('net_amount');
        $previousGrossSales = $previousWeekTransactions->sum('total_amount');
        $previousTransactionCount = $previousWeekTransactions->count();

        // Calculate percentage changes
        $netSalesChange = $previousNetSales > 0 ? (($netSales - $previousNetSales) / $previousNetSales) * 100 : null;
        $grossSalesChange = $previousGrossSales > 0 ? (($grossSales - $previousGrossSales) / $previousGrossSales) * 100 : null;
        $transactionCountChange = $previousTransactionCount > 0 ? (($transactionCount - $previousTransactionCount) / $previousTransactionCount) * 100 : null;

        // Payment types breakdown
        $paymentTypesBreakdown = $currentWeekTransactions->groupBy('payment_type')->map->count();

        // Return the metrics as a JSON response
        return response()->json([
            'net_sales' => $netSales,
            'net_sales_change_percentage' => $netSalesChange,
            'gross_sales' => $grossSales,
            'gross_sales_change_percentage' => $grossSalesChange,
            'transaction_count' => $transactionCount,
            'transaction_count_change_percentage' => $transactionCountChange,
            'payment_types_breakdown' => $paymentTypesBreakdown,
        ]);
    }
}
