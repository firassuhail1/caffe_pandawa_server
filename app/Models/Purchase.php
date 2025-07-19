<?php

namespace App\Models;

use App\Models\Tenant\Outlet;
use App\Models\PurchaseDetail;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $guarded = [];

    public function purchase_details()
    {
        return $this->hasMany(PurchaseDetail::class);
    }
}
