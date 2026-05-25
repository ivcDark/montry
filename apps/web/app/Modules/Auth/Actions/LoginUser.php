<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\DTO\LoginUserData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    public function handle(LoginUserData $data): void
    {
        if (! Auth::attempt($data->credentials(), $data->remember)) {
            throw ValidationException::withMessages([
                'email' => 'Неверная пара почта/пароль',
            ]);
        }

        if ((bool) Auth::user()?->is_blocked) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Учетная запись заблокирована.',
            ]);
        }
    }
}
