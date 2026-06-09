<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Http\Requests\TableDataRequest;
use App\Models\Supplier;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('manage suppliers'), 403);

        return view('admin.suppliers.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('manage suppliers'), 403);

        $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:active,inactive'],
        ]);

        $suppliers = Supplier::query()
            ->withCount('purchaseOrders as po_count')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $suppliers->through(fn (Supplier $supplier) => $this->formatSupplierRow($supplier))
        );
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('manage suppliers'), 403);

        return view('admin.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    public function edit(Request $request, Supplier $supplier): View
    {
        abort_unless($request->user()?->can('manage suppliers'), 403);

        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()
            ->route('admin.suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($request->user()?->can('manage suppliers'), 403);

        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete supplier with existing purchase orders.',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSupplierRow(Supplier $supplier): array
    {
        $status = $supplier->status instanceof RecordStatus
            ? $supplier->status->value
            : (string) $supplier->status;

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact' => $supplier->contact ?? '—',
            'address' => $supplier->address ? Str::limit($supplier->address, 50) : '—',
            'notes' => $supplier->notes ? Str::limit($supplier->notes, 60) : '—',
            'status' => $status,
            'status_label' => ucfirst($status),
            'po_count' => $supplier->po_count,
            'created_at' => $supplier->created_at->format('M d, Y H:i'),
            'edit_url' => route('admin.suppliers.edit', $supplier),
        ];
    }
}
