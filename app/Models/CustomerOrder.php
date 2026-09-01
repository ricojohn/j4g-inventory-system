<?php

namespace App\Models;

use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerSource;
use App\Enums\OrderLayoutStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\ProductionStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CustomerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'customer_contact',
        'customer_source',
        'branch_id',
        'external_source',
        'external_id',
        'customer_notes',
        'due_date',
        'order_total',
        'amount_paid',
        'delivery_method',
        'delivery_address',
        'payment_method_preference',
        'receiver_name',
        'proof_or_tracking',
        'released_at',
        'release_override_reason',
        'release_override_by',
        'image_path',
        'status',
        'production_stage',
        'production_blocked',
        'supplier_order_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerOrderStatus::class,
            'customer_source' => CustomerSource::class,
            'production_stage' => ProductionStage::class,
            'production_blocked' => 'boolean',
            'due_date' => 'date',
            'order_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'released_at' => 'datetime',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(OrderLayout::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaseOverrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'release_override_by');
    }

    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }

    public function balanceDue(): float
    {
        return round((float) $this->order_total - (float) $this->amount_paid, 2);
    }

    public function paymentStatus(): OrderPaymentStatus
    {
        $total = (float) $this->order_total;
        $paid = (float) $this->amount_paid;

        if ($total <= 0 || $paid <= 0) {
            return $paid > 0 && $total <= 0
                ? OrderPaymentStatus::Paid
                : OrderPaymentStatus::Unpaid;
        }

        if ($paid >= $total) {
            return OrderPaymentStatus::Paid;
        }

        return OrderPaymentStatus::PartialDp;
    }

    public function paymentStatusLabel(): string
    {
        return $this->paymentStatus()->label();
    }

    public function latestLayout(): ?OrderLayout
    {
        if ($this->relationLoaded('layouts')) {
            return $this->layouts->sortByDesc('version')->first();
        }

        return $this->layouts()->orderByDesc('version')->first();
    }

    public function approvedLayout(): ?OrderLayout
    {
        if ($this->relationLoaded('layouts')) {
            return $this->layouts
                ->where('status', OrderLayoutStatus::Approved)
                ->sortByDesc('version')
                ->first();
        }

        return $this->layouts()
            ->where('status', OrderLayoutStatus::Approved)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Prefer the approved layout image, then the latest layout, then legacy image_path.
     */
    public function imageUrl(): ?string
    {
        $layout = $this->approvedLayout() ?? $this->latestLayout();

        if ($layout?->fileUrl()) {
            return $layout->fileUrl();
        }

        if (blank($this->image_path) || ! Storage::disk('public')->exists($this->image_path)) {
            return null;
        }

        return asset('storage/'.$this->image_path);
    }
}
