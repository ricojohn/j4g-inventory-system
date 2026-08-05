<?php

use App\Support\OrderOpsPresenter;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Collection;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

it('shows the today dashboard for authorized users', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Needs attention')
        ->assertSee('Reservation pulse')
        ->assertSee('Stock shortages');
});

it('builds an attention feed collection', function () {
    expect(app(OrderOpsPresenter::class)->attentionFeed())->toBeInstanceOf(Collection::class);
});
