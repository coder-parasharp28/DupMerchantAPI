<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdsGoogleBusinessProfile extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'merchant_id',
        'location_id',
        'ads_integration_id',
        'google_business_profile_id',
        'name',
        'google_business_profile_object'
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

    public function adsIntegration()
    {
        return $this->belongsTo(AdsIntegration::class);
    }
}
