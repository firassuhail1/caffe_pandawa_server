<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        User::create([
            'role' => 'owner',
            'nama' => 'Owner',
            'alamat' => 'Bae',
            'no_hp' => '089509209384',
            'email' => 'owner@gmail.com',
            'password' => Hash::make('12345678'),
        ]);

        User::create([
            'role' => 'staff',
            'nama' => 'Staff',
            'alamat' => 'Bae',
            'no_hp' => '089509209384',
            'email' => 'staff@gmail.com',
            'password' => Hash::make('12345678'),
        ]);

        $this->call([
            ProductSeeder::class,
            MainCashBalanceSeeder::class,
        ]);
    }
}
