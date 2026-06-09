<?php

namespace App\Http\Controllers;

use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerSource;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Http\Requests\TableDataRequest;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\ProductColorSize;
use App\Services\CustomerOrderService;
use App\Support\PaginatedJsonResponse;
use App\Support\ProductCellLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class CustomerOrderController extends Controller
{
    public function __construct(
        private CustomerOrderService $customerOrderService,
        private ProductCellLookup $productCellLookup,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('view orders'), 403);

        return view('orders.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view orders'), 403);

        $request->validate([
            'status' => ['sometimes', 'nullable', 'string'],
            'source' => ['sometimes', 'nullable', 'string'],
        ]);

        $orders = CustomerOrder::query()
            ->with(['creator', 'supplierOrder'])
            ->withCount('items')
            ->withSum('items', 'quantity_ordered')
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

            $order = CustomerOrder::query()->create([
                'customer_name' => $request->string('customer_name'),
                'customer_contact' => $request->input('customer_contact'),
                'customer_source' => $request->input('customer_source'),
                'customer_notes' => $request->input('customer_notes'),
                'status' => CustomerOrderStatus::Pending,
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->input('items') as $item) {
                $order->items()->create([
                    'product_color_size_id' => $item['product_color_size_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'quantity_reserved' => 0,
                    'status' => 'pending',
                ]);
            }

            $this->customerOrderService->reserveOrder($order);

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
            'items.cell.color.color',
            'items.cell.color.product',
            'items.cell.size.size',
            'supplierOrder.supplier',
        ]);

        return view('orders.show', [
            'order' => $order,
        ]);
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
            'show_url' => route('orders.show', $order),
        ];
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
