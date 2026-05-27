<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\DTO\LoginUserData;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Observability\Application\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function handle(LoginUserData $data, ?Request $request = null): void
    {
        if (! Auth::attempt($data->credentials(), $data->remember)) {
            $adminUser = User::query()
                ->where('email', $data->email)
                ->where('is_admin', true)
                ->first(['id']);

            if ($adminUser !== null) {
                $this->audit->record(
                    category: 'auth',
                    action: 'admin.login_failed',
                    outcome: 'failed',
                    request: $request,
                    targetType: 'user',
                    targetId: (string) $adminUser->id,
                    metadata: [
                        'email_hash' => $this->audit->hashValue($data->email),
                    ],
                );
            }

            throw ValidationException::withMessages([
                'email' => 'Неверная пара почта/пароль',
            ]);
        }

        if ((bool) Auth::user()?->is_blocked) {
            if ((bool) Auth::user()?->is_admin) {
                $this->audit->record(
                    category: 'auth',
                    action: 'admin.login_blocked',
                    outcome: 'blocked',
                    request: $request,
                    actorUserId: Auth::id(),
                    targetType: 'user',
                    targetId: (string) Auth::id(),
                );
            }

            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Учетная запись заблокирована.',
            ]);
        }

        if ((bool) Auth::user()?->is_admin) {
            $this->audit->record(
                category: 'auth',
                action: 'admin.login',
                outcome: 'success',
                request: $request,
                actorUserId: Auth::id(),
                targetType: 'user',
                targetId: (string) Auth::id(),
            );
        }
    }
}
