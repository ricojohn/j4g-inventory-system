<?php

namespace App\Http\Controllers;

use App\Models\FacebookPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FacebookPageController extends Controller
{
    public function index(Request $request): View
    {
        $pages = FacebookPage::query()->where('branch_id', $request->user()->branch_id)->orderBy('name')->get();

        return view('facebook-pages.index', compact('pages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['ai_enabled'] = $request->boolean('ai_enabled');
        FacebookPage::query()->create([...$data, 'branch_id' => $request->user()->branch_id]);

        return back()->with('success', 'Facebook Page configured.');
    }

    public function update(Request $request, FacebookPage $page): RedirectResponse
    {
        abort_unless($page->branch_id === $request->user()->branch_id, 404);
        $data = $this->validated($request, $page);
        $data['ai_enabled'] = $request->boolean('ai_enabled');
        if (blank($data['access_token'] ?? null)) {
            unset($data['access_token']);
        }
        $page->update($data);

        return back()->with('success', 'Facebook Page updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FacebookPage $page = null): array
    {
        return $request->validate([
            'page_id' => ['required', 'string', 'max:255', Rule::unique('facebook_pages')->ignore($page)],
            'name' => ['required', 'string', 'max:255'],
            'access_token' => [$page ? 'nullable' : 'required', 'nullable', 'string', 'max:2000'],
            'graph_api_version' => ['required', 'regex:/^v\d+\.\d+$/', 'max:20'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'ai_enabled' => ['sometimes', 'boolean'],
        ]);
    }
}
