<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Facebook\FacebookWebhookIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FacebookWebhookController extends Controller
{
    public function __construct(private FacebookWebhookIngestService $ingestService) {}

    public function verify(Request $request): Response
    {
        if ($request->query('hub_mode') !== 'subscribe'
            || ! hash_equals((string) config('services.facebook.verify_token'), (string) $request->query('hub_verify_token'))) {
            abort(403, 'Facebook webhook verification failed.');
        }

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'object' => ['required', 'in:page'],
            'entry' => ['required', 'array', 'max:100'],
            'entry.*.id' => ['required', 'string', 'max:255'],
            'entry.*.messaging' => ['sometimes', 'array', 'max:100'],
        ]);

        $accepted = $this->ingestService->ingest($payload);

        return response()->json(['accepted' => $accepted]);
    }
}
