<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\AccountingEntry;
use App\Models\MerchantBalance;
use App\Models\StripeBalance;
use App\Models\PlatformFeesBalance;
use Stripe\StripeClient;
use Stripe\PaymentIntent;
use Stripe\Charge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $transaction;

    // Constructor to accept transaction ID or transaction object
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function handle()
    {   

        /*
        This job is responsible for finalizing a transaction by:
            We use double entry accounting to record the transaction.
            We update the merchant balance, stripe balance, and platform fees balance.
            We set the reconciliation status to 'complete'
        */

        // Start a database transaction
        DB::beginTransaction(); 

        try {
            $transaction = $this->transaction;

            if ($transaction->conciliation_status === 'complete') {
                return;
            }

            // Calculate fees and update transaction amounts
            $totalAmountWithTip = $transaction->total_amount + $transaction->tax_amount + $transaction->tip_amount;

            // Net Amount
            $netAmount = $transaction->net_amount;

            // Stripe Fee
            $stripeFee = $transaction->stripe_fee;

            // Platform Fee
            $platformFee = $transaction->platform_fee;

            // Fetch account IDs for all necessary accounts to optimize the process
            $revenueAccountId = Account::where('account_name', 'Revenue')->first()->id;
            $taxPayableAccountId = Account::where('account_name', 'Tax Payable')->first()->id;
            $merchantPayableAccountId = Account::where('account_name', 'Merchant Payable')->first()->id;
            $stripeFeesAccountId = Account::where('account_name', 'Stripe Fees')->first()->id;
            $platformFeesAccountId = Account::where('account_name', 'Platform Fees')->first()->id;
            $cashBankAccountId = Account::where('account_name', 'Cash/Bank')->first()->id;

            // -- Create accounting entries --

            // Revenue entry (Total amount + Tip)
            AccountingEntry::create([
                'transaction_id' => $transaction->id,
                'merchant_id' => $transaction->merchant_id,
                'location_id' => $transaction->location_id,
                'account_id' => $revenueAccountId,
                'debit' => 0,
                'credit' => $totalAmountWithTip,  // Credit to Revenue
                'entry_date' => now(),
            ]);

            // Tax Payable entry (Tax collected)
            AccountingEntry::create([
                'transaction_id' => $transaction->id,
                'merchant_id' => $transaction->merchant_id,
                'location_id' => $transaction->location_id,
                'account_id' => $taxPayableAccountId,
                'debit' => 0,
                'credit' => $transaction->tax_amount,  // Credit to Tax Payable
                'entry_date' => now(),
            ]);

            // Merchant Payable entry (Net amount owed to the merchant)
            AccountingEntry::create([
                'transaction_id' => $transaction->id,
                'merchant_id' => $transaction->merchant_id,
                'location_id' => $transaction->location_id,
                'account_id' => $merchantPayableAccountId,
                'debit' => $netAmount,  // Debit to Merchant Payable (liability decreases)
                'credit' => 0,
                'entry_date' => now(),
            ]);

            // Stripe Fees entry (Stripe fee paid by platform)
            AccountingEntry::create([
                'transaction_id' => $transaction->id,
                'merchant_id' => $transaction->merchant_id,
                'location_id' => $transaction->location_id,
                'account_id' => $stripeFeesAccountId,
                'debit' => $stripeFee,  // Debit to Stripe Fees (expense increases)
                'credit' => 0,
                'entry_date' => now(),
            ]);

            // Platform Fees entry (Platform fee paid by platform)
            AccountingEntry::create([
                'transaction_id' => $transaction->id,
                'merchant_id' => $transaction->merchant_id,
                'location_id' => $transaction->location_id,
                'account_id' => $platformFeesAccountId,
                'debit' => $platformFee,  // Debit to Platform Fees (expense increases)
                'credit' => 0,
                'entry_date' => now(),
            ]);

            // Cash/Bank entry (Total cash received from the customer)
            AccountingEntry::create([
                'transaction_id' => $transaction->id,
                'merchant_id' => $transaction->merchant_id,
                'location_id' => $transaction->location_id,
                'account_id' => $cashBankAccountId,
                'debit' => 0,
                'credit' => $totalAmountWithTip,  // Credit to Cash/Bank (asset increases)
                'entry_date' => now(),
            ]);

            // Update MerchantBalance (with location_id)
            $merchantBalance = MerchantBalance::firstOrNew([
                'merchant_id' => $transaction->merchant_id,
                'location_id' => $transaction->location_id,  // Include location_id
            ]);
            $merchantBalance->current_balance += $netAmount;
            $merchantBalance->last_transaction_id = $transaction->id; // Update last_transaction_id
            $merchantBalance->save();

            // Update StripeBalance (for Stripe fees)
            $stripeBalance = StripeBalance::firstOrNew([]);
            $stripeBalance->current_balance += $stripeFee;
            $stripeBalance->last_transaction_id = $transaction->id; // Update last_transaction_id
            $stripeBalance->save();

            // Update PlatformFeesBalance (for platform fees)
            $platformFeesBalance = PlatformFeesBalance::firstOrNew([]);
            $platformFeesBalance->current_balance += $platformFee;
            $platformFeesBalance->last_transaction_id = $transaction->id; // Update last_transaction_id
            $platformFeesBalance->save();

            // Commit the transaction after all operations are successful
            DB::commit();

            // Set the reconciliation status to 'complete' now that everything is successful
            $transaction->conciliation_status = 'complete';
            $transaction->save();

        } catch (\Exception $e) {
            // Rollback the transaction if anything goes wrong
            DB::rollBack();
            Log::error("Error finalizing payment intent: " . $e->getMessage());
            $transaction->conciliation_status = 'failed';
            $transaction->save();
        }
    }

}

