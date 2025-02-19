<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdCampaign extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'merchant_id',
        'location_id',
        'business_profile_id',
        'external_id',
        'name',
        'budget',
        'status',
        'processing_status',
        'payment_status',
        'stripe_checkout_session_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'type',
        'goal',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip_code',
        'radius',
        'headline1',
        'headline2',
        'headline3',
        'description1',
        'description2',
        'landing_page_url',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
