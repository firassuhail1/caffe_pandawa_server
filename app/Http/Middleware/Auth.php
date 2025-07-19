<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class Auth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        // Gunakan findToken yang lebih aman
        $accessToken = PersonalAccessToken::findToken($token);

        Log::info('Token search result:', [
            'token_found' => $accessToken ? 'yes' : 'no',
            'database' => DB::connection()->getDatabaseName()
        ]);

        if (!$accessToken) {
            return response()->json([
                'message' => 'Invalid token'
            ], 401);
        }

        // Set user yang authenticated
        $user = $accessToken->tokenable;
        
        if (!$user) {
            return response()->json([
                'message' => 'User not found or inactive'
            ], 401);
        }

        // Update last used timestamp tanpa trigger session
        $accessToken->timestamps = false;
        $accessToken->forceFill(['last_used_at' => now()])->save();

        // Set current access token untuk user
        $user->withAccessToken($accessToken);

        // Set user resolver untuk request ini
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
