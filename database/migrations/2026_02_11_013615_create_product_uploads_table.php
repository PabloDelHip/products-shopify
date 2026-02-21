<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_uploads', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Producto interno (tu DB)
            $table->unsignedBigInteger('local_product_id')->nullable()->index();

            // Proveedor (shopify, etc)
            $table->string('provider', 50)->index();

            /**
             * Clave para evitar duplicados
             * Ej: shopify:SKU123
             */
            $table->string('dedupe_key', 191);

            /**
             * pending | processing | success | retry | failed
             */
            $table->string('status', 20)->default('pending')->index();

            // Reintentos
            $table->unsignedSmallInteger('attempts')->default(0);

            // Payload que se envía al provider
            $table->json('data');

            // Respuesta del provider (debug)
            $table->json('response_payload')->nullable();

            // ID del producto en Shopify
            $table->string('external_product_id', 100)->nullable()->index();

            // Último error
            $table->text('error_message')->nullable();

            // Lock para evitar que se pisen llamadas
            $table->uuid('processing_token')->nullable()->index();
            $table->dateTime('locked_at')->nullable()->index();

            // Fechas de control
            $table->dateTime('queued_at')->useCurrent()->index();
            $table->dateTime('processed_at')->nullable()->index();
            $table->dateTime('uploaded_at')->nullable()->index();

            $table->timestamps();

            // Dedupe por provider
            $table->unique(['provider', 'dedupe_key'], 'uq_product_uploads_provider_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_uploads');
    }
};
