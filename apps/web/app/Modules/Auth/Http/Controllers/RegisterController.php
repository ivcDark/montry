<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Application\Onboarding\Actions\CreateAccount;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(
        RegisterRequest $request,
        CreateAccount $createAccount,
    ): RedirectResponse
    {
        $user = $createAccount->handle($request->toData());

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
