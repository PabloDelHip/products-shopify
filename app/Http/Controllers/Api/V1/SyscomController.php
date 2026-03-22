<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Syscom\SyscomApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(name="Syscom API", description="Proxy para la API de Syscom")
 */
class SyscomController extends Controller
{
    public function __construct(private readonly SyscomApiService $syscomService) {}

    #[OA\Post(
        path: "/api/v1/syscom/auth/login",
        summary: "Obtener JWT de Syscom",
        description: "Obtiene y retorna el access_token de Syscom usando las credenciales configuradas en el servidor.",
        tags: ["Syscom API"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Token obtenido exitosamente",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "access_token", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "No se pudo obtener el token")
        ]
    )]
    public function login(): JsonResponse
    {
        $token = $this->syscomService->getAccessToken();

        if (!$token) {
            return response()->json(['error' => 'Could not authenticate with Syscom'], 401);
        }

        return response()->json([
            'access_token' => $token
        ]);
    }

    #[OA\Get(
        path: "/api/v1/syscom/categorias",
        summary: "Listar categorías de Syscom",
        tags: ["Syscom API"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Listado de categorías")
        ]
    )]
    public function getCategories(): JsonResponse
    {
        return response()->json($this->syscomService->getCategories());
    }

    #[OA\Get(
        path: "/api/v1/syscom/categorias/{id}",
        summary: "Detalle de categoría de Syscom",
        tags: ["Syscom API"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalle de la categoría")
        ]
    )]
    public function getCategoryDetail(string $id): JsonResponse
    {
        return response()->json($this->syscomService->getCategoryDetail($id));
    }

    #[OA\Get(
        path: "/api/v1/syscom/productos",
        summary: "Listar productos de Syscom",
        tags: ["Syscom API"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "categoria", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "pagina", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1)),
            new OA\Parameter(name: "busqueda", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Listado de productos")
        ]
    )]
    public function getProducts(Request $request): JsonResponse
    {
        $filters = $request->only(['categoria', 'pagina', 'busqueda']);
        return response()->json($this->syscomService->getProducts($filters));
    }

    #[OA\Get(
        path: "/api/v1/syscom/productos/{id}",
        summary: "Detalle de producto de Syscom",
        tags: ["Syscom API"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalle del producto")
        ]
    )]
    public function getProductDetail(string $id): JsonResponse
    {
        return response()->json($this->syscomService->getProductDetail($id));
    }
}
