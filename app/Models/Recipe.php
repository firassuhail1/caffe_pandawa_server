<?php

namespace App\Models;

use App\Models\Product;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $guarded = [];

    /**
     * Get the product that this recipe belongs to.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the ingredients for the recipe.
     */
    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
