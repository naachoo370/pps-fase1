<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD de los elementos privados de la API común.
 *
 * Regla de oro: un usuario solo puede listar, crear, modificar y borrar
 * SUS elementos. Si el elemento es de otro usuario se responde 403.
 */
class ItemController extends Controller
{
    /**
     * GET /api/items
     */
    public function index(Request $request): JsonResponse
    {
        $items = Item::where('user_id', $request->user()->id)
            ->orderBy('id')
            ->get();

        return response()->json($items, 200);
    }

    /**
     * POST /api/items
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->payload($request);

        $validator = Validator::make($data, [
            'title' => 'required|string|max:100',
            'text' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'datos no válidos'], 400);
        }

        $item = Item::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'text' => $data['text'],
        ]);

        return response()->json($item, 201);
    }

    /**
     * PUT /api/items/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = Item::find($id);

        if (! $item) {
            return response()->json(['error' => 'no encontrado'], 404);
        }

        if ((int) $item->user_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'no tienes permiso'], 403);
        }

        $data = $this->payload($request);

        $validator = Validator::make($data, [
            'title' => 'required|string|max:100',
            'text' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'datos no válidos'], 400);
        }

        $item->update([
            'title' => $data['title'],
            'text' => $data['text'],
        ]);

        return response()->json($item->fresh(), 200);
    }

    /**
     * DELETE /api/items/{id}
     */
    public function destroy(Request $request, int $id): Response|JsonResponse
    {
        $item = Item::find($id);

        if (! $item) {
            return response()->json(['error' => 'no encontrado'], 404);
        }

        if ((int) $item->user_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'no tienes permiso'], 403);
        }

        $item->delete();

        return response()->noContent();
    }

    /**
     * Acepta los nombres de la API común (title/text) y también los alias
     * en castellano (titulo/texto) para facilitar las pruebas a mano.
     */
    private function payload(Request $request): array
    {
        $title = $request->input('title') ?? $request->input('titulo');
        $text = $request->input('text') ?? $request->input('texto');

        return [
            'title' => $title,
            'text' => $text,
        ];
    }
}
