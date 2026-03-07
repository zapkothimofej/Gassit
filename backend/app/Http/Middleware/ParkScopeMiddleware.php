<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ParkScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $parkId = $request->route('park_id')
            ?? $request->route('park')
            ?? $request->input('park_id');

        if ($parkId !== null && ! self::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Unauthorized access to this park.'], 403);
        }

        return $next($request);
    }

    public static function hasAccessToPark(object $user, int $parkId): bool
    {
        if (in_array($user->role, ['admin', 'main_manager'], true)) {
            return true;
        }

        return $user->parks()->where('parks.id', $parkId)->exists();
    }
}
