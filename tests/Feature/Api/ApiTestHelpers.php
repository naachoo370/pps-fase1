<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Utilidades compartidas por las pruebas de la API.
 * Cada prueba crea sus propios usuarios con nombres únicos.
 */
trait ApiTestHelpers
{
    protected function uniqueUsername(string $prefix = 'user'): string
    {
        return $prefix.'_'.Str::lower(Str::random(12));
    }

    protected function makeUser(?string $username = null, string $password = 'Clave-1234'): User
    {
        $username ??= $this->uniqueUsername();

        return User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@maktub.local',
            'password' => Hash::make($password),
            'role' => 'cliente',
        ]);
    }

    protected function makeItem(
        User $user,
        string $title = 'Elemento de prueba',
        string $text = 'Texto de prueba'
    ): Item {
        return Item::create([
            'user_id' => $user->id,
            'title' => $title,
            'text' => $text,
        ]);
    }

    protected function loginToken(User $user, string $password = 'Clave-1234'): string
    {
        $response = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => $password,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'token_type']);

        return $response->json('token');
    }

    protected function authHeaders(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
