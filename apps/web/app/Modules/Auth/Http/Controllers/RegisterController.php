<?php

namespace app\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use app\Modules\Auth\Actions\RegisterUser;
use app\Modules\Auth\Http\Requests\RegisterRequest;
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

    public function store(RegisterRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle($request->data());

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
