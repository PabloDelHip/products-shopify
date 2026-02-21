<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopify_product_logs', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('provider', 50); // syscom, otro proveedor en el futuro
            $table->string('external_id');  // id del producto en el proveedor
            $table->string('shopify_product_id')->nullable();

            // Acción y estado
            $table->enum('action', ['CREATE', 'UPDATE']);
            $table->enum('status', ['SUCCESS', 'ERROR']);

            // Payload completo enviado a Shopify
            $table->json('payload');

            // (Opcional pero MUY recomendado)
            $table->json('response')->nullable(); // respuesta de Shopify
            $table->text('error_message')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['provider', 'external_id']);
            $table->index('shopify_product_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_product_logs');
    }
};
