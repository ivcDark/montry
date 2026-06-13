<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\DTO\LoginUserData;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => "Неверный email",
            'password.required' => 'Пароль не заполнен'
        ];
    }

    public function toData(): LoginUserData
    {
        return new LoginUserData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            remember: $this->boolean('remember'),
        );
    }
}
