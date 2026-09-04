<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyFacebookWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.facebook.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        if ($secret === '' || ! str_starts_with($signature, 'sha256=')) {
            abort(401, 'Invalid Facebook webhook signature.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid Facebook webhook signature.');
        }

        return $next($request);
    }
}
