<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Sites\DTO\CreateMonitorData;
use App\Modules\Sites\Enums\MonitorType;
use App\Modules\Sites\Models\SiteMonitor;
use App\Modules\Sites\Models\Site;

final readonly class CreateDefaultHttpMonitorAction
{
    public function __construct(
        private CreateMonitorAction $createMonitor,
    ) {
    }

    public function execute(Site $site): SiteMonitor
    {
        return $this->createMonitor->execute(
            site: $site,
            data: new CreateMonitorData(
                name: 'HTTP check',
                type: MonitorType::Http,
                isEnabled: true,
                intervalSeconds: 60,
                timeoutMs: 10000,
                settings: [
                    'method' => 'GET',
                    'path' => '/',
                    'expected_status_min' => 200,
                    'expected_status_max' => 399,
                    'follow_redirects' => true,
                ],
            ),
        );
    }
}
