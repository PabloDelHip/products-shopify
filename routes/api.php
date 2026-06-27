<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ShopifyProductController;
use App\Http\Controllers\Api\V1\CategoriesController;
use App\Http\Controllers\Api\V1\SyscomController;
use App\Http\Controllers\Api\V1\UserController;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::get('/debug-token', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'authorization_header' => $request->header('Authorization'),
        'bearer_token' => $request->bearerToken(),
        'expects_json' => $request->expectsJson(),
    ]);
});

      Route::get('auth/me', [AuthController::class, 'me']);
      Route::post('auth/refresh', [AuthController::class, 'refresh']);
      Route::post('auth/logout', [AuthController::class, 'logout']);

      // Syscom Proxy
      Route::post('syscom/auth/login', [SyscomController::class, 'login']);
      Route::get('syscom/categorias', [SyscomController::class, 'getCategories']);
      Route::get('syscom/categorias/{id}', [SyscomController::class, 'getCategoryDetail']);
      Route::get('syscom/productos', [SyscomController::class, 'getProducts']);
      Route::get('syscom/productos/{id}', [SyscomController::class, 'getProductDetail']);

      Route::post('category', [CategoriesController::class, 'create']);
      Route::get('category', [CategoriesController::class, 'list']);
      Route::get('category/tree', [CategoriesController::class, 'getTree']);
      Route::get('category/ancestors', [CategoriesController::class, 'getAncestors']);
      Route::put('category/{id}/active', [CategoriesController::class, 'active']);

      Route::post('shopify/products', [ShopifyProductController::class, 'create']);
      Route::post('/shopify/products/uploads/process', [ShopifyProductController::class, 'uploadPending']);
      Route::get(
        'shopify/products/provider/{provider}',
        [ShopifyProductController::class, 'listByProvider']
      );
      Route::get(
        'shopify/product-uploads/metrics',
        [ShopifyProductController::class, 'getStatusCounts']
    );
      Route::get('shopify/products/{provider}/{externalId}', [ShopifyProductController::class, 'findSyncWithCategories']);
      Route::delete('shopify/products/{provider}/{externalId}', [ShopifyProductController::class, 'deleteProduct']);
    
      Route::get('shopify/variants', [ShopifyProductController::class, 'inspectProductVariantsBulkInput']);
      Route::get('shopify/locations', [ShopifyProductController::class, 'listLocations']);
      Route::get('shopify/publications', [ShopifyProductController::class, 'listPublications']);

      Route::post('shopify/collections', [ShopifyProductController::class, 'createCollection']);
      Route::get('shopify/collections', [ShopifyProductController::class, 'listCollections']);

      Route::get('shopify/reports/uploads', [ShopifyProductController::class, 'getUploadReport']);

      Route::get('users', [UserController::class, 'index']);
      Route::get('users/{id}', [UserController::class, 'show']);
      Route::post('users', [UserController::class, 'store']);
      Route::put('users/{id}', [UserController::class, 'update']);
      Route::delete('users/{id}', [UserController::class, 'destroy']);
});
