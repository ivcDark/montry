<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\StartRegistrationVerification;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use Illuminate\Http\RedirectResponse;
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
        StartRegistrationVerification $startRegistrationVerification,
    ): RedirectResponse {
        $user = $startRegistrationVerification->handle($request->toData());

        $request->session()->put('pending_registration_user_id', $user->id);

        return redirect()->route('register.verify-code');
    }
}
