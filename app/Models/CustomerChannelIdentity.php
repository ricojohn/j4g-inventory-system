<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerChannelIdentity extends Model
{
    protected $fillable = ['branch_id', 'customer_id', 'provider', 'provider_account_id', 'external_user_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
