<?php

namespace app\Modules\Auth\DTO;

final class LoginUserData
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
    )
    {
    }

    public function credentials(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
