<?php

namespace App\Models;

use App\Enums\AiOrderDraftStatus;
use App\Enums\CustomerSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiOrderDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_message',
        'parsed_json',
        'matched_json',
        'confidence_score',
        'status',
        'customer_name',
        'customer_contact',
        'customer_source',
        'customer_notes',
        'customer_order_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'parsed_json' => 'array',
            'matched_json' => 'array',
            'confidence_score' => 'float',
            'status' => AiOrderDraftStatus::class,
            'customer_source' => CustomerSource::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function messagePreview(int $length = 80): string
    {
        return str($this->raw_message)->squish()->limit($length)->toString();
    }
}
