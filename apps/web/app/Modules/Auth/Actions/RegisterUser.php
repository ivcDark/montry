<?php

namespace app\Modules\Auth\Actions;

use App\Models\User;
use app\Modules\Auth\DTO\RegisterUserData;
use Illuminate\Auth\Events\Registered;

final class RegisterUser
{
    public function handle(RegisterUserData $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);

        event(new Registered($user));

        return $user;
    }
}
