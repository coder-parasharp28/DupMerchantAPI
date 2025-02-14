<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Charge;
use Stripe\BalanceTransactions;
use Stripe\StripeClient;
use App\Models\ItemVariation;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Device;
use App\Jobs\FinalizeTransactionJob;


class TransactionController extends Controller
{   
    // Create a payment intent - manual payment
    public function createPaymentIntent(Request $request)
    {
        // Validate the request input
        $validatedData = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'tip_amount' => 'required|numeric|min:0',
            'transaction_items' => 'required|array',
            'transaction_items.*.id' => 'required|exists:items,id',
            'transaction_items.*.variation.id' => 'required|exists:item_variations,id',
            'transaction_items.*.quantity' => 'required|integer|min:1',
            'payment_type' => 'required|in:cash,card_present,manual,online,other',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $totalAmount = 0;
        $totalTaxAmount = 0;

        // Create a new transaction
        $transaction = Transaction::create([
            'merchant_id' => $validatedData['merchant_id'],
            'location_id' => $validatedData['location_id'],
            'customer_id' => $validatedData['customer_id'] ?? null, 
            'total_amount' => 0,
            'tax_amount' => 0,
            'tip_amount' => $validatedData['tip_amount'],
            'payment_type' => $validatedData['payment_type'],
            'status' => 'initiated',
        ]);

        // Calculate total amount and total tax amount
        foreach ($validatedData['transaction_items'] as $item) {
            $itemVariation = ItemVariation::find($item['variation']['id']);
            
            if (!$itemVariation) {
                Log::error("ItemVariation not found for ID: {$item['variation']['id']}");
                return response()->json(['error' => "Item variation not found for ID: {$item['variation']['id']}"], 404);
            }

            $itemPrice = $itemVariation->price;
            $itemTaxRate = $itemVariation->item->tax_rate; // Assuming tax rate is on the item

            $itemTotal = $itemPrice * $item['quantity'];
            $itemTaxAmount = round($itemTotal * ($itemTaxRate / 100), 2);

            $totalAmount += $itemTotal;
            $totalTaxAmount += $itemTaxAmount;

            // Create transaction item
            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'merchant_id' => $validatedData['merchant_id'],
                'location_id' => $validatedData['location_id'],
                'item_id' => $item['id'],
                'item_variation_id' => $item['variation']['id'],
                'item_name' => $itemVariation->item->name,
                'item_variation_name' => $itemVariation->name,
                'item_price' => $itemPrice,
                'quantity' => $item['quantity'],
                'item_tax_rate' => $itemTaxRate,
                'item_tax_amount' => $itemTaxAmount,
            ]);
        }

        $transaction->total_amount = $totalAmount;
        $transaction->tax_amount = $totalTaxAmount;
        $transaction->save();

        // Set your secret key. Remember to switch to your live secret key in production!
        Stripe::setApiKey(env('STRIPE_SECRET'));

        if ($validatedData['payment_type'] == 'card_present') {
            $paymentMethodTypes = ['card_present'];
        } else {
            $paymentMethodTypes = ['card'];
        }

        // Create a PaymentIntent with the order amount and currency
        $paymentIntent = PaymentIntent::create([
            'amount' => ($totalAmount + $totalTaxAmount + $validatedData['tip_amount']) * 100, // Amount in cents
            'payment_method_types' => $paymentMethodTypes,
            'capture_method' => 'automatic',
            'currency' => 'usd',
            'metadata' => [
                'transaction_id' => $transaction->id,
            ],
        ]);

        $transaction->payment_intent_id = $paymentIntent->id;
        $transaction->save();

        // Return the client secret to the frontend
        return response()->json(['client_secret' => $paymentIntent->client_secret, 'transaction_id' => $transaction->id, 'payment_intent_id' => $paymentIntent->id]);
    }

    // Process a payment intent - card present
    public function processPaymentIntent(Request $request) {

        $validatedData = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'transaction_id' => 'required|exists:transactions,id',
            'device_id' => 'required|string'
        ]);

        $transaction = Transaction::find($validatedData['transaction_id']);

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if ($transaction->status !== 'initiated') {
            return response()->json(['error' => 'Transaction is not valid.'], 400);
        }

        if ($transaction->payment_type !== 'card_present') {
            return response()->json(['error' => 'Transaction is not valid.'], 400);
        }

        $device = Device::where('id', $validatedData['device_id'])->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found.'], 404);
        }

        $stripe = new StripeClient(env('STRIPE_SECRET'));

        $response = $stripe->terminal->readers->processPaymentIntent(
            $device->stripe_reader_id,
            ['payment_intent' => $transaction->payment_intent_id]
        );

        $transaction->status = 'in_progress';
        $transaction->save();

        return response()->json($response);
    }

    // Confirm a payment intent on reader - card present
    public function checkPaymentIntent(Request $request) {

        $validatedData = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'location_id' => 'required|exists:locations,id',
            'transaction_id' => 'required|exists:transactions,id',
            'device_id' => 'required|string'
        ]);

        $transaction = Transaction::find($validatedData['transaction_id']);

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $device = Device::where('id', $validatedData['device_id'])->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found.'], 404);
        }

        $stripe = new StripeClient(env('STRIPE_SECRET'));

        $reader = $stripe->terminal->readers->retrieve($device->stripe_reader_id, []);
        
        if ($reader->action !== null && $reader->action->status !== null) {
            if ($reader->action->status === 'failed') {
                $transaction->status = 'cancelled';
                $transaction->save();
                return response()->json(['status' => 'cancelled']);
            }
            return response()->json(['status' => $reader->action->status]);
        }

        $transaction->status = 'cancelled';
        $transaction->save();
        return response()->json(['status' => 'cancelled']);
    }

    public function finalizePaymentIntent(Request $request)
    {
        $validatedData = $request->validate([
            'transaction_id' => 'required|exists:transactions,id'
        ]);

        $transaction = Transaction::find($validatedData['transaction_id']);

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $transaction->status = 'pending';
        $transaction->save();

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Retrieve the PaymentIntent
            $paymentIntent = PaymentIntent::retrieve($transaction->payment_intent_id);

            // Check if the payment was successful
            if ($paymentIntent->status !== 'succeeded') {
                $transaction->status = 'cancelled';
                $transaction->save();
                return response()->json(['error' => 'Payment not successful'], 200);
            }

            // Retrieve the charge associated with the payment intent
            $charge_id = $paymentIntent->latest_charge;
            $stripeRealFee = 0;
            $cardType = null;
            $cardLastFour = null;


            if (!empty($charge_id)) {
                $charge = Charge::retrieve($charge_id); // Assuming there's only one charge
                $balance_transaction = $charge->balance_transaction;
                if ($balance_transaction) {
                    $stripe = new StripeClient(env('STRIPE_SECRET'));
                    $balance_transaction_object = $stripe->balanceTransactions->retrieve($balance_transaction, []);
                    $stripeRealFee = $balance_transaction_object->fee / 100; // Convert from cents to dollars
                }

                // Extract card details
                if (isset($charge->payment_method_details->card_present)) {
                    $cardType = $charge->payment_method_details->card_present->brand;
                    $cardLastFour = $charge->payment_method_details->card_present->last4;
                } else if (isset($charge->payment_method_details->card)) {
                    $cardType = $charge->payment_method_details->card->brand;
                    $cardLastFour = $charge->payment_method_details->card->last4;
                }

            }

            // Calculate fees
            $totalAmountWithTip = $transaction->total_amount + $transaction->tax_amount + $transaction->tip_amount;
            if ($transaction->payment_type == 'card_present') {
                $stripeFee = (env('STRIPE_CARD_PRESENT_PROCESSING_RATE') / 100) * $totalAmountWithTip + env('STRIPE_CARD_PRESENT_PROCESSING_CENTS');
                $platformFee = (env('PLATFORM_CARD_PRESENT_PROCESSING_RATE') / 100) * $totalAmountWithTip + env('PLATFORM_CARD_PRESENT_PROCESSING_CENTS');
            } else {
                $stripeFee = (env('STRIPE_MANUAL_PROCESSING_RATE') / 100) * $totalAmountWithTip + env('STRIPE_MANUAL_PROCESSING_CENTS');
                $platformFee = (env('PLATFORM_MANUAL_PROCESSING_RATE') / 100) * $totalAmountWithTip + env('PLATFORM_MANUAL_PROCESSING_CENTS');
            }
            $netAmount = $totalAmountWithTip - $stripeFee - $platformFee;

            // Update the transaction
            $transaction->update([
                'status' => 'completed',
                'stripe_fee' => $stripeFee,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'stripe_real_fee' => $stripeRealFee,
                'card_type' => $cardType,
                'card_last_four' => $cardLastFour,
            ]);

            dispatch(new FinalizeTransactionJob($transaction));

            // Return success response
            return response()->json(['success' => true, 'transaction' => $transaction]);

        } catch (\Exception $e) {
            Log::error("Error finalizing payment intent: " . $e->getMessage());
            $transaction->status = 'cancelled';
            $transaction->save();
            return response()->json(['error' => $e->getMessage()], 500);
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
            ->where('location_id', $locationId)
            ->whereIn('status', ['completed', 'cancelled'])
            ->orderBy('created_at', 'desc');

        // Apply date range filter if provided
        if ($request->has('startDate')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('startDate'))->startOfDay());
        }

        if ($request->has('endDate')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('endDate'))->endOfDay());
        }

        // Retrieve the transactions
        $transactions = $query->get();

        // Format the amounts to 2 decimal points
        $transactions->transform(function ($transaction) {
            $transaction->total_amount = number_format($transaction->total_amount, 2, '.', '');
            $transaction->tax_amount = number_format($transaction->tax_amount, 2, '.', '');
            $transaction->stripe_fee = number_format($transaction->stripe_fee, 2, '.', '');
            $transaction->platform_fee = number_format($transaction->platform_fee, 2, '.', '');
            $transaction->net_amount = number_format($transaction->net_amount, 2, '.', '');
            $transaction->tip_amount = number_format($transaction->tip_amount, 2, '.', '');
            $transaction->stripe_real_fee = number_format($transaction->stripe_real_fee, 2, '.', '');
            return $transaction;
        });

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
            ->whereIn('status', ['completed'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Get previous week transactions
        $previousWeekTransactions = Transaction::where('merchant_id', $merchantId)
            ->where('location_id', $locationId)
            ->whereIn('status', ['completed'])
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->get();

        // Calculate metrics for the current week
        $netSales = $currentWeekTransactions->sum('net_amount');
        $grossSales = $currentWeekTransactions->sum('total_amount') + $currentWeekTransactions->sum('tax_amount');
        $transactionCount = $currentWeekTransactions->count();

        // Calculate metrics for the previous week
        $previousNetSales = $previousWeekTransactions->sum('net_amount');
        $previousGrossSales = $previousWeekTransactions->sum('total_amount') + $previousWeekTransactions->sum('tax_amount');
        $previousTransactionCount = $previousWeekTransactions->count();

        // Calculate percentage changes
        $netSalesChange = $previousNetSales > 0 ? (($netSales - $previousNetSales) / $previousNetSales) * 100 : 100;
        $grossSalesChange = $previousGrossSales > 0 ? (($grossSales - $previousGrossSales) / $previousGrossSales) * 100 : 100;
        $transactionCountChange = $previousTransactionCount > 0 ? (($transactionCount - $previousTransactionCount) / $previousTransactionCount) * 100 : 100;

        // Payment types breakdown
        $paymentTypesBreakdown = $currentWeekTransactions->groupBy('card_type')->map->count();

        // Return the metrics as a JSON response
        return response()->json([
            'net_sales' => round($netSales, 2),
            'net_sales_change_percentage' => round($netSalesChange, 2),
            'gross_sales' => round($grossSales, 2),
            'gross_sales_change_percentage' => round($grossSalesChange, 2),
            'transaction_count' => $transactionCount,
            'transaction_count_change_percentage' => round($transactionCountChange, 2),
            'payment_types_breakdown' => $paymentTypesBreakdown,
        ]);
    }
}
