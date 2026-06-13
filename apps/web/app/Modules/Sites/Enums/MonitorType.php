<?php

namespace App\Modules\Sites\Enums;

enum MonitorType: string
{
    case Http = 'http';
    case Ssl = 'ssl';
    case Domain = 'domain';
    case Dns = 'dns';
    case RobotsTxt = 'robots_txt';
    case SitemapXml = 'sitemap_xml';
    case ApiEndpoint = 'api_endpoint';
    case TcpPort = 'tcp_port';
    case Ping = 'ping';

    public function label(): string
    {
        return match ($this) {
            self::Http => 'HTTP',
            self::Ssl => 'SSL Certificate',
            self::Domain => 'Domain expiration',
            self::Dns => 'DNS records',
            self::RobotsTxt => 'Robots.txt',
            self::SitemapXml => 'Sitemap.xml',
            self::ApiEndpoint => 'API endpoint',
            self::TcpPort => 'TCP port',
            self::Ping => 'Ping site',
        };
    }
}
