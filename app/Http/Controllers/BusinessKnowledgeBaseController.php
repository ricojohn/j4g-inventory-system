<?php

namespace App\Http\Controllers;

use App\Models\BusinessKnowledgeBaseEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessKnowledgeBaseController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('manage integrations'), 403);

        $entries = BusinessKnowledgeBaseEntry::query()
            ->when($request->user()->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->orderByDesc('sort_order')
            ->orderBy('title')
            ->get();

        return view('business-knowledge-base.index', compact('entries'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage integrations'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        BusinessKnowledgeBaseEntry::query()->create([
            'branch_id' => $request->user()->branch_id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'Knowledge base entry added.');
    }

    public function update(Request $request, BusinessKnowledgeBaseEntry $entry): RedirectResponse
    {
        abort_unless($request->user()?->can('manage integrations'), 403);
        abort_if($request->user()->branch_id && $request->user()->branch_id !== $entry->branch_id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $entry->update([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'Knowledge base entry updated.');
    }

    public function destroy(Request $request, BusinessKnowledgeBaseEntry $entry): RedirectResponse
    {
        abort_unless($request->user()?->can('manage integrations'), 403);
        abort_if($request->user()->branch_id && $request->user()->branch_id !== $entry->branch_id, 404);

        $entry->delete();

        return back()->with('success', 'Knowledge base entry removed.');
    }
}
