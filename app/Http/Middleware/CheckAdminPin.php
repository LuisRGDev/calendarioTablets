<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPin
{
    /**
     * Handle an incoming request.
     * Redirects to /admin/login if the admin session is not set.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->get('admin_authenticated')) {
            return redirect()->route('admin.login')
                ->with('intended', $request->url());
        }

        return $next($request);
    }
}
