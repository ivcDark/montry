<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) $request->user()?->is_blocked) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'Учетная запись заблокирована.',
            ]);
        }

        return $next($request);
    }
}
