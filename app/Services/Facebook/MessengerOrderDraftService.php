<?php

namespace App\Services\Facebook;

use App\Models\MessengerOrderDraft;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MessengerOrderDraftService
{
    public function __construct(private InventoryService $inventoryService) {}

    public function prepareSummary(MessengerOrderDraft $draft): MessengerOrderDraft
    {
        return DB::transaction(function () use ($draft): MessengerOrderDraft {
            $draft = MessengerOrderDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $draft->load('items.cell.color.product', 'items.cell.color.color', 'items.cell.size.size');

            $this->validateRequiredFields($draft);
            $items = [];

            foreach ($draft->items as $item) {
                if ($item->cell->color->product->branch_id !== $draft->branch_id) {
                    throw new RuntimeException('A draft item belongs to another branch.');
                }

                $available = $this->inventoryService->getAvailableStock($item->cell->fresh());

                if ($available < $item->quantity) {
                    throw new RuntimeException("Insufficient stock for {$item->cell->color->product->name}.");
                }

                $snapshot = [
                    'cell_id' => $item->cell->id,
                    'product' => $item->cell->color->product->name,
                    'color' => $item->cell->color->color->name,
                    'size' => $item->cell->size->size->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                ];
                $items[] = $snapshot;
                $item->update(['product_snapshot' => $snapshot, 'available_stock_snapshot' => $available]);
            }

            $summaryData = [
                'version' => $draft->version,
                'customer_name' => $draft->customer_name,
                'psid' => $draft->psid,
                'fulfillment_method' => $draft->fulfillment_method,
                'delivery_address' => $draft->delivery_address,
                'payment_method_preference' => $draft->payment_method_preference,
                'items' => $items,
            ];
            $summaryText = $this->formatSummary($summaryData);
            $summaryHash = hash('sha256', json_encode($summaryData, JSON_THROW_ON_ERROR));

            $draft->update([
                'status' => 'awaiting_confirmation',
                'summary_data' => $summaryData,
                'summary_text' => $summaryText,
                'summary_hash' => $summaryHash,
                'summarized_at' => now(),
                'confirmed_at' => null,
                'confirmation_actor_type' => null,
                'confirmed_by_user_id' => null,
                'confirmation_message_id' => null,
                'confirmation_expires_at' => now()->addMinutes(30),
            ]);

            return $draft->fresh('items.cell');
        });
    }

    public function invalidateSummary(MessengerOrderDraft $draft): void
    {
        $draft->increment('version');
        $draft->update([
            'status' => 'collecting',
            'summary_data' => null,
            'summary_text' => null,
            'summary_hash' => null,
            'summarized_at' => null,
            'confirmed_at' => null,
            'confirmation_actor_type' => null,
            'confirmed_by_user_id' => null,
            'confirmation_message_id' => null,
            'confirmation_expires_at' => null,
        ]);
    }

    private function validateRequiredFields(MessengerOrderDraft $draft): void
    {
        if (blank($draft->customer_name) || blank($draft->fulfillment_method) || blank($draft->payment_method_preference)) {
            throw new RuntimeException('Name, fulfillment method, and payment preference are required.');
        }

        if (! in_array($draft->fulfillment_method, ['delivery', 'pickup'], true)) {
            throw new RuntimeException('Fulfillment method must be delivery or pickup.');
        }

        if ($draft->fulfillment_method === 'delivery' && blank($draft->delivery_address)) {
            throw new RuntimeException('Delivery address is required for delivery.');
        }

        if ($draft->items->isEmpty()) {
            throw new RuntimeException('At least one order item is required.');
        }
    }

    /** @param array<string, mixed> $summary */
    private function formatSummary(array $summary): string
    {
        $lines = ['Final order summary', 'Customer: '.$summary['customer_name']];
        foreach ($summary['items'] as $item) {
            $lines[] = sprintf('- %s / %s / %s x %d', $item['product'], $item['color'], $item['size'], $item['quantity']);
        }
        $lines[] = 'Method: '.ucfirst($summary['fulfillment_method']);
        if ($summary['delivery_address']) {
            $lines[] = 'Address: '.$summary['delivery_address'];
        }
        $lines[] = 'Payment preference: '.$summary['payment_method_preference'];
        $lines[] = 'Please explicitly confirm this exact summary before Create Order is used.';

        return implode("\n", $lines);
    }
}
