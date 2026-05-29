<?php

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $plans = Plan::query()
        ->with('limits')
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('price_cents')
        ->get()
        ->map(fn (Plan $plan): array => [
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'sort_order' => $plan->sort_order,
            'limits' => $plan->limits
                ->mapWithKeys(fn ($limit): array => [$limit->key => $limit->value])
                ->all(),
        ])
        ->values();

    return Inertia::render('Welcome', [
        'plans' => $plans,
    ]);
})->name('home');
