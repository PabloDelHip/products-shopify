<?php

namespace App\Services\Shopify;

use App\Models\ShopifyProductLog;
use App\Models\ProductUpload;
use Illuminate\Support\Facades\DB;
use App\Exports\ShopifyUploadExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use App\Contracts\ShopifyLogs\ShopifyLogsRepositoryInterface;
use App\Contracts\Shopify\ShopifyProductsRepositoryInterface;

class ShopifyProductService
{
    public function __construct(
        private readonly ShopifyClient $client,
        private readonly ShopifyLogsRepositoryInterface $shopifyLogsRepository,
        private readonly ShopifyProductsRepositoryInterface $shopifyProductsRepository,
    ) {}

    public function inspectProductVariantsBulkInput(): array
    {
        $query = <<<'GQL'
            query {
            __type(name: "ProductVariantsBulkInput") {
                name
                inputFields {
                name
                type {
                    kind
                    name
                    ofType {
                    kind
                    name
                    ofType {
                        kind
                        name
                    }
                    }
                }
                }
            }
            }
            GQL;
    
        $resp = $this->client->graphql($query, []);
    
        if (!isset($resp['data']['__type'])) {
            throw new \RuntimeException('Failed to introspect ProductVariantsBulkInput');
        }
    
        return $resp['data']['__type'];
    }
    
    private function buildSyncSnapshot(array $data): array
    {
        $v0 = (!empty($data['variants']) && is_array($data['variants']))
            ? $data['variants'][0]
            : null;

        return [
            'product_name' => (string)($data['title'] ?? ''),
            'sku' => $v0['sku'] ?? null,
            'price_amount' => array_key_exists('price', (array)$v0) ? (float)$v0['price'] : null,
            'stock' => array_key_exists('inventory_quantity', (array)$v0) ? (int)$v0['inventory_quantity'] : 0,
        ];
    }

    public function listPublications(): array
    {
        $query = <<<'GQL'
            query ListPublications {
            publications(first: 20) {
                nodes {
                id
                name
                }
            }
            }
            GQL;

        $resp = $this->client->graphql($query, []);

        // Manejo estándar de errores GraphQL
        if (!empty($resp['errors'])) {
            $msg = $resp['errors'][0]['message'] ?? 'Unknown GraphQL error';
            throw new \RuntimeException("GraphQL error: {$msg}");
        }

        return $resp['data']['publications']['nodes'] ?? [];
    }

    public function listLocations(): array
    {
        $query = <<<'GQL'
        query ListLocations {
        locations(first: 20, includeLegacy: true) {
            edges {
            node {
                id
                name
                isActive
            }
            }
        }
        }
        GQL;

        $resp = $this->client->graphql($query, []);

        if (!empty($resp['errors'])) {
            $msg = $resp['errors'][0]['message'] ?? 'Unknown GraphQL error';
            throw new \RuntimeException("GraphQL error: {$msg}");
        }

        return $resp['data']['locations']['edges'] ?? [];
    }

    public function getStatusCounts(): array
    {
        $results = ProductUpload::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        $statuses = [
            'pending' => 0,
            'processing' => 0,
            'success' => 0,
            'retry' => 0,
            'failed' => 0,
        ];

        foreach ($results as $row) {
            $statuses[$row->status] = (int) $row->total;
        }

        return $statuses;
    }

    public function saveProducts(array $products): array
    {
        $summary = [
            'total' => count($products),
            'created' => 0,
            'failed' => 0,
            'items' => [],
        ];
    
        if (empty($products)) {
            return $summary;
        }
    
        $now = now();
    
        foreach (array_chunk($products, 200) as $chunk) {
            DB::transaction(function () use (&$summary, $chunk, $now) {
                foreach ($chunk as $p) {
                    try {
                        $provider   = (string)($p['provider'] ?? 'syscom');
                        $externalId = (string)($p['external_id'] ?? '');
    
                        if ($externalId === '') {
                            throw new \InvalidArgumentException('products.*.external_id is required');
                        }
    
                        // Si quieres conservarlo como referencia, pero YA NO es unique
                        $dedupeKey = "{$provider}:{$externalId}";
    
                        $upload = ProductUpload::create([
                            'local_product_id' => null,
                            'provider' => $provider,
                            'dedupe_key' => $dedupeKey,
                            'status' => ProductUpload::STATUS_PENDING,
                            'attempts' => 0,
                            'data' => $p,
                            'response_payload' => null,
                            'external_product_id' => null,
                            'error_message' => null,
                            'processing_token' => null,
                            'locked_at' => null,
                            'queued_at' => $now,
                            'processed_at' => null,
                            'uploaded_at' => null,
                        ]);
    
                        $summary['created']++;
                        $summary['items'][] = [
                            'external_id' => $externalId,
                            'provider' => $provider,
                            'status' => 'created',
                            'upload_id' => $upload->id,
                        ];
                    } catch (\Throwable $e) {
                        $summary['failed']++;
                        $summary['items'][] = [
                            'external_id' => $p['external_id'] ?? null,
                            'provider' => $p['provider'] ?? 'syscom',
                            'status' => 'failed',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            });
        }
    
        return $summary;
    }

    public function Create(array $products): array
    {
        $summary = [
            'total' => count($products),
            'created' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach (array_chunk($products, 25) as $chunk) {
            foreach ($chunk as $p) {
                try {
                    $created = $this->createOne($p);

                    $summary['created']++;
                    $summary['items'][] = [
                        'title' => $p['title'] ?? '(no-title)',
                        'status' => 'created',
                        'shopify' => $created,
                    ];
                } catch (\Throwable $e) {
                    $summary['failed']++;
                    $summary['items'][] = [
                        'title' => $p['title'] ?? '(no-title)',
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $summary;
    }

    public function processPendingUploads(int $limit = 10, int $lockMinutes = 10): array
    {
        $token = (string) Str::uuid();
        $now = now();

        $summary = [
            'requested' => $limit,
            'claimed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'items' => [],
            'processing_token' => $token,
            'pending_remaining' => null,
            'retry_remaining' => null,
            'remaining_total' => null,
        ];

        // 1) Claim atómico: toma N pendientes/retry con lock expirado o sin lock
        $claimed = DB::transaction(function () use ($limit, $lockMinutes, $token, $now) {
            $rows = ProductUpload::query()
            ->where(function ($q) {
                $q->where('status', ProductUpload::STATUS_PENDING)
                ->orWhere(function ($q2) {
                    $q2->where('status', ProductUpload::STATUS_RETRY)
                        ->where('attempts', '<', 3);
                });
            })
            ->orderBy('queued_at')
            ->limit($limit)
            ->lockForUpdate()
            ->get();
            
            if ($rows->isEmpty()) {
                return collect();
            }

            ProductUpload::query()
                ->whereIn('id', $rows->pluck('id'))
                ->update([
                    'status' => ProductUpload::STATUS_PROCESSING,
                    'processing_token' => $token,
                    'locked_at' => $now,
                    'processed_at' => $now,
                ]);

            return $rows;
        });

        $summary['claimed'] = $claimed->count();

        if ($claimed->isEmpty()) {
            return $summary;
        }

        // 2) Procesa uno por uno
        foreach ($claimed as $upload) {
            try {
                // data viene casteado a array en el modelo
                $payload = is_array($upload->data) ? $upload->data : (array) $upload->data;

                $result = $this->createOne($payload);

                // Tu createOne puede retornar status 'created' o 'updated'
                $action = (string)($result['status'] ?? 'created');

                $upload->update([
                    'status' => ProductUpload::STATUS_SUCCESS,
                    'uploaded_at' => now(),
                    'external_product_id' => $result['shopify_product_id'] ?? null,
                    'response_payload' => $result,
                    'error_message' => null,
                    'processing_token' => null,
                    'locked_at' => null,
                ]);

                if ($action === 'updated') $summary['updated']++;
                else $summary['created']++;

                $summary['items'][] = [
                    'upload_id' => $upload->id,
                    'external_id' => $payload['external_id'] ?? null,
                    'title' => $payload['title'] ?? '(no-title)',
                    'action' => $action,
                    'status' => 'success',
                    'shopify_product_id' => $result['shopify_product_id'] ?? null,
                ];
            } catch (\Throwable $e) {
                $attempts = (int) $upload->attempts + 1;
                $maxAttempts = 3;

                $upload->update([
                    'status' => $attempts >= $maxAttempts
                        ? ProductUpload::STATUS_FAILED
                        : ProductUpload::STATUS_RETRY,
                    'attempts' => $attempts,
                    'error_message' => $e->getMessage(),
                    'response_payload' => [
                        'exception' => class_basename($e),
                        'message' => $e->getMessage(),
                    ],
                    'processing_token' => null,
                    'locked_at' => null,
                ]);

                $summary['failed']++;

                $summary['items'][] = [
                    'upload_id' => $upload->id,
                    'external_id' => ($upload->data['external_id'] ?? null),
                    'title' => ($upload->data['title'] ?? '(no-title)'),
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $pendingRemaining = ProductUpload::query()
            ->where('status', ProductUpload::STATUS_PENDING)
            ->count();

        $retryRemaining = ProductUpload::query()
            ->where('status', ProductUpload::STATUS_RETRY)
            ->where('attempts', '<', 3)
            ->count();

        $summary['pending_remaining'] = $pendingRemaining;
        $summary['retry_remaining'] = $retryRemaining;
        $summary['remaining_total'] = $pendingRemaining + $retryRemaining;

        return $summary;
    }

    public function listCollections(int $first = 50, ?string $after = null): array
    {
        $query = <<<'GQL'
        query ListCollections($first: Int!, $after: String) {
        collections(first: $first, after: $after) {
            pageInfo {
            hasNextPage
            endCursor
            }
            nodes {
            id
            title
            handle
            }
        }
        }
        GQL;

        $variables = [
            'first' => max(1, min($first, 250)), // Shopify max 250
            'after' => $after,
        ];

        $resp = $this->client->graphql($query, $variables);

        if (!empty($resp['errors'])) {
            $msg = $resp['errors'][0]['message'] ?? 'Unknown GraphQL error';
            throw new \RuntimeException("GraphQL error: {$msg}");
        }

        $collections = $resp['data']['collections'] ?? null;
        if (!$collections) {
            throw new \RuntimeException('Invalid Shopify response on collections');
        }

        return [
            'items' => $collections['nodes'] ?? [],
            'pageInfo' => $collections['pageInfo'] ?? [
                'hasNextPage' => false,
                'endCursor' => null,
            ],
        ];
    }

    public function createManualCollection(array $data, bool $publish = true): array
    {
        // El front manda "name" (no "title")
        $title = trim((string)($data['name'] ?? ''));
    
        if ($title === '') {
            throw new \InvalidArgumentException('Category name is required to create a Shopify collection');
        }
    
        $createMutation = <<<'GQL'
        mutation CreateCollection($input: CollectionInput!) {
          collectionCreate(input: $input) {
            collection { id title handle }
            userErrors { field message }
          }
        }
        GQL;
    
        $createVars = [
            'input' => array_filter([
                'title' => $title,
                // No lo mandas desde front, así que normalmente queda null
                'descriptionHtml' => null,
            ], fn($v) => $v !== null && $v !== ''),
        ];
    
        $resp = $this->client->graphql($createMutation, $createVars);
    
        if (!empty($resp['errors'])) {
            $msg = $resp['errors'][0]['message'] ?? 'Unknown GraphQL error';
            throw new \RuntimeException("GraphQL error: {$msg}");
        }
    
        $payload = $resp['data']['collectionCreate'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on collectionCreate');
        }
    
        $this->throwFirstUserError($payload['userErrors'] ?? []);
    
        $collection = $payload['collection'] ?? null;
        if (!$collection || empty($collection['id'])) {
            throw new \RuntimeException('Shopify did not return collection id');
        }
    
        if ($publish) {
            $publicationId = env('SHOPIFY_ONLINE_STORE_ID');
            if (!$publicationId) {
                throw new \RuntimeException('Missing SHOPIFY_ONLINE_STORE_ID (gid://shopify/Publication/...)');
            }
    
            $publishMutation = <<<'GQL'
            mutation PublishCollection($id: ID!, $pubId: ID!) {
              publishablePublish(id: $id, input: { publicationId: $pubId }) {
                userErrors { field message }
              }
            }
            GQL;
    
            $publishResp = $this->client->graphql($publishMutation, [
                'id' => $collection['id'],
                'pubId' => $publicationId,
            ]);
    
            if (!empty($publishResp['errors'])) {
                $msg = $publishResp['errors'][0]['message'] ?? 'Unknown GraphQL error';
                throw new \RuntimeException("GraphQL error: {$msg}");
            }
    
            $publishPayload = $publishResp['data']['publishablePublish'] ?? null;
            if (!$publishPayload) {
                throw new \RuntimeException('Invalid Shopify response on publishablePublish');
            }
    
            $this->throwFirstUserError($publishPayload['userErrors'] ?? []);
        }
    
        return $collection;
    }

    private function resolveCategoryIdsFromCollections(array $collections): array
    {
        // collections viene como: [ ["id" => "gid://shopify/Collection/..."], ... ]
        $collectionIds = collect($collections)
            ->map(function ($c) {
                if (is_array($c)) return $c['id'] ?? null;
                if (is_string($c)) return $c;
                return null;
            })
            ->filter(fn($id) => is_string($id) && str_starts_with($id, 'gid://shopify/Collection/'))
            ->values()
            ->all();

        if (empty($collectionIds)) return [];

        return \App\Models\Category::query()
            ->whereIn('shopify_id', $collectionIds)
            ->pluck('id')
            ->map(fn($id) => (int)$id)
            ->all();
    }

    private function attachCategoriesToSync(string $provider, string $externalId, array $categoryIds): void
    {
        // dump($externalId, $categoryIds);
        // $categoryIds = array_values(array_unique(array_filter(array_map(
        //     fn($id) => is_numeric($id) ? (int)$id : null,
        //     $categoryIds
        // ))));

        if (empty($categoryIds)) return;

        $sync = \App\Models\ShopifyProductSync::query()
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        if (!$sync) {
            throw new \RuntimeException("ShopifyProductSync not found for provider={$provider} external_id={$externalId}");
        }

        $sync->categories()->syncWithoutDetaching($categoryIds);
    }

    private function createOne(array $data): array
    {
        $provider   = (string)($data['provider'] ?? 'syscom');
        $externalId = (string)($data['external_id'] ?? '');
    
        if ($externalId === '') {
            throw new \InvalidArgumentException('products.*.external_id is required');
        }

        // 0) Procesa la estructura 'categorias' si viene en el payload
        $extraCatInternalIds = [];
        if (!empty($data['categorias']) && is_array($data['categorias'])) {
            $catResult = $this->ensureCategories($provider, $data['categorias']);
            $extraCatInternalIds = $catResult['internal_ids'];

            // Mezclar colecciones de Shopify (nivel 1)
            if (!isset($data['collections'])) {
                $data['collections'] = [];
            }
            foreach ($catResult['collection_ids'] as $cid) {
                $exists = false;
                foreach ($data['collections'] as $existing) {
                    if (($existing['id'] ?? '') === $cid) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $data['collections'][] = ['id' => $cid];
                }
            }

            // Mezclar tags (nivel 2+)
            if (!isset($data['tags'])) {
                $data['tags'] = [];
            }
            $data['tags'] = array_unique(array_merge($data['tags'], $catResult['tags']));
        }
    
        $payloadHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
        $snapshot = $this->buildSyncSnapshot($data);
        // 1) leer sync local (si existe)
        $sync = $this->shopifyProductsRepository->findByProviderExternalId($provider, $externalId);
        
        
        // if ($sync) {
        //     $this->syncLocalCategoriesFromCollections(
        //         $sync,
        //         $data['collections'] ?? []
        //     );
        // }
        
        $shopifyProductId = $sync?->shopify_product_id; // gid://shopify/Product/...
    
        // 2) Si tengo Shopify ID => UPDATE EN SHOPIFY (real)
        if (!empty($shopifyProductId)) {
            try {
                $updated = $this->updateOne($shopifyProductId, $data);
                // sync table
                $this->shopifyProductsRepository->upsertByProviderExternalId($provider, $externalId, [
                    'shopify_product_id' => $shopifyProductId,
                    'sync_status' => 'SUCCESS',
                    'payload_hash' => $payloadHash,
                    'last_synced_at' => now(),
                    'last_error' => null,
                    ...$snapshot,
                ]);

                $categoryIds = $this->resolveCategoryIdsFromCollections($data['collections'] ?? []);
                $categoryIds = array_unique(array_merge($categoryIds, $extraCatInternalIds));
                $this->attachCategoriesToSync($provider, $externalId, $categoryIds);
    
                // logs
                $this->shopifyLogsRepository->create([
                    'provider' => $provider,
                    'external_id' => $externalId,
                    'shopify_product_id' => $shopifyProductId,
                    'action' => ShopifyProductLog::ACTION_UPDATE,
                    'status' => ShopifyProductLog::STATUS_SUCCESS,
                    'payload' => $data,
                    'response' => $updated,
                ]);
    
                return [
                    'status' => 'updated',
                    'shopify_product_id' => $shopifyProductId,
                    'shopify' => $updated,
                ];
            } catch (\Throwable $e) {
                // Si el error es específicamente que NO se encuentra el producto en Shopify...
                if ($this->isShopifyNotFoundError($e)) {
                    // Limpiamos el ID para que el flujo siga hacia abajo y lo CREE de nuevo en Shopify
                    $shopifyProductId = null;
                } else {
                    // Si es otro tipo de error, mantenemos el comportamiento original: reportar el error y relanzar
                    $this->shopifyProductsRepository->upsertByProviderExternalId($provider, $externalId, [
                        'shopify_product_id' => $shopifyProductId,
                        'sync_status' => 'ERROR',
                        'payload_hash' => $payloadHash,
                        'last_error' => $e->getMessage(),
                        ...$snapshot,
                    ]);

                    $categoryIds = $this->resolveCategoryIdsFromCollections($data['collections'] ?? []);
                    $categoryIds = array_unique(array_merge($categoryIds, $extraCatInternalIds));
                    if (!empty($categoryIds)) {
                        $this->attachCategoriesToSync($provider, $externalId, $categoryIds);
                    }

                    $this->shopifyLogsRepository->create([
                        'provider' => $provider,
                        'external_id' => $externalId,
                        'shopify_product_id' => $shopifyProductId,
                        'action' => ShopifyProductLog::ACTION_UPDATE,
                        'status' => ShopifyProductLog::STATUS_ERROR,
                        'payload' => $data,
                        'error_message' => $e->getMessage(),
                    ]);
        
                    throw $e;
                }
            }
        }
    
        // 3) Si NO tengo Shopify ID => CREATE EN SHOPIFY (tu flujo actual)
        $productId = null;
    
        try {
            $locationId = env('SHOPIFY_LOCATION_ID');
            if (!$locationId) {
                throw new \RuntimeException('Missing SHOPIFY_LOCATION_ID');
            }
    
            $product = $this->step1_createProduct($data);
            $productId = $product['id'] ?? null;
    
            if (!$productId) {
                throw new \RuntimeException('Shopify did not return product id');
            }
    
            $this->publishToOnlineStore($productId);
            $variants = $this->step2_bulkCreateVariants($productId, $locationId, $data);
            $media = $this->step3_createMedia($productId, $data);
            $this->step_assignCollections($productId, $data['collections'] ?? []);
            $result = compact('product', 'variants', 'media');
            $snapshot = $this->buildSyncSnapshot($data);
            // dd($snapshot);
            $this->shopifyProductsRepository->upsertByProviderExternalId($provider, $externalId, [
                'shopify_product_id' => $productId,
                'sync_status' => 'SUCCESS',
                'payload_hash' => $payloadHash,
                'last_synced_at' => now(),
                'last_error' => null,
                ...$snapshot,
            ]);
    
            $this->shopifyLogsRepository->create([
                'provider' => $provider,
                'external_id' => $externalId,
                'shopify_product_id' => $productId,
                'action' => ShopifyProductLog::ACTION_CREATE,
                'status' => ShopifyProductLog::STATUS_SUCCESS,
                'payload' => $data,
                'response' => $result,
            ]);

            $categoryIds = $this->resolveCategoryIdsFromCollections($data['collections'] ?? []);
            $categoryIds = array_unique(array_merge($categoryIds, $extraCatInternalIds));
            $this->attachCategoriesToSync($provider, $externalId, $categoryIds);
    
            return [
                'status' => 'created',
                'shopify_product_id' => $productId,
                'shopify' => $result,
            ];
    
        } catch (\Throwable $e) {
            $this->shopifyProductsRepository->upsertByProviderExternalId($provider, $externalId, [
                'shopify_product_id' => $productId,
                'sync_status' => $productId ? 'PARTIAL' : 'ERROR',
                'payload_hash' => $payloadHash,
                'last_error' => $e->getMessage(),
                ...$snapshot,
            ]);
    
            $this->shopifyLogsRepository->create([
                'provider' => $provider,
                'external_id' => $externalId,
                'shopify_product_id' => $productId,
                'action' => ShopifyProductLog::ACTION_CREATE,
                'status' => ShopifyProductLog::STATUS_ERROR,
                'payload' => $data,
                'error_message' => $e->getMessage(),
            ]);
    
            throw $e;
        }
    }

    private function step_assignCollections(string $productId, $collections): array
    {
        if (empty($collections)) {
            return ['assigned' => 0, 'items' => []];
        }

        if (!is_array($collections)) {
            throw new \RuntimeException('collections must be an array');
        }

        $assigned = 0;
        $items = [];

        foreach ($collections as $c) {
            $collectionId = null;

            // Caso: { "id": "gid://shopify/Collection/..." }
            if (is_array($c) && !empty($c['id'])) {
                $collectionId = (string) $c['id'];
            }

            // (opcional) si algún día mandas strings directos
            if (!$collectionId && is_string($c) && str_starts_with($c, 'gid://shopify/Collection/')) {
                $collectionId = $c;
            }

            if (!$collectionId) {
                $items[] = [
                    'status' => 'skipped',
                    'reason' => 'missing_collection_id',
                ];
                continue;
            }



            try {
                $this->addProductToCollection($collectionId, $productId);
            } catch (\Throwable $e) {
                if (!str_contains($e->getMessage(), 'Error adding')) {
                    throw $e;
                }
                // si ya está, no pasa nada
            }

            $assigned++;
            $items[] = [
                'collection_id' => $collectionId,
                'status' => 'assigned',
            ];
        }

        return ['assigned' => $assigned, 'items' => $items];
    }

    private function addProductToCollection(string $collectionId, string $productId): void
    {
        $mutation = <<<'GQL'
        mutation CollectionAddProducts($id: ID!, $productIds: [ID!]!) {
        collectionAddProducts(id: $id, productIds: $productIds) {
            userErrors { field message }
        }
        }
        GQL;

        $resp = $this->client->graphql($mutation, [
            'id' => $collectionId,
            'productIds' => [$productId],
        ]);

        if (!empty($resp['errors'])) {
            $msg = $resp['errors'][0]['message'] ?? 'Unknown GraphQL error';
            throw new \RuntimeException("GraphQL error: {$msg}");
        }

        $payload = $resp['data']['collectionAddProducts'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on collectionAddProducts');
        }

        $this->throwFirstUserError($payload['userErrors'] ?? []);
    }

    private function updateOne(string $productId, array $data): array
    {
        // 1) Producto base
        $product = $this->step_updateProduct($productId, $data);
    
        // 2) Metafields
        $metafields = $this->step_setMetafields($productId, $data);
    
        // 3) Tomamos la primera variante del payload (tu caso actual)
        $variants = $data['variants'] ?? [];
        $v0 = (is_array($variants) && !empty($variants)) ? $variants[0] : null;
    
        $variantUpdated = null;
        $inventoryResult = null;
    
        if ($v0 && !empty($v0['sku'])) {
            // 3.1) Encontrar variantId real en Shopify por SKU
            $variantId = $this->findVariantIdBySku($productId, (string)$v0['sku']);
    
            if ($variantId) {
                // 3.2) Update campos de la variante (precio, compareAtPrice, taxable, etc.)
                $variantUpdated = $this->step_updateVariant($productId, $variantId, $v0);
    
                // 3.3) Inventario (si viene inventory_quantity)
                if (array_key_exists('inventory_quantity', $v0) && $v0['inventory_quantity'] !== null) {
                    $locationId = env('SHOPIFY_LOCATION_ID');
                    if (!$locationId) {
                        throw new \RuntimeException('Missing SHOPIFY_LOCATION_ID');
                    }
    
                    // inventoryItemId se obtiene desde el variantId
                    $inventoryItemId = $this->getInventoryItemIdByVariantId($variantId);
    
                    $inventoryResult = $this->setInventoryAvailable(
                        $inventoryItemId,
                        $locationId,
                        (int)$v0['inventory_quantity']
                    );
                }
            } else {
                $variantUpdated = ['warning' => 'Variant SKU not found in product; not updated'];
            }
        }
    
        // 4) Media (Limpiar existentes y subir nuevas para evitar duplicados)
        if (isset($data['images']) && is_array($data['images'])) {
            $existingMediaIds = $this->getProductMediaIds($productId);
            if (!empty($existingMediaIds)) {
                $this->step_deleteMedia($productId, $existingMediaIds);
            }
        }
        $media = $this->step3_createMedia($productId, $data);
        $this->step_assignCollections($productId, $data['collections'] ?? []);

        return compact('product', 'metafields', 'variantUpdated', 'inventoryResult', 'media');
    }
    
    private function syncLocalCategoriesFromCollections(
        \App\Models\ShopifyProductSync $sync,
        array $collections
    ): void {
        if (empty($collections)) return;

        // collections viene como array de strings (gid://shopify/Collection/...)
        $collectionIds = array_values(array_filter(array_map(
            fn($c) => is_string($c) ? trim($c) : '',
            $collections
        )));

        if (empty($collectionIds)) return;

        // Buscar categorías locales por shopify_id (colecciones)
        $categoryIds = \App\Models\Category::query()
            ->where('shopify_type', 'collection')
            ->whereIn('shopify_id', $collectionIds)
            ->pluck('id')
            ->all();

        if (empty($categoryIds)) return;

        // Guardar pivote (sin duplicar)
        $sync->categories()->syncWithoutDetaching($categoryIds);
    }
    
    private function getInventoryItemIdByVariantId(string $variantId): string
    {
        $query = <<<'GQL'
        query VariantInventoryItem($id: ID!) {
        productVariant(id: $id) {
            id
            inventoryItem { id }
        }
        }
        GQL;

        $resp = $this->client->graphql($query, ['id' => $variantId]);

        if (!empty($resp['errors'])) {
            $msg = $resp['errors'][0]['message'] ?? 'Unknown GraphQL error';
            throw new \RuntimeException("GraphQL error: {$msg}");
        }

        $iid = $resp['data']['productVariant']['inventoryItem']['id'] ?? null;
        if (!$iid) {
            throw new \RuntimeException('Shopify did not return inventoryItem id for variant');
        }

        return $iid;
    }

    private function setInventoryAvailable(string $inventoryItemId, string $locationId, int $availableQty): array
    {
        $mutation = <<<'GQL'
        mutation inventorySetQuantities($input: InventorySetQuantitiesInput!) {
          inventorySetQuantities(input: $input) {
            userErrors { field message }
            inventoryAdjustmentGroup { id }
          }
        }
        GQL;
    
        $input = [
            'name' => 'available',
            'reason' => 'correction',
            'ignoreCompareQuantity' => true, // ✅ clave
            'quantities' => [[
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'quantity' => $availableQty,
            ]],
        ];
    
        $resp = $this->client->graphql($mutation, ['input' => $input]);
    
        $payload = $resp['data']['inventorySetQuantities'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on inventorySetQuantities');
        }
    
        $this->throwFirstUserError($payload['userErrors'] ?? []);
    
        return $payload;
    }
    

    private function step_updateProduct(string $productId, array $data): array
    {
        $mutation = <<<'GQL'
        mutation productUpdate($input: ProductInput!) {
        productUpdate(input: $input) {
            product { id title handle status }
            userErrors { field message }
        }
        }
        GQL;

        $input = array_filter([
            'id' => $productId,
            'title' => $data['title'] ?? null,
            'descriptionHtml' => $data['description_html'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'productType' => $data['product_type'] ?? null,
            'status' => $data['status'] ?? null, // ACTIVE / DRAFT
            'tags' => $data['tags'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        $resp = $this->client->graphql($mutation, ['input' => $input]);

        $payload = $resp['data']['productUpdate'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on productUpdate');
        }

        $this->throwFirstUserError($payload['userErrors'] ?? []);

        return $payload['product'] ?? [];
    }
    
    private function step_setMetafields(string $productId, array $data): array
    {
        $metafields = $this->mapMetafields($data['metafields'] ?? null);

        if (empty($metafields)) {
            return ['updated' => 0, 'metafields' => []];
        }

        $inputs = array_map(fn($m) => [
            'ownerId' => $productId,
            'namespace' => $m['namespace'],
            'key' => $m['key'],
            'type' => $m['type'],
            'value' => $m['value'],
        ], $metafields);

        $mutation = <<<'GQL'
        mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
        metafieldsSet(metafields: $metafields) {
            metafields { id namespace key }
            userErrors { field message }
        }
        }
        GQL;

        $resp = $this->client->graphql($mutation, ['metafields' => $inputs]);

        $payload = $resp['data']['metafieldsSet'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on metafieldsSet');
        }

        $this->throwFirstUserError($payload['userErrors'] ?? []);

        return [
            'updated' => count($payload['metafields'] ?? []),
            'metafields' => $payload['metafields'] ?? [],
        ];
    }

    private function findVariantIdBySku(string $productId, string $sku): ?string
    {
        $query = <<<'GQL'
        query ProductVariants($id: ID!) {
        product(id: $id) {
            variants(first: 100) {
            nodes { id sku }
            }
        }
        }
        GQL;

        $resp = $this->client->graphql($query, ['id' => $productId]);

        if (!empty($resp['errors'])) {
            $msg = $resp['errors'][0]['message'] ?? 'Unknown GraphQL error';
            throw new \RuntimeException("GraphQL error: {$msg}");
        }

        $nodes = $resp['data']['product']['variants']['nodes'] ?? [];

        foreach ($nodes as $v) {
            if (($v['sku'] ?? null) === $sku) {
                return $v['id'];
            }
        }

        return null;
    }

    private function step_updateVariant(string $productId, string $variantId, array $variantData): array
    {
        $mutation = <<<'GQL'
        mutation productVariantsBulkUpdate(
          $productId: ID!,
          $variants: [ProductVariantsBulkInput!]!
        ) {
          productVariantsBulkUpdate(productId: $productId, variants: $variants) {
            product { id }
            productVariants { id sku price compareAtPrice taxable }
            userErrors { field message }
          }
        }
        GQL;
    
        $variantInput = array_filter([
            'id' => $variantId,
            'price' => isset($variantData['price'])
                ? number_format((float)$variantData['price'], 2, '.', '')
                : null,
            'compareAtPrice' => isset($variantData['compare_at_price'])
                ? number_format((float)$variantData['compare_at_price'], 2, '.', '')
                : null,
            'taxable' => $variantData['taxable'] ?? null,
    
            // ⚠️ OJO: NO metas inventoryQuantities aquí en update
            // para inventario usa inventorySetQuantities como ya lo haces
        ], fn($v) => $v !== null && $v !== '');
    
        $resp = $this->client->graphql($mutation, [
            'productId' => $productId,
            'variants' => [$variantInput],
        ]);
    
        $payload = $resp['data']['productVariantsBulkUpdate'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on productVariantsBulkUpdate');
        }
    
        $this->throwFirstUserError($payload['userErrors'] ?? []);
    
        return $payload['productVariants'][0] ?? [];
    }
    
    

    private function publishToOnlineStore(string $productId): void
    {
        $publicationId = env('SHOPIFY_ONLINE_STORE_ID');
        if (!$publicationId) {
            throw new \RuntimeException('Missing SHOPIFY_ONLINE_STORE_ID (gid://shopify/Publication/...)');
        }

        $mutation = <<<'GQL'
            mutation PublishProductToOnlineStore($id: ID!, $pubId: ID!) {
            publishablePublish(id: $id, input: { publicationId: $pubId }) {
                userErrors { field message }
            }
            }
            GQL;

        $resp = $this->client->graphql($mutation, [
            'id' => $productId,
            'pubId' => $publicationId,
        ]);

        $payload = $resp['data']['publishablePublish'] ?? null;
        if (!$payload) {
            // por si Shopify responde raro
            $msg = $resp['errors'][0]['message'] ?? 'Invalid Shopify response on publishablePublish';
            throw new \RuntimeException($msg);
        }

        $this->throwFirstUserError($payload['userErrors'] ?? []);
    }

    private function step1_createProduct(array $data): array
    {
        $mutation = <<<'GQL'
          mutation productCreate($product: ProductCreateInput!) {
            productCreate(product: $product) {
              product { id title handle status }
              userErrors { field message }
            }
          }
          GQL;

        $metafields = $this->mapMetafields($data['metafields'] ?? null);

        $variables = [
            'product' => array_filter([
                'title' => $data['title'] ?? null,
                'descriptionHtml' => $data['description_html'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'productType' => $data['product_type'] ?? null,
                'status' => $data['status'] ?? null, // ACTIVE / DRAFT
                'tags' => $data['tags'] ?? null,
                'metafields' => !empty($metafields) ? $metafields : null,
            ], fn($v) => $v !== null && $v !== ''),
        ];

        $resp = $this->client->graphql($mutation, $variables);

        $payload = $resp['data']['productCreate'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on productCreate');
        }

        $this->throwFirstUserError($payload['userErrors'] ?? []);

        return $payload['product'];
    }

    private function step2_bulkCreateVariants(string $productId, string $locationId, array $data): array
    {
        $variants = $data['variants'] ?? [];
        if (!is_array($variants) || count($variants) < 1) {
            throw new \RuntimeException('products.*.variants must contain at least 1 variant');
        }
    
        $mutation = <<<'GQL'
            mutation productVariantsBulkCreate(
            $productId: ID!,
            $variants: [ProductVariantsBulkInput!]!,
            $strategy: ProductVariantsBulkCreateStrategy
            ) {
            productVariantsBulkCreate(productId: $productId, variants: $variants, strategy: $strategy) {
                product { id }
                productVariants { id }
                userErrors { field message }
            }
            }
            GQL;
    
        $variantsInput = array_map(function ($v) use ($locationId) {
            $invQty = isset($v['inventory_quantity']) ? (int)$v['inventory_quantity'] : null;
    
            return array_filter([
                // dinero (tu schema usa Money, Shopify acepta string/decimal)
                'price' => isset($v['price']) ? number_format((float)$v['price'], 2, '.', '') : null,
                'compareAtPrice' => isset($v['compare_at_price'])
                    ? number_format((float)$v['compare_at_price'], 2, '.', '')
                    : null,
    
                'taxable' => $v['taxable'] ?? null,
    
                // inventario por location (esto SÍ existe en ProductVariantsBulkInput)
                'inventoryQuantities' => $invQty !== null
                    ? [[
                        'locationId' => $locationId,
                        'availableQuantity' => $invQty,
                    ]]
                    : null,
    
                // ✅ SKU y requiresShipping van dentro de inventoryItem (tu schema lo confirma)
                'inventoryItem' => array_filter([
                    'sku' => $v['sku'] ?? null,
                    'tracked' => (($v['inventory_management'] ?? 'SHOPIFY') === 'SHOPIFY') ? true : null,
                    'requiresShipping' => $v['requires_shipping'] ?? null,
                ], fn($x) => $x !== null && $x !== ''),
            ], fn($x) => $x !== null && $x !== '');
        }, $variants);
    
        $variables = [
            'productId' => $productId,
            'strategy' => 'REMOVE_STANDALONE_VARIANT',
            'variants' => $variantsInput,
        ];
    
        $resp = $this->client->graphql($mutation, $variables);
    
        $payload = $resp['data']['productVariantsBulkCreate'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on productVariantsBulkCreate');
        }
    
        $this->throwFirstUserError($payload['userErrors'] ?? []);
    
        return [
            'productId' => $payload['product']['id'] ?? $productId,
            'variants' => $payload['productVariants'] ?? [],
        ];
    }

    private function step3_createMedia(string $productId, array $data): array
    {
        $images = $data['images'] ?? [];
        if (empty($images)) {
            return ['created' => 0, 'media' => []];
        }
        if (!is_array($images)) {
            throw new \RuntimeException('images must be an array of urls');
        }

        $mutation = <<<'GQL'
        mutation productCreateMedia($productId: ID!, $media: [CreateMediaInput!]!) {
        productCreateMedia(productId: $productId, media: $media) {
            media { ... on MediaImage { id image { url } } }
            mediaUserErrors { field message }
        }
        }
        GQL;

        $media = array_map(fn($url) => [
            'mediaContentType' => 'IMAGE',
            'originalSource' => $url,
        ], $images);

        $variables = [
            'productId' => $productId,
            'media' => $media,
        ];

        $resp = $this->client->graphql($mutation, $variables);

        $payload = $resp['data']['productCreateMedia'] ?? null;
        if (!$payload) {
            throw new \RuntimeException('Invalid Shopify response on productCreateMedia');
        }

        // mediaUserErrors (no userErrors)
        $errs = $payload['mediaUserErrors'] ?? [];
        if (!empty($errs)) {
            $first = $errs[0];
            $field = isset($first['field']) ? implode('.', (array)$first['field']) : '';
            throw new \RuntimeException(trim($field . ' ' . ($first['message'] ?? 'Unknown media error')));
        }

        return [
            'created' => count($payload['media'] ?? []),
            'media' => $payload['media'] ?? [],
        ];
    }

    private function getProductMediaIds(string $productId): array
    {
        $query = <<<'GQL'
        query GetProductMedia($id: ID!) {
          product(id: $id) {
            media(first: 100) {
              nodes {
                id
              }
            }
          }
        }
        GQL;

        $resp = $this->client->graphql($query, ['id' => $productId]);

        if (!empty($resp['errors'])) {
            return [];
        }

        return array_map(fn($node) => $node['id'], $resp['data']['product']['media']['nodes'] ?? []);
    }

    private function step_deleteMedia(string $productId, array $mediaIds): void
    {
        if (empty($mediaIds)) return;

        $mutation = <<<'GQL'
        mutation productDeleteMedia($productId: ID!, $mediaIds: [ID!]!) {
          productDeleteMedia(productId: $productId, mediaIds: $mediaIds) {
            deletedMediaIds
            userErrors { field message }
          }
        }
        GQL;

        $this->client->graphql($mutation, [
            'productId' => $productId,
            'mediaIds' => $mediaIds,
        ]);
    }

    

    private function mapMetafields($metafieldsKV): array
    {
        if (empty($metafieldsKV) || !is_array($metafieldsKV)) {
            return [];
        }

        $out = [];
        foreach ($metafieldsKV as $k => $val) {
            // Shopify metafield key: solo letras, números, guión bajo; mejor asegurar
            $key = strtolower((string)$k);
            $key = preg_replace('/[^a-z0-9_]/', '_', $key);

            $isArray = is_array($val);

            $out[] = [
                'namespace' => 'syscom',
                'key' => $key,
                'type' => $isArray ? 'json' : 'single_line_text_field',
                'value' => $isArray ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string)$val,
            ];
        }

        return $out;
    }

    private function throwFirstUserError(array $userErrors): void
    {
        if (empty($userErrors)) return;

        $first = $userErrors[0];
        $field = isset($first['field']) ? implode('.', (array)$first['field']) : '';
        $msg = $first['message'] ?? 'Unknown Shopify error';

        throw new \RuntimeException(trim($field . ' ' . $msg));
    }

    public function findSyncWithCategories(string $provider, string $externalId): ?\App\Models\ShopifyProductSync
    {
        return \App\Models\ShopifyProductSync::query()
            ->with(['categories:id,name,shopify_id,shopify_type'])
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();
    }

    public function listSyncsByProvider(string $provider, int $perPage = 50, ?int $categoryId = null)
    {
        $query = \App\Models\ShopifyProductSync::query()
            ->with([
                'categories:id,name,shopify_id,shopify_type'
            ])
            ->where('provider', $provider);

        if ($categoryId) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                // Buscamos por el ID del proveedor (syscom id) que es el que se manda por la URL
                $q->where('categories.provider_category_id', $categoryId);
            });
        }

        return $query->orderByDesc('id')
            ->paginate($perPage);
    }

    private function isShopifyNotFoundError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        // 1. Error de GraphQL (top level) cuando el ID no existe en el esquema o es inválido
        // Ej: "Variable $input of type ProductInput! was provided invalid value for id (Could not find Product with id gid://shopify/Product/123)"
        if (str_contains($message, 'Could not find Product with id')) {
            return true;
        }

        // 2. Error de userErrors (segundo nivel) cuando el ID es sintácticamente correcto pero el objeto no está
        // Ej: "id Product not found"
        if (str_contains(strtolower($message), 'product not found')) {
            return true;
        }

        return false;
    }

    private function ensureCategories(string $provider, array $categorias): array
    {
        $collectionIds = [];
        $tags = [];
        $internalIds = [];

        foreach ($categorias as $cat) {
            $catId = (string)($cat['id'] ?? '');
            $nombre = (string)($cat['nombre'] ?? '');
            $nivel = (int)($cat['nivel'] ?? 0);

            if ($catId === '') continue;

            $category = \App\Models\Category::firstOrCreate(
                [
                    'provider' => $provider,
                    'provider_category_id' => $catId,
                ],
                [
                    'name' => $nombre,
                    'level' => $nivel,
                    'active' => true,
                    'created_by_batch' => true,
                ]
            );

            $internalIds[] = $category->id;

            if ($nivel === 1) {
                if (empty($category->shopify_id)) {
                    try {
                        $shopifyCol = $this->createManualCollection(['name' => $nombre], true);
                        $category->update([
                            'shopify_id' => $shopifyCol['id'],
                            'shopify_type' => 'COLLECTION',
                        ]);
                    } catch (\Throwable $e) {
                        // Opcional: registrar error?
                    }
                }
                if (!empty($category->shopify_id)) {
                    $collectionIds[] = $category->shopify_id;
                }
            } else {
                // Nivel 2 en adelante se guarda en la DB (ya está arriba) y se usa como tag
                $tags[] = $nombre;
            }
        }

        return [
            'collection_ids' => $collectionIds,
            'tags' => $tags,
            'internal_ids' => $internalIds,
        ];
    }

    public function getUploadReport(array $filters): array
    {
        $query = \App\Models\ShopifyProductLog::query();

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (filter_var($filters['summary'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $query->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "SUCCESS" THEN 1 ELSE 0 END) as success'),
                DB::raw('SUM(CASE WHEN status = "ERROR" THEN 1 ELSE 0 END) as error')
            )
            ->groupBy('date')
            ->orderByDesc('date')
            ->get()
            ->toArray();
        }

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 50)
            ->toArray();
    }

    public function exportUploadReport(array $filters)
    {
        $filename = 'shopify_uploads_' . now()->format('Y_m_d_His') . '.xlsx';
        return Excel::download(new ShopifyUploadExport($filters), $filename);
    }
}
