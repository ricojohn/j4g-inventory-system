<?php

use App\Http\Controllers\Webhooks\FacebookWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/facebook', [FacebookWebhookController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('webhooks.facebook.verify');

Route::post('/webhooks/facebook', [FacebookWebhookController::class, 'receive'])
    ->middleware(['facebook.signature', 'throttle:120,1'])
    ->name('webhooks.facebook.receive');
