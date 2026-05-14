<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\LoginUser;
use App\Modules\Auth\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, LoginUser $loginUser): RedirectResponse
    {
        $loginUser->handle($request->toData());

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard.index', absolute: false));
    }
}
