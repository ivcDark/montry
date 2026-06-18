<?php

namespace App\Modules\CheckTypes\DnsCheck\Domain;

use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use InvalidArgumentException;

final class DnsCheckTypeDefinition implements CheckTypeDefinitionInterface
{
    private const ALLOWED_RECORD_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT'];

    public function type(): string
    {
        return 'dns';
    }

    public function label(): string
    {
        return 'DNS';
    }

    public function defaultSettings(): array
    {
        return [
            'domain' => null,
            'record_types' => ['A', 'AAAA'],
            'nameservers' => [],
            'warn_on_change' => false,
        ];
    }

    public function defaultExpected(): array
    {
        return [
            'resolves' => true,
            'min_records' => 1,
        ];
    }

    public function validateSettings(array $settings): array
    {
        $settings = array_replace($this->defaultSettings(), $settings);
        $domain = trim((string) $settings['domain']);

        if ($domain === '') {
            throw new InvalidArgumentException('DNS domain is required.');
        }

        return [
            'domain' => strtolower($domain),
            'record_types' => $this->normalizeRecordTypes($settings['record_types']),
            'nameservers' => $this->normalizeNameservers($settings['nameservers']),
            'warn_on_change' => (bool) $settings['warn_on_change'],
        ];
    }

    public function validateExpected(array $expected): array
    {
        $expected = array_replace($this->defaultExpected(), $expected);
        $minRecords = (int) $expected['min_records'];

        if ($minRecords < 0) {
            throw new InvalidArgumentException('DNS min_records must be zero or greater.');
        }

        return [
            'resolves' => (bool) $expected['resolves'],
            'min_records' => $minRecords,
        ];
    }

    public function normalizeSettings(array $settings): array
    {
        return $this->validateSettings($settings);
    }

    public function normalizeWorkerResult(array $result): array
    {
        $records = is_array($result['records'] ?? null) ? array_values($result['records']) : [];

        return [
            'resolved' => (bool) ($result['resolved'] ?? $result['resolves'] ?? count($records) > 0),
            'domain' => $result['domain'] ?? null,
            'record_types' => is_array($result['record_types'] ?? null) ? array_values($result['record_types']) : [],
            'records' => $records,
            'response_time_ms' => isset($result['response_time_ms']) ? (int) $result['response_time_ms'] : null,
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

        if ($expected['resolves'] && ! ($normalizedResult['resolved'] ?? false)) {
            return 'failure';
        }

        $recordsCount = count($normalizedResult['records'] ?? []);

        if ($recordsCount < $expected['min_records']) {
            return 'failure';
        }

        return 'success';
    }

    private function normalizeRecordTypes(mixed $recordTypes): array
    {
        if (! is_array($recordTypes) || $recordTypes === []) {
            throw new InvalidArgumentException('DNS record_types must be a non-empty array.');
        }

        $recordTypes = array_values(array_unique(array_map(
            static fn (mixed $recordType): string => strtoupper(trim((string) $recordType)),
            $recordTypes,
        )));

        foreach ($recordTypes as $recordType) {
            if (! in_array($recordType, self::ALLOWED_RECORD_TYPES, true)) {
                throw new InvalidArgumentException('DNS record type is not supported.');
            }
        }

        return $recordTypes;
    }

    private function normalizeNameservers(mixed $nameservers): array
    {
        if ($nameservers === null) {
            return [];
        }

        if (! is_array($nameservers)) {
            throw new InvalidArgumentException('DNS nameservers must be an array.');
        }

        return array_values(array_filter(array_map(
            static fn (mixed $nameserver): string => trim((string) $nameserver),
            $nameservers,
        ), static fn (string $nameserver): bool => $nameserver !== ''));
    }
}
