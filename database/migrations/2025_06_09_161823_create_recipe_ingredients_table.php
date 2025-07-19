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
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            // Mengacu pada resep induk
            $table->foreignId('recipe_id')
                ->constrained('recipes')
                ->onDelete('cascade'); // Jika resep dihapus, bahan-bahan di dalamnya juga dihapus

            // Mengacu pada bahan baku yang dibutuhkan
            $table->foreignId('raw_material_id')
                ->constrained('raw_materials')
                ->onDelete('restrict'); // Jangan hapus bahan baku jika masih terdaftar dalam resep

            $table->decimal('quantity_needed', 10, 2); // Kuantitas bahan baku yang dibutuhkan untuk 1 unit produk jadi
            $table->timestamps();

            // Kombinasi recipe_id dan raw_material_id harus unik untuk mencegah bahan baku ganda dalam satu resep
            $table->unique(['recipe_id', 'raw_material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
