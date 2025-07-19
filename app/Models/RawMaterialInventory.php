<?php

namespace App\Models;

use App\Models\RawMaterial;
use App\Models\Tenant\Outlet;
use Illuminate\Database\Eloquent\Model;

class RawMaterialInventory extends Model
{
    protected $guarded = [];
    
    public function raw_material()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
