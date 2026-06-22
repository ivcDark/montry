<?php

namespace App\Modules\StatusPages\Presentation\Http\Requests;

use App\Modules\StatusPages\Application\DTO\SaveStatusPageData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveStatusPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $statusPageId = $this->route('statusPage')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('status_pages', 'slug')->ignore($statusPageId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['required', 'boolean'],
            'show_incident_history' => ['required', 'boolean'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'monitors' => ['required', 'array', 'min:1'],
            'monitors.*.monitor_id' => ['required', 'integer', 'distinct', Rule::exists('monitors', 'id')],
            'monitors.*.display_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(int $organizationId): SaveStatusPageData
    {
        $validated = $this->validated();

        return new SaveStatusPageData(
            organizationId: $organizationId,
            createdUserId: $this->user()?->id,
            name: trim($validated['name']),
            slug: strtolower(trim($validated['slug'])),
            description: filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            isPublished: (bool) $validated['is_published'],
            showIncidentHistory: (bool) $validated['show_incident_history'],
            accentColor: strtoupper($validated['accent_color']),
            monitors: collect($validated['monitors'])
                ->map(fn (array $monitor): array => [
                    'monitor_id' => (int) $monitor['monitor_id'],
                    'display_name' => filled($monitor['display_name'] ?? null)
                        ? trim($monitor['display_name'])
                        : null,
                ])
                ->values()
                ->all(),
        );
    }
}
