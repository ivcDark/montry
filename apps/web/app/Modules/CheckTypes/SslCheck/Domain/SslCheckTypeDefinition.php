<?php

namespace App\Modules\CheckTypes\SslCheck\Domain;

use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use InvalidArgumentException;

final class SslCheckTypeDefinition implements CheckTypeDefinitionInterface
{
    public function type(): string
    {
        return 'ssl';
    }

    public function label(): string
    {
        return 'SSL Certificate';
    }

    public function defaultSettings(): array
    {
        return [
            'domain' => null,
            'port' => 443,
            'warning_days' => [30, 14, 7, 3, 1],
        ];
    }

    public function defaultExpected(): array
    {
        return [
            'valid' => true,
        ];
    }

    public function validateSettings(array $settings): array
    {
        $settings = array_replace($this->defaultSettings(), $settings);
        $domain = trim((string) $settings['domain']);

        if ($domain === '') {
            throw new InvalidArgumentException('SSL domain is required.');
        }

        $port = (int) $settings['port'];

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('SSL port must be between 1 and 65535.');
        }

        return [
            'domain' => strtolower($domain),
            'port' => $port,
            'warning_days' => $this->normalizeWarningDays($settings['warning_days']),
        ];
    }

    public function validateExpected(array $expected): array
    {
        $expected = array_replace($this->defaultExpected(), $expected);

        return [
            'valid' => (bool) $expected['valid'],
        ];
    }

    public function normalizeSettings(array $settings): array
    {
        return $this->validateSettings($settings);
    }

    public function normalizeWorkerResult(array $result): array
    {
        return [
            'valid' => (bool) ($result['valid'] ?? false),
            'issued_at' => $result['issued_at'] ?? null,
            'expires_at' => $result['expires_at'] ?? null,
            'days_until_expiration' => isset($result['days_until_expiration']) ? (int) $result['days_until_expiration'] : null,
            'issuer' => $result['issuer'] ?? null,
            'subject' => $result['subject'] ?? null,
            'serial_number' => isset($result['serial_number']) ? (string) $result['serial_number'] : null,
            'dns_names' => is_array($result['dns_names'] ?? null) ? array_values($result['dns_names']) : [],
            'chain_length' => isset($result['chain_length']) ? (int) $result['chain_length'] : null,
            'error_code' => $result['error']['code'] ?? $result['error_code'] ?? null,
            'error_message' => $result['error']['message'] ?? $result['error_message'] ?? null,
        ];
    }

    public function resolveStatus(array $normalizedResult, array $expected): string
    {
        $expected = $this->validateExpected($expected);

        if (($normalizedResult['error_code'] ?? null) !== null) {
            return 'failure';
        }

        if ($expected['valid'] && ! ($normalizedResult['valid'] ?? false)) {
            return 'failure';
        }

        $daysUntilExpiration = $normalizedResult['days_until_expiration'] ?? null;

        if ($daysUntilExpiration !== null && $daysUntilExpiration <= 0) {
            return 'failure';
        }

        return 'success';
    }

    private function normalizeWarningDays(mixed $warningDays): array
    {
        if (! is_array($warningDays) || $warningDays === []) {
            throw new InvalidArgumentException('SSL warning_days must be a non-empty array.');
        }

        $warningDays = array_values(array_unique(array_map('intval', $warningDays)));
        rsort($warningDays);

        foreach ($warningDays as $day) {
            if ($day < 1 || $day > 365) {
                throw new InvalidArgumentException('SSL warning days must be between 1 and 365.');
            }
        }

        return $warningDays;
    }
}
