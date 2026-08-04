<?php

namespace App\Http\Controllers;

use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerSource;
use App\Enums\OrderLayoutStatus;
use App\Enums\ProductionStage;
use App\Enums\SupplierOrderStatus;
use App\Http\Requests\ApproveOrderLayoutRequest;
use App\Http\Requests\ReleaseOrderDeliveryRequest;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Http\Requests\StoreOrderLayoutRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateOrderDeliveryRequest;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\OrderLayout;
use App\Models\Product;
use App\Models\ProductColorSize;
use App\Services\CustomerOrderService;
use App\Services\OrderActivityLogger;
use App\Support\OrderOpsPresenter;
use App\Support\PaginatedJsonResponse;
use App\Support\ProductCellLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class CustomerOrderController extends Controller
{
    public function __construct(
        private CustomerOrderService $customerOrderService,
        private ProductCellLookup $productCellLookup,
        private OrderActivityLogger $activityLogger,
        private OrderOpsPresenter $orderOpsPresenter,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('view orders'), 403);

        return view('orders.index');
    }

    public function board(Request $request): View
    {
        abort_unless($request->user()?->can('view orders'), 403);

        return view('orders.board', [
            'boardConfig' => [
                'dataUrl' => route('orders.board.data'),
                'fulfillUrlBase' => url('/orders'),
                'cancelUrlBase' => url('/orders'),
                'canFulfill' => $request->user()->can('fulfill orders'),
                'canCancel' => $request->user()->can('cancel orders'),
                'canCreateSupplierOrders' => $request->user()->can('create supplier orders'),
                'supplierOrderCreateUrl' => route('supplier-orders.create'),
            ],
        ]);
    }

    public function boardData(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view orders'), 403);

        $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $request->user();
        $canFulfill = $user->can('fulfill orders');
        $canCancel = $user->can('cancel orders');

        $baseQuery = CustomerOrder::query()
            ->with(['supplierOrder', 'items'])
            ->withCount('items')
            ->withSum('items', 'quantity_ordered')
            ->withSum('items', 'quantity_reserved')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('source'), fn ($query) => $query->where('customer_source', $request->string('source')));

        $columns = collect(CustomerOrderStatus::boardColumns())->map(function (CustomerOrderStatus $status) use ($baseQuery, $canFulfill, $canCancel) {
            $statusQuery = (clone $baseQuery)->where('status', $status->value);
            $count = (clone $statusQuery)->count();

            $ordersQuery = (clone $statusQuery)->latest();

            if ($status->isTerminal()) {
                $ordersQuery->limit(20);
            }

            $orders = $ordersQuery->get()->map(
                fn (CustomerOrder $order) => $this->formatBoardCard($order, $canFulfill, $canCancel)
            );

            return [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $count,
                'orders' => $orders,
            ];
        })->values();

        $attentionOrders = (clone $baseQuery)
            ->whereNotIn('status', [
                CustomerOrderStatus::Fulfilled->value,
                CustomerOrderStatus::Cancelled->value,
            ])
            ->latest()
            ->get()
            ->map(fn (CustomerOrder $order) => $this->formatBoardCard($order, $canFulfill, $canCancel))
            ->filter(fn (array $card) => $card['has_shortage'] || $card['has_draft_po'])
            ->take(10)
            ->values();

        return response()->json([
            'success' => true,
            'attention' => $attentionOrders,
            'columns' => $columns,
        ]);
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view orders'), 403);

        $request->validate([
            'status' => ['sometimes', 'nullable', 'string'],
            'source' => ['sometimes', 'nullable', 'string'],
        ]);

        $orders = CustomerOrder::query()
            ->with(['creator', 'supplierOrder', 'items'])
            ->withCount('items')
            ->withSum('items', 'quantity_ordered')
            ->withSum('items', 'quantity_reserved')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($query) => $query->where('customer_source', $request->string('source')))
            ->latest()
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $orders->through(fn (CustomerOrder $order) => $this->formatOrderListRow($order))
        );
    }

    public function productCells(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('create orders'), 403);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        return response()->json([
            'success' => true,
            'cells' => $this->productCellLookup->cellsForProduct($product),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create orders'), 403);

        return view('orders.create', [
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'handle', 'contact', 'source', 'notes']),
            'customerSources' => CustomerSource::cases(),
        ]);
    }

    public function store(StoreCustomerOrderRequest $request): RedirectResponse
    {
        $result = DB::transaction(function () use ($request) {
            $cells = ProductColorSize::query()
                ->whereIn('id', collect($request->input('items'))->pluck('product_color_size_id'))
                ->with('color.product')
                ->get();

            $this->productCellLookup->ensureActiveProducts($cells);

            $orderTotal = collect($request->input('items'))->sum(
                fn (array $item) => ((float) ($item['unit_price'] ?? 0)) * (int) $item['quantity_ordered']
            );

            $order = CustomerOrder::query()->create([
                'customer_id' => $request->input('customer_id'),
                'customer_name' => $request->string('customer_name'),
                'customer_contact' => $request->input('customer_contact'),
                'customer_source' => $request->input('customer_source'),
                'customer_notes' => $request->input('customer_notes'),
                'due_date' => $request->input('due_date'),
                'order_total' => round($orderTotal, 2),
                'amount_paid' => 0,
                'status' => CustomerOrderStatus::Pending,
                'production_stage' => ProductionStage::Ready,
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->input('items') as $item) {
                $order->items()->create([
                    'product_color_size_id' => $item['product_color_size_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'quantity_reserved' => 0,
                    'unit_price' => round((float) ($item['unit_price'] ?? 0), 2),
                    'status' => 'pending',
                ]);
            }

            if ($request->hasFile('order_image')) {
                $layout = $order->layouts()->create([
                    'version' => 1,
                    'title' => 'Initial layout',
                    'file_path' => $request->file('order_image')->store('order-layouts', 'public'),
                    'status' => OrderLayoutStatus::Draft,
                ]);

                $this->activityLogger->log(
                    $order,
                    'layout_uploaded',
                    'Layout version uploaded',
                    sprintf('v%s — %s', $layout->version, $layout->title),
                    [
                        'layout_id' => $layout->id,
                        'version' => $layout->version,
                    ],
                    $request->user(),
                );
            }

            $this->customerOrderService->reserveOrder($order);

            $this->activityLogger->log(
                $order->fresh(),
                'order_created',
                'Order created',
                sprintf('Order %s created for %s', $order->order_number, $order->customer_name),
                [
                    'order_total' => (float) $order->order_total,
                    'item_count' => count($request->input('items')),
                ],
                $request->user(),
            );

            $shortage = $this->customerOrderService->getShortageItems($order->fresh());

            return [
                'order' => $order->fresh(),
                'has_shortage' => $shortage->isNotEmpty(),
            ];
        });

        $order = $result['order'];

        if ($result['has_shortage'] && $request->user()->can('create supplier orders')) {
            return redirect()
                ->route('supplier-orders.create', ['from_order_id' => $order->id])
                ->with('shortage_notice', 'Stock is short for this order. Review and create a purchase order.');
        }

        $redirect = redirect()->route('orders.show', $order);

        if ($result['has_shortage']) {
            $redirect->with('shortage_notice', 'Stock is short for this order. A purchase order is required to fulfill remaining quantities.');
        }

        return $redirect;
    }

    public function show(Request $request, CustomerOrder $order): View
    {
        abort_unless($request->user()?->can('view orders'), 403);

        $order->load([
            'creator',
            'customer',
            'items.cell.color.color',
            'items.cell.color.product',
            'items.cell.size.size',
            'supplierOrder.supplier',
            'payments.recorder',
            'layouts.approver',
            'activities.actor',
            'releaseOverrideBy',
        ]);

        $shortageQty = $this->orderOpsPresenter->shortageQuantity($order);
        $nextAction = $this->orderOpsPresenter->nextAction($order);
        $readiness = $this->orderOpsPresenter->readinessChecklist($order);
        $activeTab = $request->string('tab', 'overview')->toString();

        return view('orders.show', [
            'order' => $order,
            'shortageQty' => $shortageQty,
            'nextAction' => $nextAction,
            'readiness' => $readiness,
            'activeTab' => $activeTab,
            'paymentStatus' => $order->paymentStatus(),
            'balanceDue' => $order->balanceDue(),
            'canManageFinance' => $request->user()->can('manage finance'),
            'canFulfill' => $request->user()->can('fulfill orders'),
            'canManageProduction' => $request->user()->can('manage production'),
        ]);
    }

    public function storeLayout(StoreOrderLayoutRequest $request, CustomerOrder $order): RedirectResponse
    {
        $layout = DB::transaction(function () use ($request, $order) {
            $nextVersion = ((int) $order->layouts()->max('version')) + 1;

            $layout = $order->layouts()->create([
                'version' => $nextVersion,
                'title' => $request->string('title'),
                'notes' => $request->input('notes'),
                'file_path' => $request->file('layout_file')->store('order-layouts', 'public'),
                'status' => OrderLayoutStatus::Draft,
            ]);

            $this->activityLogger->log(
                $order,
                'layout_uploaded',
                'Layout version uploaded',
                sprintf('v%s — %s', $layout->version, $layout->title),
                [
                    'layout_id' => $layout->id,
                    'version' => $layout->version,
                ],
                $request->user(),
            );

            return $layout;
        });

        return redirect()
            ->route('orders.show', ['order' => $order, 'tab' => 'layouts'])
            ->with('success', "Layout v{$layout->version} uploaded.");
    }

    public function approveLayout(ApproveOrderLayoutRequest $request, CustomerOrder $order, OrderLayout $layout): RedirectResponse
    {
        abort_unless($layout->customer_order_id === $order->id, 404);

        if ($layout->status !== OrderLayoutStatus::Draft) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Only draft layouts can be approved.');
        }

        DB::transaction(function () use ($request, $order, $layout): void {
            $order->layouts()
                ->where('status', OrderLayoutStatus::Approved)
                ->update(['status' => OrderLayoutStatus::Superseded]);

            $layout->update([
                'status' => OrderLayoutStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'approval_channel' => $request->input('approval_channel'),
            ]);

            $this->activityLogger->log(
                $order,
                'layout_approved',
                'Layout approved',
                sprintf('v%s approved%s', $layout->version, $layout->approval_channel ? " via {$layout->approval_channel}" : ''),
                [
                    'layout_id' => $layout->id,
                    'version' => $layout->version,
                    'channel' => $layout->approval_channel,
                ],
                $request->user(),
            );
        });

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Layout approved.');
    }

    public function updateDelivery(UpdateOrderDeliveryRequest $request, CustomerOrder $order): RedirectResponse
    {
        $order->update($request->validated());

        $this->activityLogger->log(
            $order,
            'delivery_updated',
            'Delivery details updated',
            null,
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Delivery details updated.');
    }

    public function release(ReleaseOrderDeliveryRequest $request, CustomerOrder $order): RedirectResponse
    {
        if ($order->released_at !== null) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Order is already released.');
        }

        $balanceDue = $order->balanceDue();
        $overrideReason = $request->input('release_override_reason');

        if ($balanceDue > 0 && blank($overrideReason)) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Cannot release while balance is due unless an override reason is provided.');
        }

        $order->update([
            'released_at' => now(),
            'release_override_reason' => $balanceDue > 0 ? $overrideReason : null,
            'release_override_by' => $balanceDue > 0 ? $request->user()->id : null,
        ]);

        $this->activityLogger->log(
            $order,
            'order_released',
            'Order released',
            $balanceDue > 0
                ? "Released with balance override: {$overrideReason}"
                : 'Released with zero balance due',
            [
                'balance_due' => $balanceDue,
                'override' => $balanceDue > 0,
            ],
            $request->user(),
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order released.');
    }

    public function fulfill(Request $request, CustomerOrder $order): JsonResponse
    {
        abort_unless($request->user()?->can('fulfill orders'), 403);

        if (! in_array($order->status, [CustomerOrderStatus::Reserved, CustomerOrderStatus::PartiallyReserved], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only reserved or partially reserved orders can be fulfilled.',
            ], 422);
        }

        return $this->runOrderAction($order, fn () => $this->customerOrderService->fulfillOrder($order));
    }

    public function cancel(Request $request, CustomerOrder $order): JsonResponse
    {
        abort_unless($request->user()?->can('cancel orders'), 403);

        if (in_array($order->status, [CustomerOrderStatus::Fulfilled, CustomerOrderStatus::Cancelled], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Fulfilled or cancelled orders cannot be cancelled.',
            ], 422);
        }

        return $this->runOrderAction($order, fn () => $this->customerOrderService->cancelOrder($order));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderListRow(CustomerOrder $order): array
    {
        $source = $order->customer_source;
        $nextAction = $this->orderOpsPresenter->nextAction($order);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_contact' => $order->customer_contact ?? '—',
            'customer_source' => $source?->value,
            'customer_source_label' => $source?->label(),
            'customer_source_icon' => $source?->icon(),
            'customer_source_badge_color' => $source?->badgeColor(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'item_count' => $order->items_count,
            'total_qty_ordered' => (int) ($order->items_sum_quantity_ordered ?? 0),
            'po_number' => $order->supplierOrder?->po_number,
            'supplier_order_id' => $order->supplier_order_id,
            'po_status' => $order->supplierOrder?->status->value,
            'created_by_name' => $order->creator?->name ?? 'System',
            'created_at' => $order->created_at->format('M d, Y H:i'),
            'due_date' => $order->due_date?->format('M j'),
            'payment_status' => $order->paymentStatusLabel(),
            'next_action_label' => $nextAction['label'],
            'next_action_tag' => $nextAction['tag'],
            'show_url' => route('orders.show', $order),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBoardCard(CustomerOrder $order, bool $canFulfill, bool $canCancel): array
    {
        $source = $order->customer_source;
        $shortageQty = $this->shortageQuantity($order);
        $hasShortage = $shortageQty > 0;
        $hasDraftPo = $order->supplierOrder?->status === SupplierOrderStatus::Draft;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_contact' => $order->customer_contact ?? '—',
            'customer_source' => $source?->value,
            'customer_source_label' => $source?->label(),
            'customer_source_icon' => $source?->icon(),
            'customer_source_badge_color' => $source?->badgeColor(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'item_count' => (int) ($order->items_count ?? $order->items->count()),
            'total_qty_ordered' => (int) ($order->items_sum_quantity_ordered ?? $order->items->sum('quantity_ordered')),
            'has_shortage' => $hasShortage,
            'shortage_qty' => $shortageQty,
            'has_draft_po' => $hasDraftPo,
            'po_number' => $order->supplierOrder?->po_number,
            'supplier_order_id' => $order->supplier_order_id,
            'po_status' => $order->supplierOrder?->status?->value,
            'created_at' => $order->created_at->format('M d, Y H:i'),
            'show_url' => route('orders.show', $order),
            'can_fulfill' => $canFulfill && $order->status->allowsFulfill(),
            'can_cancel' => $canCancel && $order->status->allowsCancel(),
            'allowed_targets' => collect($order->status->kanbanTargets())
                ->map(fn (CustomerOrderStatus $status) => $status->value)
                ->values()
                ->all(),
        ];
    }

    private function shortageQuantity(CustomerOrder $order): int
    {
        /** @var Collection<int, CustomerOrderItem> $items */
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        return (int) $items->sum(
            fn (CustomerOrderItem $item) => max(0, $item->quantity_ordered - $item->quantity_reserved)
        );
    }

    private function runOrderAction(CustomerOrder $order, callable $action): JsonResponse
    {
        try {
            $action();
            $order->refresh();

            return response()->json([
                'success' => true,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
