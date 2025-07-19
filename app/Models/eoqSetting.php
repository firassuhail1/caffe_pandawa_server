<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class eoqSetting extends Model
{
    protected $guarded = [];

    // Definisikan relasi ke RawMaterial
    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    // Accessor untuk menghitung holding_cost secara dinamis
    public function getHoldingCostAttribute()
    {
        // Pastikan rawMaterial dimuat untuk menghindari N+1 query jika diakses di loop
        // Atau gunakan eager loading jika memuat banyak EOQSettings
        if ($this->rawMaterial) {
            return ($this->holding_cost_percent / 100) * $this->rawMaterial->standart_cost_price;
        }
        return 0; // Kembalikan 0 jika rawMaterial tidak ada
    }
}
