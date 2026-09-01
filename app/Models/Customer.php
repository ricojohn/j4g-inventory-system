<?php

namespace App\Models;

use App\Enums\CustomerSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'handle',
        'contact',
        'notes',
        'source',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => CustomerSource::class,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }
}
