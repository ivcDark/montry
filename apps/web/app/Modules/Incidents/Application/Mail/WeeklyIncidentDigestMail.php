<?php

namespace App\Modules\Incidents\Application\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class WeeklyIncidentDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{site:string,type:string,started_at:string,duration:string}> $incidents
     */
    public function __construct(
        public readonly string $organizationName,
        public readonly CarbonImmutable $weekStart,
        public readonly CarbonImmutable $weekEnd,
        public readonly int $incidentCount,
        public readonly array $incidents,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Montry: отчет по инцидентам за неделю')
            ->view('emails.incidents.weekly-digest');
    }
}
