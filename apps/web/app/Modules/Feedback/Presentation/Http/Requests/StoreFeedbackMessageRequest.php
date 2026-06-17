<?php

namespace App\Modules\Feedback\Presentation\Http\Requests;

use App\Modules\Feedback\Application\Commands\SendFeedbackMessageCommand;
use Illuminate\Foundation\Http\FormRequest;

final class StoreFeedbackMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'source' => ['nullable', 'string', 'in:landing,account'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Имя обязательно для заполнения.',
            'name.max' => 'Имя должно быть не длиннее 255 символов.',
            'email.required' => 'Почта обязательна для заполнения.',
            'email.email' => 'Почта введена в неверном формате.',
            'email.max' => 'Почта должна быть не длиннее 255 символов.',
            'subject.max' => 'Тема должна быть не длиннее 255 символов.',
            'message.required' => 'Текст обращения обязателен для заполнения.',
            'message.max' => 'Текст обращения должен быть не длиннее 5000 символов.',
        ];
    }

    public function toCommand(): SendFeedbackMessageCommand
    {
        $user = $this->user();
        $organization = $user?->organizations()->first(['organizations.id', 'organizations.name']);
        $source = $this->string('source', 'landing')->toString() === 'account' && $user !== null
            ? 'account'
            : 'landing';

        return new SendFeedbackMessageCommand(
            name: $source === 'account' ? (string) $user->name : $this->string('name')->toString(),
            email: $source === 'account' ? (string) $user->email : $this->string('email')->toString(),
            message: $this->string('message')->toString(),
            subject: $this->filled('subject') ? $this->string('subject')->toString() : null,
            source: $source,
            pageUrl: $this->headers->get('referer'),
            ipAddress: $this->ip(),
            userAgent: $this->userAgent(),
            userId: $user?->id,
            userName: $user?->name,
            userEmail: $user?->email,
            organizationId: $organization?->id,
            organizationName: $organization?->name,
        );
    }
}
