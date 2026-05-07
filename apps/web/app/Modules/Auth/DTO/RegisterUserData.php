<?php

namespace app\Modules\Auth\DTO;

final class RegisterUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    )
    {
    }
}
