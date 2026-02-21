<?php

namespace App\Services\Categories;

use App\Services\Shopify\ShopifyProductService;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Contracts\Category\CategoryRepositoryInterface;

class CategoriesService
{

  public function __construct(
    private readonly CategoryRepositoryInterface $categoryRepository,
    private readonly ShopifyProductService $shopifyProductService
  ) {}

  public function createMany(array $categories): array
  {
      $created = [];

      foreach ($categories as $payload) {

          // Evitar duplicados (provider + provider_category_id)
          $exists = Category::where('provider', $payload['provider'])
              ->where('provider_category_id', $payload['provider_category_id'] ?? null)
              ->first();

          if ($exists) {
              $created[] = $exists;
              continue;
          }

          $level = (int) $payload['level'];

          // Relación padre (opcional)
          $parentId = $payload['parent_id'] ?? null;
          // Nivel 1 => Shopify Collection
          if ($level === 1) {
              $collection = $this->shopifyProductService->createManualCollection([
                  'name' => $payload['name'],
              ], true);

              $created[] = Category::create([
                  'provider' => $payload['provider'],
                  'provider_category_id' => $payload['provider_category_id'] ?? null,
                  'parent_id' => null, // nivel 1 no tiene padre
                  'name' => $payload['name'],
                  'level' => $level,
                  'active' => true,
                  'shopify_type' => 'collection',
                  'shopify_id' => $collection['id'],
              ]);

              continue;
          }

          // Nivel 2+ => Tag (string) guardado en tu DB
          $tag = Str::slug($payload['name']); // "Cámaras IP" => "camaras-ip"

          $created[] = Category::create([
              'provider' => $payload['provider'],
              'provider_category_id' => $payload['provider_category_id'] ?? null,
              'parent_provider_category_id' => $parentId, // 👈 aquí se vincula al padre
              'name' => $payload['name'],
              'level' => $level,
              'active' => true,
              'shopify_type' => 'tag',
              'shopify_id' => $tag,
          ]);
      }

      return $created;
  }

  public function list(array $filters)
  {
      $query = $this->categoryRepository
          ->query()
          ->where('provider', $filters['provider']);
  
      if (!empty($filters['level'])) {
          $query->where('level', (int) $filters['level']);
      }
  
      // Si mandan parent_id, filtra hijos; si no, no filtra
      if (array_key_exists('parent_id', $filters) && $filters['parent_id'] !== null) {
          $query->where('parent_id', (int) $filters['parent_id']);
      }
  
      if (!empty($filters['parent_provider_category_id'])) {
          $query->where(
              'parent_provider_category_id',
              (string) $filters['parent_provider_category_id']
          );
      }
  
      if (array_key_exists('active', $filters)) {
          $query->where('active', (bool) $filters['active']);
      }
  
      return $query
          ->orderBy('level')
          ->orderBy('name')
          ->get();
  }

  public function tree(array $filters): array
  {
      $query = Category::query()
          ->where('provider', $filters['provider']);
  
      if (array_key_exists('active', $filters)) {
          $query->where('active', (bool) $filters['active']);
      }
  
      $rows = $query
          ->orderBy('level')
          ->orderBy('name')
          ->get([
              'id',
              'provider',
              'provider_category_id',
              'parent_provider_category_id',
              'name',
              'level',
              'shopify_type',
              'shopify_id',
              'active',
          ])
          ->toArray();
  
      // index por provider_category_id
      $byProviderId = [];
      foreach ($rows as $row) {
          $row['children'] = [];
          $byProviderId[(string)($row['provider_category_id'] ?? '')] = $row;
      }
  
      // construir árbol por parent_provider_category_id
      $tree = [];
      foreach ($byProviderId as $providerId => &$node) {
          $parentProviderId = $node['parent_provider_category_id'] ?? null;
  
          if ($parentProviderId !== null && $parentProviderId !== '' && isset($byProviderId[(string)$parentProviderId])) {
              $byProviderId[(string)$parentProviderId]['children'][] = &$node;
          } else {
              $tree[] = &$node; // roots
          }
      }
      unset($node);
  
      return $tree;
  }
  
  public function active(int $id, bool $active) {
    $category = $this->categoryRepository->findOrFail($id);

    $category->update([
        'active' => $active,
    ]);

    return $category;
  }
}
