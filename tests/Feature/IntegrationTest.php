<?php

use App\Models\Integration;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('admin can access integrations page', function () {
    $this->actingAs(userWithRole('Admin'))
        ->get(route('integrations.index'))
        ->assertOk();
});

test('manager can access integrations page', function () {
    $this->actingAs(userWithRole('Manager'))
        ->get(route('integrations.index'))
        ->assertOk();
});

test('staff cannot manage integrations', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('integrations.index'))
        ->assertForbidden();
});

test('viewer cannot manage integrations', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->get(route('integrations.index'))
        ->assertForbidden();
});

test('openai credentials are stored encrypted', function () {
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response(['data' => []], 200),
        'api.openai.com/*' => Http::response(['data' => []], 200),
    ]);

    $apiKey = 'sk-test-secret-key-12345';

    $this->actingAs(userWithRole('Admin'))
        ->putJson(route('integrations.update', 'openai'), [
            'api_key' => $apiKey,
            'model' => 'gpt-4o-mini',
        ])
        ->assertOk();

    $raw = DB::table('integrations')
        ->where('provider', 'openai')
        ->value('credentials');

    expect($raw)->not->toContain($apiKey);

    $integration = Integration::openAi();

    expect($integration->apiKey())->toBe($apiKey);
});

test('gemini credentials are stored encrypted', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'OK']]]],
            ],
        ], 200),
    ]);

    $apiKey = 'gemini-test-secret-key-12345';

    $this->actingAs(userWithRole('Admin'))
        ->putJson(route('integrations.update', 'gemini'), [
            'api_key' => $apiKey,
            'model' => 'gemini-1.5-flash',
        ])
        ->assertOk();

    $raw = DB::table('integrations')
        ->where('provider', 'gemini')
        ->value('credentials');

    expect($raw)->not->toContain($apiKey);

    $integration = Integration::gemini();

    expect($integration->apiKey())->toBe($apiKey);
});

test('openai key is never returned in plain text', function () {
    createTestIntegration('openai', ['credentials' => ['api_key' => 'sk-hidden-key']]);

    $response = $this->actingAs(userWithRole('Admin'))
        ->putJson(route('integrations.update', 'openai'), [
            'model' => 'gpt-4o-mini',
        ])
        ->assertOk()
        ->json();

    expect($response)->not->toHaveKey('api_key')
        ->and(json_encode($response))->not->toContain('sk-hidden-key');
});

test('gemini key is never returned in plain text', function () {
    createTestIntegration('gemini', ['credentials' => ['api_key' => 'gemini-hidden-key']]);

    $response = $this->actingAs(userWithRole('Admin'))
        ->putJson(route('integrations.update', 'gemini'), [
            'model' => 'gemini-1.5-flash',
        ])
        ->assertOk()
        ->json();

    expect($response)->not->toHaveKey('api_key')
        ->and(json_encode($response))->not->toContain('gemini-hidden-key');
});

test('admin can configure gemini integration', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'OK']]]],
            ],
        ], 200),
    ]);

    $this->actingAs(userWithRole('Admin'))
        ->putJson(route('integrations.update', 'gemini'), [
            'api_key' => 'gemini-test-key',
            'model' => 'gemini-2.0-flash',
        ])
        ->assertOk()
        ->assertJsonPath('provider', 'gemini');

    $integration = Integration::gemini();

    expect($integration->isConnected())->toBeTrue()
        ->and($integration->defaultModel())->toBe('gemini-2.0-flash');
});

test('admin can test openai connection', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['data' => []], 200),
    ]);

    createTestIntegration('openai');

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('integrations.test', 'openai'))
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('admin can test gemini connection', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'OK']]]],
            ],
        ], 200),
    ]);

    createTestIntegration('gemini');

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('integrations.test', 'gemini'))
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('admin can disconnect openai integration', function () {
    createTestIntegration('openai');

    $this->actingAs(userWithRole('Admin'))
        ->deleteJson(route('integrations.disconnect', 'openai'))
        ->assertOk();

    $integration = Integration::openAi()->fresh();

    expect($integration->status)->toBe('inactive')
        ->and($integration->credentials)->toBeNull();
});

test('only one provider can be default', function () {
    createTestIntegration('openai', [
        'settings' => [
            'default_model' => 'gpt-4o-mini',
            'is_default_provider' => true,
        ],
    ]);

    createTestIntegration('gemini', [
        'settings' => [
            'default_model' => 'gemini-1.5-flash',
            'is_default_provider' => false,
        ],
    ]);

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('integrations.default', 'gemini'))
        ->assertOk();

    expect(Integration::openAi()->fresh()->isDefault())->toBeFalse()
        ->and(Integration::gemini()->fresh()->isDefault())->toBeTrue();
});

test('credentials encrypted under another app key are treated as disconnected and can be replaced', function () {
    $integration = createTestIntegration('openai');
    DB::table('integrations')->where('id', $integration->id)->update(['credentials' => 'invalid-encrypted-payload']);

    $this->actingAs(userWithRole('Admin'))
        ->get(route('integrations.index'))
        ->assertOk();

    expect($integration->fresh()->isConnected())->toBeFalse()
        ->and($integration->fresh()->apiKey())->toBeNull();
});
