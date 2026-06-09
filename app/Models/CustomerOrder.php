<?php

namespace App\Models;

use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_contact',
        'customer_source',
        'customer_notes',
        'status',
        'supplier_order_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerOrderStatus::class,
            'customer_source' => CustomerSource::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerOrder $order): void {
            if (blank($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $latest = self::query()
            ->where('order_number', 'like', 'CO-%')
            ->orderByRaw('CAST(SUBSTRING(order_number, 4) AS UNSIGNED) DESC')
            ->value('order_number');

        $sequence = $latest ? ((int) substr($latest, 3)) + 1 : 1;

        return 'CO-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }
}
