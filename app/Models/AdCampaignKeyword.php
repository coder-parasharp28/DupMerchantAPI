<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdCampaignKeyword extends Model
{
    use HasFactory;

    protected $fillable = ['ad_campaign_id', 'keyword', 'keyword_type'];

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'ad_campaign_keywords';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function adCampaign()
    {
        return $this->belongsTo(AdCampaign::class);
    }
}
