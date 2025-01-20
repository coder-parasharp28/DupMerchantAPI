<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Illuminate\Support\Str;

class Item extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'type',
        'merchant_id',
        'location_id',
        'tax_rate',
        'color',
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

    public function variations()
    {
        return $this->hasMany(ItemVariation::class);
    }

    public function toSearchableArray()
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'merchant_id' => $this->merchant_id,
            'location_id' => $this->location_id
        ];
    }
}
