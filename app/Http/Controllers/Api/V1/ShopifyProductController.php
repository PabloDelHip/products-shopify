<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shopify\CreateProductsRequest;
use App\Services\Shopify\ShopifyProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(name="Shopify Products", description="Gestión de productos en Shopify")
 */
class ShopifyProductController extends Controller
{
    public function __construct(private readonly ShopifyProductService $service) {}

    #[OA\Post(
        path: "/api/v1/shopify/products",
        summary: "Sincronizar productos con Shopify",
        tags: ["Shopify Products"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["products"],
                properties: [
                    new OA\Property(
                        property: "products",
                        type: "array",
                        items: new OA\Items(
                            required: ["external_id", "title", "status", "variants"],
                            properties: [
                                new OA\Property(property: "external_id", type: "string", example: "77"),
                                new OA\Property(property: "title", type: "string", example: "Cámara IP Domo neww"),
                                new OA\Property(property: "vendor", type: "string", example: "HIKVISION"),
                                new OA\Property(property: "product_type", type: "string", example: "Cámaras IP"),
                                new OA\Property(property: "description_html", type: "string", example: "<p>Full HD con visión nocturna y PoE.</p>"),
                                new OA\Property(property: "status", type: "string", example: "ACTIVE"),
                                new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string"), example: ["syscom", "hikvision", "camara-ip", "domo", "ds-2cd2143g0-i"]),
                                new OA\Property(property: "images", type: "array", items: new OA\Items(type: "string", format: "url"), example: ["https://i.postimg.cc/j2W22gvK/computadora-3.jpg"]),
                                new OA\Property(
                                    property: "variants",
                                    type: "array",
                                    items: new OA\Items(
                                        required: ["sku", "price"],
                                        properties: [
                                            new OA\Property(property: "sku", type: "string", example: "DS-2CD2143G0-I"),
                                            new OA\Property(property: "price", type: "number", example: 850.0),
                                            new OA\Property(property: "compare_at_price", type: "number", example: 1000.0),
                                            new OA\Property(property: "inventory_quantity", type: "integer", example: 10),
                                            new OA\Property(property: "inventory_management", type: "string", example: "SHOPIFY"),
                                            new OA\Property(property: "requires_shipping", type: "boolean", example: true),
                                            new OA\Property(property: "taxable", type: "boolean", example: true)
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: "collections",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "string", example: "gid://shopify/Collection/488809431263")
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: "metafields",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "syscom_producto_id", type: "string", example: "77"),
                                        new OA\Property(property: "syscom_modelo", type: "string", example: "DS-2CD2143G0-I"),
                                        new OA\Property(property: "syscom_sat_key", type: "string", example: "43222600"),
                                        new OA\Property(property: "syscom_link", type: "string", example: "https://syscom/..."),
                                        new OA\Property(property: "syscom_total_existencia", type: "integer", example: 10),
                                        new OA\Property(property: "syscom_precio_lista", type: "number", example: 1000.0),
                                        new OA\Property(property: "syscom_precio_especial", type: "number", example: 850.0),
                                        new OA\Property(property: "syscom_caracteristicas", type: "array", items: new OA\Items(type: "string"), example: ["Full HD", "IP67", "PoE"]),
                                        new OA\Property(
                                            property: "syscom_recursos",
                                            type: "array",
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: "recurso", type: "string", example: "Manual"),
                                                    new OA\Property(property: "path", type: "string", example: "/manual.pdf")
                                                ]
                                            )
                                        )
                                    ]
                                )
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Products saved for processing",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function Create(CreateProductsRequest $request)
    {
        // $result = $this->service->Create($request->validated()['products']);

        // return response()->json(['ok' => true, 'data' => $result]);
        $result = $this->service->saveProducts($request->validated()['products']);

        return response()->json(['ok' => true, 'data' => $result]);
    }


    #[OA\Get(
        path: "/api/v1/shopify/product-uploads/metrics",
        summary: "Obtener métricas de carga de productos",
        tags: ["Shopify Products"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Metrics retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "total", type: "integer", example: 100),
                                new OA\Property(property: "pending", type: "integer", example: 10),
                                new OA\Property(property: "completed", type: "integer", example: 85),
                                new OA\Property(property: "failed", type: "integer", example: 5)
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function getStatusCounts(): JsonResponse
    {
        $data = $this->service->getStatusCounts();

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }

    #[OA\Post(
        path: "/api/v1/shopify/products/uploads/process",
        summary: "Procesar cargas de productos pendientes",
        tags: ["Shopify Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "limit",
                in: "query",
                description: "Número máximo de productos a procesar",
                required: false,
                schema: new OA\Schema(type: "integer", default: 5)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Process results",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function uploadPending(Request $request)
    {
        $limit = (int) $request->input('limit', 5); // o query('limit', 5)
        $limit = max(1, min($limit, 50)); // evita abusos

        $result = $this->service->processPendingUploads($limit);

        return response()->json(['ok' => true, 'data' => $result]);
    }

    public function createCollection(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string'],
            'description_html' => ['nullable', 'string'],
        ]);

        $collection = $this->service->createManualCollection(
            $request->only(['title', 'description_html']),
            true
        );

        return response()->json([
            'ok' => true,
            'data' => $collection,
        ]);
    }

    #[OA\Get(
        path: "/api/v1/shopify/variants",
        summary: "Inspeccionar variantes de productos",
        tags: ["Shopify Products"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Variants list",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function inspectProductVariantsBulkInput(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->inspectProductVariantsBulkInput(),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/shopify/publications",
        summary: "Listar canales de venta (publications) de Shopify",
        tags: ["Shopify Products"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Publications list",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function listPublications(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->listPublications(),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/shopify/locations",
        summary: "Listar ubicaciones (inventory locations) de Shopify",
        tags: ["Shopify Products"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Locations list",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function listLocations(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->listLocations(),
        ]);
    }

    public function listCollections(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->listCollections(),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/shopify/products/{provider}/{externalId}",
        summary: "Buscar sincronización de producto con categorías",
        tags: ["Shopify Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "provider", in: "path", required: true, schema: new OA\Schema(type: "string"), example: "syscom"),
            new OA\Parameter(name: "externalId", in: "path", required: true, schema: new OA\Schema(type: "string"), example: "12345")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Product sync data",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function findSyncWithCategories(string $provider, string $externalId): JsonResponse
    {
        $sync = $this->service->findSyncWithCategories($provider, $externalId);
    
        return response()->json([
            'ok' => true,
            'data' => $sync, // incluye categories por el with()
        ]);
    }

    #[OA\Get(
        path: "/api/v1/shopify/products/provider/{provider}",
        summary: "Listar productos sincronizados por proveedor",
        tags: ["Shopify Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "provider", in: "path", required: true, schema: new OA\Schema(type: "string"), example: "syscom"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 50)),
            new OA\Parameter(name: "category_id", in: "query", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated list of product syncs",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "ok", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "current_page", type: "integer"),
                                new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                                new OA\Property(property: "total", type: "integer")
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function listByProvider(Request $request, string $provider): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);
        $categoryId = $request->query('category_id') ? (int) $request->query('category_id') : null;

        $data = $this->service->listSyncsByProvider($provider, $perPage, $categoryId);

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }
}
