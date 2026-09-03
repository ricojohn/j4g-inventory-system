<?php

namespace App\Services\Facebook;

use App\Models\FacebookPage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FacebookGraphClient
{
    /** @return array<string, mixed> */
    public function sendText(FacebookPage $page, string $psid, string $text): array
    {
        try {
            $accessToken = $page->access_token;
        } catch (DecryptException) {
            throw new \RuntimeException('The saved Facebook Page token cannot be decrypted. Re-enter it in Facebook Pages.', previous: null);
        }

        if (blank($accessToken)) {
            throw new \RuntimeException('Facebook Page access token is not configured.');
        }

        $response = Http::connectTimeout(5)
            ->timeout((int) config('services.facebook.request_timeout', 15))
            ->retry(2, 250, throw: false)
            ->withToken($accessToken)
            ->post(sprintf('https://graph.facebook.com/%s/me/messages', $page->graph_api_version), [
                'recipient' => ['id' => $psid],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => str($text)->limit(2000)->toString()],
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }
}
