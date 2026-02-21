<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_product_syncs', function (Blueprint $table) {
            $table->id();
        
            // Identidad del producto en tu proveedor (syscom)
            $table->string('provider', 50);
            $table->string('external_id', 64);
        
            // Identidad en Shopify
            $table->string('shopify_product_id', 120)->nullable();
        
            // Estado de sincronización
            $table->string('sync_status', 20)->default('PENDING');
        
            // Snapshot del producto
            $table->string('product_name', 255);
            $table->string('sku', 120)->nullable();
            $table->decimal('price_amount', 12, 2)->nullable();
        
            // Control de cambios
            $table->string('payload_hash', 64)->nullable();
        
            // Auditoría
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
        
            $table->timestamps();
        
            $table->unique(['provider', 'external_id'], 'shopify_sync_provider_external_unique');
        
            $table->index('shopify_product_id');
            $table->index('sync_status');
            $table->index('last_synced_at');
        }); 
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_product_syncs');
    }
};
