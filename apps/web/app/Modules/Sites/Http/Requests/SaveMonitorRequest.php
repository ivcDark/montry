<?php

namespace App\Modules\Sites\Http\Requests;

use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use App\Modules\Sites\Enums\MonitorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(app(MonitorTypeCatalog::class)->allCodes())],
            'name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
            'interval_seconds' => $this->intervalRules(),
            'timeout_ms' => ['required', 'integer', 'min:1000', 'max:60000'],

            'settings' => ['required', 'array'],
            ...$this->settingsRules(),
        ];
    }

    private function intervalRules(): array
    {
        if ($this->input('type') === MonitorType::Http->value) {
            return ['required', 'integer', 'min:60', 'max:86400', 'multiple_of:60'];
        }

        return ['required', 'integer', 'min:86400', 'max:604800', 'multiple_of:86400'];
    }

    private function settingsRules(): array
    {
        return match ($this->input('type')) {
            MonitorType::Http->value => [
                'settings.method' => ['required', 'string', Rule::in(['GET', 'POST', 'HEAD'])],
                'settings.path' => ['required', 'string', 'max:2048'],
                'settings.expected_status_min' => ['required', 'integer', 'min:100', 'max:599'],
                'settings.expected_status_max' => ['required', 'integer', 'min:100', 'max:599', 'gte:settings.expected_status_min'],
                'settings.follow_redirects' => ['required', 'boolean'],
            ],

            MonitorType::Ssl->value => [
                'settings.host' => ['required', 'string', 'max:255'],
                'settings.port' => ['required', 'integer', 'min:1', 'max:65535'],
                'settings.warning_days' => ['required', 'integer', 'min:1', 'max:365'],
            ],

            default => [],
        };
    }
}
