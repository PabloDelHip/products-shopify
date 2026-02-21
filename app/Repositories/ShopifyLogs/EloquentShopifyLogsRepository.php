<?php

namespace App\Repositories\ShopifyLogs;

use App\Repositories\BaseRepository;
use App\Contracts\ShopifyLogs\ShopifyLogsRepositoryInterface;
use App\Models\ShopifyProductLog;

class EloquentShopifyLogsRepository extends BaseRepository
    implements ShopifyLogsRepositoryInterface
{
    public function __construct(ShopifyProductLog $model)
    {
        parent::__construct($model);
    }

    // 🚫 no repites find, findOrFail, etc.
    // aquí solo va lógica específica si algún día la necesitas
}
