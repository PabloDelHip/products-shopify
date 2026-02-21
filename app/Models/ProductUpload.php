<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductUpload extends Model
{
    use HasFactory;

    protected $table = 'product_uploads';

    /**
     * Estados posibles del proceso
     */
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS    = 'success';
    public const STATUS_RETRY      = 'retry';
    public const STATUS_FAILED     = 'failed';

    /**
     * Campos asignables masivamente
     */
    protected $fillable = [
        'local_product_id',
        'provider',
        'dedupe_key',
        'status',
        'attempts',
        'data',
        'response_payload',
        'external_product_id',
        'error_message',
        'processing_token',
        'locked_at',
        'queued_at',
        'processed_at',
        'uploaded_at',
    ];

    /**
     * Casts automáticos
     */
    protected $casts = [
        'data'             => 'array',
        'response_payload' => 'array',
        'queued_at'        => 'datetime',
        'processed_at'     => 'datetime',
        'uploaded_at'      => 'datetime',
        'locked_at'        => 'datetime',
        'attempts'         => 'integer',
    ];

    /**
     * Valores por defecto
     */
    protected $attributes = [
        'status'   => self::STATUS_PENDING,
        'attempts' => 0,
    ];

    /**
     * Scope: productos listos para procesar
     */
    public function scopeReadyToProcess($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_RETRY,
        ]);
    }

    /**
     * Scope: locks expirados (recuperación)
     */
    public function scopeWithExpiredLock($query, int $minutes = 10)
    {
        return $query->where(function ($q) use ($minutes) {
            $q->whereNull('locked_at')
              ->orWhere('locked_at', '<', now()->subMinutes($minutes));
        });
    }
}
