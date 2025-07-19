<?php

namespace Database\Seeders;

use App\Models\MainCashBalance;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MainCashBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MainCashBalance::create([
            'account_name' => 'Brankas Utama',
            'account_type' => 'cash',
            'current_balance' => 0,
        ]);

        MainCashBalance::create([
            'account_name' => 'Surplus',
            'account_type' => 'cash',
            'current_balance' => 0,
        ]);

        MainCashBalance::create([
            'account_name' => 'Defisit',
            'account_type' => 'cash',
            'current_balance' => 0,
        ]);
    }
}
