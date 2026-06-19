<?php

namespace App\Modules\Sites\DTO;

final readonly class CreateFolderData
{
    public function __construct(
        public int $organizationId,
        public string $name,
        public ?string $color,
        public ?int $sortOrder,
        public ?string $comment = null,
    )
    {
    }
}
