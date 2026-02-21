<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Category;

class ShopifyProductSync extends Model
{
    protected $table = 'shopify_product_syncs';

    protected $fillable = [
        'provider',
        'external_id',
        'shopify_product_id',
        'sync_status',
        'product_name',
        'sku',
        'price_amount',
        'payload_hash',
        'last_synced_at',
        'last_error',
      ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_ERROR   = 'ERROR';
    public const STATUS_PARTIAL = 'PARTIAL';

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'shopify_product_sync_category',
            'shopify_product_sync_id',
            'category_id'
        );
    }

}
