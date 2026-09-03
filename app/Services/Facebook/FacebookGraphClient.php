<?php

namespace App\Services\Facebook;

use App\Models\FacebookPage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class FacebookGraphClient
{
    /** @return array<string, mixed> */
    public function listConversations(FacebookPage $page, int $limit = 25, ?string $after = null): array
    {
        $response = $this->request($page)
            ->get(sprintf('https://graph.facebook.com/%s/%s/conversations', $page->graph_api_version, $page->page_id), array_filter([
                'platform' => 'MESSENGER',
                'fields' => 'id,updated_time,can_reply,message_count,senders',
                'limit' => $limit,
                'after' => $after,
            ], static fn ($value) => filled($value)));

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    /** @return array<string, mixed> */
    public function listConversationMessages(FacebookPage $page, string $conversationId, int $limit = 25, ?string $after = null): array
    {
        $response = $this->request($page)
            ->get(sprintf('https://graph.facebook.com/%s/%s/messages', $page->graph_api_version, $conversationId), array_filter([
                'limit' => $limit,
                'after' => $after,
            ], static fn ($value) => filled($value)));

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    /** @return array<string, mixed> */
    public function getMessage(FacebookPage $page, string $messageId): array
    {
        $response = $this->request($page)
            ->get(sprintf('https://graph.facebook.com/%s/%s', $page->graph_api_version, $messageId), [
                'fields' => 'id,created_time,from,to,message,attachments,is_unsupported,story',
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    /** @return array<string, mixed> */
    public function sendText(FacebookPage $page, string $psid, string $text): array
    {
        $response = $this->request($page)
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

    private function request(FacebookPage $page)
    {
        try {
            $accessToken = $page->access_token;
        } catch (DecryptException) {
            throw new \RuntimeException('The saved Facebook Page token cannot be decrypted. Re-enter it in Facebook Pages.', previous: null);
        }

        if (blank($accessToken)) {
            throw new \RuntimeException('Facebook Page access token is not configured.');
        }

        return Http::connectTimeout(5)
            ->timeout((int) config('services.facebook.request_timeout', 15))
            ->retry(2, 250, throw: false)
            ->withToken($accessToken);
    }
}
