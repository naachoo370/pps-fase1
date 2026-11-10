<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticación de la API mediante token opaco.
 *
 * El cliente envía:  Authorization: Bearer <token>
 * En la base de datos solo existe el hash SHA-256 del token.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = trim((string) $request->header('Authorization', ''));

        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return response()->json(['error' => 'no autenticado'], 401);
        }

        $token = $matches[1];

        $apiToken = ApiToken::with('user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $apiToken || ! $apiToken->user) {
            return response()->json(['error' => 'no autenticado'], 401);
        }

        if ($apiToken->expires_at !== null && $apiToken->expires_at->isPast()) {
            $apiToken->delete();

            return response()->json(['error' => 'no autenticado'], 401);
        }

        // Dejamos el usuario disponible tanto en el guard de Laravel como en la petición.
        Auth::setUser($apiToken->user);
        $request->setUserResolver(fn () => $apiToken->user);
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
