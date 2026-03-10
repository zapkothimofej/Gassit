<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminDocsAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Access restricted to administrators.');
        }

        return $next($request);
    }
}
