<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ShopifyProductSync;

class Category extends Model
{
    protected $table = 'categories';

    /**
     * Campos asignables en mass assignment
     */
    protected $fillable = [
        'provider',
        'provider_category_id',
        'shopify_id',
        'shopify_type',
        'parent_provider_category_id',
        'name',
        'level',
        'active',
        'created_by_batch',
    ];

    /**
     * Casts automáticos
     */
    protected $casts = [
        'active' => 'boolean',
        'created_by_batch' => 'boolean',
        'level'  => 'integer',
    ];

    public function products()
    {
        return $this->belongsToMany(
            ShopifyProductSync::class,
            'shopify_product_sync_category',
            'category_id',
            'shopify_product_sync_id'
        );
    }
    

}
