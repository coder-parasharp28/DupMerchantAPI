<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountSeeder extends Seeder
{
    public function run()
    {
        // Insert default accounts into the accounts table
        DB::table('accounts')->insert([
            [
                'id' => Str::uuid(), // Generating UUID for the primary key
                'account_name' => 'Revenue',
                'account_type' => 'Revenue',
                'account_description' => 'Income from sales or services.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'account_name' => 'Tax Payable',
                'account_type' => 'Liability',
                'account_description' => 'Taxes collected from customers, owed to the government.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'account_name' => 'Merchant Payable',
                'account_type' => 'Liability',
                'account_description' => 'Amount owed to the merchant after deducting fees.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'account_name' => 'Stripe Fees',
                'account_type' => 'Expense',
                'account_description' => 'Fees charged by Stripe for payment processing.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'account_name' => 'Platform Fees',
                'account_type' => 'Expense',
                'account_description' => 'Fees charged by the platform for providing services.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'account_name' => 'Cash/Bank',
                'account_type' => 'Asset',
                'account_description' => 'Funds available in cash or bank accounts.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'account_name' => 'Astra Fees',
                'account_type' => 'Expense',
                'account_description' => 'Fees charged by Astra for transactions or services.',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}


