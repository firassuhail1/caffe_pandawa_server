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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            // Mengacu pada produk jadi yang dihasilkan dari resep ini
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade'); // Jika produk dihapus, resepnya juga dihapus
            $table->string('name')->nullable(); // Nama resep (misal: "Resep Kopi Susu")
            $table->text('description')->nullable(); // Deskripsi resep
            $table->boolean('is_active')->default(true); // Apakah resep ini aktif/digunakan
            $table->timestamps();
        
            // Pastikan product_id adalah unik untuk mencegah duplikasi resep per produk
            $table->unique('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
