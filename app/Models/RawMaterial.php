<?php

namespace App\Models;

use App\Models\Product;
use App\Models\PurchaseDetail;
use App\Models\RawMaterialInventory;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    // protected $fillable = [
    //     'nama',
    //     'sku',
    //     'unit_of_measure',
    //     'cost_price',
    //     'min_stock_alert',
    // ];

    protected $guarded = [];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'min_stock_alert' => 'decimal:2',
    ];

    protected $appends = ['name']; // 'name' adalah nama accessor Anda (getNameAttribute -> name)

    public function purchaseDetails()
    {
        return $this->morphMany(PurchaseDetail::class, 'item');
    }

    /**
     * Sebuah RawMaterial memiliki banyak entri di RecipeIngredient (penggunaan dalam resep).
     */
    public function recipeUsages() // Nama method untuk menghindari konflik dengan 'ingredients'
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function inventories()
    {
        return $this->hasMany(RawMaterialInventory::class, 'raw_material_id');
    }

    public function batches()
    {
        return $this->hasMany(RawMaterialInventoryBatch::class, 'raw_material_id');
    }
   
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,         // Model tujuan akhir
            Recipe::class,          // Model perantara kedua (setelah RecipeIngredient)
            'id',                   // Foreign key di tabel `recipes` (local key dari RecipeIngredient ke Recipe)
            'product_id',           // Foreign key di tabel `products` yang merujuk ke `recipes`
            'id',                   // Local key di tabel `raw_materials`
            'raw_material_id'       // Foreign key di tabel `recipe_ingredients` yang merujuk ke `raw_materials`
        );
    }
    
    // public function products()
    // {
    //     return $this->belongsToMany(Product::class, 'recipes', 'raw_material_id', 'product_id')
    //                 ->withPivot('quantity_needed')
    //                 ->withTimestamps();
    // }

    public function eoqSetting()
    {
        return $this->hasOne(EOQSetting::class);
    }


    // alias agar field 'nama' di table ini bisa di panggil dengan 'name'
    public function getNameAttribute()
    {
        return $this->attributes['nama'];
    }
}
