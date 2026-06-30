<?php

namespace App\Modules\Auth\DTO;

final readonly class VkUserData
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
    ) {}
}
