<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemsApiTest extends TestCase
{
    use RefreshDatabase;
    use ApiTestHelpers;

    public function test_get_items_without_session_returns_401(): void
    {
        $this->getJson('/api/items')->assertStatus(401);
    }

    public function test_get_items_returns_only_the_authenticated_users_items(): void
    {
        $userA = $this->makeUser($this->uniqueUsername('ana'));
        $userB = $this->makeUser($this->uniqueUsername('bruno'));

        $this->makeItem($userA, 'Elemento de Ana', 'Solo lo puede ver Ana');
        $this->makeItem($userA, 'Segundo de Ana', 'También es de Ana');
        $this->makeItem($userB, 'Elemento de Bruno', 'Bruno no debe verlo Ana');

        $token = $this->loginToken($userA);

        $response = $this->getJson('/api/items', $this->authHeaders($token));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['title' => 'Elemento de Ana'])
            ->assertJsonMissing(['title' => 'Elemento de Bruno']);
    }

    public function test_create_item_returns_201_and_persists(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $response = $this->postJson('/api/items', [
            'title' => 'Nuevo elemento',
            'text' => 'Contenido del elemento',
        ], $this->authHeaders($token));

        $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'Nuevo elemento']);

        $this->assertDatabaseHas('items', [
            'user_id' => $user->id,
            'title' => 'Nuevo elemento',
            'text' => 'Contenido del elemento',
        ]);
    }

    public function test_create_item_accepts_title_at_max_length(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);
        $title = str_repeat('T', 100);

        $response = $this->postJson('/api/items', [
            'title' => $title,
            'text' => 'Texto válido',
        ], $this->authHeaders($token));

        $response->assertStatus(201);
        $this->assertDatabaseHas('items', ['user_id' => $user->id, 'title' => $title]);
    }

    public function test_create_item_rejects_title_one_character_over_max_length(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $response = $this->postJson('/api/items', [
            'title' => str_repeat('T', 101),
            'text' => 'Texto válido',
        ], $this->authHeaders($token));

        $response->assertStatus(400);
    }

    public function test_create_item_accepts_text_at_max_length(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $response = $this->postJson('/api/items', [
            'title' => 'Texto límite',
            'text' => str_repeat('x', 1000),
        ], $this->authHeaders($token));

        $response->assertStatus(201);
    }

    public function test_create_item_rejects_text_one_character_over_max_length(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $response = $this->postJson('/api/items', [
            'title' => 'Texto demasiado largo',
            'text' => str_repeat('x', 1001),
        ], $this->authHeaders($token));

        $response->assertStatus(400);
    }

    public function test_create_item_rejects_empty_fields_with_400(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $this->postJson('/api/items', [
            'title' => '',
            'text' => '',
        ], $this->authHeaders($token))->assertStatus(400);
    }

    public function test_create_item_rejects_wrong_types_with_400(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $this->postJson('/api/items', [
            'title' => ['no', 'es', 'texto'],
            'text' => ['tampoco'],
        ], $this->authHeaders($token))->assertStatus(400);
    }

    public function test_create_item_without_session_returns_401(): void
    {
        $this->postJson('/api/items', [
            'title' => 'Sin sesión',
            'text' => 'No debería crearse',
        ])->assertStatus(401);
    }

    public function test_update_own_item_returns_200(): void
    {
        $user = $this->makeUser();
        $item = $this->makeItem($user, 'Título viejo', 'Texto viejo');
        $token = $this->loginToken($user);

        $response = $this->putJson('/api/items/'.$item->id, [
            'title' => 'Título nuevo',
            'text' => 'Texto nuevo',
        ], $this->authHeaders($token));

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Título nuevo', 'text' => 'Texto nuevo']);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'title' => 'Título nuevo',
            'text' => 'Texto nuevo',
        ]);
    }

    public function test_update_rejects_invalid_data_with_400(): void
    {
        $user = $this->makeUser();
        $item = $this->makeItem($user);
        $token = $this->loginToken($user);

        $this->putJson('/api/items/'.$item->id, [
            'title' => '',
            'text' => str_repeat('x', 1001),
        ], $this->authHeaders($token))->assertStatus(400);
    }

    public function test_update_other_users_item_is_forbidden(): void
    {
        $owner = $this->makeUser($this->uniqueUsername('owner'));
        $intruder = $this->makeUser($this->uniqueUsername('intruder'));
        $item = $this->makeItem($owner, 'Privado', 'No se puede tocar');
        $token = $this->loginToken($intruder);

        $response = $this->putJson('/api/items/'.$item->id, [
            'title' => 'Secuestrado',
            'text' => 'Intento de modificación',
        ], $this->authHeaders($token));

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'title' => 'Privado',
            'text' => 'No se puede tocar',
        ]);
    }

    public function test_update_missing_item_returns_404(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $this->putJson('/api/items/999999', [
            'title' => 'No existe',
            'text' => 'No existe',
        ], $this->authHeaders($token))->assertStatus(404);
    }

    public function test_update_without_session_returns_401(): void
    {
        $user = $this->makeUser();
        $item = $this->makeItem($user);

        $this->putJson('/api/items/'.$item->id, [
            'title' => 'Sin sesión',
            'text' => 'Sin sesión',
        ])->assertStatus(401);
    }

    public function test_delete_own_item_returns_204_and_removes_it(): void
    {
        $user = $this->makeUser();
        $item = $this->makeItem($user);
        $token = $this->loginToken($user);

        $this->deleteJson('/api/items/'.$item->id, [], $this->authHeaders($token))
            ->assertStatus(204);

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_delete_other_users_item_is_forbidden(): void
    {
        $owner = $this->makeUser($this->uniqueUsername('owner'));
        $intruder = $this->makeUser($this->uniqueUsername('intruder'));
        $item = $this->makeItem($owner);
        $token = $this->loginToken($intruder);

        $response = $this->deleteJson('/api/items/'.$item->id, [], $this->authHeaders($token));

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_delete_missing_item_returns_404(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $this->deleteJson('/api/items/999999', [], $this->authHeaders($token))
            ->assertStatus(404);
    }

    public function test_delete_without_session_returns_401(): void
    {
        $user = $this->makeUser();
        $item = $this->makeItem($user);

        $this->deleteJson('/api/items/'.$item->id)->assertStatus(401);
    }

    public function test_error_responses_do_not_leak_internal_details(): void
    {
        $user = $this->makeUser();
        $item = $this->makeItem($user);
        $token = $this->loginToken($user);

        $responses = [
            $this->postJson('/api/register', [], ['Accept' => 'application/json']),
            $this->getJson('/api/items'),
            $this->putJson('/api/items/'.$item->id, ['title' => '', 'text' => '']),
            $this->putJson('/api/items/999999', ['title' => 'x', 'text' => 'y'], $this->authHeaders($token)),
        ];

        foreach ($responses as $response) {
            $content = $response->getContent();

            $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
            $this->assertStringNotContainsString('SQLSTATE', $content);
            $this->assertStringNotContainsString('Stack trace', $content);
            $this->assertStringNotContainsString('vendor/', $content);
            $this->assertStringNotContainsString('#0 ', $content);
            $this->assertStringNotContainsString('Exception', $content);
        }
    }
}
