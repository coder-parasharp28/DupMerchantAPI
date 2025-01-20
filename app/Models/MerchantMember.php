<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantMember extends Model
{
    use HasFactory;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'merchant_id',
        'user_id',
        'role', // e.g., 'owner', 'manager', etc.
        'is_activated'
    ];

    public function merchant_id()
    {
        return $this->belongsTo(Merchant::class);
    }
}
