<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Enums\SupplierOrderStatus;
use App\Http\Requests\ReceiveSupplierOrderRequest;
use App\Http\Requests\StoreSupplierOrderRequest;
use App\Http\Requests\TableDataRequest;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use App\Models\ProductColorSize;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Services\SupplierOrderService;
use App\Support\PaginatedJsonResponse;
use App\Support\ProductCellLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class SupplierOrderController extends Controller
{
    public function __construct(
        private SupplierOrderService $supplierOrderService,
        private ProductCellLookup $productCellLookup,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('view supplier orders'), 403);

        return view('supplier-orders.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view supplier orders'), 403);

        $request->validate([
            'status' => ['sometimes', 'nullable', 'string'],
        ]);

        $orders = SupplierOrder::query()
            ->with(['creator', 'supplier', 'customerOrder'])
            ->withCount('items')
            ->withSum('items', 'quantity_ordered')
            ->withSum('items', 'quantity_received')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('customerOrder', fn ($q) => $q->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $orders->through(fn (SupplierOrder $order) => $this->formatOrderListRow($order))
        );
    }

    public function productCells(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('create supplier orders'), 403);

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
        abort_unless($request->user()?->can('create supplier orders'), 403);

        $prefillItems = [];
        $fromOrder = null;

        if ($request->filled('from_order_id')) {
            $fromOrder = CustomerOrder::query()
                ->with(['items.cell.color.color', 'items.cell.size.size', 'items.cell.color.product'])
                ->find($request->integer('from_order_id'));

            if ($fromOrder) {
                $prefillItems = $fromOrder->items
                    ->filter(fn (CustomerOrderItem $item) => $item->quantity_ordered > $item->quantity_reserved)
                    ->map(fn (CustomerOrderItem $item) => [
                        'customer_order_item_id' => $item->id,
                        'product_color_size_id' => $item->product_color_size_id,
                        'quantity_ordered' => $item->quantity_ordered - $item->quantity_reserved,
                        'item_code' => $item->cell->color->item_code,
                        'product_name' => $item->cell->color->product->name,
                        'color_name' => $item->cell->color->color->name,
                        'size_name' => $item->cell->size->size->name,
                        'image_url' => $item->cell->color->imageUrl(),
                    ])
                    ->values()
                    ->all();
            }
        }

        return view('supplier-orders.create', [
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(['id', 'name']),
            'prefillItems' => $prefillItems,
            'fromOrder' => $fromOrder,
        ]);
    }

    public function store(StoreSupplierOrderRequest $request): RedirectResponse
    {
        if ($request->filled('supplier_id')) {
            $supplier = Supplier::query()->findOrFail($request->integer('supplier_id'));

            if ($supplier->status !== RecordStatus::Active) {
                abort(422, 'Cannot create purchase order for an inactive supplier.');
            }
        }

        $order = DB::transaction(function () use ($request) {
            $cells = ProductColorSize::query()
                ->whereIn('id', collect($request->input('items'))->pluck('product_color_size_id'))
                ->with('color.product')
                ->get();

            $this->productCellLookup->ensureActiveProducts($cells);

            $order = SupplierOrder::query()->create([
                'supplier_id' => $request->input('supplier_id'),
                'remarks' => $request->input('remarks'),
                'status' => SupplierOrderStatus::Draft,
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->input('items') as $item) {
                $order->items()->create([
                    'product_color_size_id' => $item['product_color_size_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'customer_order_item_id' => $item['customer_order_item_id'] ?? null,
                ]);
            }

            if ($request->filled('from_order_id')) {
                CustomerOrder::query()
                    ->whereKey($request->integer('from_order_id'))
                    ->update(['supplier_order_id' => $order->id]);
            }

            return $order;
        });

        return redirect()
            ->route('supplier-orders.show', $order)
            ->with('success', 'Purchase order created.');
    }

    public function show(Request $request, SupplierOrder $po): View
    {
        abort_unless($request->user()?->can('view supplier orders'), 403);

        $po->load([
            'creator',
            'supplier',
            'customerOrder',
            'items.cell.color.color',
            'items.cell.color.product',
            'items.cell.size.size',
            'items.customerOrderItem.order',
        ]);

        return view('supplier-orders.show', ['po' => $po]);
    }

    public function receive(ReceiveSupplierOrderRequest $request, SupplierOrder $po): JsonResponse
    {
        abort_unless($request->user()?->can('receive supplier orders'), 403);

        if (in_array($po->status, [SupplierOrderStatus::Received, SupplierOrderStatus::Cancelled], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This purchase order cannot receive stock.',
            ], 422);
        }

        try {
            $result = $this->supplierOrderService->receiveItems($po, $request->input('qtys', []));
            $po->refresh();

            $reservedCount = $result['reserved_orders'];

            return response()->json([
                'success' => true,
                'status' => $po->status->value,
                'status_label' => $po->status->label(),
                'reserved_orders' => $reservedCount,
                'message' => "Delivery received. {$reservedCount} customer order(s) updated with reserved stock.",
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, SupplierOrder $po): JsonResponse
    {
        abort_unless($request->user()?->can('cancel supplier orders'), 403);

        if (in_array($po->status, [SupplierOrderStatus::Received, SupplierOrderStatus::Cancelled], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Received or cancelled purchase orders cannot be cancelled.',
            ], 422);
        }

        $po->update(['status' => SupplierOrderStatus::Cancelled]);

        return response()->json([
            'success' => true,
            'status' => $po->status->value,
            'status_label' => $po->status->label(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderListRow(SupplierOrder $order): array
    {
        return [
            'id' => $order->id,
            'po_number' => $order->po_number,
            'supplier_name' => $order->supplier?->name ?? '—',
            'linked_order_number' => $order->customerOrder?->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'item_count' => $order->items_count,
            'total_qty_ordered' => (int) ($order->items_sum_quantity_ordered ?? 0),
            'total_qty_received' => (int) ($order->items_sum_quantity_received ?? 0),
            'created_by_name' => $order->creator?->name ?? 'System',
            'created_at' => $order->created_at->format('M d, Y H:i'),
            'show_url' => route('supplier-orders.show', $order),
        ];
    }
}
