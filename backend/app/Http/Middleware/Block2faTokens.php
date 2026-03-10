<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Block2faTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && property_exists($token, 'name') && $token->name === 'temp-2fa') {
            return response()->json(['message' => '2FA verification required.'], 403);
        }

        return $next($request);
    }
}
