<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class MerchantBalance extends Model
{
    use HasFactory;

    // Define the table associated with the model
    protected $table = 'merchant_balances';

    // Specify the fields that are mass assignable
    protected $fillable = [
        'merchant_id',
        'location_id',
        'current_balance',
        'last_transaction_id',
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

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function lastTransaction()
    {
        return $this->belongsTo(Transaction::class, 'last_transaction_id');
    }
}
