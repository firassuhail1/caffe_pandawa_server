<?php

namespace App\Models;

use App\Models\Recipe;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    protected $guarded = [];

    /**
     * Get the recipe that the ingredient belongs to.
     */
    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the raw material that is part of the ingredient.
     */
    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
