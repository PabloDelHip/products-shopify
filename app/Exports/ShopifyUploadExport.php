<?php

namespace App\Exports;

use App\Models\ShopifyProductLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ShopifyUploadExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly array $filters) {}

    public function collection()
    {
        $query = ShopifyProductLog::query();

        if (!empty($this->filters['from'])) {
            $query->whereDate('created_at', '>=', $this->filters['from']);
        }
        if (!empty($this->filters['to'])) {
            $query->whereDate('created_at', '<=', $this->filters['to']);
        }
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['provider'])) {
            $query->where('provider', $this->filters['provider']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function headings(): array
    {
        return [
            'ID Log',
            'Proveedor',
            'External ID (Proveedor)',
            'Shopify Product ID',
            'Acción',
            'Estado',
            'Nombre del Producto',
            'Modelo/SKU',
            'Precio',
            'Error (si hubo)',
            'Fecha de Intento',
        ];
    }

    /**
     * @param ShopifyProductLog $log
     */
    public function map($log): array
    {
        $payload = $log->payload;
        $title = $payload['title'] ?? '(Sin título)';
        
        // Extraer SKU de la primera variante si existe
        $sku = null;
        if (!empty($payload['variants']) && is_array($payload['variants'])) {
            $sku = $payload['variants'][0]['sku'] ?? null;
        }

        // Si no hay SKU en el payload, intentamos buscar el modelo en el payload base
        if (!$sku) {
            $sku = $payload['modelo'] ?? '(Variante única)';
        }

        $price = null;
        if (!empty($payload['variants']) && is_array($payload['variants'])) {
            $price = $payload['variants'][0]['price'] ?? null;
        }

        return [
            $log->id,
            $log->provider,
            $log->external_id,
            $log->shopify_product_id,
            $log->action,
            $log->status,
            $title,
            $sku,
            $price,
            $log->error_message,
            $log->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
