<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'status', 'automation_user_id'];

    public function automationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'automation_user_id');
    }

    public function facebookPages(): HasMany
    {
        return $this->hasMany(FacebookPage::class);
    }
}
