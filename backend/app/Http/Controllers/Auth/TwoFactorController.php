<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json(['message' => '2FA is already enabled. Disable it first to reconfigure.'], 422);
        }

        $google2fa = new Google2FA();

        $secret = $google2fa->generateSecretKey();
        $user->totp_secret = $secret;
        $user->save();

        $qrCodeUri = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_uri' => $qrCodeUri,
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $user = $request->user();

        if (! $user->totp_secret) {
            return response()->json(['message' => '2FA setup not initiated.'], 422);
        }

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($user->totp_secret, $request->code)) {
            return response()->json(['message' => 'Invalid TOTP code.'], 422);
        }

        $user->update(['two_factor_enabled' => true]);

        return response()->json(['message' => '2FA enabled successfully.']);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'temp_token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::findToken($request->temp_token);

        if (! $tokenRecord || $tokenRecord->name !== 'temp-2fa') {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        if ($tokenRecord->created_at->lt(now()->subMinutes(5))) {
            $tokenRecord->delete();
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = $tokenRecord->tokenable;

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($user->totp_secret, $request->code)) {
            return response()->json(['message' => 'Invalid TOTP code.'], 422);
        }

        // Revoke temp token, issue full access token
        $tokenRecord->delete();
        $accessToken = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'parks' => $user->parks()->select('parks.id', 'parks.name')->get(),
            ],
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password confirmation failed.'], 422);
        }

        $user->two_factor_enabled = false;
        $user->totp_secret = null;
        $user->save();

        return response()->json(['message' => '2FA disabled successfully.']);
    }
}
