<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        if ($admin === null || ! $admin->isActive()) {
            auth('admin')->logout();

            return redirect()->route('admin.login')->with('error', 'This account cannot sign in.');
        }

        return $next($request);
    }
}
