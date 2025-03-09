<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'merchant_id',
        'location_id',
        'customer_id',
        'invoice_date',
        'due_date',
        'payer_memo',
        'internal_note',
        'surcharging_enabled',
        'surcharging_rate',
        'status',
        'total_amount',
        'tax_amount',
        'surcharging_amount',
        'transaction_id',
        'template_id',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
