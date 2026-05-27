<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\ClickHouse;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class ClickHouseClient
{
    public function __construct(
        private string $baseUrl,
        private string $database,
        private string $username,
        private string $password,
        private float $timeoutSeconds,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertJsonEachRow(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $body = implode("\n", array_map(
            static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $rows,
        ));

        $query = sprintf(
            'INSERT INTO %s.%s FORMAT JSONEachRow',
            $this->quoteIdentifier($this->database),
            $this->quoteIdentifier($table),
        );

        try {
            Http::withBasicAuth($this->username, $this->password)
                ->timeout($this->timeoutSeconds)
                ->withBody($body, 'application/x-ndjson')
                ->post($this->baseUrl . '/?' . http_build_query(['query' => $query]))
                ->throw();
        } catch (ConnectionException | RequestException $exception) {
            throw new RuntimeException('ClickHouse insert failed: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function quoteIdentifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }
}
