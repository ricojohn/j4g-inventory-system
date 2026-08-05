<?php

namespace App\Http\Controllers;

use App\Enums\CustomerOrderStatus;
use App\Enums\ProductionStage;
use App\Models\CustomerOrder;
use App\Services\OrderActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(
        private OrderActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('view production'), 403);

        return view('production.index', [
            'boardConfig' => [
                'dataUrl' => route('production.board.data'),
                'advanceUrlBase' => url('/production'),
                'canManage' => $request->user()->can('manage production'),
            ],
        ]);
    }

    public function boardData(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view production'), 403);

        $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $canManage = $request->user()->can('manage production');

        $baseQuery = CustomerOrder::query()
            ->with(['items'])
            ->withCount('items')
            ->whereNotIn('status', [
                CustomerOrderStatus::Cancelled->value,
            ])
            ->whereIn('status', [
                CustomerOrderStatus::Pending->value,
                CustomerOrderStatus::Reserved->value,
                CustomerOrderStatus::PartiallyReserved->value,
                CustomerOrderStatus::Fulfilled->value,
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            });

        $columns = collect(ProductionStage::boardColumns())->map(function (ProductionStage $stage) use ($baseQuery, $canManage) {
            $stageQuery = (clone $baseQuery)->where('production_stage', $stage->value);
            $count = (clone $stageQuery)->count();

            $ordersQuery = (clone $stageQuery)->latest();

            if ($stage->isTerminal()) {
                $ordersQuery->limit(20);
            }

            $orders = $ordersQuery->get()->map(
                fn (CustomerOrder $order) => $this->formatBoardCard($order, $canManage)
            );

            return [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $count,
                'orders' => $orders,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'columns' => $columns,
        ]);
    }

    public function advance(Request $request, CustomerOrder $order): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->can('manage production'), 403);

        if ($order->status === CustomerOrderStatus::Cancelled) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cancelled orders cannot advance in production.',
                ], 422);
            }

            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Cancelled orders cannot advance in production.');
        }

        if ($order->production_blocked) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order is blocked in production.',
                ], 422);
            }

            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Order is blocked in production.');
        }

        $current = $order->production_stage ?? ProductionStage::Ready;
        $next = $current->next();

        if ($next === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order is already at the final production stage.',
                ], 422);
            }

            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Order is already at the final production stage.');
        }

        $order->update(['production_stage' => $next]);

        $this->activityLogger->log(
            $order,
            'production_advanced',
            'Production stage advanced',
            sprintf('%s → %s', $current->label(), $next->label()),
            [
                'from' => $current->value,
                'to' => $next->value,
            ],
            $request->user(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'production_stage' => $next->value,
                'production_stage_label' => $next->label(),
            ]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', "Production advanced to {$next->label()}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBoardCard(CustomerOrder $order, bool $canManage): array
    {
        $stage = $order->production_stage ?? ProductionStage::Ready;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'production_stage' => $stage->value,
            'production_stage_label' => $stage->label(),
            'production_blocked' => $order->production_blocked,
            'due_date' => $order->due_date?->format('M j'),
            'item_count' => (int) ($order->items_count ?? $order->items->count()),
            'created_at' => $order->created_at->format('M d, Y H:i'),
            'show_url' => route('orders.show', $order),
            'can_advance' => $canManage && ! $order->production_blocked && $stage->next() !== null,
        ];
    }
}
