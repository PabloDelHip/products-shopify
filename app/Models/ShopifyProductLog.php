<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProductLog extends Model
{
    protected $table = 'shopify_product_logs';

    /**
     * Campos asignables en mass assignment
     */
    protected $fillable = [
        'provider',
        'external_id',
        'shopify_product_id',
        'action',
        'status',
        'payload',
        'response',
        'error_message',
    ];

    /**
     * Casts automáticos
     */
    protected $casts = [
        'payload'  => 'array',
        'response' => 'array',
    ];

    /**
     * Valores permitidos (documental / ayuda interna)
     */
    public const ACTION_CREATE = 'CREATE';
    public const ACTION_UPDATE = 'UPDATE';

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_ERROR   = 'ERROR';
}
