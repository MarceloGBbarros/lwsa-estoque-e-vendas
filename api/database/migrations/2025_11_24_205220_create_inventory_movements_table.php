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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            // *** ESSA LINHA CRIA A COLUNA product_id ***
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('type', 10); // 'in' ou 'out'
            $table->integer('quantity');
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
