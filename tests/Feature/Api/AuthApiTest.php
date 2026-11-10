<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;
    use ApiTestHelpers;

    public function test_register_creates_user_and_saves_password_hashed(): void
    {
        $username = $this->uniqueUsername('registro');

        $response = $this->postJson('/api/register', [
            'username' => $username,
            'password' => 'Clave-1234',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJson(['username' => $username, 'usuario' => $username]);

        $user = User::where('username', $username)->first();

        $this->assertNotNull($user);
        $this->assertNotSame('Clave-1234', $user->password);
        $this->assertTrue(Hash::check('Clave-1234', $user->password));
        $this->assertArrayNotHasKey('password', $response->json());
    }

    public function test_register_accepts_spanish_alias_fields(): void
    {
        $username = $this->uniqueUsername('alias');

        $response = $this->postJson('/api/register', [
            'usuario' => $username,
            'clave' => 'Clave-1234',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['username' => $username]);
    }

    public function test_register_rejects_missing_fields_with_400(): void
    {
        $response = $this->postJson('/api/register', [], ['Accept' => 'application/json']);

        $response->assertStatus(400)->assertJson(['error' => 'datos no válidos']);
    }

    public function test_register_rejects_empty_fields_with_400(): void
    {
        $response = $this->postJson('/api/register', [
            'username' => '',
            'password' => '',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(400);
    }

    public function test_register_rejects_wrong_types_with_400(): void
    {
        $response = $this->postJson('/api/register', [
            'username' => ['no', 'es', 'texto'],
            'password' => ['tampoco'],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(400);
    }

    public function test_register_accepts_username_at_max_length(): void
    {
        $username = str_repeat('a', 50);

        $response = $this->postJson('/api/register', [
            'username' => $username,
            'password' => 'Clave-1234',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['username' => $username]);
    }

    public function test_register_rejects_username_one_character_over_max_length(): void
    {
        $response = $this->postJson('/api/register', [
            'username' => str_repeat('a', 51),
            'password' => 'Clave-1234',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(400);
    }

    public function test_register_rejects_duplicate_username_with_409(): void
    {
        $username = $this->uniqueUsername('duplicado');
        $this->makeUser($username);

        $response = $this->postJson('/api/register', [
            'username' => $username,
            'password' => 'Clave-1234',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(409);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
        ]);
        // El token en claro nunca se guarda en la base de datos.
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $token]);
    }

    public function test_login_rejects_invalid_credentials_with_401(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'contraseña-incorrecta',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_login_rejects_missing_credentials_with_401(): void
    {
        $response = $this->postJson('/api/login', [], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_logout_invalidates_the_token(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $this->postJson('/api/logout', [], $this->authHeaders($token))
            ->assertStatus(204);

        $this->getJson('/api/items', $this->authHeaders($token))
            ->assertStatus(401);
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->getJson('/api/items', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer token-invalido',
        ])->assertStatus(401);
    }

    public function test_expired_token_returns_401(): void
    {
        $user = $this->makeUser();
        $plainToken = str_repeat('a', 64);

        ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/items', $this->authHeaders($plainToken))
            ->assertStatus(401);
    }
}
