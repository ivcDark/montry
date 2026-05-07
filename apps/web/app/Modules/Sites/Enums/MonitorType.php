<?php

namespace App\Modules\Sites\Enums;

enum MonitorType: string
{
    case Http = 'http';
    case Ping = 'ping';
    case Dns = 'dns';
    case Ssl = 'ssl';
}
