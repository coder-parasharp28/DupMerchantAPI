<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransactionItem extends Model
{
    use HasFactory;

    // Define the table associated with the model
    protected $table = 'transaction_items';

    // Specify the fields that are mass assignable
    protected $fillable = [
        'transaction_id',
        'merchant_id',
        'location_id',
        'item_id',
        'item_variation_id',
        'item_name',
        'item_variation_name',
        'item_price',
        'quantity',
        'item_tax_rate',
        'item_tax_amount',
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
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
