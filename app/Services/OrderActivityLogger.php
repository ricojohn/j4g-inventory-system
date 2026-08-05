<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\OrderActivity;
use App\Models\User;

class OrderActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        CustomerOrder $order,
        string $type,
        string $title,
        ?string $body = null,
        ?array $meta = null,
        ?User $actor = null,
    ): OrderActivity {
        return $order->activities()->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'meta' => $meta,
            'actor_id' => $actor?->id,
            'created_at' => now(),
        ]);
    }
}
