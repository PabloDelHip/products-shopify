<?php

namespace App\Repositories\Shopify;

use App\Repositories\BaseRepository;
use App\Contracts\Shopify\ShopifyProductsRepositoryInterface;
use App\Models\ShopifyProductSync;

class EloquentShopifyProductsRepository extends BaseRepository
    implements ShopifyProductsRepositoryInterface
{
    public function __construct(ShopifyProductSync $model)
    {
        parent::__construct($model);
    }

    public function findByProviderExternalId(string $provider, string $externalId): ?ShopifyProductSync
    {
        /** @var ShopifyProductSync|null $row */
        $row = $this->query()
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        return $row;
    }

    public function upsertByProviderExternalId(string $provider, string $externalId, array $data): ShopifyProductSync
    {
        // dd($data);
        /** @var ShopifyProductSync $row */
        $row = $this->query()->updateOrCreate(
            ['provider' => $provider, 'external_id' => $externalId],
            $data
        );

        return $row;
    }
}
