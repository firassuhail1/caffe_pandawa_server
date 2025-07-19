<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // public function register(Request $request) {
    //     $validated = $request->validate([
    //         'storeName' => 'required|string',
    //         'ownerName' => 'required|string',
    //         'alamat' => 'required|string',
    //         'noHp' => ['required','numeric','regex:/^08[0-9]{8,13}$/'],
    //         'jenisToko' => 'required',
    //         'email' => 'required|email:rfc,dns|unique:tenants,email',
    //     ]);

    //     $dataLastId = Tenant::orderBy('id', 'desc')->first();
    //     $lastId = $dataLastId ? $dataLastId->id + 1 : 1;

    //     // Generate nama database
    //     $databaseName = 'tenant_' . strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $validated['storeName'])) . '_' . time();
    //     $storeCode = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $validated['storeName'])) . '_' .  $lastId;
    //     $slug = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $validated['storeName']));

    //     // Simpan data tenant ke database master
    //     $tenant = Tenant::create([
    //         'store_name' => $request->storeName,
    //         'store_code' => $storeCode,
    //         'slug' => $slug,
    //         'owner_name' => $request->ownerName,
    //         'alamat' => $request->alamat,
    //         'no_hp' => $request->noHp, 
    //         'jenis_toko' => $request->jenisToko,
    //         'email' => $request->email,
    //         'database_name' => $databaseName,
    //     ]);

    //     // Buat database untuk tenant
    //     DB::statement("CREATE DATABASE `$databaseName`");

    //     // Atur koneksi tenant
    //     // Set koneksi database untuk tenant yang ditemukan
    //     $this->setTenantDatabaseConnection($tenant->database_name);

    //     // Untuk migrasi tenant
    //     Artisan::call('migrate', [
    //         '--database' => 'tenant_connection', // Koneksi database tenant
    //         '--path' => 'database/migrations/tenant', // Tentukan path untuk migrasi tenant jika diperlukan
    //         '--force' => true
    //     ]);

    //     // Jalankan seeder tenant
    //     Artisan::call('db:seed', [
    //         '--class' => 'Database\\Seeders\\Tenant\\DatabaseSeeder', // Sesuaikan namespace seeder tenant
    //         '--database' => 'tenant_connection',
    //         '--force' => true
    //     ]);

    //     // Karena koneksi sudah diset ke database tenant, UserTenant akan disimpan di sana
    //     $owner = UserTenant::create([
    //         'role' => 'owner', // jika ada kolom role
    //         'nama' => $request->ownerName,
    //         'email' => $request->email,
    //         'password' => Hash::make($request->password), // atau bcrypt('password123')
    //         'created_at' => now(),
    //         'updated_at' => now(),
    //     ]);

    //     return response()->json(['success' => true, 'message' => 'Tenant registered successfully!', 'tenant' => $tenant]);
    // }

    public function login(Request $request)
    {
        // Validasi input
        $request->validate([
            'email' => 'required|email',    // Validasi email
            'password' => 'required|min:6', // Validasi password
        ]);

        // Cek kredensial pengguna menggunakan Auth
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Email not found'], 404);
        }
        
        // driver Sanctum tidak bisa menggunakan auth attempt, jadi manual pengecekan seperti ini
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Jika login sukses, buat token untuk autentikasi
        $tokenUser = $user->createToken('auth_token')->plainTextToken;

        // Login berhasil
        return response()->json(['success' => true, 'data' => $user, 'token_user' => $tokenUser], 200);
        
    }

    public function logout(Request $request) {
        $user = $request->user();

        if ($user) {
            // Mencabut token saat ini yang digunakan untuk request ini
            $user->currentAccessToken()->delete();

            return response()->json(['message' => 'Logged out successfully'], 200);
        }

        return response()->json(['message' => 'User not authenticated or token invalid.'], 401);
    }
}
