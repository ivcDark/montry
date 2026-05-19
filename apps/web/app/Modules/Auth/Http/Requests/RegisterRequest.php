<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\DTO\RegisterUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc,dns',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(6),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Имя должно быть меньше 255 символов',
            'name.required' => 'Имя обязательно для заполнения',
            'email.required' => 'Email обязательно для заполнения',
            'email.email' => 'Email введен в неверном формате',
            'email.max' => 'Email должно быть менее 255 символов',
            'email.unique' => 'Данный Email уже занят',
            'password.required' => 'Пароль обязателен для заполнения',
            'password.min' => 'Длина пароля должна быть более 6 символов',
            'password.confirmed' => 'Пароли не совпадают'
        ];
    }

    public function toData(): RegisterUserData
    {
        return new RegisterUserData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
