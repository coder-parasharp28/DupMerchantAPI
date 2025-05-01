<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'zipcode',
        'business_email',
        'tax_rate',
        'stripe_location_id',
        'stripe_customer_id',
        'min_avg_order_value',
        'max_avg_order_value',
        'entitlement_pie_payments_enabled',
        'entitlement_pie_ads_enabled'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function merchant_id()
    {
        return $this->belongsTo(Merchant::class);
    }
}