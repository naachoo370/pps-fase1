<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Registro, login y logout de la API común.
 *
 * La validación se hace a mano con Validator::make() para devolver 400
 * (la validación automática de Laravel devolvería 422).
 */
class AuthController extends Controller
{
    /**
     * POST /api/register
     */
    public function register(Request $request): JsonResponse
    {
        $username = $request->input('username') ?? $request->input('usuario');
        $password = $request->input('password') ?? $request->input('clave');

        $validator = Validator::make(
            ['username' => $username, 'password' => $password],
            [
                'username' => 'required|string|max:50',
                'password' => 'required|string|min:8|max:100',
            ]
        );

        if ($validator->fails() || ! is_string($username) || trim($username) === '') {
            return response()->json(['error' => 'datos no válidos'], 400);
        }

        $username = trim($username);

        if (User::where('username', $username)->exists()) {
            return response()->json(['error' => 'el usuario ya existe'], 409);
        }

        try {
            $user = User::create([
                'name' => $username,
                'username' => $username,
                'email' => $this->emailFor($username),
                'password' => Hash::make($password),
                'role' => 'cliente',
            ]);
        } catch (QueryException) {
            // Carrera entre dos registros simultáneos con el mismo usuario.
            return response()->json(['error' => 'el usuario ya existe'], 409);
        }

        return response()->json([
            'usuario' => $user->username,
            'username' => $user->username,
        ], 201);
    }

    /**
     * POST /api/login
     */
    public function login(Request $request): JsonResponse
    {
        $username = $request->input('username') ?? $request->input('usuario');
        $password = $request->input('password') ?? $request->input('clave');

        if (! is_string($username) || ! is_string($password) || $username === '' || $password === '') {
            return response()->json(['error' => 'credenciales no válidas'], 401);
        }

        $username = trim($username);

        $user = User::where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(['error' => 'credenciales no válidas'], 401);
        }

        // Token opaco de 64 caracteres hexadecimales (256 bits de entropía).
        $plainToken = bin2hex(random_bytes(32));

        ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_in' => 604800,
            'usuario' => $user->username,
            'username' => $user->username,
        ], 200);
    }

    /**
     * POST /api/logout (ruta extra para que el cliente pueda cerrar sesión).
     */
    public function logout(Request $request): Response
    {
        $apiToken = $request->attributes->get('api_token');

        if ($apiToken instanceof ApiToken) {
            $apiToken->delete();
        }

        return response()->noContent();
    }

    /**
     * Genera un email interno único. La tienda web sigue usando email para
     * entrar; la API usa el username. Así no se rompe el login existente.
     */
    private function emailFor(string $username): string
    {
        $base = Str::slug($username);
        $base = $base !== '' ? substr($base, 0, 40) : 'usuario';

        do {
            $email = $base . '.' . Str::lower(Str::random(8)) . '@maktub.local';
        } while (User::where('email', $email)->exists());

        return $email;
    }
}
