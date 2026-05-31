<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
        // #region agent log
        @file_put_contents(
            base_path('debug-b44e8b.log'),
            json_encode(['sessionId' => 'b44e8b', 'hypothesisId' => 'H1', 'location' => 'StockUpdated.php:__construct', 'message' => 'StockUpdated event instantiated', 'data' => ['variant_id' => $payload['variant_id'] ?? null, 'movement_type' => $payload['movement_type'] ?? null], 'timestamp' => (int) (microtime(true) * 1000)]).PHP_EOL,
            FILE_APPEND
        );
        // #endregion
    }

    public function broadcastOn(): Channel
    {
        return new Channel('inventory');
    }

    public function broadcastAs(): string
    {
        return 'stock.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
