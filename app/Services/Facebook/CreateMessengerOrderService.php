<?php

namespace App\Services\Facebook;

use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerSource;
use App\Enums\ProductionStage;
use App\Models\Customer;
use App\Models\CustomerChannelIdentity;
use App\Models\CustomerOrder;
use App\Models\MessengerOrderDraft;
use App\Models\ProductColorSize;
use App\Models\User;
use App\Services\CustomerOrderService;
use App\Services\InventoryService;
use App\Services\OrderActivityLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateMessengerOrderService
{
    public const ACTION_NAME = 'Create Order';

    public function __construct(
        private InventoryService $inventoryService,
        private CustomerOrderService $customerOrderService,
        private OrderActivityLogger $activityLogger,
    ) {}

    public function execute(MessengerOrderDraft $draft, User $actor): CustomerOrder
    {
        return DB::transaction(function () use ($draft, $actor): CustomerOrder {
            $draft = MessengerOrderDraft::query()->lockForUpdate()->with('conversation.page.branch', 'items')->findOrFail($draft->id);

            if ($draft->customer_order_id) {
                return $draft->order()->firstOrFail();
            }

            $this->assertConfirmed($draft);

            if ($actor->branch_id !== null && $actor->branch_id !== $draft->branch_id) {
                throw new RuntimeException('You cannot create an order for another branch.');
            }

            $cells = ProductColorSize::query()
                ->whereIn('id', $draft->items->pluck('product_color_size_id'))
                ->with('color.product')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($draft->items as $item) {
                $cell = $cells->get($item->product_color_size_id);
                if (! $cell || $cell->color->product->branch_id !== $draft->branch_id || $cell->color->product->status !== 'active') {
                    throw new RuntimeException('One or more products are unavailable for this branch.');
                }
                if ($this->inventoryService->getAvailableStock($cell) < $item->quantity) {
                    throw new RuntimeException('Stock changed after confirmation. Review and confirm a new summary.');
                }
            }

            $customer = $this->resolveCustomer($draft);
            $order = CustomerOrder::query()->firstOrCreate(
                ['branch_id' => $draft->branch_id, 'external_source' => 'facebook_messenger', 'external_id' => (string) $draft->id],
                [
                    'customer_id' => $customer->id,
                    'customer_name' => $draft->customer_name,
                    'customer_contact' => $draft->psid,
                    'customer_source' => CustomerSource::Facebook,
                    'delivery_method' => $draft->fulfillment_method,
                    'delivery_address' => $draft->delivery_address,
                    'payment_method_preference' => $draft->payment_method_preference,
                    'order_total' => $draft->items->sum(fn ($item) => (float) $item->unit_price * $item->quantity),
                    'amount_paid' => 0,
                    'status' => CustomerOrderStatus::Pending,
                    'production_stage' => ProductionStage::Ready,
                    'created_by' => $actor->id,
                ],
            );

            if ($order->wasRecentlyCreated) {
                foreach ($draft->items as $item) {
                    $order->items()->create([
                        'product_color_size_id' => $item->product_color_size_id,
                        'quantity_ordered' => $item->quantity,
                        'quantity_reserved' => 0,
                        'unit_price' => $item->unit_price,
                        'status' => 'pending',
                    ]);
                }
                $this->customerOrderService->reserveOrder($order);
                $order->load('items');
                if ($order->items->contains(fn ($item) => $item->quantity_reserved !== $item->quantity_ordered)) {
                    throw new RuntimeException('Unable to reserve every confirmed item. No order was created.');
                }
                $this->activityLogger->log($order, 'facebook_order_created', self::ACTION_NAME, 'Created from an explicitly confirmed Messenger summary.', [
                    'draft_id' => $draft->id,
                    'summary_hash' => $draft->summary_hash,
                    'confirmation_actor_type' => $draft->confirmation_actor_type,
                ], $actor);
            }

            $draft->update(['customer_id' => $customer->id, 'customer_order_id' => $order->id, 'status' => 'converted']);
            $draft->conversation->update(['customer_id' => $customer->id, 'state' => 'order_created']);

            return $order->fresh('items');
        });
    }

    private function assertConfirmed(MessengerOrderDraft $draft): void
    {
        if ($draft->status !== 'confirmed' || ! $draft->confirmed_at || ! $draft->summary_hash) {
            throw new RuntimeException('Create Order requires explicit confirmation of a final summary.');
        }
        if (! $draft->confirmation_expires_at || $draft->confirmation_expires_at->isPast()) {
            throw new RuntimeException('The confirmation expired. Prepare and confirm a new summary.');
        }
        $expectedHash = hash('sha256', json_encode($draft->summary_data, JSON_THROW_ON_ERROR));
        if (! hash_equals($expectedHash, $draft->summary_hash) || ($draft->summary_data['version'] ?? null) !== $draft->version) {
            throw new RuntimeException('The confirmed summary no longer matches this draft.');
        }
    }

    private function resolveCustomer(MessengerOrderDraft $draft): Customer
    {
        $identity = CustomerChannelIdentity::query()->where([
            'provider' => 'facebook',
            'provider_account_id' => $draft->conversation->page->page_id,
            'external_user_id' => $draft->psid,
        ])->first();
        if ($identity) {
            return $identity->customer;
        }
        $customer = Customer::query()->create([
            'branch_id' => $draft->branch_id,
            'name' => $draft->customer_name,
            'handle' => $draft->psid,
            'source' => CustomerSource::Facebook,
        ]);
        CustomerChannelIdentity::query()->create([
            'branch_id' => $draft->branch_id,
            'customer_id' => $customer->id,
            'provider' => 'facebook',
            'provider_account_id' => $draft->conversation->page->page_id,
            'external_user_id' => $draft->psid,
        ]);

        return $customer;
    }
}
