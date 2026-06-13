<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Logging;

use Monolog\Formatter\JsonFormatter;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

final readonly class ConfigureStructuredLogging
{
    public function __construct(
        private ContextProcessor $contextProcessor,
    ) {
    }

    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Logger) {
            return;
        }

        foreach ($monolog->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter(appendNewline: true));
        }

        $monolog->pushProcessor(new PsrLogMessageProcessor());
        $monolog->pushProcessor($this->contextProcessor);
    }
}
