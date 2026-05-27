<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Infrastructure\Persistence\Models\DeadLetter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DeadLetterIndexController
{
    public function __invoke(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();

        $query = DeadLetter::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->latest('failed_at');

        $deadLetters = $query
            ->limit(100)
            ->get()
            ->map(fn (DeadLetter $deadLetter): array => [
                'id' => $deadLetter->id,
                'event_id' => $deadLetter->event_id,
                'source' => $deadLetter->source,
                'type' => $deadLetter->type,
                'status' => $deadLetter->status,
                'recoverable' => $deadLetter->recoverable,
                'organization_id' => $deadLetter->organization_id,
                'subject_type' => $deadLetter->subject_type,
                'subject_id' => $deadLetter->subject_id,
                'error_class' => $deadLetter->error_class,
                'error_message' => $deadLetter->error_message,
                'attempts' => $deadLetter->attempts,
                'max_attempts' => $deadLetter->max_attempts,
                'failed_at' => $deadLetter->failed_at?->toISOString(),
                'last_retry_at' => $deadLetter->last_retry_at?->toISOString(),
                'resolved_at' => $deadLetter->resolved_at?->toISOString(),
                'correlation_id' => $deadLetter->correlation_id,
            ]);

        $stats = [
            'open' => DeadLetter::query()->where('status', DeadLetter::STATUS_OPEN)->count(),
            'retrying' => DeadLetter::query()->where('status', DeadLetter::STATUS_RETRYING)->count(),
            'resolved' => DeadLetter::query()->where('status', DeadLetter::STATUS_RESOLVED)->count(),
            'recoverable_open' => DeadLetter::query()
                ->where('status', DeadLetter::STATUS_OPEN)
                ->where('recoverable', true)
                ->count(),
        ];

        return Inertia::render('Admin/DeadLetters/Index', [
            'deadLetters' => $deadLetters,
            'filters' => [
                'status' => $status,
                'source' => $source,
            ],
            'stats' => $stats,
        ]);
    }
}

