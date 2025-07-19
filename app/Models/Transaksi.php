<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $guarded = [];

    protected $casts = [
        'daftar_barang' => 'array',
    ];
}
