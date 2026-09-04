<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FacebookTag extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'name', 'slug', 'color'];

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(FacebookConversation::class, 'facebook_conversation_tag')->withTimestamps();
    }
}
