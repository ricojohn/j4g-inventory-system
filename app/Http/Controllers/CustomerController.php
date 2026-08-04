<?php

namespace App\Http\Controllers;

use App\Enums\CustomerSource;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('view customers'), 403);

        return view('customers.index', [
            'canManage' => $request->user()->can('manage customers'),
        ]);
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view customers'), 403);

        $request->validate([
            'source' => ['sometimes', 'nullable', 'string'],
        ]);

        $customers = Customer::query()
            ->withCount('orders')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('handle', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        $canManage = $request->user()->can('manage customers');

        return PaginatedJsonResponse::fromPaginator(
            $customers->through(fn (Customer $customer) => $this->formatCustomerRow($customer, $canManage))
        );
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('manage customers'), 403);

        return view('customers.create', [
            'customerSources' => CustomerSource::cases(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function show(Request $request, Customer $customer): View
    {
        abort_unless($request->user()?->can('view customers'), 403);

        $customer->load([
            'orders' => fn ($query) => $query->latest()->limit(50),
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'canManage' => $request->user()->can('manage customers'),
        ]);
    }

    public function edit(Request $request, Customer $customer): View
    {
        abort_unless($request->user()?->can('manage customers'), 403);

        return view('customers.edit', [
            'customer' => $customer,
            'customerSources' => CustomerSource::cases(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()?->can('manage customers'), 403);

        if ($customer->orders()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with existing orders.',
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCustomerRow(Customer $customer, bool $canManage = false): array
    {
        $source = $customer->source;

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'handle' => $customer->handle ?? '—',
            'contact' => $customer->contact ?? '—',
            'source' => $source?->value,
            'source_label' => $source?->label() ?? '—',
            'notes' => $customer->notes ? Str::limit($customer->notes, 60) : '—',
            'orders_count' => $customer->orders_count,
            'show_url' => route('customers.show', $customer),
            'edit_url' => $canManage ? route('customers.edit', $customer) : null,
            'destroy_url' => $canManage ? route('customers.destroy', $customer) : null,
        ];
    }
}
