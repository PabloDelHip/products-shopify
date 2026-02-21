<?php

namespace App\Contracts\Shopify;

use App\Contracts\BaseRepositoryInterface;
use App\Models\ShopifyProductSync;

interface ShopifyProductsRepositoryInterface extends BaseRepositoryInterface
{
    public function findByProviderExternalId(string $provider, string $externalId): ?ShopifyProductSync;

    public function upsertByProviderExternalId(string $provider, string $externalId, array $data): ShopifyProductSync;
}
