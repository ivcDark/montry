<?php

namespace app\Modules\Auth\Actions;

use app\Modules\Auth\DTO\LoginUserData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    public function handle(LoginUserData $data): void
    {
        if (! Auth::attempt($data->credentials(), $data->remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }
    }
}
