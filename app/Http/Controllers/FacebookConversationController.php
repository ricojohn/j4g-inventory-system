<?php

namespace App\Http\Controllers;

use App\Events\MessengerConversationUpdated;
use App\Http\Requests\UpdateMessengerOrderDraftRequest;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\ProductColorSize;
use App\Services\Facebook\CreateMessengerOrderService;
use App\Services\Facebook\MessengerConversationService;
use App\Services\Facebook\MessengerOrderDraftService;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class FacebookConversationController extends Controller
{
    public function index(Request $request): View
    {
        [$conversations, $selectedConversation, $cells] = $this->loadInboxState($request);

        return view('facebook-conversations.show', compact('conversations', 'selectedConversation', 'cells'));
    }

    public function show(Request $request, FacebookConversation $conversation): View
    {
        $this->assertBranch($request, $conversation);
        [$conversations, $selectedConversation, $cells] = $this->loadInboxState($request, $conversation);

        return view('facebook-conversations.show', compact('conversations', 'selectedConversation', 'cells'));
    }

    public function snapshot(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $this->assertBranch($request, $conversation);

        [$conversations, $selectedConversation, $cells] = $this->loadInboxState($request, $conversation);

        return response()->json([
            'conversations' => $conversations->map(fn (FacebookConversation $row) => $this->conversationSummary($row)),
            'selectedConversation' => $selectedConversation ? $this->conversationDetail($selectedConversation) : null,
            'cells' => $cells->map(fn (ProductColorSize $cell) => $this->cellSummary($cell)),
            'selectedConversationId' => $selectedConversation?->id,
        ]);
    }

    public function takeOver(Request $request, FacebookConversation $conversation): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        DB::transaction(function () use ($request, $conversation): void {
            $locked = FacebookConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $locked->update(['control_mode' => 'human', 'assigned_user_id' => $request->user()->id, 'taken_over_at' => now(), 'version' => $locked->version + 1]);
            $locked->messages()->where('direction', 'outbound')->where('status', 'pending')->update(['status' => 'suppressed', 'error_message' => 'Human takeover']);
        });
        $this->broadcastConversationUpdated($conversation->fresh());

        return back()->with('success', 'Human takeover enabled. AI replies are stopped.');
    }

    public function returnToAi(Request $request, FacebookConversation $conversation): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        $conversation->update(['control_mode' => 'ai', 'assigned_user_id' => null, 'returned_to_ai_at' => now(), 'version' => $conversation->version + 1]);
        $this->broadcastConversationUpdated($conversation->fresh());

        return back()->with('success', 'Conversation returned to AI.');
    }

    public function reply(Request $request, FacebookConversation $conversation, MessengerConversationService $service): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        abort_unless($conversation->control_mode === 'human', 422, 'Take over the conversation before sending a staff reply.');
        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $service->queueReply($conversation, $validated['message'], false);
        $this->broadcastConversationUpdated($conversation->fresh());

        return back()->with('success', 'Staff reply queued.');
    }

    public function prepareSummary(Request $request, FacebookConversation $conversation, MessengerOrderDraftService $service): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        try {
            $service->prepareSummary($conversation->draft()->firstOrFail());

            return back()->with('success', 'Final summary prepared. Explicit confirmation is now required.');
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function updateDraft(UpdateMessengerOrderDraftRequest $request, FacebookConversation $conversation, MessengerOrderDraftService $service): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        $draft = $conversation->draft()->firstOrCreate(['branch_id' => $conversation->branch_id, 'psid' => $conversation->psid]);
        $cellIds = collect($request->validated('items'))->pluck('product_color_size_id');
        $validCellIds = ProductColorSize::query()->whereIn('id', $cellIds)
            ->whereHas('color.product', fn ($query) => $query->where('branch_id', $conversation->branch_id)->where('status', 'active'))
            ->pluck('id');
        abort_unless($validCellIds->count() === $cellIds->unique()->count(), 422, 'Every item must belong to an active product in this branch.');

        DB::transaction(function () use ($request, $draft, $service): void {
            $service->invalidateSummary($draft);
            $draft->refresh()->update($request->safe()->only(['customer_name', 'fulfillment_method', 'delivery_address', 'payment_method_preference']));
            $draft->items()->delete();
            foreach ($request->validated('items') as $item) {
                $draft->items()->create($item);
            }
        });

        return back()->with('success', 'Draft updated. Prepare a new final summary before confirmation.');
    }

    public function confirm(Request $request, FacebookConversation $conversation): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        $draft = $conversation->draft()->firstOrFail();
        if ($draft->status !== 'awaiting_confirmation' || ! $draft->summary_hash || $draft->confirmation_expires_at?->isPast()) {
            return back()->with('error', 'Prepare a current final summary before confirming.');
        }
        $draft->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmation_actor_type' => 'staff', 'confirmed_by_user_id' => $request->user()->id]);

        return back()->with('success', 'Summary explicitly confirmed. Create Order is now available.');
    }

    public function createOrder(Request $request, FacebookConversation $conversation, CreateMessengerOrderService $service): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        try {
            $order = $service->execute($conversation->draft()->firstOrFail(), $request->user());

            return redirect()->route('orders.show', $order)->with('success', 'Create Order completed.');
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    private function assertBranch(Request $request, FacebookConversation $conversation): void
    {
        abort_if($request->user()->branch_id !== null && $request->user()->branch_id !== $conversation->branch_id, 404);
    }

    /**
     * @return array{0:\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, FacebookConversation>, 1:FacebookConversation|null, 2:\Illuminate\Support\Collection<int, ProductColorSize>}
     */
    private function loadInboxState(Request $request, ?FacebookConversation $selectedConversation = null): array
    {
        $branchId = $request->user()->branch_id;

        $conversations = FacebookConversation::query()
            ->with('page', 'assignedUser', 'draft')
            ->withCount(['messages as unread_message_count' => fn (Builder $query) => $query->where('direction', 'inbound')])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('last_inbound_at')
            ->orderByDesc('updated_at')
            ->paginate(30);

        $selectedConversation ??= $conversations->getCollection()->first() ?? FacebookConversation::query()
            ->with('page', 'assignedUser', 'messages', 'draft.items.cell.color.product', 'draft.items.cell.color.color', 'draft.items.cell.size.size')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('last_inbound_at')
            ->orderByDesc('updated_at')
            ->first();

        if ($selectedConversation) {
            $selectedConversation->load('page', 'assignedUser', 'messages', 'draft.items.cell.color.product', 'draft.items.cell.color.color', 'draft.items.cell.size.size');
        }

        $cells = ProductColorSize::query()
            ->whereHas('color.product', fn ($query) => $query->where('branch_id', $selectedConversation?->branch_id ?? $branchId)->where('status', 'active'))
            ->with('color.product', 'color.color', 'size.size')
            ->get()
            ->sortBy(fn ($cell) => $cell->color->product->name.$cell->color->color->name.$cell->size->size->name)
            ->values();

        return [$conversations, $selectedConversation, $cells];
    }

    private function conversationSummary(FacebookConversation $conversation): array
    {
        $conversation->loadMissing('page', 'draft');

        return [
            'id' => $conversation->id,
            'psid' => $conversation->psid,
            'page_name' => $conversation->page->name,
            'control_mode' => $conversation->control_mode,
            'state' => $conversation->state,
            'last_inbound_at' => $conversation->last_inbound_at?->toIso8601String(),
            'last_outbound_at' => $conversation->last_outbound_at?->toIso8601String(),
            'unread_message_count' => (int) ($conversation->unread_message_count ?? 0),
            'draft_status' => $conversation->draft?->status,
            'assigned_user_name' => $conversation->assignedUser?->name,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    private function conversationDetail(FacebookConversation $conversation): array
    {
        $conversation->loadMissing('page', 'assignedUser', 'messages', 'draft.items.cell.color.product', 'draft.items.cell.color.color', 'draft.items.cell.size.size');

        return [
            'id' => $conversation->id,
            'psid' => $conversation->psid,
            'page_name' => $conversation->page->name,
            'control_mode' => $conversation->control_mode,
            'state' => $conversation->state,
            'assigned_user_name' => $conversation->assignedUser?->name,
            'taken_over_at' => $conversation->taken_over_at?->toIso8601String(),
            'returned_to_ai_at' => $conversation->returned_to_ai_at?->toIso8601String(),
            'last_inbound_at' => $conversation->last_inbound_at?->toIso8601String(),
            'last_outbound_at' => $conversation->last_outbound_at?->toIso8601String(),
            'messages' => $conversation->messages->map(function (FacebookMessage $message): array {
                return [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'sender_type' => $message->sender_type,
                    'message_type' => $message->message_type,
                    'body' => $message->body,
                    'ai_generated' => $message->ai_generated,
                    'status' => $message->status,
                    'sent_at' => $message->sent_at?->toIso8601String(),
                    'created_at' => $message->created_at?->toIso8601String(),
                ];
            }),
            'draft' => $conversation->draft ? [
                'id' => $conversation->draft->id,
                'customer_name' => $conversation->draft->customer_name,
                'psid' => $conversation->draft->psid,
                'fulfillment_method' => $conversation->draft->fulfillment_method,
                'delivery_address' => $conversation->draft->delivery_address,
                'payment_method_preference' => $conversation->draft->payment_method_preference,
                'status' => $conversation->draft->status,
                'summary_text' => $conversation->draft->summary_text,
                'summary_hash' => $conversation->draft->summary_hash,
                'confirmation_expires_at' => $conversation->draft->confirmation_expires_at?->toIso8601String(),
                'items' => $conversation->draft->items->map(function ($item): array {
                    return [
                        'product_color_size_id' => $item->product_color_size_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'label' => trim(sprintf('%s / %s / %s', $item->cell->color->product->name, $item->cell->color->color->name, $item->cell->size->size->name)),
                    ];
                }),
            ] : null,
        ];
    }

    private function cellSummary(ProductColorSize $cell): array
    {
        return [
            'id' => $cell->id,
            'label' => trim(sprintf('%s / %s / %s', $cell->color->product->name, $cell->color->color->name, $cell->size->size->name)),
            'available_stock' => $cell->available_stock,
        ];
    }

    private function broadcastConversationUpdated(?FacebookConversation $conversation): void
    {
        if (! $conversation) {
            return;
        }

        $conversation->loadMissing('page', 'assignedUser', 'draft');
        $conversation->loadCount(['messages as unread_message_count' => fn ($query) => $query->where('direction', 'inbound')]);

        broadcast(new MessengerConversationUpdated([
            'conversation' => [
                'id' => $conversation->id,
                'branch_id' => $conversation->branch_id,
                'psid' => $conversation->psid,
                'page_name' => $conversation->page->name,
                'control_mode' => $conversation->control_mode,
                'state' => $conversation->state,
                'assigned_user_name' => $conversation->assignedUser?->name,
                'last_inbound_at' => $conversation->last_inbound_at?->toIso8601String(),
                'last_outbound_at' => $conversation->last_outbound_at?->toIso8601String(),
                'unread_message_count' => (int) ($conversation->unread_message_count ?? 0),
                'draft_status' => $conversation->draft?->status,
                'updated_at' => $conversation->updated_at?->toIso8601String(),
            ],
        ]));
    }
}
