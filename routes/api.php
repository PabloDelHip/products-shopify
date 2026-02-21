<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ShopifyProductController;
use App\Http\Controllers\Api\V1\CategoriesController;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
      Route::get('auth/me', [AuthController::class, 'me']);
      Route::post('auth/refresh', [AuthController::class, 'refresh']);
      Route::post('auth/logout', [AuthController::class, 'logout']);

      Route::post('category', [CategoriesController::class, 'create']);
      Route::get('category', [CategoriesController::class, 'list']);
      Route::get('category/tree', [CategoriesController::class, 'getTree']);
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
    
      Route::get('shopify/variants', [ShopifyProductController::class, 'inspectProductVariantsBulkInput']);
      Route::get('shopify/locations', [ShopifyProductController::class, 'listLocations']);
      Route::get('shopify/publications', [ShopifyProductController::class, 'listPublications']);

      Route::post('shopify/collections', [ShopifyProductController::class, 'createCollection']);
      Route::get('shopify/collections', [ShopifyProductController::class, 'listCollections']);
  });
});
