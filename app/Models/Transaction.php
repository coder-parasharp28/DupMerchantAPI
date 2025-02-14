<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class Transaction extends Model
{
    use HasFactory;

    // Define the table associated with the model
    protected $table = 'transactions';

    // Specify the fields that are mass assignable
    protected $fillable = [
        'merchant_id',
        'location_id',
        'customer_id',
        'payment_type',
        'card_type',
        'card_last_four',
        'payment_intent_id',
        'total_amount',
        'tax_amount',
        'stripe_fee',
        'stripe_real_fee',
        'platform_fee',
        'tip_amount',
        'net_amount',
        'status',
        'conciliation_status'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Boot function from Laravel.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically generate a UUID for the id when creating a new model
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // Define relationships
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
