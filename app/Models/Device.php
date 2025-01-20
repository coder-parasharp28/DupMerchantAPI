<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'merchant_id',
        'location_id',
        'stripe_location_id',
        'stripe_reader_id',
        'type',
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

    // Define relationships if needed
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
