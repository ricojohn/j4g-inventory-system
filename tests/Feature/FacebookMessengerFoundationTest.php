<?php

use App\Models\Branch;
use App\Models\FacebookConversation;
use App\Models\FacebookPage;
use App\Models\MessengerOrderDraft;
use Illuminate\Database\QueryException;

test('legacy records are assigned to the default branch', function () {
    $branch = Branch::query()->where('code', 'MAIN')->firstOrFail();

    expect($branch->status)->toBe('active');
});

test('facebook psid is unique within a page and may exist on another page', function () {
    $branch = Branch::query()->firstOrFail();
    $otherBranch = Branch::query()->create(['code' => 'SECOND', 'name' => 'Second']);
    $page = FacebookPage::query()->create(['branch_id' => $branch->id, 'page_id' => 'page-1', 'name' => 'Main']);
    $otherPage = FacebookPage::query()->create(['branch_id' => $otherBranch->id, 'page_id' => 'page-2', 'name' => 'Second']);

    FacebookConversation::query()->create(['branch_id' => $branch->id, 'facebook_page_id' => $page->id, 'psid' => 'psid-1']);
    FacebookConversation::query()->create(['branch_id' => $otherBranch->id, 'facebook_page_id' => $otherPage->id, 'psid' => 'psid-1']);

    expect(fn () => FacebookConversation::query()->create([
        'branch_id' => $branch->id,
        'facebook_page_id' => $page->id,
        'psid' => 'psid-1',
    ]))->toThrow(QueryException::class);
});

test('a conversation has only one active messenger order draft', function () {
    $branch = Branch::query()->firstOrFail();
    $page = FacebookPage::query()->create(['branch_id' => $branch->id, 'page_id' => 'page-1', 'name' => 'Main']);
    $conversation = FacebookConversation::query()->create(['branch_id' => $branch->id, 'facebook_page_id' => $page->id, 'psid' => 'psid-1']);

    MessengerOrderDraft::query()->create([
        'branch_id' => $branch->id,
        'facebook_conversation_id' => $conversation->id,
        'psid' => $conversation->psid,
    ]);

    expect(fn () => MessengerOrderDraft::query()->create([
        'branch_id' => $branch->id,
        'facebook_conversation_id' => $conversation->id,
        'psid' => $conversation->psid,
    ]))->toThrow(QueryException::class);
});
