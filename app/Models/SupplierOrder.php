<?php

namespace App\Models;

use App\Enums\SupplierOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'remarks',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierOrderStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierOrder $order): void {
            if (blank($order->po_number)) {
                $order->po_number = self::generatePoNumber();
            }
        });
    }

    public static function generatePoNumber(): string
    {
        $latest = self::query()
            ->where('po_number', 'like', 'PO-%')
            ->orderByRaw('CAST(SUBSTRING(po_number, 4) AS UNSIGNED) DESC')
            ->value('po_number');

        $sequence = $latest ? ((int) substr($latest, 3)) + 1 : 1;

        return 'PO-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierOrderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerOrder(): HasOne
    {
        return $this->hasOne(CustomerOrder::class, 'supplier_order_id');
    }
}
