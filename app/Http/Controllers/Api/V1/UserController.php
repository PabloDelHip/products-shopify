<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'created_at')
            ->orderBy('id')
            ->paginate(20);

        return response()->json(['ok' => true, 'data' => $users]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::select('id', 'name', 'email', 'created_at')->find($id);

        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        return response()->json(['ok' => true, 'data' => $user]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create($data);

        return response()->json([
            'ok'   => true,
            'data' => $user->only('id', 'name', 'email', 'created_at'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => "sometimes|email|unique:users,email,{$id}",
            'password' => ['sometimes', Password::min(8)],
        ]);

        $user->update($data);

        return response()->json([
            'ok'   => true,
            'data' => $user->fresh()->only('id', 'name', 'email', 'created_at'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $user->delete();

        return response()->json(['ok' => true, 'message' => 'Usuario eliminado']);
    }
}
