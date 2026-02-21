<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(name="Auth", description="Autenticación de usuarios")
 */
class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    #[OA\Post(
        path: "/api/v1/auth/login",
        summary: "Iniciar sesión",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "admin@conectos.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "access_token", type: "string", example: "eyJ0eXAiOiJKV1QiLCJhbGci..."),
                                new OA\Property(property: "token_type", type: "string", example: "Bearer"),
                                new OA\Property(
                                    property: "user",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 1),
                                        new OA\Property(property: "name", type: "string", example: "Admin"),
                                        new OA\Property(property: "email", type: "string", example: "admin@conectos.com")
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Invalid credentials",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Credenciales inválidas.")
                    ]
                )
            )
        ]
    )]

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login(
            $request->string('email'),
            $request->string('password')
        );
        return response()->json([
            'ok' => true,
            'data' => $result,
        ]);
    }

    #[OA\Get(
        path: "/api/v1/auth/me",
        summary: "Obtener usuario autenticado",
        tags: ["Auth"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "User profile",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Admin"),
                                new OA\Property(property: "email", type: "string", example: "admin@conectos.com")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function me()
    {
        return response()->json([
            'ok' => true,
            'data' => auth('api')->user(),
        ]);
    }

    #[OA\Post(
        path: "/api/v1/auth/refresh",
        summary: "Refrescar token de acceso",
        tags: ["Auth"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Token refreshed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "access_token", type: "string", example: "eyJ0eXAiOiJKV1QiLCJhbGci..."),
                                new OA\Property(property: "token_type", type: "string", example: "Bearer"),
                                new OA\Property(
                                    property: "user",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 1),
                                        new OA\Property(property: "name", type: "string", example: "Admin"),
                                        new OA\Property(property: "email", type: "string", example: "admin@conectos.com")
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function refresh()
    {
        $result = $this->auth->refresh();

        return response()->json([
            'ok' => true,
            'data' => $result,
        ]);
    }

    #[OA\Post(
        path: "/api/v1/auth/logout",
        summary: "Cerrar sesión",
        tags: ["Auth"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Session closed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Sesión cerrada")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function logout()
    {
        $this->auth->logout();

        return response()->json([
            'ok' => true,
            'message' => 'Sesión cerrada',
        ]);
    }

}
