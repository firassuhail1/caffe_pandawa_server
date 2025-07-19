<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    /**
     * Get the recipe associated with the product.
     * Asumsi: Satu produk memiliki satu resep utama.
     */
    public function recipe() // Menggunakan 'recipe' (singular) karena hasOne
    {
        return $this->hasOne(Recipe::class);
    }
}
