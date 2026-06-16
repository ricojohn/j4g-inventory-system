<?php

namespace App\Http\Controllers;

use App\Enums\AiOrderDraftStatus;
use App\Enums\CustomerSource;
use App\Exceptions\IntegrationException;
use App\Http\Requests\AnalyzeMessageRequest;
use App\Http\Requests\ConvertDraftRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Http\Requests\UploadDraftImageRequest;
use App\Models\AiOrderDraft;
use App\Models\Product;
use App\Services\Ai\AiProviderManager;
use App\Services\AiOrderDraftService;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class AiOrderAssistantController extends Controller
{
    public function __construct(
        private AiOrderDraftService $aiOrderDraftService,
        private AiProviderManager $aiProviderManager,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('use ai assistant'), 403);

        $this->aiProviderManager->ensureProviderRows();
        $providers = $this->aiProviderManager->listProviders();
        $defaultProviderKey = $this->aiProviderManager->getDefaultProviderKey();
        $currentProvider = collect($providers)->firstWhere('provider', $defaultProviderKey);
        $connected = filled($defaultProviderKey);

        return view('ai.order-assistant.index', [
            'connected' => $connected,
            'currentProvider' => $currentProvider,
            'providers' => $providers,
            'canManageIntegrations' => $request->user()->can('manage integrations'),
            'customerSources' => CustomerSource::cases(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'assistantConfig' => [
                'connected' => $connected,
                'currentProvider' => $currentProvider,
                'products' => Product::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code'])->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                ])->values(),
                'customerSources' => collect(CustomerSource::cases())->map(fn (CustomerSource $source) => [
                    'value' => $source->value,
                    'label' => $source->label(),
                ])->values(),
                'urls' => [
                    'analyze' => route('ai.order-assistant.analyze'),
                    'drafts' => route('ai.order-assistant.drafts'),
                    'draftShow' => url('/ai/order-assistant/drafts'),
                    'draftUpdate' => url('/ai/order-assistant/drafts'),
                    'draftConvert' => url('/ai/order-assistant/drafts'),
                    'draftReject' => url('/ai/order-assistant/drafts'),
                    'draftImage' => url('/ai/order-assistant/drafts'),
                    'productCells' => route('orders.product-cells'),
                    'setProvider' => route('ai.order-assistant.set-provider'),
                ],
            ],
        ]);
    }

    public function setProvider(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('manage integrations'), 403);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', $this->aiProviderManager->providerKeys())],
        ]);

        try {
            $this->aiProviderManager->setDefaultProvider($validated['provider']);
        } catch (IntegrationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Default AI provider updated.',
        ]);
    }

    public function drafts(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('use ai assistant'), 403);

        $drafts = AiOrderDraft::query()
            ->with('creator')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('raw_message', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $drafts->through(fn (AiOrderDraft $draft) => $this->formatDraftListRow($draft))
        );
    }

    public function show(Request $request, AiOrderDraft $draft): JsonResponse
    {
        abort_unless($request->user()?->can('use ai assistant'), 403);

        return response()->json([
            'success' => true,
            'draft' => $this->formatDraftDetail($draft),
        ]);
    }

    public function analyze(AnalyzeMessageRequest $request): JsonResponse
    {
        if (blank($this->aiProviderManager->getDefaultProviderKey())) {
            return response()->json([
                'success' => false,
                'message' => 'No AI provider is connected. Ask an admin to configure one in Integrations.',
            ], 422);
        }

        try {
            $draft = $this->aiOrderDraftService->createDraftFromMessage(
                $request->string('raw_message')->toString(),
                $request->user(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Conversation analyzed.',
                'draft' => $this->formatDraftDetail($draft),
            ]);
        } catch (IntegrationException|RuntimeException|InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function convert(ConvertDraftRequest $request, AiOrderDraft $draft): JsonResponse
    {
        if ($draft->status === AiOrderDraftStatus::Converted) {
            return response()->json([
                'success' => false,
                'message' => 'This draft has already been converted.',
            ], 422);
        }

        if ($draft->status === AiOrderDraftStatus::Rejected) {
            return response()->json([
                'success' => false,
                'message' => 'Rejected drafts cannot be converted.',
            ], 422);
        }

        try {
            $result = $this->aiOrderDraftService->convertDraftToCustomerOrder(
                $draft,
                $request->validated(),
                $request->user(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Customer order created.',
                'redirect_url' => $result['redirect_url'],
                'order_id' => $result['order']->id,
                'order_number' => $result['order']->order_number,
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function reject(Request $request, AiOrderDraft $draft): JsonResponse
    {
        abort_unless($request->user()?->can('use ai assistant'), 403);

        if ($draft->status === AiOrderDraftStatus::Converted) {
            return response()->json([
                'success' => false,
                'message' => 'Converted drafts cannot be rejected.',
            ], 422);
        }

        $draft->update(['status' => AiOrderDraftStatus::Rejected]);

        return response()->json([
            'success' => true,
            'message' => 'Draft rejected.',
        ]);
    }

    public function update(UpdateDraftRequest $request, AiOrderDraft $draft): JsonResponse
    {
        if ($draft->status === AiOrderDraftStatus::Converted) {
            return response()->json([
                'success' => false,
                'message' => 'Converted drafts cannot be edited.',
            ], 422);
        }

        $draft->update(array_filter([
            'customer_name' => $request->input('customer_name'),
            'customer_contact' => $request->input('customer_contact'),
            'customer_source' => $request->input('customer_source'),
            'customer_notes' => $request->input('customer_notes'),
            'matched_json' => $request->input('matched_json'),
            'status' => AiOrderDraftStatus::Draft,
        ], fn ($value) => $value !== null));

        return response()->json([
            'success' => true,
            'message' => 'Draft saved.',
            'draft' => $this->formatDraftDetail($draft->fresh()),
        ]);
    }

    public function uploadImage(UploadDraftImageRequest $request, AiOrderDraft $draft): JsonResponse
    {
        if ($draft->status === AiOrderDraftStatus::Converted) {
            return response()->json([
                'success' => false,
                'message' => 'Converted drafts cannot be edited.',
            ], 422);
        }

        if (filled($draft->image_path)) {
            Storage::disk('public')->delete($draft->image_path);
        }

        $path = $request->file('image')->store('order-images', 'public');
        $draft->update(['image_path' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Reference image uploaded.',
            'image_url' => $draft->fresh()->imageUrl(),
        ]);
    }

    public function deleteImage(Request $request, AiOrderDraft $draft): JsonResponse
    {
        abort_unless($request->user()?->can('use ai assistant'), 403);

        if ($draft->status === AiOrderDraftStatus::Converted) {
            return response()->json([
                'success' => false,
                'message' => 'Converted drafts cannot be edited.',
            ], 422);
        }

        if (filled($draft->image_path)) {
            Storage::disk('public')->delete($draft->image_path);
            $draft->update(['image_path' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reference image removed.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDraftListRow(AiOrderDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'message_preview' => $draft->messagePreview(),
            'status' => $draft->status->value,
            'status_label' => $draft->status->label(),
            'status_badge_color' => $draft->status->badgeColor(),
            'confidence_score' => $draft->confidence_score,
            'customer_name' => $draft->customer_name ?? '—',
            'created_by_name' => $draft->creator?->name ?? 'System',
            'created_at' => $draft->created_at->format('M d, Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDraftDetail(AiOrderDraft $draft): array
    {
        $draft->loadMissing(['creator', 'customerOrder']);

        return [
            'id' => $draft->id,
            'raw_message' => $draft->raw_message,
            'parsed_json' => $draft->parsed_json,
            'matched_json' => $draft->matched_json,
            'confidence_score' => $draft->confidence_score,
            'status' => $draft->status->value,
            'status_label' => $draft->status->label(),
            'customer_name' => $draft->customer_name,
            'customer_contact' => $draft->customer_contact,
            'customer_source' => $draft->customer_source?->value,
            'customer_notes' => $draft->customer_notes,
            'image_path' => $draft->image_path,
            'image_url' => $draft->imageUrl(),
            'customer_order_id' => $draft->customer_order_id,
            'customer_order_number' => $draft->customerOrder?->order_number,
            'created_at' => $draft->created_at->format('M d, Y H:i'),
        ];
    }
}
