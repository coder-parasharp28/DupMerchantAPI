<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdsIntegration extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'merchant_id',
        'location_id',
        'type',
        'access_token',
        'expires_in',
        'refresh_token',
        'customer_id',
        'mcc_id',
        'status',
        'gbp_linking_status',
        'gbp_admin_invitation_status',
        'ads_account_creation_status',
        'ads_account_conversion_status',
        'ads_account_billing_status',
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

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
