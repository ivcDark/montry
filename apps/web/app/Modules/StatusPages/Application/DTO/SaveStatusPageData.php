<?php

namespace App\Modules\StatusPages\Application\DTO;

final readonly class SaveStatusPageData
{
    /**
     * @param  list<array{monitor_id: int, display_name: string|null}>  $monitors
     */
    public function __construct(
        public int $organizationId,
        public ?int $createdUserId,
        public string $name,
        public string $slug,
        public ?string $description,
        public bool $isPublished,
        public bool $showIncidentHistory,
        public string $accentColor,
        public array $monitors,
    ) {}
}
