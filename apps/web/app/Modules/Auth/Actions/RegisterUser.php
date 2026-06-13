<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\DTO\RegisterUserData;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

final class RegisterUser
{
    public function handle(RegisterUserData $data): User
    {
        $user = User::query()->create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);

        event(new Registered($user));

        return $user;
    }
}
