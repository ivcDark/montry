<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\LoginUser;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Billing\Application\Services\PlanIntentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LoginController extends Controller
{
    public function create(Request $request, PlanIntentService $planIntent): Response
    {
        $planIntent->captureFromRequest($request);

        return Inertia::render('Auth/Login', [
            'intendedPlanCode' => $planIntent->get($request),
        ]);
    }

    public function store(LoginRequest $request, LoginUser $loginUser): RedirectResponse
    {
        $loginUser->handle($request->toData());

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard.index', absolute: false));
    }
}
