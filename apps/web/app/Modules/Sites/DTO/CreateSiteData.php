<?php

namespace App\Modules\Sites\DTO;

final readonly class CreateSiteData
{
    public function __construct(
        public string $organizationId,
        public string $folderId,
        public string $createdUserId,
        public string $name,
        public string $url,
        public string $scheme,
        public string $host,
        public string $path,
        public string $status,
        public string $notes,
        public ?int $port,
    )
    {
    }
}
