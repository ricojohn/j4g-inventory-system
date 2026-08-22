<?php

use App\Models\Integration;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
    createTestIntegration('openai');
});

function fakeAssistanceChatSequence(array $responses): void
{
    $sequence = Http::sequence();

    foreach ($responses as $response) {
        $sequence->push($response);
    }

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => $sequence,
        'https://api.openai.com/v1/models' => Http::response(['data' => []], 200),
        'api.openai.com/*' => Http::response(['data' => []], 200),
    ]);
}

test('guests cannot access ai assistance page', function () {
    $this->get(route('ai.assistance.index'))
        ->assertRedirect(route('login'));
});

test('staff cannot access ai assistance page', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('ai.assistance.index'))
        ->assertForbidden();
});

test('viewer cannot access ai assistance page', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->get(route('ai.assistance.index'))
        ->assertForbidden();
});

test('admin can access ai assistance page', function () {
    $this->actingAs(userWithRole('Admin'))
        ->get(route('ai.assistance.index'))
        ->assertOk()
        ->assertSee('AI Assistance');
});

test('manager can access ai assistance page', function () {
    $this->actingAs(userWithRole('Manager'))
        ->get(route('ai.assistance.index'))
        ->assertOk();
});

test('staff cannot ask ai assistance', function () {
    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.assistance.ask'), [
            'message' => 'What is low stock right now?',
        ])
        ->assertForbidden();
});

test('ask endpoint runs tool loop and returns grounded answer', function () {
    createTestCell(3);

    fakeAssistanceChatSequence([
        [
            'choices' => [
                [
                    'message' => [
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_low_stock',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'low_stock_items',
                                    'arguments' => json_encode(['limit' => 10]),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'choices' => [
                [
                    'message' => [
                        'content' => 'There is 1 low-stock SKU based on current inventory.',
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('ai.assistance.ask'), [
            'message' => 'Give me a low stock summary.',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('answer', 'There is 1 low-stock SKU based on current inventory.')
        ->assertJsonPath('tool_trace.0.name', 'low_stock_items')
        ->assertJsonStructure([
            'rows',
            'tool_trace',
        ]);
});

test('ask endpoint refuses with mocked direct answer for off-topic', function () {
    fakeAssistanceChatSequence([
        [
            'choices' => [
                [
                    'message' => [
                        'content' => 'I can only help with J4G inventory and operations data.',
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs(userWithRole('Manager'))
        ->postJson(route('ai.assistance.ask'), [
            'message' => 'Write me a poem about cats.',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('answer', 'I can only help with J4G inventory and operations data.')
        ->assertJsonPath('tool_trace', []);
});

test('ask endpoint fails clearly when no provider is connected', function () {
    Integration::query()->delete();

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('ai.assistance.ask'), [
            'message' => 'Summarize inventory.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonFragment(['message' => 'No AI provider is connected. Configure one in Integrations.']);
});

test('admin can export csv report', function () {
    $this->actingAs(userWithRole('Admin'))
        ->post(route('ai.assistance.export.csv'), [
            'answer' => 'Low stock summary for active products.',
            'title' => 'Low Stock Report',
            'rows' => [
                [
                    'product' => 'Test Product',
                    'color' => 'Black',
                    'size' => 'M',
                    'available' => 3,
                ],
            ],
        ])
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('admin can export pdf report', function () {
    $this->actingAs(userWithRole('Admin'))
        ->post(route('ai.assistance.export.pdf'), [
            'answer' => "Orders this month look healthy.\nPending: 2",
            'title' => 'Orders Summary',
            'rows' => [
                [
                    'status' => 'pending',
                    'count' => 2,
                ],
            ],
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('staff cannot export assistance reports', function () {
    $this->actingAs(userWithRole('Staff'))
        ->post(route('ai.assistance.export.csv'), [
            'answer' => 'Secret summary',
        ])
        ->assertForbidden();
});
