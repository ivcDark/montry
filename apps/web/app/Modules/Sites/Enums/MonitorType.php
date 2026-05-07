<?php

namespace App\Modules\Sites\Enums;

enum MonitorType: string
{
    case Http = 'http';
    case Ping = 'ping';
    case Dns = 'dns';
    case Ssl = 'ssl';

    public function label(): string
    {
        return match ($this) {
            self::Http => 'HTTP',
            self::Ssl => 'SSL Certificate',
            self::Ping => 'Ping site',
            self::Dns => 'DNS server',
        };
    }
}
