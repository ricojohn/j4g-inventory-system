<?php

namespace App\Models;

use App\Enums\OrderLayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderLayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_order_id',
        'version',
        'title',
        'notes',
        'file_path',
        'status',
        'approved_at',
        'approved_by',
        'approval_channel',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderLayoutStatus::class,
            'approved_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fileUrl(): ?string
    {
        if (blank($this->file_path) || ! Storage::disk('public')->exists($this->file_path)) {
            return null;
        }

        return asset('storage/'.$this->file_path);
    }
}
