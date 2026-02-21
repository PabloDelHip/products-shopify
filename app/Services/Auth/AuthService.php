<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use App\Contracts\User\UserRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users
    ) {}

    public function login(string $email, string $password): array
    {
        $credentials = ['email' => $email, 'password' => $password];

        // attempt() valida credenciales y si son correctas regresa el JWT
        $token = Auth::guard('api')->attempt($credentials);
        if (!$token) {
            throw new HttpResponseException(
                response()->json([
                    'ok' => false,
                    'message' => 'Credenciales inválidas.',
                ], 401)
            );
        }

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            // opcional: usuario actual (ya autenticado con ese token)
            'user' => Auth::guard('api')->user(),
        ];
    }

    public function refresh(): array
    {
        $newToken = Auth::guard('api')->refresh();

        return [
            'access_token' => $newToken,
            'token_type' => 'Bearer',
            'user' => Auth::guard('api')->user(), // sigue siendo el mismo usuario
        ];
    }

    public function logout(): void
    {
        Auth::guard('api')->logout(); // invalida el token actual (blacklist)
    }
}
