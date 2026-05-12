<?php

namespace App\Modules\Monitoring\Application\Services;

use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use InvalidArgumentException;

final class CheckTypeRegistry
{
    /**
     * @var array<string, CheckTypeDefinitionInterface>
     */
    private array $types = [];

    public function register(CheckTypeDefinitionInterface $definition): void
    {
        $this->types[$definition->type()] = $definition;
    }

    public function get(string $type): CheckTypeDefinitionInterface
    {
        if (! isset($this->types[$type])) {
            throw new InvalidArgumentException("Unknown check type: {$type}");
        }

        return $this->types[$type];
    }

    /**
     * @return array<string, CheckTypeDefinitionInterface>
     */
    public function all(): array
    {
        return $this->types;
    }
}
