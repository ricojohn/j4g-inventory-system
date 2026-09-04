<?php

namespace App\Services\Facebook;

class FacebookWebhookEventKey
{
    /** @param array<string, mixed> $event */
    public function for(array $event): string
    {
        $messageId = data_get($event, 'message.mid');

        if (is_string($messageId) && $messageId !== '') {
            return 'message:'.$messageId;
        }

        $postbackMid = data_get($event, 'postback.mid');

        if (is_string($postbackMid) && $postbackMid !== '') {
            return 'postback:'.$postbackMid;
        }

        $normalized = $event;
        ksort($normalized);

        return 'event:'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
