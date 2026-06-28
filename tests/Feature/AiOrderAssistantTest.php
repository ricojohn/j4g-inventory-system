<?php

use App\Enums\AiOrderDraftStatus;
use App\Enums\CustomerOrderStatus;
use App\Models\AiOrderDraft;
use App\Models\CustomerOrder;
use App\Models\Integration;
use App\Models\ProductColorSize;
use App\Services\AiOrderDraftService;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
    createTestIntegration('openai');
});

function fakeOpenAiParsedPayload(array $overrides = []): array
{
    return array_merge([
        'intent' => 'create_order',
        'customer_name' => 'Juan Dela Cruz',
        'customer_contact' => '@juan',
        'customer_source' => 'facebook',
        'items' => [
            [
                'product_name' => 'Test Product',
                'color_name' => 'Black',
                'size_name' => 'M',
                'quantity' => 5,
                'notes' => null,
            ],
        ],
        'deadline' => 'Friday',
        'notes' => 'Need sa Friday',
        'missing_fields' => [],
        'confidence' => 0.9,
    ], $overrides);
}

function fakeOpenAiResponses(array $parsedOverrides = []): void
{
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode(fakeOpenAiParsedPayload($parsedOverrides)),
                    ],
                ],
            ],
        ], 200),
        'https://api.openai.com/v1/models' => Http::response(['data' => []], 200),
        'api.openai.com/*' => Http::response(['data' => []], 200),
    ]);
}

function fakeGeminiResponses(array $parsedOverrides = [], bool $wrapInFence = false): void
{
    $json = json_encode(fakeOpenAiParsedPayload($parsedOverrides));
    $content = $wrapInFence ? "```json\n{$json}\n```" : $json;

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $content],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);
}

test('staff can access ai order assistant page', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('ai.order-assistant.index'))
        ->assertOk();
});

test('viewer cannot access ai order assistant page', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->get(route('ai.order-assistant.index'))
        ->assertForbidden();
});

test('viewer cannot analyze conversation', function () {
    fakeOpenAiResponses();

    $this->actingAs(userWithRole('Viewer'))
        ->postJson(route('ai.order-assistant.analyze'), [
            'raw_message' => 'Boss pa order po 5 pcs test product black M',
        ])
        ->assertForbidden();
});

test('analyze endpoint creates ai order draft with openai', function () {
    fakeOpenAiResponses();
    createTestCell(20);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.analyze'), [
            'raw_message' => 'Boss pa order po 5 pcs test product black M',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(AiOrderDraft::query()->count())->toBe(1);

    $draft = AiOrderDraft::query()->first();

    expect($draft->status)->toBe(AiOrderDraftStatus::Draft)
        ->and($draft->parsed_json['intent'])->toBe('create_order')
        ->and($draft->matched_json['items'])->toHaveCount(1);
});

test('analyze endpoint creates ai order draft with gemini', function () {
    createTestIntegration('gemini', [
        'settings' => [
            'default_model' => 'gemini-1.5-flash',
            'is_default_provider' => true,
        ],
    ]);

    Integration::openAi()->update([
        'settings' => [
            'default_model' => 'gpt-4o-mini',
            'is_default_provider' => false,
        ],
    ]);

    fakeGeminiResponses([], true);
    createTestCell(20);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.analyze'), [
            'raw_message' => 'Boss pa order po 5 pcs test product black M',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(AiOrderDraft::query()->count())->toBe(1);
});

test('assistant uses default provider', function () {
    createTestIntegration('gemini', [
        'settings' => [
            'default_model' => 'gemini-1.5-flash',
            'is_default_provider' => true,
        ],
    ]);

    Integration::openAi()->update([
        'settings' => [
            'default_model' => 'gpt-4o-mini',
            'is_default_provider' => false,
        ],
    ]);

    fakeGeminiResponses();
    createTestCell(20);

    Http::preventStrayRequests();

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.analyze'), [
            'raw_message' => 'Order test product black M x5',
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));
});

test('invalid json from provider does not create draft', function () {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'not valid json at all',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.analyze'), [
            'raw_message' => 'Test message',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(AiOrderDraft::query()->count())->toBe(0);
});

test('analyze fails when no ai provider is connected', function () {
    Integration::query()->update([
        'credentials' => null,
        'status' => 'inactive',
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.analyze'), [
            'raw_message' => 'Test message',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('draft conversion creates customer order', function () {
    $cell = createTestCell(20);
    $product = $cell->color->product;

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'customer_name' => 'Juan Dela Cruz',
        'customer_source' => 'facebook',
        'matched_json' => [
            'items' => [
                [
                    'matched' => true,
                    'cell_id' => $cell->id,
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'available_stock' => 20,
                ],
            ],
        ],
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.drafts.convert', $draft), [
            'customer_name' => 'Juan Dela Cruz',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity' => 5],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $draft->refresh();

    expect(CustomerOrder::query()->count())->toBe(1)
        ->and($draft->status)->toBe(AiOrderDraftStatus::Converted)
        ->and($draft->customer_order_id)->not->toBeNull();
});

test('converted order triggers reservation workflow', function () {
    $cell = createTestCell(20);

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.drafts.convert', $draft), [
            'customer_name' => 'Reserved Customer',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity' => 5],
            ],
        ])
        ->assertOk();

    $order = CustomerOrder::query()->first();
    $cell->refresh();

    expect($order->status)->toBe(CustomerOrderStatus::Reserved)
        ->and($order->items->first()->quantity_reserved)->toBe(5)
        ->and($cell->reserved_quantity)->toBe(5);
});

test('unmatched ai items require review before conversion', function () {
    $cell = createTestCell(20);

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'matched_json' => [
            'items' => [
                [
                    'matched' => false,
                    'status' => 'needs_review',
                    'cell_id' => null,
                    'quantity' => 5,
                ],
            ],
        ],
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.drafts.convert', $draft), [
            'customer_name' => 'Needs Review Customer',
            'customer_source' => 'facebook',
            'items' => [],
        ])
        ->assertStatus(422);

    expect(CustomerOrder::query()->count())->toBe(0)
        ->and($draft->fresh()->status)->toBe(AiOrderDraftStatus::Draft);
});

test('analyze marks unmatched inventory items as needs review', function () {
    fakeOpenAiResponses([
        'items' => [
            [
                'product_name' => 'Unknown Product XYZ',
                'color_name' => 'Purple',
                'size_name' => 'XXL',
                'quantity' => 3,
                'notes' => null,
            ],
        ],
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.analyze'), [
            'raw_message' => 'Order unknown product',
        ])
        ->assertOk();

    $draft = AiOrderDraft::query()->first();
    $item = $draft->matched_json['items'][0];

    expect($item['status'])->toBe('needs_review')
        ->and($item['matched'])->toBeFalse();
});

test('staff can reject draft', function () {
    $draft = AiOrderDraft::factory()->create([
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.drafts.reject', $draft))
        ->assertOk();

    expect($draft->fresh()->status)->toBe(AiOrderDraftStatus::Rejected);
});

test('staff can analyze but not manage integrations', function () {
    fakeOpenAiResponses();

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.analyze'), [
            'raw_message' => 'Boss pa order po 5 pcs test product black M',
        ])
        ->assertOk();

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.set-provider'), [
            'provider' => 'openai',
        ])
        ->assertForbidden();
});

test('convert creates exactly the items sent in request payload', function () {
    $cell = createTestCell(20);
    $extraColor = attachTestColor($cell->color->product, 'Gray', 2);
    $extraCell = ProductColorSize::query()
        ->where('product_color_id', $extraColor->id)
        ->where('product_size_id', $cell->product_size_id)
        ->firstOrFail();

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'customer_name' => 'Juan Dela Cruz',
        'customer_source' => 'facebook',
        'matched_json' => [
            'items' => [
                ['matched' => true, 'cell_id' => $cell->id, 'quantity' => 5],
                ['matched' => true, 'cell_id' => $extraCell->id, 'quantity' => 3],
                ['matched' => true, 'cell_id' => $cell->id, 'quantity' => 10],
                ['matched' => true, 'cell_id' => $extraCell->id, 'quantity' => 7],
            ],
        ],
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.drafts.convert', $draft), [
            'customer_name' => 'Juan Dela Cruz',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity' => 6],
                ['product_color_size_id' => $cell->id, 'quantity' => 15],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $order = CustomerOrder::query()->first();

    expect($order->items)->toHaveCount(2)
        ->and($order->items->pluck('product_color_size_id')->unique())->toHaveCount(1);
});

test('color matching prefers exact red black over gray black', function () {
    $product = createTestProduct(['name' => 'Reversible Adult']);
    attachTestSize($product, 'Regular', 1);
    $redBlack = attachTestColor($product, 'RED / BLACK', 1);
    attachTestColor($product, 'GRAY / BLACK', 2);

    $redCell = ProductColorSize::query()
        ->where('product_color_id', $redBlack->id)
        ->firstOrFail();

    $matched = app(AiOrderDraftService::class)->matchParsedItemsToInventory([
        'items' => [
            [
                'product_name' => 'Reversible Adult',
                'color_name' => 'RED / BLACK',
                'size_name' => 'Regular',
                'quantity' => 6,
            ],
        ],
    ]);

    expect($matched['items'][0]['matched'])->toBeTrue()
        ->and($matched['items'][0]['cell_id'])->toBe($redCell->id)
        ->and($matched['items'][0]['color_name'])->toBe('RED / BLACK');
});

test('ambiguous single token color black does not auto match dual tone colors', function () {
    $product = createTestProduct(['name' => 'Reversible Adult']);
    attachTestSize($product, 'Regular', 1);
    attachTestColor($product, 'RED / BLACK', 1);
    attachTestColor($product, 'GRAY / BLACK', 2);

    $matched = app(AiOrderDraftService::class)->matchParsedItemsToInventory([
        'items' => [
            [
                'product_name' => 'Reversible Adult',
                'color_name' => 'BLACK',
                'size_name' => 'Regular',
                'quantity' => 5,
            ],
        ],
    ]);

    expect($matched['items'][0]['matched'])->toBeFalse();
});
