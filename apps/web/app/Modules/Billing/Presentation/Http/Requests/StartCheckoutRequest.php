<?php

namespace App\Modules\Billing\Presentation\Http\Requests;

use App\Modules\Billing\Application\Services\BillingAddonCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $addonCodes = array_keys(app(BillingAddonCatalog::class)->all());

        return [
            'plan_code' => [
                'required',
                'string',
                Rule::exists('plans', 'code')->where('is_active', true),
            ],
            'addons' => ['sometimes', 'array:'.implode(',', $addonCodes)],
            'addons.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
