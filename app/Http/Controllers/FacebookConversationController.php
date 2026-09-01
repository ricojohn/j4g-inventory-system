<?php

namespace App\Http\Controllers;

use App\Models\FacebookConversation;
use App\Services\Facebook\CreateMessengerOrderService;
use App\Services\Facebook\MessengerOrderDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class FacebookConversationController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = FacebookConversation::query()->with('page', 'assignedUser', 'draft')
            ->when($request->user()->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->latest('last_inbound_at')->paginate(30);

        return view('facebook-conversations.index', compact('conversations'));
    }

    public function show(Request $request, FacebookConversation $conversation): View
    {
        $this->assertBranch($request, $conversation);
        $conversation->load('page', 'assignedUser', 'messages', 'draft.items.cell.color.product', 'draft.items.cell.color.color', 'draft.items.cell.size.size');

        return view('facebook-conversations.show', compact('conversation'));
    }

    public function takeOver(Request $request, FacebookConversation $conversation): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        DB::transaction(function () use ($request, $conversation): void {
            $locked = FacebookConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $locked->update(['control_mode' => 'human', 'assigned_user_id' => $request->user()->id, 'taken_over_at' => now(), 'version' => $locked->version + 1]);
            $locked->messages()->where('direction', 'outbound')->where('status', 'pending')->update(['status' => 'suppressed', 'error_message' => 'Human takeover']);
        });

        return back()->with('success', 'Human takeover enabled. AI replies are stopped.');
    }

    public function returnToAi(Request $request, FacebookConversation $conversation): RedirectResponse
    {
        $this->assertBranch($request, $conversation);
        $conversation->update(['control_mode' => 'ai', 'assigned_user_id' => null, 'returned_to_ai_at' => now(), 'version' => $conversation->version + 1]);

        return back()->with('success', 'Conversation returned to AI.');
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
}
