<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Categories\CategoriesService;
use App\Http\Requests\Category\CategoryRequest;
use App\Http\Requests\Category\CategoryIndexRequest;
use App\Http\Requests\Category\CategoryTreeRequest;
use App\Http\Requests\Category\CategoryActiveRequest;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * @OA\OpenApi(
 *   @OA\Info(
 *     title="Shopify Conectos API",
 *     version="1.0.0"
 *   )
 * )
 */
class CategoriesController extends Controller
{
    public function __construct(
        private readonly CategoriesService $service,
    ) {}

    #[OA\Post(
        path: "/api/v1/category",
        summary: "Crear categorías masivamente",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "array",
                items: new OA\Items(
                    required: ["provider", "provider_category_id", "name", "level"],
                    properties: [
                        new OA\Property(property: "provider", type: "string", example: "syscom"),
                        new OA\Property(property: "provider_category_id", type: "string", example: "12"),
                        new OA\Property(property: "parent_id", type: "string", nullable: true, example: null),
                        new OA\Property(property: "name", type: "string", example: "Cámaras IP nivel 12"),
                        new OA\Property(property: "level", type: "integer", example: 1)
                    ],
                    example: [
                        [
                            "provider" => "syscom",
                            "provider_category_id" => "12",
                            "name" => "Cámaras IP nivel 12",
                            "level" => 1
                        ],
                        [
                            "provider" => "syscom",
                            "provider_category_id" => "2",
                            "parent_id" => "12",
                            "name" => "Cámaras IP",
                            "level" => 2
                        ],
                        [
                            "provider" => "syscom",
                            "provider_category_id" => "3",
                            "parent_id" => "2",
                            "name" => "Domo",
                            "level" => 3
                        ]
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Created",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "provider", type: "string", example: "syscom"),
                                    new OA\Property(property: "provider_category_id", type: "string", example: "12"),
                                    new OA\Property(property: "name", type: "string", example: "Cámaras IP nivel 12"),
                                    new OA\Property(property: "level", type: "integer", example: 1),
                                    new OA\Property(property: "active", type: "boolean", example: true),
                                    new OA\Property(property: "shopify_type", type: "string", example: "collection"),
                                    new OA\Property(property: "shopify_id", type: "string", example: "gid://shopify/Collection/488809431263"),
                                    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                                    new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                    new OA\Property(property: "id", type: "integer", example: 15),
                                    new OA\Property(property: "parent_provider_category_id", type: "string", nullable: true, example: "12")
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function create(CategoryRequest $request)
    {
        $category = $this->service->createMany($request->validated());
        return response()->json(['ok' => true, 'data' => $category], 201);
    }

    #[OA\Get(
        path: "/api/v1/category",
        summary: "Listar categorías planas",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "provider", in: "query", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "level", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "parent_provider_category_id", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "active", in: "query", required: false, schema: new OA\Schema(type: "boolean"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of categories",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "provider", type: "string", example: "syscom"),
                            new OA\Property(property: "provider_category_id", type: "string", example: "12"),
                            new OA\Property(property: "name", type: "string", example: "Cámaras IP nivel 12"),
                            new OA\Property(property: "level", type: "integer", example: 1),
                            new OA\Property(property: "active", type: "boolean", example: true),
                            new OA\Property(property: "shopify_type", type: "string", example: "collection"),
                            new OA\Property(property: "shopify_id", type: "string", example: "gid://shopify/Collection/488809431263"),
                            new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                            new OA\Property(property: "created_at", type: "string", format: "date-time"),
                            new OA\Property(property: "id", type: "integer", example: 15),
                            new OA\Property(property: "parent_provider_category_id", type: "string", nullable: true, example: "12")
                        ]
                    )
                )
            )
        ]
    )]
    public function list(CategoryIndexRequest $request)
    {
        $filters = $request->validated();

        $data = $this->service->list($filters);

        return response()->json($data);
    }

    #[OA\Get(
        path: "/api/v1/category/tree",
        summary: "Obtener árbol de categorías",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "provider", in: "query", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "active", in: "query", required: false, schema: new OA\Schema(type: "boolean"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Category Tree",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "provider", type: "string", example: "syscom"),
                                    new OA\Property(property: "provider_category_id", type: "string", example: "12"),
                                    new OA\Property(property: "parent_provider_category_id", type: "string", nullable: true, example: null),
                                    new OA\Property(property: "name", type: "string", example: "Cámaras IP"),
                                    new OA\Property(property: "level", type: "integer", example: 1),
                                    new OA\Property(property: "active", type: "boolean", example: true),
                                    new OA\Property(property: "shopify_type", type: "string", example: "collection"),
                                    new OA\Property(property: "shopify_id", type: "string", example: "gid://shopify/Collection/123"),
                                    new OA\Property(
                                        property: "children",
                                        type: "array",
                                        items: new OA\Items(type: "object")
                                    )
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function getTree(CategoryTreeRequest $request)
    {
        $filters = $request->validated();

        $tree = $this->service->tree($filters);

        return response()->json([
            'ok' => true,
            'data' => $tree,
        ]);
    }

    #[OA\Put(
        path: "/api/v1/category/{id}/active",
        summary: "Activar/Desactivar categoría",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["active"],
                properties: [
                    new OA\Property(property: "active", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Updated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "provider", type: "string", example: "syscom"),
                        new OA\Property(property: "provider_category_id", type: "string", example: "12"),
                        new OA\Property(property: "name", type: "string", example: "Cámaras IP"),
                        new OA\Property(property: "level", type: "integer", example: 1),
                        new OA\Property(property: "active", type: "boolean", example: true),
                        new OA\Property(property: "shopify_type", type: "string", example: "collection"),
                        new OA\Property(property: "shopify_id", type: "string", example: "gid://shopify/Collection/123"),
                        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                        new OA\Property(property: "created_at", type: "string", format: "date-time")
                    ]
                )
            )
        ]
    )]
    public function active(CategoryActiveRequest $request, int $id)
    {
        $data = $request->validated();          // ['active' => true/false]
        $response = $this->service->active($id, (bool) $data['active']);
    
        return $response;
    }
}
