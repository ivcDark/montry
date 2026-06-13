<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Observability\Application\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LogoutController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        if ((bool) $user?->is_admin) {
            $audit->record(
                category: 'auth',
                action: 'admin.logout',
                outcome: 'success',
                request: $request,
                actorUserId: $user->id,
                targetType: 'user',
                targetId: (string) $user->id,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
