<?php

namespace App\Models;

use App\Models\Tenant\Product;
use App\Models\Tenant\RawMaterial;
use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    protected $guarded = [];

    // Definisi relasi polymorphic
    public function item()
    {
        return $this->morphTo();
    }
}
