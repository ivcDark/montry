<?php

namespace App\Modules\Notifications\Infrastructure\Providers;

use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Monitoring\Domain\Events\DomainExpiring;
use App\Modules\Monitoring\Domain\Events\SslExpiring;
use App\Modules\Notifications\Application\Listeners\SendDomainExpiringNotification;
use App\Modules\Notifications\Application\Listeners\SendIncidentOpenedNotification;
use App\Modules\Notifications\Application\Listeners\SendIncidentResolvedNotification;
use App\Modules\Notifications\Application\Listeners\SendSslExpiringNotification;
use App\Modules\Notifications\Application\Senders\EmailNotificationSender;
use App\Modules\Notifications\Application\Senders\TelegramNotificationSender;
use App\Modules\Notifications\Application\Services\NotificationDispatcher;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use App\Modules\Observability\Application\Services\DeadLetterRecorder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class NotificationsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationDispatcher::class, function ($app): NotificationDispatcher {
            return new NotificationDispatcher(
                $app->make(\App\Modules\Notifications\Application\Services\NotificationRecipientResolver::class),
                [
                    $app->make(EmailNotificationSender::class),
                    $app->make(TelegramNotificationSender::class),
                ],
                $app->make(BusinessEventRecorder::class),
                $app->make(DeadLetterRecorder::class),
            );
        });
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../../Presentation/Routes/web.php');

        Event::listen(IncidentOpened::class, SendIncidentOpenedNotification::class);
        Event::listen(IncidentResolved::class, SendIncidentResolvedNotification::class);
        Event::listen(SslExpiring::class, SendSslExpiringNotification::class);
        Event::listen(DomainExpiring::class, SendDomainExpiringNotification::class);
    }
}
