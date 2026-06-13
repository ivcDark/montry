<?php

namespace App\Modules\Monitoring\Presentation\Http\Requests;

use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $types = app(MonitorTypeCatalog::class)->allCodes();

        return [
            'type' => ['required', 'string', Rule::in($types)],
            'name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
            'interval_seconds' => ['required', 'integer', 'min:300', 'max:86400', 'multiple_of:60'],
            'timeout_ms' => ['required', 'integer', 'min:1000', 'max:60000'],
            'settings' => ['required', 'array'],
            'expected' => ['sometimes', 'array'],
        ];
    }
}
