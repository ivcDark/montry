<?php

namespace app\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use app\Modules\Auth\Actions\LoginUser;
use App\Modules\Auth\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
        $loginUser->handle($request->data());

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
