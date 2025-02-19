<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'category',
        'mcc_id',
        'brand_color',
        'logo_url',
        'icon_url',
        'ein',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'zipcode',
        'business_email',
        'verification_id',
        'verification_document_id',
        'verification_used',
        'verification_status',
        'verification_document_status',
        'verification_date'
    ];

    protected $hidden = [
        'verification_id',
        'verification_document_id',
        'verification_used'
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

    public function members()
    {
        return $this->hasMany(MerchantMember::class);
    }

    public function locations()
   {
       return $this->hasMany(Location::class);
   }
}
