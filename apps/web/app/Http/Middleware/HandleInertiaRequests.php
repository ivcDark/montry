<?php

namespace App\Http\Middleware;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $successMessage = $request->session()->get('success');
        $errorMessage = $request->session()->get('error');

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                    ]
                    : null,
            ],

            'flash' => [
                'success' => $successMessage,
                'error' => $errorMessage,
                'token' => $successMessage !== null || $errorMessage !== null
                    ? (string) Str::uuid()
                    : null,
            ],

            'billing' => fn () => $this->billingSummary($request),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function billingSummary(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $organization = $user->organizations()->first(['organizations.id']);

        if ($organization === null) {
            return null;
        }

        $subscription = Subscription::query()
            ->with(['plan.limits', 'items'])
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();

        $plan = $subscription?->plan ?? Plan::query()
            ->with('limits')
            ->where('code', 'free')
            ->first();

        $limits = app(LimitChecker::class);
        $usage = $limits->usageSummary((int) $organization->id);

        return [
            'plan' => $plan === null ? null : [
                'code' => $plan->code,
                'name' => $plan->name,
            ],
            'monitors' => [
                'current' => $usage['monitors']['current'] ?? 0,
                'limit' => $usage['monitors']['limit'] ?? null,
            ],
            'sites' => [
                'current' => $usage['sites']['current'] ?? 0,
                'limit' => $usage['sites']['limit'] ?? null,
                'extra_packs' => $usage['sites']['extra_packs'] ?? 0,
            ],
            'addons' => $usage['paid_checks'] ?? [],
            'telegram_available' => $usage['telegram_available'] ?? false,
            'minimum_check_interval_seconds' => $usage['minimum_check_interval_seconds'] ?? null,
            'history_retention_days' => $usage['history_retention_days'] ?? null,
        ];
    }

    private function planLimit(?Plan $plan, string $key): ?int
    {
        $value = $plan?->limits->firstWhere('key', $key)?->value;

        if (! is_array($value) || ! array_key_exists('limit', $value)) {
            return null;
        }

        $limit = $value['limit'];

        return is_numeric($limit) ? (int) $limit : null;
    }
}
